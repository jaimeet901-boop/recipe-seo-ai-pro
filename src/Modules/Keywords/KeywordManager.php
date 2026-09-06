<?php
declare(strict_types=1);

/**
 * Keyword Workspace mutations (Phase 3.4) — no AI generation.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Keywords;

use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Modules\Projects\ProjectManager;
use RecipeSeoAiPro\Modules\Projects\ProjectService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KeywordManager
 */
final class KeywordManager {

	private KeywordRepository $repository;

	private KeywordService $service;

	private LoggerInterface $logger;

	private EventDispatcherInterface $events;

	private ?ProjectManager $project_manager;

	public function __construct(
		KeywordRepository $repository,
		KeywordService $service,
		LoggerInterface $logger,
		EventDispatcherInterface $events,
		?ProjectManager $project_manager = null
	) {
		$this->repository      = $repository;
		$this->service         = $service;
		$this->logger          = $logger;
		$this->events          = $events;
		$this->project_manager = $project_manager;
	}

	/**
	 * Manual keyword entry only (not research).
	 *
	 * @param array<string, mixed> $input Fields.
	 * @return KeywordDTO|\WP_Error
	 */
	public function create( array $input, int $user_id = 0 ) {
		$this->service->ensure_tables();
		$keyword = sanitize_text_field( (string) ( $input['primary_keyword'] ?? '' ) );
		if ( $keyword === '' ) {
			return new \WP_Error( 'rsaip_keyword_empty', 'Primary keyword is required.' );
		}

		$now = \RSAIP_DB::now_gmt_sql();
		$row = $this->normalize_row( $input, true );
		$row['primary_keyword'] = mb_substr( $keyword, 0, 255 );
		$row['user_id']         = max( 0, $user_id );
		$row['created_at']      = $now;
		$row['updated_at']      = $now;

		$id = $this->repository->insert( $row );
		if ( $id <= 0 ) {
			return new \WP_Error( 'rsaip_keyword_create', 'Could not create keyword.' );
		}

		$this->history( $id, $user_id, 'created', '', '', '', 'Keyword created' );
		$this->sync_project_asset( (int) $row['project_id'], $id, $keyword, $user_id );
		$this->events->dispatch( 'rsaip.keyword.created', array( 'id' => $id ) );
		$this->logger->info( 'keywords.created', array( 'id' => $id ) );

		return $this->service->get( $id, true );
	}

	/**
	 * @param array<string, mixed> $input Fields.
	 * @return KeywordDTO|\WP_Error
	 */
	public function update( int $id, array $input, int $user_id = 0 ) {
		$this->service->ensure_tables();
		$existing = $this->repository->find( $id );
		if ( ! $existing ) {
			return new \WP_Error( 'rsaip_keyword_missing', 'Keyword not found.', array( 'status' => 404 ) );
		}

		$row = $this->normalize_row( $input, false );
		if ( array_key_exists( 'primary_keyword', $input ) ) {
			$kw = sanitize_text_field( (string) $input['primary_keyword'] );
			if ( $kw === '' ) {
				return new \WP_Error( 'rsaip_keyword_empty', 'Primary keyword is required.' );
			}
			$row['primary_keyword'] = mb_substr( $kw, 0, 255 );
		}
		$row['updated_at'] = \RSAIP_DB::now_gmt_sql();

		foreach ( $row as $field => $value ) {
			$old = (string) ( $existing[ $field ] ?? '' );
			$new = (string) $value;
			if ( (string) $old !== (string) $new ) {
				$this->history( $id, $user_id, 'updated', $field, $old, $new, 'Field updated' );
			}
		}

		if ( ! $this->repository->update( $id, $row ) ) {
			return new \WP_Error( 'rsaip_keyword_update', 'Could not update keyword.' );
		}

		$project_id = isset( $row['project_id'] ) ? (int) $row['project_id'] : (int) $existing['project_id'];
		$title      = isset( $row['primary_keyword'] ) ? (string) $row['primary_keyword'] : (string) $existing['primary_keyword'];
		$this->sync_project_asset( $project_id, $id, $title, $user_id );

		$this->events->dispatch( 'rsaip.keyword.updated', array( 'id' => $id ) );
		return $this->service->get( $id, true );
	}

	/**
	 * @return true|\WP_Error
	 */
	public function delete( int $id, int $user_id = 0 ) {
		$row = $this->repository->find( $id );
		if ( ! $row ) {
			return new \WP_Error( 'rsaip_keyword_missing', 'Keyword not found.', array( 'status' => 404 ) );
		}
		if ( ! $this->repository->delete( $id ) ) {
			return new \WP_Error( 'rsaip_keyword_delete', 'Could not delete keyword.' );
		}
		if ( $this->project_manager && (int) $row['project_id'] > 0 ) {
			$this->project_manager->detach_asset( (int) $row['project_id'], ProjectService::ASSET_KEYWORD, $id, $user_id );
		}
		$this->events->dispatch( 'rsaip.keyword.deleted', array( 'id' => $id ) );
		return true;
	}

	/**
	 * @param list<int>            $ids  IDs.
	 * @param array<string, mixed> $data Bulk fields (status, cluster_id, project_id, priority, roadmap_phase).
	 * @return array{updated: int}
	 */
	public function bulk_update( array $ids, array $data, int $user_id = 0 ): array {
		$row = array( 'updated_at' => \RSAIP_DB::now_gmt_sql() );
		foreach ( array( 'status', 'cluster_id', 'project_id', 'priority', 'roadmap_phase', 'intent' ) as $key ) {
			if ( array_key_exists( $key, $data ) && $data[ $key ] !== '' && $data[ $key ] !== null ) {
				if ( in_array( $key, array( 'cluster_id', 'project_id', 'priority' ), true ) ) {
					$row[ $key ] = absint( $data[ $key ] );
				} else {
					$row[ $key ] = sanitize_key( (string) $data[ $key ] );
					if ( $key === 'roadmap_phase' || $key === 'status' || $key === 'intent' ) {
						$row[ $key ] = sanitize_text_field( (string) $data[ $key ] );
					}
				}
			}
		}
		$updated = $this->repository->bulk_update( $ids, $row );
		foreach ( $ids as $id ) {
			$this->history( (int) $id, $user_id, 'bulk_updated', '', '', '', 'Bulk update' );
		}
		$this->events->dispatch( 'rsaip.keyword.bulk_updated', array( 'ids' => $ids, 'updated' => $updated ) );
		return array( 'updated' => $updated );
	}

	/**
	 * @return KeywordDTO|\WP_Error
	 */
	public function assign_brief( int $id, int $brief_id, int $user_id = 0 ) {
		return $this->update( $id, array( 'brief_id' => max( 0, $brief_id ) ), $user_id );
	}

	/**
	 * @return KeywordDTO|\WP_Error
	 */
	public function assign_article( int $id, int $article_id, int $user_id = 0 ) {
		return $this->update( $id, array( 'article_id' => max( 0, $article_id ) ), $user_id );
	}

	/**
	 * @return KeywordDTO|\WP_Error
	 */
	public function change_status( int $id, string $status, int $user_id = 0 ) {
		return $this->update( $id, array( 'status' => $this->normalize_status( $status ) ), $user_id );
	}

	/**
	 * @param array<string, mixed> $input Cluster fields.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create_cluster( array $input ) {
		$this->service->ensure_tables();
		$name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		if ( $name === '' ) {
			return new \WP_Error( 'rsaip_cluster_name', 'Cluster name is required.' );
		}
		$now = \RSAIP_DB::now_gmt_sql();
		$id  = $this->repository->insert_cluster(
			array(
				'project_id'  => absint( $input['project_id'] ?? 0 ),
				'name'        => mb_substr( $name, 0, 255 ),
				'description' => sanitize_textarea_field( (string) ( $input['description'] ?? '' ) ),
				'color'       => sanitize_text_field( (string) ( $input['color'] ?? '' ) ),
				'created_at'  => $now,
				'updated_at'  => $now,
			)
		);
		if ( $id <= 0 ) {
			return new \WP_Error( 'rsaip_cluster_create', 'Could not create cluster.' );
		}
		return array(
			'id'         => $id,
			'name'       => $name,
			'project_id' => absint( $input['project_id'] ?? 0 ),
		);
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function add_note( int $keyword_id, string $note, int $user_id = 0 ) {
		if ( ! $this->repository->find( $keyword_id ) ) {
			return new \WP_Error( 'rsaip_keyword_missing', 'Keyword not found.', array( 'status' => 404 ) );
		}
		$note = sanitize_textarea_field( $note );
		if ( $note === '' ) {
			return new \WP_Error( 'rsaip_keyword_note', 'Note is required.' );
		}
		$id = $this->repository->insert_note(
			array(
				'keyword_id' => $keyword_id,
				'user_id'    => max( 0, $user_id ),
				'note'       => $note,
				'created_at' => \RSAIP_DB::now_gmt_sql(),
			)
		);
		$this->history( $keyword_id, $user_id, 'note_added', 'note', '', '', 'Note added' );
		return array(
			'id'         => $id,
			'keyword_id' => $keyword_id,
			'note'       => $note,
		);
	}

	/**
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>
	 */
	private function normalize_row( array $input, bool $for_create ): array {
		$row = array();
		$map = array(
			'intent'            => 'text',
			'difficulty'        => 'int',
			'priority'          => 'int',
			'status'            => 'status',
			'project_id'        => 'int',
			'brief_id'          => 'int',
			'article_id'        => 'int',
			'target_url'        => 'url',
			'cluster_id'        => 'int',
			'parent_keyword_id' => 'int',
			'language'          => 'text',
			'country'           => 'country',
			'roadmap_phase'     => 'text',
			'roadmap_order'     => 'int',
		);

		foreach ( $map as $key => $type ) {
			if ( ! $for_create && ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$value = $input[ $key ] ?? ( $for_create ? $this->default_for( $key ) : null );
			if ( null === $value && ! $for_create ) {
				continue;
			}
			switch ( $type ) {
				case 'int':
					$row[ $key ] = absint( $value );
					if ( 'priority' === $key ) {
						$row[ $key ] = max( 0, min( 100, $row[ $key ] ) );
					}
					if ( 'difficulty' === $key ) {
						$row[ $key ] = max( 0, min( 100, $row[ $key ] ) );
					}
					break;
				case 'status':
					$row[ $key ] = $this->normalize_status( (string) $value );
					break;
				case 'url':
					$row[ $key ] = esc_url_raw( (string) $value );
					break;
				case 'country':
					$row[ $key ] = strtoupper( sanitize_text_field( (string) $value ) );
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
			'intent'            => '',
			'difficulty'        => 0,
			'priority'          => 50,
			'status'            => 'idea',
			'project_id'        => 0,
			'brief_id'          => 0,
			'article_id'        => 0,
			'target_url'        => '',
			'cluster_id'        => 0,
			'parent_keyword_id' => 0,
			'language'          => '',
			'country'           => '',
			'roadmap_phase'     => 'backlog',
			'roadmap_order'     => 0,
		);
		return $defaults[ $key ] ?? '';
	}

	public function normalize_status( string $status ): string {
		$status = sanitize_key( $status );
		$allowed = array( 'idea', 'queued', 'in_progress', 'briefed', 'writing', 'published', 'archived' );
		return in_array( $status, $allowed, true ) ? $status : 'idea';
	}

	private function history( int $keyword_id, int $user_id, string $action, string $field, string $old, string $new, string $message ): void {
		$this->repository->insert_history(
			array(
				'keyword_id' => $keyword_id,
				'user_id'    => max( 0, $user_id ),
				'action'     => sanitize_key( $action ),
				'field_name' => sanitize_key( $field ),
				'old_value'  => mb_substr( $old, 0, 1000 ),
				'new_value'  => mb_substr( $new, 0, 1000 ),
				'message'    => mb_substr( sanitize_text_field( $message ), 0, 500 ),
				'created_at' => \RSAIP_DB::now_gmt_sql(),
			)
		);
	}

	private function sync_project_asset( int $project_id, int $keyword_id, string $title, int $user_id ): void {
		if ( $project_id <= 0 || ! $this->project_manager ) {
			return;
		}
		$this->project_manager->attach_asset( $project_id, ProjectService::ASSET_KEYWORD, $keyword_id, $title, $user_id );
	}
}
