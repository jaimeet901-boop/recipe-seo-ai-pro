<?php
declare(strict_types=1);

/**
 * Content Calendar reads / views (Phase 3.7).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Calendar;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CalendarService
 */
final class CalendarService {

	private CalendarRepository $repository;

	private CacheInterface $cache;

	private LoggerInterface $logger;

	public function __construct(
		CalendarRepository $repository,
		CacheInterface $cache,
		LoggerInterface $logger
	) {
		$this->repository = $repository;
		$this->cache      = $cache;
		$this->logger     = $logger;
	}

	public function ensure_tables(): void {
		if ( class_exists( 'RSAIP_DB' ) ) {
			\RSAIP_DB::migrate_calendar_tables();
		}
	}

	/**
	 * @return CalendarDTO|\WP_Error
	 */
	public function get_event( int $id ) {
		$this->ensure_tables();
		$row = $this->repository->find_event( $id );
		if ( ! $row ) {
			return new \WP_Error( 'rsaip_cal_missing', 'Calendar event not found.', array( 'status' => 404 ) );
		}
		return $this->dto_from_row( $row );
	}

	/**
	 * @param array<string, mixed> $args Filters.
	 * @return list<array<string, mixed>>
	 */
	public function list_events( array $args ): array {
		$this->ensure_tables();
		$key = 'rsaip_cal_events_' . md5( (string) wp_json_encode( $args ) );
		if ( empty( $args['bypass_cache'] ) ) {
			$cached = $this->cache->get( $key, null );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$rows  = $this->repository->list_events( $args );
		$items = array();
		foreach ( $rows as $row ) {
			$items[] = $this->dto_from_row( $row )->to_array();
		}
		$this->cache->set( $key, $items, 15 );
		$this->logger->debug( 'calendar.list', array( 'count' => count( $items ) ) );
		return $items;
	}

	/**
	 * Group events for UI views.
	 *
	 * @param array<string, mixed> $args Filters including view, anchor_date.
	 * @return array<string, mixed>
	 */
	public function view_payload( array $args ): array {
		$view   = isset( $args['view'] ) ? sanitize_key( (string) $args['view'] ) : 'month';
		$anchor = isset( $args['anchor_date'] ) ? (string) $args['anchor_date'] : gmdate( 'Y-m-d' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $anchor ) ) {
			$anchor = gmdate( 'Y-m-d' );
		}

		$range = $this->range_for_view( $view, $anchor );
		$list_args = array(
			'project_id'   => absint( $args['project_id'] ?? 0 ),
			'calendar_id'  => absint( $args['calendar_id'] ?? 0 ),
			'status'       => (string) ( $args['status'] ?? 'all' ),
			'q'            => (string) ( $args['q'] ?? '' ),
			'from'         => $range['from'],
			'to'           => $range['to'],
			'limit'        => 500,
			'bypass_cache' => true,
		);
		if ( $view === 'agenda' ) {
			unset( $list_args['from'], $list_args['to'] );
			$list_args['upcoming'] = true;
			$list_args['limit']    = 100;
		}
		if ( $view === 'roadmap' ) {
			unset( $list_args['from'], $list_args['to'] );
			$list_args['limit'] = 200;
		}

		$events = $this->list_events( $list_args );

		$payload = array(
			'view'        => $view,
			'anchor_date' => $anchor,
			'range'       => $range,
			'events'      => $events,
			'groups'      => array(),
		);

		if ( $view === 'roadmap' ) {
			$phases = array(
				'now'     => array(),
				'next'    => array(),
				'later'   => array(),
				'backlog' => array(),
			);
			foreach ( $events as $event ) {
				$phase = (string) ( $event['roadmap_phase'] ?? 'backlog' );
				if ( ! isset( $phases[ $phase ] ) ) {
					$phase = 'backlog';
				}
				$phases[ $phase ][] = $event;
			}
			$payload['groups'] = $phases;
		} elseif ( in_array( $view, array( 'month', 'week', 'day' ), true ) ) {
			$by_day = array();
			foreach ( $events as $event ) {
				$day = substr( (string) ( $event['publish_at'] ?? '' ), 0, 10 );
				if ( $day === '' ) {
					continue;
				}
				if ( ! isset( $by_day[ $day ] ) ) {
					$by_day[ $day ] = array();
				}
				$by_day[ $day ][] = $event;
			}
			$payload['groups'] = $by_day;
		} else {
			$payload['groups'] = array( 'agenda' => $events );
		}

		return $payload;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function upcoming( int $project_id = 0, int $limit = 20 ): array {
		return $this->list_events(
			array(
				'project_id'   => $project_id,
				'upcoming'     => true,
				'limit'        => $limit,
				'bypass_cache' => true,
			)
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function overdue( int $project_id = 0, int $limit = 50 ): array {
		return $this->list_events(
			array(
				'project_id'   => $project_id,
				'overdue'      => true,
				'limit'        => $limit,
				'bypass_cache' => true,
			)
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_calendars( int $project_id = 0 ): array {
		$this->ensure_tables();
		return $this->repository->list_calendars( $project_id );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_queue( array $args = array() ): array {
		$this->ensure_tables();
		return $this->repository->list_queue( $args );
	}

	/**
	 * @param array<string, mixed> $row DB row.
	 */
	public function dto_from_row( array $row ): CalendarDTO {
		$dto                     = new CalendarDTO();
		$dto->id                 = (int) ( $row['id'] ?? 0 );
		$dto->calendar_id        = (int) ( $row['calendar_id'] ?? 0 );
		$dto->title              = (string) ( $row['title'] ?? '' );
		$dto->project_id         = (int) ( $row['project_id'] ?? 0 );
		$dto->keyword_id         = (int) ( $row['keyword_id'] ?? 0 );
		$dto->brief_id           = (int) ( $row['brief_id'] ?? 0 );
		$dto->article_id         = (int) ( $row['article_id'] ?? 0 );
		$dto->publish_at         = (string) ( $row['publish_at'] ?? '' );
		$dto->status             = (string) ( $row['status'] ?? 'planned' );
		$dto->priority           = (int) ( $row['priority'] ?? 50 );
		$dto->assigned_user_id   = (int) ( $row['assigned_user_id'] ?? 0 );
		$dto->publishing_channel = (string) ( $row['publishing_channel'] ?? 'blog' );
		$dto->timezone           = (string) ( $row['timezone'] ?? 'UTC' );
		$dto->notes              = (string) ( $row['notes'] ?? '' );
		$dto->roadmap_phase      = (string) ( $row['roadmap_phase'] ?? 'now' );
		$dto->color              = (string) ( $row['color'] ?? '' );
		$dto->user_id            = (int) ( $row['user_id'] ?? 0 );
		$dto->created_at         = (string) ( $row['created_at'] ?? '' );
		$dto->updated_at         = (string) ( $row['updated_at'] ?? '' );

		$now = \RSAIP_DB::now_gmt_sql();
		$dto->is_overdue = $dto->publish_at !== ''
			&& $dto->publish_at < $now
			&& ! in_array( $dto->status, array( 'published', 'cancelled' ), true );

		return $dto;
	}

	/**
	 * @return array{from: string, to: string}
	 */
	private function range_for_view( string $view, string $anchor ): array {
		$ts = strtotime( $anchor . ' 00:00:00 UTC' );
		if ( false === $ts ) {
			$ts = time();
		}
		if ( $view === 'day' ) {
			return array(
				'from' => gmdate( 'Y-m-d 00:00:00', $ts ),
				'to'   => gmdate( 'Y-m-d 23:59:59', $ts ),
			);
		}
		if ( $view === 'week' ) {
			$dow = (int) gmdate( 'N', $ts ); // 1=Mon
			$start = $ts - ( ( $dow - 1 ) * DAY_IN_SECONDS );
			$end   = $start + ( 6 * DAY_IN_SECONDS );
			return array(
				'from' => gmdate( 'Y-m-d 00:00:00', $start ),
				'to'   => gmdate( 'Y-m-d 23:59:59', $end ),
			);
		}
		// month (default)
		$start = strtotime( gmdate( 'Y-m-01', $ts ) . ' 00:00:00 UTC' );
		$end   = strtotime( gmdate( 'Y-m-t', $ts ) . ' 23:59:59 UTC' );
		return array(
			'from' => gmdate( 'Y-m-d H:i:s', (int) $start ),
			'to'   => gmdate( 'Y-m-d H:i:s', (int) $end ),
		);
	}
}
