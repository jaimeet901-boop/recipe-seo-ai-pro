<?php
declare(strict_types=1);

/**
 * Project mutations and membership (Phase 3.3).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Projects;

use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProjectManager
 */
final class ProjectManager {

	public const STATUS_ACTIVE   = 'active';
	public const STATUS_PAUSED   = 'paused';
	public const STATUS_ARCHIVED = 'archived';

	private ProjectRepository $repository;

	private ProjectService $service;

	private LoggerInterface $logger;

	private EventDispatcherInterface $events;

	public function __construct(
		ProjectRepository $repository,
		ProjectService $service,
		LoggerInterface $logger,
		EventDispatcherInterface $events
	) {
		$this->repository = $repository;
		$this->service    = $service;
		$this->logger     = $logger;
		$this->events     = $events;
	}

	/**
	 * @param array<string, mixed> $input Project fields.
	 * @return ProjectDTO|\WP_Error
	 */
	public function create( array $input, int $owner_user_id ) {
		$this->service->ensure_tables();

		$name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		if ( $name === '' ) {
			return new \WP_Error( 'rsaip_project_name', 'Project name is required.' );
		}

		$now = \RSAIP_DB::now_gmt_sql();
		$row = array(
			'name'           => mb_substr( $name, 0, 255 ),
			'description'    => sanitize_textarea_field( (string) ( $input['description'] ?? '' ) ),
			'target_country' => strtoupper( sanitize_text_field( (string) ( $input['target_country'] ?? '' ) ) ),
			'language'       => sanitize_text_field( (string) ( $input['language'] ?? '' ) ),
			'niche'          => sanitize_text_field( (string) ( $input['niche'] ?? '' ) ),
			'status'         => $this->normalize_status( (string) ( $input['status'] ?? self::STATUS_ACTIVE ) ),
			'owner_user_id'  => max( 0, $owner_user_id ),
			'created_at'     => $now,
			'updated_at'     => $now,
		);

		$id = $this->repository->insert_project( $row );
		if ( $id <= 0 ) {
			return new \WP_Error( 'rsaip_project_create', 'Could not create project.' );
		}

		if ( $owner_user_id > 0 ) {
			$this->repository->upsert_member(
				array(
					'project_id' => $id,
					'user_id'    => $owner_user_id,
					'role'       => 'owner',
					'created_at' => $now,
				)
			);
		}

		$this->service->refresh_statistics( $id );
		$this->log_activity( $id, $owner_user_id, 'project.created', 'Project created' );

		$dto = $this->service->get( $id, true );
		$this->events->dispatch( 'rsaip.project.created', array( 'id' => $id ) );
		$this->logger->info( 'projects.created', array( 'id' => $id ) );

		return $dto;
	}

	/**
	 * @param array<string, mixed> $input Project fields.
	 * @return ProjectDTO|\WP_Error
	 */
	public function update( int $id, array $input, int $actor_user_id = 0 ) {
		$this->service->ensure_tables();
		if ( ! $this->repository->find_project( $id ) ) {
			return new \WP_Error( 'rsaip_project_missing', 'Project not found.', array( 'status' => 404 ) );
		}

		$row = array( 'updated_at' => \RSAIP_DB::now_gmt_sql() );
		if ( array_key_exists( 'name', $input ) ) {
			$name = sanitize_text_field( (string) $input['name'] );
			if ( $name === '' ) {
				return new \WP_Error( 'rsaip_project_name', 'Project name is required.' );
			}
			$row['name'] = mb_substr( $name, 0, 255 );
		}
		if ( array_key_exists( 'description', $input ) ) {
			$row['description'] = sanitize_textarea_field( (string) $input['description'] );
		}
		if ( array_key_exists( 'target_country', $input ) ) {
			$row['target_country'] = strtoupper( sanitize_text_field( (string) $input['target_country'] ) );
		}
		if ( array_key_exists( 'language', $input ) ) {
			$row['language'] = sanitize_text_field( (string) $input['language'] );
		}
		if ( array_key_exists( 'niche', $input ) ) {
			$row['niche'] = sanitize_text_field( (string) $input['niche'] );
		}
		if ( array_key_exists( 'status', $input ) ) {
			$row['status'] = $this->normalize_status( (string) $input['status'] );
		}

		if ( ! $this->repository->update_project( $id, $row ) ) {
			return new \WP_Error( 'rsaip_project_update', 'Could not update project.' );
		}

		$this->log_activity( $id, $actor_user_id, 'project.updated', 'Project updated' );
		$this->events->dispatch( 'rsaip.project.updated', array( 'id' => $id ) );

		return $this->service->get( $id, true );
	}

	/**
	 * @return true|\WP_Error
	 */
	public function delete( int $id, int $actor_user_id = 0 ) {
		$this->service->ensure_tables();
		if ( ! $this->repository->find_project( $id ) ) {
			return new \WP_Error( 'rsaip_project_missing', 'Project not found.', array( 'status' => 404 ) );
		}
		if ( ! $this->repository->delete_project( $id ) ) {
			return new \WP_Error( 'rsaip_project_delete', 'Could not delete project.' );
		}
		$this->events->dispatch( 'rsaip.project.deleted', array( 'id' => $id, 'user_id' => $actor_user_id ) );
		$this->logger->info( 'projects.deleted', array( 'id' => $id ) );
		return true;
	}

	/**
	 * @return ProjectDTO|\WP_Error
	 */
	public function add_member( int $project_id, int $user_id, string $role = 'member', int $actor_user_id = 0 ) {
		if ( ! $this->repository->find_project( $project_id ) ) {
			return new \WP_Error( 'rsaip_project_missing', 'Project not found.', array( 'status' => 404 ) );
		}
		if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
			return new \WP_Error( 'rsaip_project_member', 'Valid user is required.' );
		}
		$role = sanitize_key( $role );
		if ( ! in_array( $role, array( 'owner', 'editor', 'member', 'viewer' ), true ) ) {
			$role = 'member';
		}

		$this->repository->upsert_member(
			array(
				'project_id' => $project_id,
				'user_id'    => $user_id,
				'role'       => $role,
				'created_at' => \RSAIP_DB::now_gmt_sql(),
			)
		);
		$this->log_activity( $project_id, $actor_user_id, 'member.added', 'Member added: #' . $user_id );
		$this->repository->update_project( $project_id, array( 'updated_at' => \RSAIP_DB::now_gmt_sql() ) );

		return $this->service->get( $project_id, true );
	}

	/**
	 * @return ProjectDTO|\WP_Error
	 */
	public function remove_member( int $project_id, int $user_id, int $actor_user_id = 0 ) {
		if ( ! $this->repository->find_project( $project_id ) ) {
			return new \WP_Error( 'rsaip_project_missing', 'Project not found.', array( 'status' => 404 ) );
		}
		$this->repository->remove_member( $project_id, $user_id );
		$this->log_activity( $project_id, $actor_user_id, 'member.removed', 'Member removed: #' . $user_id );
		return $this->service->get( $project_id, true );
	}

	/**
	 * Attach a future-compatible asset (brief/keyword/article/…).
	 *
	 * @return ProjectDTO|\WP_Error
	 */
	public function attach_asset( int $project_id, string $asset_type, int $asset_id, string $title = '', int $actor_user_id = 0 ) {
		if ( ! $this->repository->find_project( $project_id ) ) {
			return new \WP_Error( 'rsaip_project_missing', 'Project not found.', array( 'status' => 404 ) );
		}
		$asset_type = sanitize_key( $asset_type );
		if ( $asset_type === '' || $asset_id <= 0 ) {
			return new \WP_Error( 'rsaip_project_asset', 'Valid asset is required.' );
		}

		// Optional existence check for briefs only (no generation changes).
		if ( $asset_type === ProjectService::ASSET_BRIEF && class_exists( 'RSAIP_DB' ) ) {
			global $wpdb;
			$table = \RSAIP_DB::table_content_briefs();
			$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d LIMIT 1", $asset_id ) );
			if ( $exists <= 0 ) {
				return new \WP_Error( 'rsaip_project_asset', 'Content brief not found.' );
			}
			if ( $title === '' ) {
				$title = (string) $wpdb->get_var( $wpdb->prepare( "SELECT title FROM {$table} WHERE id = %d LIMIT 1", $asset_id ) );
			}
		}

		$this->repository->attach_asset(
			array(
				'project_id' => $project_id,
				'asset_type' => $asset_type,
				'asset_id'   => $asset_id,
				'title'      => mb_substr( sanitize_text_field( $title ), 0, 255 ),
				'meta'       => '',
				'created_at' => \RSAIP_DB::now_gmt_sql(),
			)
		);
		$this->service->refresh_statistics( $project_id );
		$this->log_activity( $project_id, $actor_user_id, 'asset.attached', strtoupper( $asset_type ) . ' #' . $asset_id . ' attached' );
		$this->events->dispatch(
			'rsaip.project.asset_attached',
			array(
				'project_id' => $project_id,
				'asset_type' => $asset_type,
				'asset_id'   => $asset_id,
			)
		);

		return $this->service->get( $project_id, true );
	}

	/**
	 * @return ProjectDTO|\WP_Error
	 */
	public function detach_asset( int $project_id, string $asset_type, int $asset_id, int $actor_user_id = 0 ) {
		if ( ! $this->repository->find_project( $project_id ) ) {
			return new \WP_Error( 'rsaip_project_missing', 'Project not found.', array( 'status' => 404 ) );
		}
		$this->repository->detach_asset( $project_id, sanitize_key( $asset_type ), $asset_id );
		$this->service->refresh_statistics( $project_id );
		$this->log_activity( $project_id, $actor_user_id, 'asset.detached', 'Asset detached' );
		return $this->service->get( $project_id, true );
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function add_task( int $project_id, string $title, string $due_at = '', int $actor_user_id = 0 ) {
		if ( ! $this->repository->find_project( $project_id ) ) {
			return new \WP_Error( 'rsaip_project_missing', 'Project not found.', array( 'status' => 404 ) );
		}
		$title = sanitize_text_field( $title );
		if ( $title === '' ) {
			return new \WP_Error( 'rsaip_project_task', 'Task title is required.' );
		}

		$due = null;
		if ( $due_at !== '' ) {
			$ts = strtotime( $due_at );
			if ( $ts ) {
				$due = gmdate( 'Y-m-d H:i:s', $ts );
			}
		}

		$id = $this->repository->insert_task(
			array(
				'project_id' => $project_id,
				'title'      => mb_substr( $title, 0, 255 ),
				'status'     => 'open',
				'due_at'     => $due,
				'created_at' => \RSAIP_DB::now_gmt_sql(),
			)
		);
		if ( $id <= 0 ) {
			return new \WP_Error( 'rsaip_project_task', 'Could not create task.' );
		}
		$this->log_activity( $project_id, $actor_user_id, 'task.created', 'Task created' );
		return array(
			'id'         => $id,
			'project_id' => $project_id,
			'title'      => $title,
			'status'     => 'open',
			'due_at'     => $due,
		);
	}

	public function normalize_status( string $status ): string {
		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( self::STATUS_ACTIVE, self::STATUS_PAUSED, self::STATUS_ARCHIVED ), true ) ) {
			return self::STATUS_ACTIVE;
		}
		return $status;
	}

	private function log_activity( int $project_id, int $user_id, string $action, string $message ): void {
		$this->repository->insert_activity(
			array(
				'project_id' => $project_id,
				'user_id'    => max( 0, $user_id ),
				'action'     => sanitize_key( $action ),
				'message'    => mb_substr( sanitize_text_field( $message ), 0, 500 ),
				'created_at' => \RSAIP_DB::now_gmt_sql(),
			)
		);
	}
}
