<?php
declare(strict_types=1);

/**
 * Content Calendar mutations (Phase 3.7).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Calendar;

use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CalendarManager
 */
final class CalendarManager {

	private CalendarRepository $repository;

	private CalendarService $service;

	private LoggerInterface $logger;

	private EventDispatcherInterface $events;

	public function __construct(
		CalendarRepository $repository,
		CalendarService $service,
		LoggerInterface $logger,
		EventDispatcherInterface $events
	) {
		$this->repository = $repository;
		$this->service    = $service;
		$this->logger     = $logger;
		$this->events     = $events;
	}

	/**
	 * @param array<string, mixed> $input Calendar fields.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create_calendar( array $input, int $user_id = 0 ) {
		$this->service->ensure_tables();
		$name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		if ( $name === '' ) {
			return new \WP_Error( 'rsaip_cal_name', 'Calendar name is required.' );
		}
		$now = \RSAIP_DB::now_gmt_sql();
		$id  = $this->repository->insert_calendar(
			array(
				'name'        => mb_substr( $name, 0, 255 ),
				'project_id'  => absint( $input['project_id'] ?? 0 ),
				'timezone'    => $this->normalize_timezone( (string) ( $input['timezone'] ?? 'UTC' ) ),
				'description' => sanitize_textarea_field( (string) ( $input['description'] ?? '' ) ),
				'status'      => 'active',
				'user_id'     => max( 0, $user_id ),
				'created_at'  => $now,
				'updated_at'  => $now,
			)
		);
		if ( $id <= 0 ) {
			return new \WP_Error( 'rsaip_cal_create', 'Could not create calendar.' );
		}
		$this->events->dispatch( 'rsaip.calendar.created', array( 'id' => $id ) );
		return array(
			'id'   => $id,
			'name' => $name,
		);
	}

	/**
	 * @param array<string, mixed> $input Event fields.
	 * @return CalendarDTO|\WP_Error
	 */
	public function create_event( array $input, int $user_id = 0 ) {
		$this->service->ensure_tables();
		$title = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
		if ( $title === '' ) {
			return new \WP_Error( 'rsaip_cal_title', 'Event title is required.' );
		}
		$publish_at = $this->normalize_datetime( (string) ( $input['publish_at'] ?? '' ) );
		if ( $publish_at === '' ) {
			return new \WP_Error( 'rsaip_cal_date', 'Publish date is required.' );
		}

		$now = \RSAIP_DB::now_gmt_sql();
		$row = $this->normalize_event_row( $input, true );
		$row['title']      = mb_substr( $title, 0, 255 );
		$row['publish_at'] = $publish_at;
		$row['user_id']    = max( 0, $user_id );
		$row['created_at'] = $now;
		$row['updated_at'] = $now;

		$id = $this->repository->insert_event( $row );
		if ( $id <= 0 ) {
			return new \WP_Error( 'rsaip_cal_event', 'Could not create calendar event.' );
		}

		$this->sync_queue( $id, $row );
		$this->events->dispatch( 'rsaip.calendar.event_created', array( 'id' => $id ) );
		$this->logger->info( 'calendar.event_created', array( 'id' => $id ) );

		return $this->service->get_event( $id );
	}

	/**
	 * @param array<string, mixed> $input Fields.
	 * @return CalendarDTO|\WP_Error
	 */
	public function update_event( int $id, array $input, int $user_id = 0 ) {
		$this->service->ensure_tables();
		$existing = $this->repository->find_event( $id );
		if ( ! $existing ) {
			return new \WP_Error( 'rsaip_cal_missing', 'Calendar event not found.', array( 'status' => 404 ) );
		}

		$row = $this->normalize_event_row( $input, false );
		if ( array_key_exists( 'title', $input ) ) {
			$title = sanitize_text_field( (string) $input['title'] );
			if ( $title === '' ) {
				return new \WP_Error( 'rsaip_cal_title', 'Event title is required.' );
			}
			$row['title'] = mb_substr( $title, 0, 255 );
		}
		if ( array_key_exists( 'publish_at', $input ) ) {
			$publish_at = $this->normalize_datetime( (string) $input['publish_at'] );
			if ( $publish_at === '' ) {
				return new \WP_Error( 'rsaip_cal_date', 'Publish date is invalid.' );
			}
			$row['publish_at'] = $publish_at;
		}
		$row['updated_at'] = \RSAIP_DB::now_gmt_sql();

		if ( ! $this->repository->update_event( $id, $row ) ) {
			return new \WP_Error( 'rsaip_cal_update', 'Could not update calendar event.' );
		}

		$merged = array_merge( $existing, $row );
		$this->sync_queue( $id, $merged );
		$this->events->dispatch( 'rsaip.calendar.event_updated', array( 'id' => $id, 'user_id' => $user_id ) );

		return $this->service->get_event( $id );
	}

	/**
	 * @return CalendarDTO|\WP_Error
	 */
	public function reschedule( int $id, string $publish_at, int $user_id = 0 ) {
		return $this->update_event( $id, array( 'publish_at' => $publish_at ), $user_id );
	}

	/**
	 * @return CalendarDTO|\WP_Error
	 */
	public function duplicate( int $id, int $user_id = 0 ) {
		$existing = $this->repository->find_event( $id );
		if ( ! $existing ) {
			return new \WP_Error( 'rsaip_cal_missing', 'Calendar event not found.', array( 'status' => 404 ) );
		}
		unset( $existing['id'] );
		$existing['title']  = mb_substr( (string) $existing['title'] . ' (copy)', 0, 255 );
		$existing['status'] = 'planned';
		return $this->create_event( $existing, $user_id );
	}

	/**
	 * @return true|\WP_Error
	 */
	public function delete_event( int $id, int $user_id = 0 ) {
		if ( ! $this->repository->find_event( $id ) ) {
			return new \WP_Error( 'rsaip_cal_missing', 'Calendar event not found.', array( 'status' => 404 ) );
		}
		if ( ! $this->repository->delete_event( $id ) ) {
			return new \WP_Error( 'rsaip_cal_delete', 'Could not delete calendar event.' );
		}
		$this->events->dispatch( 'rsaip.calendar.event_deleted', array( 'id' => $id, 'user_id' => $user_id ) );
		return true;
	}

	/**
	 * @param list<int> $ids Event IDs.
	 * @return array{updated: int}
	 */
	public function bulk_move( array $ids, string $publish_at, int $user_id = 0 ): array {
		$publish_at = $this->normalize_datetime( $publish_at );
		if ( $publish_at === '' || ! $ids ) {
			return array( 'updated' => 0 );
		}
		$updated = 0;
		foreach ( $ids as $id ) {
			$result = $this->reschedule( (int) $id, $publish_at, $user_id );
			if ( ! is_wp_error( $result ) ) {
				++$updated;
			}
		}
		$this->events->dispatch(
			'rsaip.calendar.bulk_moved',
			array(
				'ids'     => $ids,
				'updated' => $updated,
			)
		);
		return array( 'updated' => $updated );
	}

	/**
	 * Shift each event by the same day delta from a reference date (drag-drop bulk).
	 *
	 * @param list<int> $ids Event IDs.
	 * @return array{updated: int}
	 */
	public function bulk_shift_days( array $ids, int $days, int $user_id = 0 ): array {
		if ( ! $ids || 0 === $days ) {
			return array( 'updated' => 0 );
		}
		$updated = 0;
		foreach ( $ids as $id ) {
			$row = $this->repository->find_event( (int) $id );
			if ( ! $row ) {
				continue;
			}
			$ts = strtotime( (string) $row['publish_at'] . ' UTC' );
			if ( false === $ts ) {
				continue;
			}
			$new = gmdate( 'Y-m-d H:i:s', $ts + ( $days * DAY_IN_SECONDS ) );
			$result = $this->reschedule( (int) $id, $new, $user_id );
			if ( ! is_wp_error( $result ) ) {
				++$updated;
			}
		}
		return array( 'updated' => $updated );
	}

	/**
	 * @param list<int> $ids Event IDs.
	 * @return array{deleted: int}
	 */
	public function bulk_delete( array $ids, int $user_id = 0 ): array {
		$deleted = $this->repository->bulk_delete_events( $ids );
		$this->events->dispatch(
			'rsaip.calendar.bulk_deleted',
			array(
				'ids'     => $ids,
				'deleted' => $deleted,
				'user_id' => $user_id,
			)
		);
		return array( 'deleted' => $deleted );
	}

	/**
	 * @param array<string, mixed> $event_row Event data.
	 */
	private function sync_queue( int $event_id, array $event_row ): void {
		$status  = (string) ( $event_row['status'] ?? '' );
		$channel = (string) ( $event_row['publishing_channel'] ?? 'blog' );
		$publish = (string) ( $event_row['publish_at'] ?? '' );
		$now     = \RSAIP_DB::now_gmt_sql();

		$existing = $this->repository->find_queue_by_event( $event_id );
		$queue_status = in_array( $status, array( 'scheduled', 'ready' ), true ) ? 'pending' : ( $status === 'published' ? 'published' : 'pending' );

		if ( ! in_array( $status, array( 'ready', 'scheduled', 'published' ), true ) ) {
			if ( $existing && (string) ( $existing['status'] ?? '' ) === 'pending' ) {
				$this->repository->update_queue(
					(int) $existing['id'],
					array(
						'status'     => 'cancelled',
						'updated_at' => $now,
					)
				);
			}
			return;
		}

		$row = array(
			'event_id'     => $event_id,
			'project_id'   => absint( $event_row['project_id'] ?? 0 ),
			'article_id'   => absint( $event_row['article_id'] ?? 0 ),
			'channel'      => sanitize_key( $channel ),
			'scheduled_at' => $publish,
			'status'       => $queue_status,
			'updated_at'   => $now,
		);

		if ( $existing ) {
			$this->repository->update_queue( (int) $existing['id'], $row );
			return;
		}

		$row['attempts']   = 0;
		$row['last_error'] = '';
		$row['meta']       = '';
		$row['created_at'] = $now;
		$this->repository->insert_queue( $row );
	}

	/**
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>
	 */
	private function normalize_event_row( array $input, bool $for_create ): array {
		$row = array();
		$map = array(
			'calendar_id'         => 'int',
			'project_id'          => 'int',
			'keyword_id'          => 'int',
			'brief_id'            => 'int',
			'article_id'          => 'int',
			'status'              => 'status',
			'priority'            => 'priority',
			'assigned_user_id'    => 'int',
			'publishing_channel'  => 'channel',
			'timezone'            => 'timezone',
			'notes'               => 'notes',
			'roadmap_phase'       => 'phase',
			'color'               => 'text',
		);

		foreach ( $map as $key => $type ) {
			if ( ! $for_create && ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$value = $input[ $key ] ?? $this->default_for( $key );
			switch ( $type ) {
				case 'int':
					$row[ $key ] = absint( $value );
					break;
				case 'priority':
					$row[ $key ] = max( 0, min( 100, absint( $value ) ) );
					break;
				case 'status':
					$row[ $key ] = $this->normalize_status( (string) $value );
					break;
				case 'channel':
					$row[ $key ] = sanitize_key( (string) $value ) ?: 'blog';
					break;
				case 'timezone':
					$row[ $key ] = $this->normalize_timezone( (string) $value );
					break;
				case 'notes':
					$row[ $key ] = sanitize_textarea_field( (string) $value );
					break;
				case 'phase':
					$phase = sanitize_key( (string) $value );
					$row[ $key ] = in_array( $phase, array( 'now', 'next', 'later', 'backlog' ), true ) ? $phase : 'now';
					break;
				default:
					$row[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		return $row;
	}

	/**
	 * @return mixed
	 */
	private function default_for( string $key ) {
		$defaults = array(
			'calendar_id'        => 0,
			'project_id'         => 0,
			'keyword_id'         => 0,
			'brief_id'           => 0,
			'article_id'         => 0,
			'status'             => 'planned',
			'priority'           => 50,
			'assigned_user_id'   => 0,
			'publishing_channel' => 'blog',
			'timezone'           => 'UTC',
			'notes'              => '',
			'roadmap_phase'      => 'now',
			'color'              => '',
		);
		return $defaults[ $key ] ?? '';
	}

	public function normalize_status( string $status ): string {
		$status  = sanitize_key( $status );
		$allowed = array( 'idea', 'planned', 'in_progress', 'ready', 'scheduled', 'published', 'cancelled' );
		return in_array( $status, $allowed, true ) ? $status : 'planned';
	}

	private function normalize_timezone( string $tz ): string {
		$tz = sanitize_text_field( $tz );
		if ( $tz === '' ) {
			return 'UTC';
		}
		return mb_substr( $tz, 0, 64 );
	}

	private function normalize_datetime( string $value ): string {
		$value = trim( $value );
		if ( $value === '' ) {
			return '';
		}
		// Accept Y-m-d or Y-m-d H:i(:s)
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $value . ' 09:00:00';
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}$/', $value ) ) {
			$value = str_replace( 'T', ' ', $value ) . ':00';
		}
		$ts = strtotime( $value . ( preg_match( '/Z$|UTC$/i', $value ) ? '' : ' UTC' ) );
		if ( false === $ts ) {
			$ts = strtotime( $value );
		}
		if ( false === $ts ) {
			return '';
		}
		return gmdate( 'Y-m-d H:i:s', $ts );
	}
}
