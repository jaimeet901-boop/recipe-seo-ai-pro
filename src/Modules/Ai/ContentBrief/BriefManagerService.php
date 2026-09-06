<?php
declare(strict_types=1);

/**
 * Content Brief Library mutations (Phase 3.2).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentBrief;

use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BriefManagerService
 */
final class BriefManagerService {

	private BriefRepository $repository;

	private BriefStorageService $storage;

	private LoggerInterface $logger;

	private EventDispatcherInterface $events;

	public function __construct(
		BriefRepository $repository,
		BriefStorageService $storage,
		LoggerInterface $logger,
		EventDispatcherInterface $events
	) {
		$this->repository = $repository;
		$this->storage    = $storage;
		$this->logger     = $logger;
		$this->events     = $events;
	}

	/**
	 * @param array<string, mixed> $brief_input Brief fields.
	 * @param array<string, mixed> $meta        title, status, user_id.
	 * @return array<string, mixed>|\WP_Error Presented brief.
	 */
	public function save( array $brief_input, array $meta = array() ) {
		$this->storage->ensure_table();

		$row               = $this->storage->row_from_input( $brief_input, $meta );
		$now               = \RSAIP_DB::now_gmt_sql();
		$row['created_at'] = $now;
		$row['updated_at'] = $now;

		$id = $this->repository->insert( $row );
		if ( $id <= 0 ) {
			$this->logger->error( 'content_brief.library.save_failed', array() );
			return new \WP_Error( 'rsaip_brief_save', 'Could not save content brief.' );
		}

		$saved = $this->repository->find( $id );
		if ( ! $saved ) {
			return new \WP_Error( 'rsaip_brief_save', 'Saved brief could not be reloaded.' );
		}

		$presented = $this->storage->present_row( $saved );
		$this->events->dispatch(
			'rsaip.content_brief.library.saved',
			array(
				'id'     => $id,
				'status' => $presented['status'],
			)
		);
		$this->logger->info( 'content_brief.library.saved', array( 'id' => $id ) );

		return $presented;
	}

	/**
	 * @param array<string, mixed> $brief_input Brief fields.
	 * @param array<string, mixed> $meta        title, status, user_id.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update( int $id, array $brief_input, array $meta = array() ) {
		$this->storage->ensure_table();
		$existing = $this->repository->find( $id );
		if ( ! $existing ) {
			return new \WP_Error( 'rsaip_brief_missing', 'Brief not found.', array( 'status' => 404 ) );
		}

		if ( ! isset( $meta['status'] ) ) {
			$meta['status'] = (string) ( $existing['status'] ?? BriefStorageService::STATUS_DRAFT );
		}
		if ( ! isset( $meta['user_id'] ) ) {
			$meta['user_id'] = (int) ( $existing['user_id'] ?? 0 );
		}
		if ( ! isset( $meta['title'] ) || trim( (string) $meta['title'] ) === '' ) {
			$meta['title'] = (string) ( $existing['title'] ?? '' );
		}

		$row               = $this->storage->row_from_input( $brief_input, $meta );
		$row['updated_at'] = \RSAIP_DB::now_gmt_sql();
		// Keep original created_at / do not overwrite via update set of created_at.
		unset( $row['created_at'] );

		$ok = $this->repository->update( $id, $row );
		if ( ! $ok ) {
			return new \WP_Error( 'rsaip_brief_update', 'Could not update content brief.' );
		}

		$saved = $this->repository->find( $id );
		if ( ! $saved ) {
			return new \WP_Error( 'rsaip_brief_update', 'Updated brief could not be reloaded.' );
		}

		$presented = $this->storage->present_row( $saved );
		$this->events->dispatch( 'rsaip.content_brief.library.updated', array( 'id' => $id ) );
		$this->logger->info( 'content_brief.library.updated', array( 'id' => $id ) );

		return $presented;
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get( int $id ) {
		$this->storage->ensure_table();
		$row = $this->repository->find( $id );
		if ( ! $row ) {
			return new \WP_Error( 'rsaip_brief_missing', 'Brief not found.', array( 'status' => 404 ) );
		}
		return $this->storage->present_row( $row );
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function duplicate( int $id, int $user_id = 0 ) {
		$original = $this->get( $id );
		if ( is_wp_error( $original ) ) {
			return $original;
		}

		$meta = array(
			'title'   => trim( (string) $original['title'] ) . ' (Copy)',
			'status'  => BriefStorageService::STATUS_DRAFT,
			'user_id' => $user_id > 0 ? $user_id : (int) $original['user_id'],
		);

		return $this->save( $original, $meta );
	}

	/**
	 * @return true|\WP_Error
	 */
	public function delete( int $id ) {
		$this->storage->ensure_table();
		if ( ! $this->repository->find( $id ) ) {
			return new \WP_Error( 'rsaip_brief_missing', 'Brief not found.', array( 'status' => 404 ) );
		}
		$ok = $this->repository->delete( $id );
		if ( ! $ok ) {
			return new \WP_Error( 'rsaip_brief_delete', 'Could not delete brief.' );
		}
		$this->events->dispatch( 'rsaip.content_brief.library.deleted', array( 'id' => $id ) );
		$this->logger->info( 'content_brief.library.deleted', array( 'id' => $id ) );
		return true;
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function archive( int $id ) {
		return $this->set_status( $id, BriefStorageService::STATUS_ARCHIVED );
	}

	/**
	 * Restore archived brief to draft.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function restore( int $id ) {
		return $this->set_status( $id, BriefStorageService::STATUS_DRAFT );
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function mark_completed( int $id ) {
		return $this->set_status( $id, BriefStorageService::STATUS_COMPLETED );
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	private function set_status( int $id, string $status ) {
		$this->storage->ensure_table();
		$row = $this->repository->find( $id );
		if ( ! $row ) {
			return new \WP_Error( 'rsaip_brief_missing', 'Brief not found.', array( 'status' => 404 ) );
		}

		$status = $this->storage->normalize_status( $status );
		$ok     = $this->repository->update(
			$id,
			array(
				'status'     => $status,
				'updated_at' => \RSAIP_DB::now_gmt_sql(),
			)
		);
		if ( ! $ok ) {
			return new \WP_Error( 'rsaip_brief_status', 'Could not update brief status.' );
		}

		$saved = $this->repository->find( $id );
		if ( ! $saved ) {
			return new \WP_Error( 'rsaip_brief_status', 'Brief could not be reloaded.' );
		}

		$presented = $this->storage->present_row( $saved );
		$this->events->dispatch(
			'rsaip.content_brief.library.status_changed',
			array(
				'id'     => $id,
				'status' => $status,
			)
		);
		return $presented;
	}
}
