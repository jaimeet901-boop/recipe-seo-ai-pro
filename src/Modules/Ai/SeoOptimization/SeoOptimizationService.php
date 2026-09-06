<?php
declare(strict_types=1);

/**
 * OpenAI-powered SEO optimization (Milestone 5B/5C).
 *
 * Analyze / Generate → context → Hub complete → decode → validate → proposal.
 * Generate Titles / Keywords / Meta consume the same proposal (request-scoped reuse).
 * Never writes WordPress data, never calls PostMutationService, never issues tickets.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\SeoOptimization;

use RecipeSeoAiPro\Modules\Ai\Hub\ProviderCatalog;
use RecipeSeoAiPro\Modules\Ai\Hub\ProviderManager;
use RecipeSeoAiPro\Modules\RecipeAI\AiJsonDecoder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SeoOptimizationService
 */
final class SeoOptimizationService {

	private SeoOptimizationContextBuilder $context_builder;
	private SeoOptimizationProposalValidator $validator;
	private AiJsonDecoder $decoder;
	private SeoOptimizationPromptBuilder $prompt_builder;

	/**
	 * Optional test double: function(string $prompt, array $options): string|\WP_Error|object
	 *
	 * @var callable|null
	 */
	private $completer;

	public function __construct(
		?SeoOptimizationContextBuilder $context_builder = null,
		?SeoOptimizationProposalValidator $validator = null,
		?AiJsonDecoder $decoder = null,
		?SeoOptimizationPromptBuilder $prompt_builder = null,
		$completer = null
	) {
		$this->context_builder = $context_builder instanceof SeoOptimizationContextBuilder
			? $context_builder
			: new SeoOptimizationContextBuilder();
		$this->validator = $validator instanceof SeoOptimizationProposalValidator
			? $validator
			: new SeoOptimizationProposalValidator();
		$this->decoder = $decoder instanceof AiJsonDecoder ? $decoder : new AiJsonDecoder();
		$this->prompt_builder = $prompt_builder instanceof SeoOptimizationPromptBuilder
			? $prompt_builder
			: new SeoOptimizationPromptBuilder();
		$this->completer = is_callable( $completer ) ? $completer : null;
	}

	public function context_builder(): SeoOptimizationContextBuilder {
		return $this->context_builder;
	}

	public function validator(): SeoOptimizationProposalValidator {
		return $this->validator;
	}

	public function prompt_builder(): SeoOptimizationPromptBuilder {
		return $this->prompt_builder;
	}

	/**
	 * Decode raw AI text and validate into a trusted proposal.
	 */
	public function proposal_from_raw_response( string $raw, SeoOptimizationContext $context ): SeoOptimizationValidationResult {
		$decoded = $this->decoder->decode( $raw );
		if ( ! is_array( $decoded ) ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_invalid_json', 'AI response is not valid JSON.' );
		}
		return $this->validator->validate( $decoded, $context );
	}

	/**
	 * @param array<string, mixed> $data Already-decoded object.
	 */
	public function proposal_from_decoded( array $data, SeoOptimizationContext $context ): SeoOptimizationValidationResult {
		return $this->validator->validate( $data, $context );
	}

	/**
	 * Analyze using server-owned context fields (no post load).
	 *
	 * @param array<string, mixed> $server_context Server context for ContextBuilder::from_server_array().
	 */
	public function analyze( array $server_context ): SeoOptimizationValidationResult {
		$context = $this->context_builder->from_server_array( $server_context );
		return $this->analyze_context( $context );
	}

	/**
	 * Analyze a WordPress post by server-authorized ID (read-only).
	 * On success, stores the proposal for Generate Titles/Keywords/Meta reuse (5C).
	 */
	public function analyze_post( int $post_id ): SeoOptimizationValidationResult {
		$context = $this->context_builder->from_post_id( $post_id );
		if ( ! $context instanceof SeoOptimizationContext ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_post_not_found', 'Post not found.' );
		}
		$result = $this->analyze_context( $context );
		if ( $result->ok() && $result->proposal() instanceof SeoOptimizationProposal ) {
			$fp = SeoOptimizationProposalStore::fingerprint_for_post( $post_id );
			SeoOptimizationProposalStore::put( $post_id, $fp, $result->proposal(), $context );
			return $result->with_context( $context );
		}
		return $result;
	}

	/**
	 * Return a fresh compatible cached proposal, or create one via analyze_post.
	 * Preferred single OpenAI call per Analyze/Generate cycle (5C).
	 */
	public function get_or_create_proposal_for_post( int $post_id ): SeoOptimizationValidationResult {
		$post_id = max( 0, $post_id );
		if ( $post_id <= 0 ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_invalid_post', 'Invalid post ID.' );
		}

		$fp     = SeoOptimizationProposalStore::fingerprint_for_post( $post_id );
		$cached = SeoOptimizationProposalStore::get( $post_id, $fp );
		if ( is_array( $cached ) ) {
			return SeoOptimizationValidationResult::success( $cached['proposal'], $cached['context'] );
		}

		return $this->analyze_post( $post_id );
	}

	/**
	 * Titles payload for Generate Titles (candidates only; no writes).
	 *
	 * @return array<string, mixed>
	 */
	public function titles_from_proposal( SeoOptimizationProposal $proposal, int $post_id ): array {
		$recommended = trim( (string) $proposal->recommended_title() );
		$alts        = array_values(
			array_filter(
				array_map(
					static function ( $t ) {
						return trim( (string) $t );
					},
					$proposal->seo_title_suggestions()
				)
			)
		);

		$titles = array();
		if ( $recommended !== '' ) {
			$titles[] = $recommended;
		}
		foreach ( $alts as $t ) {
			if ( $t === '' ) {
				continue;
			}
			if ( $recommended !== '' && strcasecmp( $t, $recommended ) === 0 ) {
				continue;
			}
			$titles[] = $t;
		}
		$titles = array_values( array_unique( $titles ) );

		return array(
			'ok'                    => true,
			'source'                => 'seo_optimization',
			'post_id'               => $post_id,
			'topic'                 => (string) $proposal->topic(),
			'recipe_name'           => (string) $proposal->recipe_name(),
			'recommended_title'     => $recommended,
			'titles'                => $titles,
			'seo_title_suggestions' => $alts,
		);
	}

	/**
	 * Keywords payload — primary vs secondary separated; entities display-only.
	 * Default Apply candidate is primary only until the user selects secondaries.
	 *
	 * @return array<string, mixed>
	 */
	public function keywords_from_proposal( SeoOptimizationProposal $proposal, int $post_id ): array {
		$primary     = trim( (string) $proposal->primary_focus_keyword() );
		$secondaries = array_values(
			array_filter(
				array_map(
					static function ( $k ) {
						return trim( (string) $k );
					},
					$proposal->secondary_keywords()
				)
			)
		);
		$entities = array_values(
			array_filter(
				array_map(
					static function ( $e ) {
						return trim( (string) $e );
					},
					$proposal->entities()
				)
			)
		);
		$apply_list = $primary !== '' ? array( $primary ) : array();

		return array(
			'ok'                    => true,
			'source'                => 'seo_optimization',
			'post_id'               => $post_id,
			'topic'                 => (string) $proposal->topic(),
			'recipe_name'           => (string) $proposal->recipe_name(),
			'primary_focus_keyword' => $primary,
			'secondary_keywords'    => $secondaries,
			'entities'              => $entities,
			'keywords'              => $apply_list,
			'apply_keywords'        => $apply_list,
		);
	}

	/**
	 * Meta description payload from unified proposal.
	 *
	 * @return array<string, mixed>
	 */
	public function meta_from_proposal( SeoOptimizationProposal $proposal, int $post_id ): array {
		$meta = trim( (string) $proposal->meta_description() );
		return array(
			'ok'               => true,
			'source'           => 'seo_optimization',
			'post_id'          => $post_id,
			'topic'            => (string) $proposal->topic(),
			'recipe_name'      => (string) $proposal->recipe_name(),
			'meta_description' => $meta,
			'metadesc'         => $meta,
			'description'      => $meta,
		);
	}

	/**
	 * Build KEYWORDS mutation candidate from user selection.
	 * Primary first (Rank Math CSV semantics); entities never included.
	 *
	 * @param list<string> $selected_secondaries User-selected secondaries only.
	 * @return list<string>
	 */
	public static function build_keywords_apply_list( string $primary, array $selected_secondaries ): array {
		$primary = trim( $primary );
		$out     = array();
		$seen    = array();
		if ( $primary !== '' ) {
			$out[]                   = $primary;
			$seen[ strtolower( $primary ) ] = true;
		}
		foreach ( $selected_secondaries as $kw ) {
			$kw = trim( (string) $kw );
			if ( $kw === '' ) {
				continue;
			}
			$key = strtolower( $kw );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $kw;
		}
		return array_values( $out );
	}

	/**
	 * Core analysis: one comprehensive AI request → validate → proposal.
	 * Hard-fails on AI/JSON/schema errors. No heuristic fallback.
	 */
	public function analyze_context( SeoOptimizationContext $context ): SeoOptimizationValidationResult {
		$gate = $this->assert_ai_enabled();
		if ( $gate instanceof SeoOptimizationValidationResult ) {
			return $gate;
		}

		$prompt  = $this->prompt_builder->build( $context );
		$options = $this->build_complete_options( $context );

		$raw = $this->complete( $prompt, $options );
		if ( $raw instanceof SeoOptimizationValidationResult ) {
			return $raw;
		}

		return $this->proposal_from_raw_response( $raw, $context );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_complete_options( SeoOptimizationContext $context ): array {
		$options = array(
			'max_tokens'  => 2500,
			'temperature' => 0.2,
			'system'      => $this->prompt_builder->system_prompt(),
		);

		$provider_id = $this->active_hub_provider_id();
		if ( $provider_id !== '' && ProviderCatalog::supports_json_response_format( $provider_id ) ) {
			$options['response_format'] = 'json_object';
		}

		// Article content already embedded in prompt CONTEXT_JSON; avoid duplicating.
		unset( $context );

		return $options;
	}

	/**
	 * @param array<string, mixed> $options Complete options.
	 * @return string|SeoOptimizationValidationResult
	 */
	private function complete( string $prompt, array $options ) {
		if ( is_callable( $this->completer ) ) {
			$result = call_user_func( $this->completer, $prompt, $options );
			return $this->normalize_complete_result( $result );
		}

		// Prefer active Hub provider directly — no failover to another intelligence source.
		if ( class_exists( ProviderManager::class ) && function_exists( 'get_option' ) ) {
			$manager  = ProviderManager::instance();
			$provider = $manager->resolve_active();
			if ( $provider ) {
				$result = $provider->complete( $prompt, $options );
				return $this->normalize_complete_result( $result );
			}
		}

		if ( function_exists( 'rsaip_ai_provider_registry' ) ) {
			$registry = rsaip_ai_provider_registry();
			$provider = $registry->get( 'openai_compatible' );
			$result   = $provider->complete( $prompt, $options );
			return $this->normalize_complete_result( $result );
		}

		return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_ai_unavailable', 'AI provider is unavailable.' );
	}

	/**
	 * @param mixed $result Provider result.
	 * @return string|SeoOptimizationValidationResult
	 */
	private function normalize_complete_result( $result ) {
		if ( is_string( $result ) ) {
			$trim = trim( $result );
			if ( $trim === '' ) {
				return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_ai_empty', 'AI returned an empty response.' );
			}
			return $trim;
		}

		if ( is_object( $result ) && method_exists( $result, 'get_error_message' ) ) {
			$code = method_exists( $result, 'get_error_code' ) ? (string) $result->get_error_code() : 'rsaip_seo_opt_ai_failed';
			$msg  = (string) $result->get_error_message();
			if ( $code === '' ) {
				$code = 'rsaip_seo_opt_ai_failed';
			}
			return SeoOptimizationValidationResult::failure( $code, $msg !== '' ? $msg : 'AI request failed.' );
		}

		return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_ai_failed', 'AI request failed.' );
	}

	private function assert_ai_enabled(): ?SeoOptimizationValidationResult {
		if ( is_callable( $this->completer ) ) {
			return null;
		}

		$settings = function_exists( 'rsaip_get_settings' ) ? rsaip_get_settings() : array();
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$provider = (string) ( $settings['ai_provider'] ?? '' );
		$key      = trim( (string) ( $settings['ai_api_key'] ?? '' ) );

		if ( $provider !== 'openai_compatible' ) {
			return SeoOptimizationValidationResult::failure(
				'rsaip_seo_opt_ai_disabled',
				'Enable an OpenAI-compatible AI provider in Settings to analyze posts.'
			);
		}
		if ( $key === '' ) {
			return SeoOptimizationValidationResult::failure(
				'rsaip_seo_opt_ai_disabled',
				'Configure an AI API key in Settings to analyze posts.'
			);
		}

		return null;
	}

	private function active_hub_provider_id(): string {
		if ( is_callable( $this->completer ) ) {
			return 'openai';
		}
		if ( ! class_exists( ProviderManager::class ) || ! function_exists( 'get_option' ) ) {
			return 'openai';
		}
		try {
			$manager = ProviderManager::instance();
			$id      = (string) $manager->repository()->get_active_id();
			return $id !== '' ? $id : 'openai';
		} catch ( \Throwable $e ) {
			unset( $e );
			return 'openai';
		}
	}

	/**
	 * UI/AJAX envelope for Analyze Post (read-only). Never includes Apply tickets.
	 *
	 * @return array<string, mixed>
	 */
	public function to_analyze_response( SeoOptimizationValidationResult $result, int $post_id, ?SeoOptimizationContext $context = null ): array {
		$context_status = $this->schema_status_for_response( $context );

		if ( ! $result->ok() || ! $result->proposal() instanceof SeoOptimizationProposal ) {
			return array(
				'ok'            => false,
				'post_id'       => $post_id,
				'code'          => $result->code(),
				'message'       => $result->message(),
				'source'        => 'seo_optimization',
				'recipe_schema' => array(
					'status'         => $context_status['status'],
					'source'         => $context_status['source'],
					'authoritative'  => $context_status['authoritative'],
					'details'        => $context_status['details'],
					'repair_allowed' => false,
					'label'          => $context_status['label'],
				),
			);
		}

		$proposal = $result->proposal();
		$data     = $proposal->to_array();

		return array(
			'ok'                        => true,
			'post_id'                   => $post_id,
			'source'                    => 'seo_optimization',
			'apply_allowed'             => false,
			'topic'                     => $data['topic'],
			'recipe_name'               => $data['recipe_name'],
			'canonical_recipe_entity'   => $context instanceof SeoOptimizationContext
				? $context->canonical_recipe_entity()
				: (string) ( $data['recipe_name'] ?? $data['topic'] ?? '' ),
			'search_intent'             => $data['search_intent'],
			'primary_focus_keyword'     => $data['primary_focus_keyword'],
			'secondary_keywords'        => $data['secondary_keywords'],
			'entities'                  => $data['entities'],
			'recipe_ingredients'        => $context instanceof SeoOptimizationContext ? $context->recipe_ingredients() : array(),
			'known_recipe_facts'        => $context instanceof SeoOptimizationContext ? $context->known_recipe_facts() : array(),
			'recommended_title'         => $data['recommended_title'],
			'seo_title_suggestions'     => $data['seo_title_suggestions'],
			'meta_description'          => $data['meta_description'],
			'seo_issues'                => $data['seo_issues'],
			'recommendations'           => $data['recommendations'],
			'content_gaps'              => $data['content_gaps'],
			'heading_suggestions'       => $data['heading_suggestions'],
			'faq_suggestions'           => $data['faq_suggestions'],
			'image_alt_suggestions'     => $data['image_alt_suggestions'],
			'internal_link_suggestions' => $data['internal_link_suggestions'],
			'confidence'                => $data['confidence'],
			'reasoning_summary'         => $data['reasoning_summary'],
			'recipe_schema'             => array(
				'status'         => $context_status['status'],
				'source'         => $context_status['source'],
				'authoritative'  => $context_status['authoritative'],
				'details'        => $context_status['details'],
				'repair_allowed' => false,
				'label'          => $context_status['label'],
			),
			'proposal_ticket'           => '',
		);
	}

	/**
	 * @return array{status: string, source: string, authoritative: bool, details: string, label: string}
	 */
	private function schema_status_for_response( ?SeoOptimizationContext $context ): array {
		if ( ! $context instanceof SeoOptimizationContext ) {
			return array(
				'status'        => 'external_or_unknown',
				'source'        => 'unknown',
				'authoritative' => false,
				'details'       => '',
				'label'         => 'Unknown',
			);
		}
		$status = $context->recipe_schema_status();
		$labels = array(
			'valid_recipe'        => 'Present',
			'missing'             => 'Missing',
			'incomplete'          => 'Incomplete',
			'external_or_unknown' => 'External / unknown (do not create another)',
			'multiple'            => 'Multiple detected (do not create another)',
		);
		return array(
			'status'        => $status,
			'source'        => $context->recipe_schema_source(),
			'authoritative' => $context->recipe_schema_authoritative(),
			'details'       => $context->recipe_schema_details(),
			'label'         => $labels[ $status ] ?? $status,
		);
	}
}
