<?php
declare(strict_types=1);

/**
 * Content Optimizer orchestrator (Phase 4.1).
 *
 * Isolated from RSAIP_AI / Article Generator / Brief / Workspace.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentOptimizer;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ContentOptimizerService
 */
final class ContentOptimizerService {

	private ContentAnalyzer $analyzer;

	private OptimizationEngine $engine;

	private RewriteEngine $rewrite;

	private ContentOptimizerRepository $repository;

	private CacheInterface $cache;

	private LoggerInterface $logger;

	private EventDispatcherInterface $events;

	public function __construct(
		ContentAnalyzer $analyzer,
		OptimizationEngine $engine,
		RewriteEngine $rewrite,
		ContentOptimizerRepository $repository,
		CacheInterface $cache,
		LoggerInterface $logger,
		EventDispatcherInterface $events
	) {
		$this->analyzer   = $analyzer;
		$this->engine     = $engine;
		$this->rewrite    = $rewrite;
		$this->repository = $repository;
		$this->cache      = $cache;
		$this->logger     = $logger;
		$this->events     = $events;
	}

	public function ensure_tables(): void {
		if ( class_exists( 'RSAIP_DB' ) ) {
			\RSAIP_DB::migrate_optimizer_tables();
		}
	}

	/**
	 * @param array<string, mixed> $options enrich, keywords.
	 * @return ContentOptimizerDTO|\WP_Error
	 */
	public function analyze_post( int $post_id, array $options = array() ) {
		$source = $this->load_post_source( $post_id );
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		return $this->analyze_content(
			$source['content'],
			array_merge(
				$options,
				array(
					'post_id' => $post_id,
					'title'   => $source['title'],
					'meta'    => $source['meta'],
				)
			)
		);
	}

	/**
	 * @param array<string, mixed> $options post_id, title, meta, keywords, enrich.
	 * @return ContentOptimizerDTO|\WP_Error
	 */
	public function analyze_content( string $content, array $options = array() ) {
		$this->ensure_tables();
		$analysis = $this->analyzer->analyze(
			$content,
			array(
				'title'    => (string) ( $options['title'] ?? '' ),
				'meta'     => (string) ( $options['meta'] ?? '' ),
				'keywords' => (string) ( $options['keywords'] ?? '' ),
				'enrich'   => ! empty( $options['enrich'] ),
			)
		);

		$dto                       = new ContentOptimizerDTO();
		$dto->post_id              = absint( $options['post_id'] ?? 0 );
		$dto->workflow             = 'analyze';
		$dto->scope                = 'full_article';
		$dto->status               = 'analyzed';
		$dto->seo_score            = (int) ( $analysis['seo_score'] ?? 0 );
		$dto->readability_score    = (int) ( $analysis['readability_score'] ?? 0 );
		$dto->eeat_score           = (int) ( $analysis['eeat_score'] ?? 0 );
		$dto->recipe_score         = (int) ( $analysis['recipe_quality_score'] ?? 0 );
		$dto->title                = (string) ( $options['title'] ?? '' );
		$dto->meta_description     = (string) ( $options['meta'] ?? '' );
		$dto->original_content     = $content;
		$dto->original_title       = $dto->title;
		$dto->original_meta        = $dto->meta_description;
		$dto->analysis             = $analysis;

		$now = \RSAIP_DB::now_gmt_sql();
		$id  = $this->repository->insert_run(
			array(
				'post_id'            => $dto->post_id,
				'workflow'           => 'analyze',
				'scope'              => 'full_article',
				'status'             => 'analyzed',
				'seo_score'          => $dto->seo_score,
				'readability_score'  => $dto->readability_score,
				'eeat_score'         => $dto->eeat_score,
				'recipe_score'       => $dto->recipe_score,
				'title'              => mb_substr( $dto->title, 0, 255 ),
				'meta_description'   => $dto->meta_description,
				'analysis_json'      => (string) wp_json_encode( $analysis ),
				'user_id'            => get_current_user_id(),
				'created_at'         => $now,
				'updated_at'         => $now,
			)
		);
		$dto->id         = $id;
		$dto->created_at = $now;
		$dto->updated_at = $now;

		if ( $id > 0 ) {
			$this->store_version(
				$id,
				$dto->post_id,
				'original',
				$content,
				$dto->title,
				$dto->meta_description,
				array( 'analysis' => $analysis )
			);
		}

		$this->events->dispatch( 'rsaip.content_optimizer.analyzed', array( 'id' => $id, 'post_id' => $dto->post_id ) );
		return $dto;
	}

	/**
	 * @param array<string, mixed> $options post_id, title, meta, keywords, notes, selected_text, content override.
	 * @return ContentOptimizerDTO|\WP_Error
	 */
	public function optimize( string $workflow, string $scope, array $options = array() ) {
		$this->ensure_tables();
		$post_id = absint( $options['post_id'] ?? 0 );
		$content = (string) ( $options['content'] ?? '' );
		$title   = (string) ( $options['title'] ?? '' );
		$meta    = (string) ( $options['meta'] ?? '' );

		if ( $post_id > 0 && $content === '' ) {
			$source = $this->load_post_source( $post_id );
			if ( is_wp_error( $source ) ) {
				return $source;
			}
			$content = $source['content'];
			$title   = $title !== '' ? $title : $source['title'];
			$meta    = $meta !== '' ? $meta : $source['meta'];
		}
		if ( trim( $content ) === '' && $this->rewrite->normalize_scope( $scope ) !== 'meta_description' ) {
			return new \WP_Error( 'rsaip_opt_content', 'Content is required.' );
		}

		$result = $this->engine->run(
			$workflow,
			$scope,
			$content,
			array(
				'title'          => $title,
				'meta'           => $meta,
				'keywords'       => (string) ( $options['keywords'] ?? '' ),
				'notes'          => (string) ( $options['notes'] ?? '' ),
				'selected_text'  => (string) ( $options['selected_text'] ?? '' ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$analysis = $this->analyzer->analyze(
			(string) $result['content'],
			array(
				'title' => (string) $result['title'],
				'meta'  => (string) $result['meta_description'],
			)
		);

		$dto                      = new ContentOptimizerDTO();
		$dto->post_id             = $post_id;
		$dto->workflow            = (string) $result['workflow'];
		$dto->scope               = (string) $result['scope'];
		$dto->status              = 'optimized';
		$dto->seo_score           = (int) ( $analysis['seo_score'] ?? 0 );
		$dto->readability_score   = (int) ( $analysis['readability_score'] ?? 0 );
		$dto->eeat_score          = (int) ( $analysis['eeat_score'] ?? 0 );
		$dto->recipe_score        = (int) ( $analysis['recipe_quality_score'] ?? 0 );
		$dto->original_content    = $content;
		$dto->optimized_content   = (string) $result['content'];
		$dto->original_title      = $title;
		$dto->optimized_title     = (string) $result['title'];
		$dto->original_meta       = $meta;
		$dto->optimized_meta      = (string) $result['meta_description'];
		$dto->title               = $dto->optimized_title;
		$dto->meta_description    = $dto->optimized_meta;
		$dto->analysis            = array_merge(
			$analysis,
			array(
				'summary'                   => $result['summary'] ?? array(),
				'internal_link_suggestions' => $result['internal_link_suggestions'] ?? array(),
				'schema_recommendations'    => $result['schema_recommendations'] ?? array(),
				'warnings'                  => $result['warnings'] ?? array(),
			)
		);
		$dto->diff = $this->rewrite->build_diff(
			wp_strip_all_tags( $content ),
			wp_strip_all_tags( $dto->optimized_content )
		);

		$now = \RSAIP_DB::now_gmt_sql();
		$id  = $this->repository->insert_run(
			array(
				'post_id'           => $post_id,
				'workflow'          => $dto->workflow,
				'scope'             => $dto->scope,
				'status'            => 'optimized',
				'seo_score'         => $dto->seo_score,
				'readability_score' => $dto->readability_score,
				'eeat_score'        => $dto->eeat_score,
				'recipe_score'      => $dto->recipe_score,
				'title'             => mb_substr( $dto->optimized_title, 0, 255 ),
				'meta_description'  => $dto->optimized_meta,
				'analysis_json'     => (string) wp_json_encode( $dto->analysis ),
				'user_id'           => get_current_user_id(),
				'created_at'        => $now,
				'updated_at'        => $now,
			)
		);
		$dto->id = $id;
		if ( $id > 0 ) {
			$this->store_version( $id, $post_id, 'original', $content, $title, $meta );
			$this->store_version( $id, $post_id, 'optimized', $dto->optimized_content, $dto->optimized_title, $dto->optimized_meta, $dto->analysis );
			$dto->versions = $this->repository->list_versions( $id );
		}

		$this->events->dispatch(
			'rsaip.content_optimizer.rewritten',
			array(
				'id'       => $id,
				'workflow' => $dto->workflow,
				'scope'    => $dto->scope,
			)
		);

		return $dto;
	}

	/**
	 * @return ContentOptimizerDTO|\WP_Error
	 */
	public function get( int $id ) {
		$this->ensure_tables();
		$row = $this->repository->find_run( $id );
		if ( ! $row ) {
			return new \WP_Error( 'rsaip_opt_missing', 'Optimization run not found.', array( 'status' => 404 ) );
		}
		return $this->dto_from_run( $row, true );
	}

	/**
	 * @return array{original: string, optimized: string, diff: list<array<string,string>>, titles: array<string,string>, metas: array<string,string>}|\WP_Error
	 */
	public function compare( int $optimization_id ) {
		$dto = $this->get( $optimization_id );
		if ( is_wp_error( $dto ) ) {
			return $dto;
		}
		return array(
			'original'  => $dto->original_content,
			'optimized' => $dto->optimized_content,
			'diff'      => $dto->diff,
			'titles'    => array(
				'original'  => $dto->original_title,
				'optimized' => $dto->optimized_title,
			),
			'metas'     => array(
				'original'  => $dto->original_meta,
				'optimized' => $dto->optimized_meta,
			),
		);
	}

	/**
	 * Backup current post, apply optimized version.
	 *
	 * @return ContentOptimizerDTO|\WP_Error
	 */
	public function apply( int $optimization_id, int $user_id = 0 ) {
		if ( ! function_exists( 'rsaip_never_modify_posts' ) || rsaip_never_modify_posts() ) {
			return new \WP_Error(
				'rsaip_opt_frozen',
				function_exists( 'rsaip_existing_post_mutation_frozen_message' )
					? rsaip_existing_post_mutation_frozen_message()
					: 'This existing-post mutation is temporarily disabled while Safe Article Mode is enabled.',
				array( 'status' => 403 )
			);
		}

		$dto = $this->get( $optimization_id );
		if ( is_wp_error( $dto ) ) {
			return $dto;
		}
		if ( $dto->post_id <= 0 ) {
			return new \WP_Error( 'rsaip_opt_post', 'This run is not linked to a post.' );
		}
		if ( $dto->optimized_content === '' && $dto->optimized_meta === '' && $dto->optimized_title === '' ) {
			return new \WP_Error( 'rsaip_opt_empty', 'No optimized content to apply.' );
		}

		$source = $this->load_post_source( $dto->post_id );
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		// Backup before apply.
		$this->store_version(
			$optimization_id,
			$dto->post_id,
			'backup',
			$source['content'],
			$source['title'],
			$source['meta'],
			array( 'reason' => 'pre_apply' )
		);

		$update = array( 'ID' => $dto->post_id );
		if ( $dto->scope === 'meta_description' ) {
			// Meta only — do not touch post body.
		} elseif ( $dto->optimized_content !== '' ) {
			$update['post_content'] = wp_kses_post( $dto->optimized_content );
		}
		if ( $dto->optimized_title !== '' && in_array( $dto->scope, array( 'full_article', 'heading' ), true ) ) {
			$update['post_title'] = sanitize_text_field( $dto->optimized_title );
		}

		if ( count( $update ) > 1 ) {
			$result = wp_update_post( wp_slash( $update ), true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( $dto->optimized_meta !== '' ) {
			update_post_meta( $dto->post_id, '_rsaip_meta_description', $dto->optimized_meta );
			// Common SEO meta keys without requiring Yoast/RankMath coupling.
			update_post_meta( $dto->post_id, '_yoast_wpseo_metadesc', $dto->optimized_meta );
		}

		$this->store_version(
			$optimization_id,
			$dto->post_id,
			'applied',
			$dto->optimized_content !== '' ? $dto->optimized_content : $source['content'],
			$dto->optimized_title !== '' ? $dto->optimized_title : $source['title'],
			$dto->optimized_meta !== '' ? $dto->optimized_meta : $source['meta']
		);

		$this->repository->update_run(
			$optimization_id,
			array(
				'status'     => 'applied',
				'updated_at' => \RSAIP_DB::now_gmt_sql(),
			)
		);

		$this->events->dispatch(
			'rsaip.content_optimizer.applied',
			array(
				'id'      => $optimization_id,
				'post_id' => $dto->post_id,
				'user_id' => $user_id,
			)
		);

		return $this->get( $optimization_id );
	}

	/**
	 * Undo last applied change using latest backup for this run (or post).
	 *
	 * @return ContentOptimizerDTO|\WP_Error
	 */
	public function undo( int $optimization_id, int $user_id = 0 ) {
		$dto = $this->get( $optimization_id );
		if ( is_wp_error( $dto ) ) {
			return $dto;
		}
		if ( $dto->post_id <= 0 ) {
			return new \WP_Error( 'rsaip_opt_post', 'This run is not linked to a post.' );
		}

		$backup = $this->repository->find_latest_labeled( $dto->post_id, 'backup', $optimization_id );
		if ( ! $backup ) {
			$backup = $this->repository->find_latest_labeled( $dto->post_id, 'backup', 0 );
		}
		if ( ! $backup ) {
			return new \WP_Error( 'rsaip_opt_undo', 'No backup available to undo.' );
		}

		return $this->restore_version( (int) $backup['id'], $user_id );
	}

	/**
	 * @return ContentOptimizerDTO|\WP_Error
	 */
	public function restore_version( int $version_id, int $user_id = 0 ) {
		if ( ! function_exists( 'rsaip_never_modify_posts' ) || rsaip_never_modify_posts() ) {
			return new \WP_Error(
				'rsaip_opt_frozen',
				function_exists( 'rsaip_existing_post_mutation_frozen_message' )
					? rsaip_existing_post_mutation_frozen_message()
					: 'This existing-post mutation is temporarily disabled while Safe Article Mode is enabled.',
				array( 'status' => 403 )
			);
		}

		$this->ensure_tables();
		$version = $this->repository->find_version( $version_id );
		if ( ! $version ) {
			return new \WP_Error( 'rsaip_opt_version', 'Version not found.', array( 'status' => 404 ) );
		}
		$post_id = (int) ( $version['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'rsaip_opt_post', 'Version is not linked to a post.' );
		}

		$current = $this->load_post_source( $post_id );
		if ( is_wp_error( $current ) ) {
			return $current;
		}

		$opt_id = (int) ( $version['optimization_id'] ?? 0 );
		// Snapshot current before restore.
		$this->store_version( $opt_id, $post_id, 'backup', $current['content'], $current['title'], $current['meta'], array( 'reason' => 'pre_restore' ) );

		$result = wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_content' => wp_kses_post( (string) ( $version['content'] ?? '' ) ),
					'post_title'   => sanitize_text_field( (string) ( $version['title'] ?? $current['title'] ) ),
				)
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$meta = (string) ( $version['meta_description'] ?? '' );
		if ( $meta !== '' ) {
			update_post_meta( $post_id, '_rsaip_meta_description', $meta );
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta );
		}

		if ( $opt_id > 0 ) {
			$this->repository->update_run(
				$opt_id,
				array(
					'status'     => 'restored',
					'updated_at' => \RSAIP_DB::now_gmt_sql(),
				)
			);
		}

		$this->events->dispatch(
			'rsaip.content_optimizer.undone',
			array(
				'version_id' => $version_id,
				'post_id'    => $post_id,
				'user_id'    => $user_id,
			)
		);

		if ( $opt_id > 0 ) {
			return $this->get( $opt_id );
		}

		$dto          = new ContentOptimizerDTO();
		$dto->post_id = $post_id;
		$dto->status  = 'restored';
		return $dto;
	}

	/**
	 * @return array{runs: list<array<string,mixed>>, versions: list<array<string,mixed>>}
	 */
	public function history( int $post_id ): array {
		$this->ensure_tables();
		return array(
			'runs'     => $this->repository->list_runs( $post_id, 30 ),
			'versions' => $this->repository->list_post_versions( $post_id, 50 ),
		);
	}

	/**
	 * @return array{content: string, title: string, meta: string}|\WP_Error
	 */
	public function load_post_source( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error( 'rsaip_opt_post', 'Post not found.', array( 'status' => 404 ) );
		}
		$meta = (string) get_post_meta( $post_id, '_rsaip_meta_description', true );
		if ( $meta === '' ) {
			$meta = (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
		}
		return array(
			'content' => (string) $post->post_content,
			'title'   => (string) $post->post_title,
			'meta'    => $meta,
		);
	}

	/**
	 * @param array<string, mixed> $meta Extra meta JSON.
	 */
	private function store_version(
		int $optimization_id,
		int $post_id,
		string $label,
		string $content,
		string $title,
		string $meta_description,
		array $meta = array()
	): int {
		$version_no = $optimization_id > 0 ? $this->repository->next_version_no( $optimization_id ) : 1;
		return $this->repository->insert_version(
			array(
				'optimization_id'  => $optimization_id,
				'post_id'          => $post_id,
				'version_no'       => $version_no,
				'label'            => sanitize_key( $label ),
				'content'          => $content,
				'title'            => mb_substr( $title, 0, 255 ),
				'meta_description' => $meta_description,
				'meta_json'        => (string) wp_json_encode( $meta ),
				'user_id'          => get_current_user_id(),
				'created_at'       => \RSAIP_DB::now_gmt_sql(),
			)
		);
	}

	/**
	 * @param array<string, mixed> $row Run row.
	 */
	private function dto_from_run( array $row, bool $with_versions = false ): ContentOptimizerDTO {
		$dto                      = new ContentOptimizerDTO();
		$dto->id                  = (int) ( $row['id'] ?? 0 );
		$dto->post_id             = (int) ( $row['post_id'] ?? 0 );
		$dto->workflow            = (string) ( $row['workflow'] ?? '' );
		$dto->scope               = (string) ( $row['scope'] ?? '' );
		$dto->status              = (string) ( $row['status'] ?? '' );
		$dto->seo_score           = (int) ( $row['seo_score'] ?? 0 );
		$dto->readability_score   = (int) ( $row['readability_score'] ?? 0 );
		$dto->eeat_score          = (int) ( $row['eeat_score'] ?? 0 );
		$dto->recipe_score        = (int) ( $row['recipe_score'] ?? 0 );
		$dto->title               = (string) ( $row['title'] ?? '' );
		$dto->meta_description    = (string) ( $row['meta_description'] ?? '' );
		$dto->user_id             = (int) ( $row['user_id'] ?? 0 );
		$dto->created_at          = (string) ( $row['created_at'] ?? '' );
		$dto->updated_at          = (string) ( $row['updated_at'] ?? '' );
		if ( ! empty( $row['analysis_json'] ) ) {
			$decoded = json_decode( (string) $row['analysis_json'], true );
			if ( is_array( $decoded ) ) {
				$dto->analysis = $decoded;
			}
		}

		$original  = $this->repository->find_latest_labeled( $dto->post_id, 'original', $dto->id );
		$optimized = $this->repository->find_latest_labeled( $dto->post_id, 'optimized', $dto->id );
		if ( $original ) {
			$dto->original_content = (string) ( $original['content'] ?? '' );
			$dto->original_title   = (string) ( $original['title'] ?? '' );
			$dto->original_meta    = (string) ( $original['meta_description'] ?? '' );
		}
		if ( $optimized ) {
			$dto->optimized_content = (string) ( $optimized['content'] ?? '' );
			$dto->optimized_title   = (string) ( $optimized['title'] ?? '' );
			$dto->optimized_meta    = (string) ( $optimized['meta_description'] ?? '' );
		}
		if ( $dto->original_content !== '' || $dto->optimized_content !== '' ) {
			$dto->diff = $this->rewrite->build_diff(
				wp_strip_all_tags( $dto->original_content ),
				wp_strip_all_tags( $dto->optimized_content )
			);
		}
		if ( $with_versions ) {
			$dto->versions = $this->repository->list_versions( $dto->id );
		}
		return $dto;
	}
}
