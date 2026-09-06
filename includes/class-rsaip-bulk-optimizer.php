<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSAIP_Bulk_Optimizer {
	private RSAIP_AI $ai;
	private RSAIP_Audit $audit;
	private RSAIP_Internal_Link_Suggester $suggester;
	private RSAIP_Auto_Linker $auto_linker;
	private RSAIP_Recipe_Optimizer $recipe_optimizer;
	private RSAIP_Schema_Validator $schema_validator;

	public function __construct( RSAIP_AI $ai, RSAIP_Audit $audit, RSAIP_Internal_Link_Suggester $suggester, RSAIP_Auto_Linker $auto_linker, RSAIP_Recipe_Optimizer $recipe_optimizer, RSAIP_Schema_Validator $schema_validator ) {
		$this->ai               = $ai;
		$this->audit            = $audit;
		$this->suggester        = $suggester;
		$this->auto_linker      = $auto_linker;
		$this->recipe_optimizer = $recipe_optimizer;
		$this->schema_validator = $schema_validator;
	}

	public function enqueue_missing_posts( int $limit = 100, string $task_type = 'fix_with_ai' ): array {
		$task_type = sanitize_key( $task_type );
		if ( 'fix_with_ai' === $task_type && ( ! function_exists( 'rsaip_fix_with_ai_blocked' ) || rsaip_fix_with_ai_blocked() ) ) {
			return array(
				'enqueued' => 0,
				'task'     => $task_type,
				'ok'       => false,
				'code'     => 'fix_with_ai_blocked',
				'message'  => function_exists( 'rsaip_fix_with_ai_frozen_message' )
					? rsaip_fix_with_ai_frozen_message()
					: 'Fix With AI is disabled in Milestone 4.',
			);
		}
		$frozen    = ! function_exists( 'rsaip_never_modify_posts' ) || rsaip_never_modify_posts();
		$is_mutation = function_exists( 'rsaip_is_existing_post_mutation_task' )
			? rsaip_is_existing_post_mutation_task( $task_type )
			: in_array( $task_type, array( 'fix_with_ai', 'schema_fix' ), true );
		if ( $frozen && $is_mutation ) {
			return array(
				'enqueued' => 0,
				'task'     => $task_type,
				'ok'       => false,
				'message'  => function_exists( 'rsaip_existing_post_mutation_frozen_message' )
					? rsaip_existing_post_mutation_frozen_message()
					: 'This existing-post mutation is temporarily disabled while Safe Article Mode is enabled.',
			);
		}

		$settings   = rsaip_get_settings();
		$post_types = (array) ( $settings['auto_insert_post_types'] ?? array( 'post' ) );
		$statuses   = (array) ( $settings['auto_insert_statuses'] ?? array( 'publish' ) );
		$now        = RSAIP_DB::now_gmt_sql();
		$queue      = rsaip_repo( \RecipeSeoAiPro\Database\Repositories\BulkQueueRepository::class );

		$q = new WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => $statuses,
				'posts_per_page'         => max( 1, min( 500, $limit ) ),
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$enqueued = 0;
		foreach ( (array) $q->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			if ( $queue->enqueue( (int) $post->ID, $task_type, $now ) > 0 ) {
				$enqueued++;
			}
		}

		return array(
			'enqueued' => $enqueued,
			'task'     => $task_type,
		);
	}

	public function cron_process_queue(): array {
		$settings = rsaip_get_settings();
		$batch    = (int) ( $settings['bulk_queue_batch_size'] ?? 10 );
		return $this->process_queue( $batch );
	}

	public function process_queue( int $limit ): array {
		if ( ! function_exists( 'rsaip_never_modify_posts' ) || rsaip_never_modify_posts() ) {
			$blocked = $this->neutralize_existing_post_mutation_jobs();
			$message = function_exists( 'rsaip_existing_post_mutation_frozen_message' )
				? rsaip_existing_post_mutation_frozen_message()
				: 'This existing-post mutation is temporarily disabled while Safe Article Mode is enabled.';
			return array(
				'processed' => $blocked,
				'done'      => $blocked,
				'failed'    => 0,
				'blocked'   => $blocked,
				'message'   => $message,
			);
		}

		$queue = rsaip_repo( \RecipeSeoAiPro\Database\Repositories\BulkQueueRepository::class );
		$limit = max( 1, min( 50, $limit ) );
		$now   = RSAIP_DB::now_gmt_sql();

		$rows = $queue->select_claimable( $limit );

		$processed = 0;
		$done      = 0;
		$failed    = 0;

		foreach ( $rows as $row ) {
			$id       = absint( $row['id'] ?? 0 );
			$post_id  = absint( $row['post_id'] ?? 0 );
			$task     = sanitize_key( (string) ( $row['task_type'] ?? 'fix_with_ai' ) );
			$attempts = absint( $row['attempts'] ?? 0 );

			if ( $id <= 0 || $post_id <= 0 ) {
				continue;
			}

			$queue->mark_processing( $id, $now, $attempts + 1 );

			try {
				$result = $this->run_task_for_post( $post_id, $task );
			} catch ( Throwable $e ) {
				$result = array(
					'ok'      => false,
					'message' => $e->getMessage(),
				);
			}
			$processed++;

			if ( ! empty( $result['ok'] ) ) {
				$done++;
				$queue->mark_done(
					$id,
					RSAIP_DB::now_gmt_sql(),
					(string) wp_json_encode( $result )
				);
			} else {
				$queue->mark_failed(
					$id,
					RSAIP_DB::now_gmt_sql(),
					sanitize_text_field( (string) ( $result['message'] ?? 'unknown_error' ) ),
					(string) wp_json_encode( $result )
				);
			}

			if ( empty( $result['ok'] ) ) {
				$failed++;
			}
		}

		return array(
			'processed' => $processed,
			'done'      => $done,
			'failed'    => $failed,
		);
	}

	/**
	 * Mark all existing-post mutation jobs terminal (done + blocked payload).
	 * Does not delete rows. select_claimable() only reads pending/failed, so these cannot resume.
	 */
	public function neutralize_existing_post_mutation_jobs(): int {
		$queue = rsaip_repo( \RecipeSeoAiPro\Database\Repositories\BulkQueueRepository::class );
		$types = function_exists( 'rsaip_existing_post_mutation_task_types' )
			? rsaip_existing_post_mutation_task_types()
			: array( 'fix_with_ai', 'schema_fix' );
		$message = function_exists( 'rsaip_existing_post_mutation_frozen_message' )
			? rsaip_existing_post_mutation_frozen_message()
			: 'This existing-post mutation is temporarily disabled while Safe Article Mode is enabled.';
		$payload = (string) wp_json_encode(
			array(
				'ok'      => false,
				'blocked' => true,
				'skipped' => true,
				'message' => $message,
				'reason'  => 'safe_article_mode',
			)
		);

		return $queue->retire_existing_post_mutation_jobs(
			$types,
			RSAIP_DB::now_gmt_sql(),
			$payload
		);
	}

	public function get_queue_stats(): array {
		$queue = rsaip_repo( \RecipeSeoAiPro\Database\Repositories\BulkQueueRepository::class );

		return array(
			'pending'    => $queue->count_by_status( 'pending' ),
			'processing' => $queue->count_by_status( 'processing' ),
			'done'       => $queue->count_by_status( 'done' ),
			'failed'     => $queue->count_by_status( 'failed' ),
		);
	}

	private function run_task_for_post( int $post_id, string $task_type ): array {
		if ( 'fix_with_ai' === $task_type && ( ! function_exists( 'rsaip_fix_with_ai_blocked' ) || rsaip_fix_with_ai_blocked() ) ) {
			return array(
				'ok'      => false,
				'blocked' => true,
				'code'    => 'fix_with_ai_blocked',
				'message' => function_exists( 'rsaip_fix_with_ai_frozen_message' )
					? rsaip_fix_with_ai_frozen_message()
					: 'Fix With AI is disabled in Milestone 4.',
			);
		}

		$frozen = ! function_exists( 'rsaip_never_modify_posts' ) || rsaip_never_modify_posts();
		$is_mutation = function_exists( 'rsaip_is_existing_post_mutation_task' )
			? rsaip_is_existing_post_mutation_task( $task_type )
			: in_array( $task_type, array( 'fix_with_ai', 'schema_fix' ), true );
		if ( $frozen && $is_mutation ) {
			return array(
				'ok'      => false,
				'blocked' => true,
				'message' => function_exists( 'rsaip_existing_post_mutation_frozen_message' )
					? rsaip_existing_post_mutation_frozen_message()
					: 'This existing-post mutation is temporarily disabled while Safe Article Mode is enabled.',
			);
		}

		if ( $task_type === 'fix_with_ai' ) {
			return $this->ai->fix_post_with_ai( $post_id, $this->suggester, $this->auto_linker, $this->recipe_optimizer );
		}
		if ( $task_type === 'schema_fix' ) {
			return $this->schema_validator->fix_post_schema( $post_id );
		}

		return array(
			'ok'      => false,
			'message' => 'unknown_task',
		);
	}
}
