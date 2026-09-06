<?php
declare(strict_types=1);

/**
 * AI Recipe Assistant orchestrator (Phase 5.2).
 *
 * Isolated from Recipe Builder code changes — reads/writes Builder recipes via DI only.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\RecipeAI;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Contracts\SettingsServiceInterface;
use RecipeSeoAiPro\Modules\Ai\AiProviderRegistry;
use RecipeSeoAiPro\Modules\RecipeBuilder\RecipeBuilderService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeAiService
 */
final class RecipeAiService {

	private RecipeAiRepository $repository;

	private IngredientAssistant $ingredients;

	private InstructionAssistant $instructions;

	private NutritionSuggestionEngine $nutrition;

	private RecipeRewriteEngine $rewrite;

	private RecipeVariationEngine $variations;

	private RecipePromptBuilder $prompts;

	private AiJsonDecoder $decoder;

	private AiProviderRegistry $providers;

	private SettingsServiceInterface $settings;

	private CacheInterface $cache;

	private LoggerInterface $logger;

	private EventDispatcherInterface $events;

	private ?RecipeBuilderService $builder;

	private PostRecipeSource $posts;

	public function __construct(
		RecipeAiRepository $repository,
		IngredientAssistant $ingredients,
		InstructionAssistant $instructions,
		NutritionSuggestionEngine $nutrition,
		RecipeRewriteEngine $rewrite,
		RecipeVariationEngine $variations,
		RecipePromptBuilder $prompts,
		AiJsonDecoder $decoder,
		AiProviderRegistry $providers,
		SettingsServiceInterface $settings,
		CacheInterface $cache,
		LoggerInterface $logger,
		EventDispatcherInterface $events,
		?RecipeBuilderService $builder = null,
		?PostRecipeSource $posts = null
	) {
		$this->repository   = $repository;
		$this->ingredients  = $ingredients;
		$this->instructions = $instructions;
		$this->nutrition    = $nutrition;
		$this->rewrite      = $rewrite;
		$this->variations   = $variations;
		$this->prompts      = $prompts;
		$this->decoder      = $decoder;
		$this->providers    = $providers;
		$this->settings     = $settings;
		$this->cache        = $cache;
		$this->logger       = $logger;
		$this->events       = $events;
		$this->builder      = $builder;
		$this->posts        = $posts instanceof PostRecipeSource ? $posts : new PostRecipeSource();
	}

	public function ensure_tables(): void {
		if ( class_exists( 'RSAIP_DB' ) ) {
			\RSAIP_DB::migrate_recipe_ai_tables();
		}
	}

	/**
	 * @return RecipeAiDTO|\WP_Error
	 */
	public function get( int $id ) {
		$this->ensure_tables();
		$row = $this->repository->find_run( $id );
		if ( ! $row ) {
			return new \WP_Error( 'rsaip_recipe_ai_missing', 'Run not found.', array( 'status' => 404 ) );
		}
		return $this->hydrate( $row );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_runs( int $rb_recipe_id = 0, int $limit = 30 ): array {
		$this->ensure_tables();
		return $this->repository->list_runs( $rb_recipe_id, $limit );
	}

	/**
	 * Load recipe payload from Builder DI, WordPress post, or posted JSON.
	 *
	 * @param array<string, mixed> $posted Optional posted recipe.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function resolve_recipe( int $rb_recipe_id, array $posted = array(), string $source_type = 'builder', int $post_id = 0 ) {
		$source_type = sanitize_key( $source_type );
		if ( $source_type === '' ) {
			$source_type = 'builder';
		}

		if ( $posted ) {
			$normalized = $this->normalize_recipe( $posted );
			if ( $source_type === 'post' && $normalized['post_id'] <= 0 && $post_id > 0 ) {
				$normalized['post_id']     = $post_id;
				$normalized['source_type'] = 'post';
			}
			return $normalized;
		}

		if ( $source_type === 'post' ) {
			return $this->resolve_post_recipe( $post_id > 0 ? $post_id : $rb_recipe_id );
		}

		if ( $rb_recipe_id <= 0 ) {
			return new \WP_Error( 'rsaip_recipe_ai_recipe', 'Provide a Recipe Builder recipe ID or recipe JSON.' );
		}
		if ( ! $this->builder ) {
			return new \WP_Error( 'rsaip_recipe_ai_builder', 'Recipe Builder service is unavailable.' );
		}
		$dto = $this->builder->get( $rb_recipe_id );
		if ( is_wp_error( $dto ) ) {
			return $dto;
		}
		$arr = $dto->to_array();
		$arr['source_type'] = 'builder';
		return $arr;
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function resolve_post_recipe( int $post_id ) {
		$loaded = $this->posts->load( $post_id );
		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}
		return $this->normalize_recipe( $loaded );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function search_posts( string $query, int $limit = 20 ): array {
		return $this->posts->search( $query, $limit );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_builder_recipes( int $limit = 50 ): array {
		if ( ! $this->builder ) {
			return array();
		}
		return $this->builder->list_recipes( $limit );
	}

	/**
	 * Run an AI action and persist compare snapshot.
	 *
	 * @param array<string, mixed> $options Options.
	 * @return RecipeAiDTO|\WP_Error
	 */
	public function run_action( string $action, array $options = array() ) {
		$this->ensure_tables();
		$action = sanitize_key( $action );
		$mode   = sanitize_key( (string) ( $options['mode'] ?? '' ) );
		$extra  = sanitize_textarea_field( (string) ( $options['extra'] ?? '' ) );
		$rb_id  = absint( $options['rb_recipe_id'] ?? 0 );
		$user   = absint( $options['user_id'] ?? 0 );
		$posted = isset( $options['recipe'] ) && is_array( $options['recipe'] ) ? $options['recipe'] : array();
		$source = sanitize_key( (string) ( $options['source_type'] ?? 'builder' ) );
		$post_id = absint( $options['post_id'] ?? 0 );
		if ( $source === '' ) {
			$source = 'builder';
		}
		if ( $source === 'post' && $post_id <= 0 && ! empty( $posted['post_id'] ) ) {
			$post_id = absint( $posted['post_id'] );
		}

		$original = $this->resolve_recipe( $rb_id, $posted, $source, $post_id );
		if ( is_wp_error( $original ) ) {
			return $original;
		}
		if ( $source === 'post' ) {
			$rb_id = 0;
			$post_id = absint( $original['post_id'] ?? $post_id );
			$original['source_type'] = 'post';
			$original['post_id']     = $post_id;
		} elseif ( $rb_id <= 0 ) {
			$rb_id = absint( $original['id'] ?? 0 );
			$original['source_type'] = 'builder';
		}

		$result = $this->dispatch_action( $action, $original, $mode, $extra );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$optimized = $original;
		if ( isset( $result['recipe'] ) && is_array( $result['recipe'] ) ) {
			$optimized = $this->merge_recipe( $original, $result['recipe'] );
		}

		// Preserve source identity across AI merges.
		$optimized['source_type'] = (string) ( $original['source_type'] ?? 'builder' );
		$optimized['post_id']     = absint( $original['post_id'] ?? 0 );
		$optimized['parse_mode']  = (string) ( $original['parse_mode'] ?? '' );

		$analysis = $result;
		unset( $analysis['recipe'] );
		if ( $action === 'analyze' ) {
			$optimized = $original;
		}

		$diff    = $this->build_diff( $original, $optimized );
		$summary = sanitize_textarea_field( (string) ( $result['summary'] ?? '' ) );
		$now     = \RSAIP_DB::now_gmt_sql();

		$run_id = $this->repository->insert_run(
			array(
				'rb_recipe_id'    => $rb_id,
				'action_type'     => $action,
				'mode'            => $mode,
				'status'          => 'ready',
				'original_json'   => (string) wp_json_encode( $original ),
				'optimized_json'  => (string) wp_json_encode( $optimized ),
				'analysis_json'   => (string) wp_json_encode( $analysis ),
				'diff_json'       => (string) wp_json_encode( $diff ),
				'summary'         => mb_substr( $summary, 0, 2000 ),
				'user_id'         => $user,
				'created_at'      => $now,
				'updated_at'      => $now,
			)
		);
		if ( $run_id <= 0 ) {
			return new \WP_Error( 'rsaip_recipe_ai_save', 'Could not save AI run.' );
		}

		$version_meta = array(
			'action'      => $action,
			'source_type' => (string) ( $original['source_type'] ?? 'builder' ),
			'post_id'     => absint( $original['post_id'] ?? 0 ),
		);
		$this->store_version( $run_id, $rb_id, 'original', $original, $version_meta );
		$this->store_version(
			$run_id,
			$rb_id,
			'optimized',
			$optimized,
			array_merge( $version_meta, array( 'mode' => $mode ) )
		);

		$this->events->dispatch(
			'rsaip.recipe_ai.run',
			array(
				'id'           => $run_id,
				'action'       => $action,
				'rb_recipe_id' => $rb_id,
			)
		);
		$this->logger->info( 'recipe_ai.run', array( 'id' => $run_id, 'action' => $action ) );
		$this->cache->delete( 'rsaip_recipe_ai_runs_' . $rb_id );

		return $this->get( $run_id );
	}

	/**
	 * Apply optimized recipe to Recipe Builder or WordPress post (backup first).
	 *
	 * @return RecipeAiDTO|\WP_Error
	 */
	public function apply( int $run_id, int $user_id = 0 ) {
		$this->ensure_tables();
		$dto = $this->get( $run_id );
		if ( is_wp_error( $dto ) ) {
			return $dto;
		}
		if ( empty( $dto->optimized ) ) {
			return new \WP_Error( 'rsaip_recipe_ai_empty', 'No optimized recipe to apply.' );
		}

		$source = $this->detect_source( $dto );
		if ( $source === 'post' ) {
			return $this->apply_to_post( $dto, $user_id );
		}

		if ( ! $this->builder ) {
			return new \WP_Error( 'rsaip_recipe_ai_builder', 'Recipe Builder service is unavailable.' );
		}

		$rb_id = $dto->rb_recipe_id;
		if ( $rb_id > 0 ) {
			$current = $this->builder->get( $rb_id );
			if ( ! is_wp_error( $current ) ) {
				$this->store_version( $run_id, $rb_id, 'backup', $current->to_array(), array( 'reason' => 'pre_apply', 'source_type' => 'builder' ) );
			}
		}

		$payload       = $dto->optimized;
		$payload['id'] = $rb_id > 0 ? $rb_id : absint( $payload['id'] ?? 0 );
		$saved         = $this->builder->save( $payload, $user_id );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$rb_id = $saved->id;
		$this->store_version( $run_id, $rb_id, 'applied', $saved->to_array(), array( 'reason' => 'apply', 'source_type' => 'builder' ) );
		$this->repository->update_run(
			$run_id,
			array(
				'rb_recipe_id' => $rb_id,
				'status'       => 'applied',
				'updated_at'   => \RSAIP_DB::now_gmt_sql(),
			)
		);

		$this->events->dispatch(
			'rsaip.recipe_ai.applied',
			array(
				'id'           => $run_id,
				'rb_recipe_id' => $rb_id,
				'user_id'      => $user_id,
				'source_type'  => 'builder',
			)
		);

		return $this->get( $run_id );
	}

	/**
	 * Undo using latest backup for this run (Builder or WordPress post).
	 *
	 * @return RecipeAiDTO|\WP_Error
	 */
	public function undo( int $run_id, int $user_id = 0 ) {
		$dto = $this->get( $run_id );
		if ( is_wp_error( $dto ) ) {
			return $dto;
		}

		$source = $this->detect_source( $dto );
		if ( $source === 'post' ) {
			return $this->undo_post( $dto, $user_id );
		}

		if ( $dto->rb_recipe_id <= 0 ) {
			return new \WP_Error( 'rsaip_recipe_ai_recipe', 'No Recipe Builder recipe linked to this run.' );
		}
		$backup = $this->repository->find_latest_labeled( $dto->rb_recipe_id, 'backup', $run_id );
		if ( ! $backup ) {
			$backup = $this->repository->find_latest_labeled( $dto->rb_recipe_id, 'backup', 0 );
		}
		if ( ! $backup ) {
			return new \WP_Error( 'rsaip_recipe_ai_backup', 'No backup found to undo.' );
		}
		return $this->restore_version( (int) $backup['id'], $user_id, $run_id );
	}

	/**
	 * Restore a stored version onto Recipe Builder or WordPress post (creates a new backup first).
	 *
	 * @return RecipeAiDTO|\WP_Error
	 */
	public function restore_version( int $version_id, int $user_id = 0, int $run_id = 0 ) {
		$this->ensure_tables();
		$version = $this->repository->find_version( $version_id );
		if ( ! $version ) {
			return new \WP_Error( 'rsaip_recipe_ai_version', 'Version not found.', array( 'status' => 404 ) );
		}

		$meta = json_decode( (string) ( $version['meta_json'] ?? '{}' ), true );
		$meta = is_array( $meta ) ? $meta : array();
		if ( ( $meta['source_type'] ?? '' ) === 'post' || ! empty( $meta['post_content'] ) ) {
			return $this->restore_post_version( $version, $meta, $user_id, $run_id );
		}

		if ( ! $this->builder ) {
			return new \WP_Error( 'rsaip_recipe_ai_builder', 'Recipe Builder service is unavailable.' );
		}

		$recipe = json_decode( (string) ( $version['recipe_json'] ?? '' ), true );
		if ( ! is_array( $recipe ) ) {
			return new \WP_Error( 'rsaip_recipe_ai_version', 'Version payload is invalid.' );
		}

		$rb_id = absint( $version['rb_recipe_id'] ?? 0 );
		if ( $rb_id > 0 ) {
			$current = $this->builder->get( $rb_id );
			if ( ! is_wp_error( $current ) ) {
				$this->store_version(
					$run_id > 0 ? $run_id : absint( $version['run_id'] ?? 0 ),
					$rb_id,
					'backup',
					$current->to_array(),
					array( 'reason' => 'pre_restore', 'source_type' => 'builder' )
				);
			}
		}

		$recipe['id'] = $rb_id;
		$saved        = $this->builder->save( $recipe, $user_id );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$use_run = $run_id > 0 ? $run_id : absint( $version['run_id'] ?? 0 );
		$this->store_version( $use_run, $saved->id, 'restored', $saved->to_array(), array( 'from_version' => $version_id, 'source_type' => 'builder' ) );

		if ( $use_run > 0 ) {
			$this->repository->update_run(
				$use_run,
				array(
					'status'     => 'restored',
					'updated_at' => \RSAIP_DB::now_gmt_sql(),
				)
			);
			$this->events->dispatch(
				'rsaip.recipe_ai.restored',
				array(
					'id'           => $use_run,
					'version_id'   => $version_id,
					'rb_recipe_id' => $saved->id,
					'source_type'  => 'builder',
				)
			);
			return $this->get( $use_run );
		}

		return new \WP_Error( 'rsaip_recipe_ai_run', 'Restored, but no run id to return.' );
	}

	/**
	 * @return RecipeAiDTO|\WP_Error
	 */
	private function apply_to_post( RecipeAiDTO $dto, int $user_id ) {
		$payload = $this->normalize_recipe( $dto->optimized );
		$payload['source_type'] = 'post';
		$post_id = absint( $payload['post_id'] ?? ( $dto->original['post_id'] ?? 0 ) );
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'rsaip_recipe_ai_post', 'No WordPress post linked to this run.' );
		}
		$payload['post_id'] = $post_id;

		// Backup structured + raw post before overwrite.
		$current = $this->posts->load( $post_id );
		$post    = get_post( $post_id );
		$backup_recipe = is_array( $current ) ? $current : array( 'post_id' => $post_id, 'source_type' => 'post' );
		$this->store_version(
			$dto->id,
			0,
			'backup',
			$backup_recipe,
			array(
				'reason'       => 'pre_apply',
				'source_type'  => 'post',
				'post_id'      => $post_id,
				'post_content' => $post instanceof \WP_Post ? (string) $post->post_content : '',
				'post_title'   => $post instanceof \WP_Post ? (string) $post->post_title : '',
			)
		);

		$applied = $this->posts->apply( $payload, $user_id );
		if ( is_wp_error( $applied ) ) {
			return $applied;
		}

		$this->store_version(
			$dto->id,
			0,
			'applied',
			$payload,
			array(
				'reason'      => 'apply',
				'source_type' => 'post',
				'post_id'     => $post_id,
			)
		);
		$this->repository->update_run(
			$dto->id,
			array(
				'status'     => 'applied',
				'updated_at' => \RSAIP_DB::now_gmt_sql(),
			)
		);

		$this->events->dispatch(
			'rsaip.recipe_ai.applied',
			array(
				'id'          => $dto->id,
				'post_id'     => $post_id,
				'user_id'     => $user_id,
				'source_type' => 'post',
			)
		);

		return $this->get( $dto->id );
	}

	/**
	 * @return RecipeAiDTO|\WP_Error
	 */
	private function undo_post( RecipeAiDTO $dto, int $user_id ) {
		$versions = $this->repository->list_versions_for_run( $dto->id );
		$backup   = null;
		foreach ( $versions as $row ) {
			if ( ( $row['label'] ?? '' ) === 'backup' ) {
				$backup = $row;
				break;
			}
		}
		if ( ! $backup ) {
			return new \WP_Error( 'rsaip_recipe_ai_backup', 'No post backup found to undo.' );
		}
		return $this->restore_version( (int) $backup['id'], $user_id, $dto->id );
	}

	/**
	 * @param array<string, mixed> $version Version row.
	 * @param array<string, mixed> $meta    Decoded meta.
	 * @return RecipeAiDTO|\WP_Error
	 */
	private function restore_post_version( array $version, array $meta, int $user_id, int $run_id ) {
		$post_id = absint( $meta['post_id'] ?? 0 );
		$recipe  = json_decode( (string) ( $version['recipe_json'] ?? '' ), true );
		if ( $post_id <= 0 && is_array( $recipe ) ) {
			$post_id = absint( $recipe['post_id'] ?? 0 );
		}
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'rsaip_recipe_ai_post', 'Backup is missing post_id.' );
		}

		$use_run = $run_id > 0 ? $run_id : absint( $version['run_id'] ?? 0 );
		$post    = get_post( $post_id );
		if ( $post instanceof \WP_Post ) {
			$this->store_version(
				$use_run,
				0,
				'backup',
				is_array( $recipe ) ? $recipe : array( 'post_id' => $post_id, 'source_type' => 'post' ),
				array(
					'reason'       => 'pre_restore',
					'source_type'  => 'post',
					'post_id'      => $post_id,
					'post_content' => (string) $post->post_content,
					'post_title'   => (string) $post->post_title,
				)
			);
		}

		if ( ! empty( $meta['post_content'] ) ) {
			$restored = $this->posts->restore_post(
				$post_id,
				(string) $meta['post_content'],
				(string) ( $meta['post_title'] ?? '' )
			);
			if ( is_wp_error( $restored ) ) {
				return $restored;
			}
			if ( is_array( $recipe ) ) {
				update_post_meta( $post_id, PostRecipeSource::META_STRUCTURED, wp_json_encode( $recipe ) );
			}
		} elseif ( is_array( $recipe ) ) {
			$recipe['post_id']     = $post_id;
			$recipe['source_type'] = 'post';
			$applied = $this->posts->apply( $recipe, $user_id );
			if ( is_wp_error( $applied ) ) {
				return $applied;
			}
		} else {
			return new \WP_Error( 'rsaip_recipe_ai_version', 'Version payload is invalid.' );
		}

		if ( $use_run > 0 ) {
			$this->repository->update_run(
				$use_run,
				array(
					'status'     => 'restored',
					'updated_at' => \RSAIP_DB::now_gmt_sql(),
				)
			);
			$this->events->dispatch(
				'rsaip.recipe_ai.restored',
				array(
					'id'          => $use_run,
					'version_id'  => (int) ( $version['id'] ?? 0 ),
					'post_id'     => $post_id,
					'source_type' => 'post',
				)
			);
			return $this->get( $use_run );
		}

		return new \WP_Error( 'rsaip_recipe_ai_run', 'Restored, but no run id to return.' );
	}

	private function detect_source( RecipeAiDTO $dto ): string {
		$optimized = $dto->optimized;
		$original  = $dto->original;
		if ( ( $optimized['source_type'] ?? '' ) === 'post' || ( $original['source_type'] ?? '' ) === 'post' ) {
			return 'post';
		}
		if ( absint( $optimized['post_id'] ?? 0 ) > 0 && $dto->rb_recipe_id <= 0 ) {
			return 'post';
		}
		if ( absint( $original['post_id'] ?? 0 ) > 0 && $dto->rb_recipe_id <= 0 ) {
			return 'post';
		}
		return 'builder';
	}

	/**
	 * @return array{runs: list<array<string,mixed>>, versions: list<array<string,mixed>>}
	 */
	public function history( int $rb_recipe_id, int $run_id = 0 ): array {
		$this->ensure_tables();
		$versions = array();
		if ( $run_id > 0 ) {
			$versions = $this->repository->list_versions_for_run( $run_id );
		} elseif ( $rb_recipe_id > 0 ) {
			$versions = $this->repository->list_versions_for_recipe( $rb_recipe_id );
		}
		return array(
			'runs'     => $this->repository->list_runs( $rb_recipe_id, 40 ),
			'versions' => $versions,
		);
	}

	/**
	 * Compare payload for UI.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function compare( int $run_id ) {
		$dto = $this->get( $run_id );
		if ( is_wp_error( $dto ) ) {
			return $dto;
		}
		return array(
			'original'  => $dto->original,
			'optimized' => $dto->optimized,
			'diff'      => $dto->diff,
			'analysis'  => $dto->analysis,
			'summary'   => $dto->summary,
			'action'    => $dto->action_type,
			'mode'      => $dto->mode,
		);
	}

	/**
	 * @param array<string, mixed> $recipe Recipe.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function dispatch_action( string $action, array $recipe, string $mode, string $extra ) {
		switch ( $action ) {
			case 'ingredients':
				return $this->ingredients->run( $recipe, $mode !== '' ? $mode : 'all', $extra );
			case 'instructions':
				return $this->instructions->run( $recipe, $mode !== '' ? $mode : 'all', $extra );
			case 'rewrite':
				return $this->rewrite->run( $recipe, $mode !== '' ? $mode : 'beginner_friendly' );
			case 'variation':
				return $this->variations->run( $recipe, $mode !== '' ? $mode : 'gluten_free' );
			case 'improve':
				return $this->run_prompt_action( 'improve', '', $recipe );
			case 'analyze':
				return $this->run_prompt_action( 'analyze', '', $recipe );
			case 'nutrition':
				return $this->nutrition->run( $recipe );
			default:
				return new \WP_Error( 'rsaip_recipe_ai_action', 'Unknown action type.' );
		}
	}

	/**
	 * @param array<string, mixed> $recipe Recipe.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function run_prompt_action( string $action, string $mode, array $recipe ) {
		$all = $this->settings->all();
		if ( ( $all['ai_provider'] ?? '' ) !== 'openai_compatible' ) {
			return new \WP_Error( 'rsaip_recipe_ai_provider', 'Configure an OpenAI-compatible AI provider in Settings.' );
		}
		$prompt   = $this->prompts->build( $action, $mode, $recipe );
		$provider = $this->providers->get( 'openai_compatible' );
		$result   = $provider->complete( $prompt, array( 'max_tokens' => 1200 ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$data = $this->decoder->decode( (string) $result );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'rsaip_recipe_ai_parse', 'Could not parse AI response.' );
		}
		return $data;
	}

	/**
	 * @param array<string, mixed> $recipe Recipe.
	 * @param array<string, mixed> $meta   Meta.
	 */
	private function store_version( int $run_id, int $rb_recipe_id, string $label, array $recipe, array $meta = array() ): int {
		if ( $rb_recipe_id <= 0 && empty( $recipe ) ) {
			return 0;
		}
		return $this->repository->insert_version(
			array(
				'run_id'       => max( 0, $run_id ),
				'rb_recipe_id' => max( 0, $rb_recipe_id ),
				'label'        => sanitize_key( $label ),
				'version_no'   => $this->repository->next_version_no( max( 0, $rb_recipe_id ) ),
				'recipe_json'  => (string) wp_json_encode( $recipe ),
				'meta_json'    => (string) wp_json_encode( $meta ),
				'created_at'   => \RSAIP_DB::now_gmt_sql(),
			)
		);
	}

	/**
	 * @param array<string, mixed> $original Original.
	 * @param array<string, mixed> $optimized Optimized.
	 * @return array<string, mixed>
	 */
	private function build_diff( array $original, array $optimized ): array {
		$fields = array( 'title', 'description', 'servings', 'prep_time', 'cook_time', 'total_time', 'notes', 'tips', 'unit_system' );
		$changed = array();
		foreach ( $fields as $field ) {
			$a = $original[ $field ] ?? null;
			$b = $optimized[ $field ] ?? null;
			if ( $a != $b ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
				$changed[ $field ] = array(
					'from' => $a,
					'to'   => $b,
				);
			}
		}
		$oi = count( is_array( $original['ingredients'] ?? null ) ? $original['ingredients'] : array() );
		$ni = count( is_array( $optimized['ingredients'] ?? null ) ? $optimized['ingredients'] : array() );
		$os = count( is_array( $original['steps'] ?? null ) ? $original['steps'] : array() );
		$ns = count( is_array( $optimized['steps'] ?? null ) ? $optimized['steps'] : array() );
		return array(
			'fields'              => $changed,
			'ingredient_count'    => array( 'from' => $oi, 'to' => $ni ),
			'step_count'          => array( 'from' => $os, 'to' => $ns ),
			'ingredients_changed' => (string) wp_json_encode( $original['ingredients'] ?? array() ) !== (string) wp_json_encode( $optimized['ingredients'] ?? array() ),
			'steps_changed'       => (string) wp_json_encode( $original['steps'] ?? array() ) !== (string) wp_json_encode( $optimized['steps'] ?? array() ),
		);
	}

	/**
	 * @param array<string, mixed> $base Base.
	 * @param array<string, mixed> $patch Patch.
	 * @return array<string, mixed>
	 */
	private function merge_recipe( array $base, array $patch ): array {
		$out = $this->normalize_recipe( $base );
		foreach ( array( 'title', 'description', 'notes', 'tips', 'unit_system', 'status', 'source_type', 'parse_mode' ) as $key ) {
			if ( isset( $patch[ $key ] ) && is_scalar( $patch[ $key ] ) ) {
				$out[ $key ] = (string) $patch[ $key ];
			}
		}
		foreach ( array( 'servings' ) as $key ) {
			if ( isset( $patch[ $key ] ) && is_numeric( $patch[ $key ] ) ) {
				$out[ $key ] = (float) $patch[ $key ];
			}
		}
		foreach ( array( 'prep_time', 'cook_time', 'total_time', 'post_id', 'id' ) as $key ) {
			if ( isset( $patch[ $key ] ) && is_numeric( $patch[ $key ] ) ) {
				$out[ $key ] = (int) $patch[ $key ];
			}
		}
		foreach ( array( 'ingredients', 'steps', 'sections', 'equipment' ) as $key ) {
			if ( isset( $patch[ $key ] ) && is_array( $patch[ $key ] ) ) {
				$out[ $key ] = $patch[ $key ];
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $recipe Recipe.
	 * @return array<string, mixed>
	 */
	private function normalize_recipe( array $recipe ): array {
		$source = sanitize_key( (string) ( $recipe['source_type'] ?? 'builder' ) );
		if ( $source === '' ) {
			$source = 'builder';
		}
		return array(
			'id'              => absint( $recipe['id'] ?? 0 ),
			'post_id'         => absint( $recipe['post_id'] ?? 0 ),
			'source_type'     => $source,
			'parse_mode'      => sanitize_key( (string) ( $recipe['parse_mode'] ?? '' ) ),
			'has_recipe_card' => ! empty( $recipe['has_recipe_card'] ),
			'title'           => (string) ( $recipe['title'] ?? '' ),
			'description'     => (string) ( $recipe['description'] ?? '' ),
			'servings'        => (float) ( $recipe['servings'] ?? 4 ),
			'prep_time'       => absint( $recipe['prep_time'] ?? 0 ),
			'cook_time'       => absint( $recipe['cook_time'] ?? 0 ),
			'total_time'      => absint( $recipe['total_time'] ?? 0 ),
			'notes'           => (string) ( $recipe['notes'] ?? '' ),
			'tips'            => (string) ( $recipe['tips'] ?? '' ),
			'equipment'       => isset( $recipe['equipment'] ) && is_array( $recipe['equipment'] ) ? $recipe['equipment'] : array(),
			'unit_system'     => (string) ( $recipe['unit_system'] ?? 'metric' ),
			'status'          => (string) ( $recipe['status'] ?? 'draft' ),
			'sections'        => isset( $recipe['sections'] ) && is_array( $recipe['sections'] ) ? $recipe['sections'] : array(),
			'ingredients'     => isset( $recipe['ingredients'] ) && is_array( $recipe['ingredients'] ) ? $recipe['ingredients'] : array(),
			'steps'           => isset( $recipe['steps'] ) && is_array( $recipe['steps'] ) ? $recipe['steps'] : array(),
		);
	}

	/**
	 * @param array<string, mixed> $row DB row.
	 */
	private function hydrate( array $row ): RecipeAiDTO {
		$dto               = new RecipeAiDTO();
		$dto->id           = (int) ( $row['id'] ?? 0 );
		$dto->rb_recipe_id = (int) ( $row['rb_recipe_id'] ?? 0 );
		$dto->action_type  = (string) ( $row['action_type'] ?? '' );
		$dto->mode         = (string) ( $row['mode'] ?? '' );
		$dto->status       = (string) ( $row['status'] ?? 'draft' );
		$dto->summary      = (string) ( $row['summary'] ?? '' );
		$dto->user_id      = (int) ( $row['user_id'] ?? 0 );
		$dto->created_at   = (string) ( $row['created_at'] ?? '' );
		$dto->updated_at   = (string) ( $row['updated_at'] ?? '' );

		$orig = json_decode( (string) ( $row['original_json'] ?? '{}' ), true );
		$opt  = json_decode( (string) ( $row['optimized_json'] ?? '{}' ), true );
		$ana  = json_decode( (string) ( $row['analysis_json'] ?? '{}' ), true );
		$diff = json_decode( (string) ( $row['diff_json'] ?? '{}' ), true );

		$dto->original  = is_array( $orig ) ? $orig : array();
		$dto->optimized = is_array( $opt ) ? $opt : array();
		$dto->analysis  = is_array( $ana ) ? $ana : array();
		$dto->diff      = is_array( $diff ) ? $diff : array();
		return $dto;
	}
}
