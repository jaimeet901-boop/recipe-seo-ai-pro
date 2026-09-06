<?php
declare(strict_types=1);

/**
 * Article generation service (Article Generator A–E).
 *
 * Brief → context → Hub/provider complete → decode → validate → proposal
 * → short-lived preview store (D) → explicit Create Draft via DraftCreator (E).
 * This class does not call wp_insert_post / wp_update_post itself.
 * Legacy RSAIP_AI article generator remains unchanged.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\ArticleGeneration;

use RecipeSeoAiPro\Modules\Ai\Hub\ProviderCatalog;
use RecipeSeoAiPro\Modules\Ai\Hub\ProviderManager;
use RecipeSeoAiPro\Modules\RecipeAI\AiJsonDecoder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ArticleGenerationService
 */
final class ArticleGenerationService {

	private ArticleGenerationContextBuilder $context_builder;
	private ArticleGenerationProposalValidator $validator;
	private AiJsonDecoder $decoder;
	private ArticleGenerationPromptBuilder $prompt_builder;

	/**
	 * Optional test double: function(string $prompt, array $options): string|\WP_Error|object
	 *
	 * @var callable|null
	 */
	private $completer;

	public function __construct(
		?ArticleGenerationContextBuilder $context_builder = null,
		?ArticleGenerationProposalValidator $validator = null,
		?AiJsonDecoder $decoder = null,
		?ArticleGenerationPromptBuilder $prompt_builder = null,
		$completer = null
	) {
		$this->context_builder = $context_builder instanceof ArticleGenerationContextBuilder
			? $context_builder
			: new ArticleGenerationContextBuilder();
		$this->validator = $validator instanceof ArticleGenerationProposalValidator
			? $validator
			: new ArticleGenerationProposalValidator();
		$this->decoder = $decoder instanceof AiJsonDecoder ? $decoder : new AiJsonDecoder();
		$this->prompt_builder = $prompt_builder instanceof ArticleGenerationPromptBuilder
			? $prompt_builder
			: new ArticleGenerationPromptBuilder();
		$this->completer = is_callable( $completer ) ? $completer : null;
	}

	public function context_builder(): ArticleGenerationContextBuilder {
		return $this->context_builder;
	}

	public function validator(): ArticleGenerationProposalValidator {
		return $this->validator;
	}

	public function prompt_builder(): ArticleGenerationPromptBuilder {
		return $this->prompt_builder;
	}

	/**
	 * @param array<string, mixed> $brief Server brief.
	 */
	public function generate_from_brief( array $brief ): ArticleGenerationValidationResult {
		$context = $this->context_builder->from_brief( $brief );
		return $this->generate_from_context( $context );
	}

	/**
	 * Generate a validated article proposal. Read-only: no WP writes.
	 */
	public function generate_from_context( ArticleGenerationContext $context ): ArticleGenerationValidationResult {
		if ( $context->title() === '' ) {
			return ArticleGenerationValidationResult::failure( 'rsaip_article_missing_title', 'Title is required.' );
		}
		$words = $context->requested_word_count();
		if ( $words < ArticleGenerationContext::WORD_COUNT_MIN || $words > ArticleGenerationContext::WORD_COUNT_MAX ) {
			return ArticleGenerationValidationResult::failure( 'rsaip_article_invalid_word_count', 'Word count out of bounds.' );
		}

		$gate = $this->assert_ai_enabled();
		if ( $gate instanceof ArticleGenerationValidationResult ) {
			return $gate;
		}

		$prompt  = $this->prompt_builder->build( $context );
		$options = $this->build_complete_options( $context );
		$raw     = $this->complete( $prompt, $options );
		if ( $raw instanceof ArticleGenerationValidationResult ) {
			return $raw;
		}

		$result = $this->proposal_from_raw_response( $raw, $context );
		return $result->ok() ? $result->with_context( $context ) : $result;
	}

	public function proposal_from_raw_response( string $raw, ArticleGenerationContext $context ): ArticleGenerationValidationResult {
		$decoded = $this->decoder->decode( $raw );
		if ( ! is_array( $decoded ) ) {
			return ArticleGenerationValidationResult::failure( 'rsaip_article_invalid_json', 'AI response is not valid JSON.' );
		}
		return $this->validator->validate( $decoded, $context );
	}

	/**
	 * @param array<string, mixed> $data Decoded object.
	 */
	public function proposal_from_decoded( array $data, ArticleGenerationContext $context ): ArticleGenerationValidationResult {
		return $this->validator->validate( $data, $context );
	}

	/**
	 * UI/AJAX envelope for future wiring (read-only). No tickets, no draft IDs.
	 *
	 * @return array<string, mixed>
	 */
	public function to_generate_response( ArticleGenerationValidationResult $result ): array {
		return $this->to_preview_response( $result );
	}

	/**
	 * Read-only preview envelope (Milestone D). Never includes mutation authority.
	 *
	 * @param array<string, mixed> $store_meta Optional store metadata (proposal_id, fingerprint, expires_at).
	 * @return array<string, mixed>
	 */
	public function to_preview_response( ArticleGenerationValidationResult $result, array $store_meta = array() ): array {
		if ( ! $result->ok() || ! $result->proposal() instanceof ArticleGenerationProposal ) {
			$fail = array(
				'ok'            => false,
				'code'          => $result->code(),
				'message'       => $result->message(),
				'source'        => 'article_generation_preview',
				'apply_allowed' => false,
				'draft_allowed' => false,
				'create_draft'  => false,
			);
			// Merge safe validator diagnostics (e.g. word-count band details). Never secrets.
			foreach ( $result->meta() as $key => $value ) {
				if ( ! is_string( $key ) || $key === '' ) {
					continue;
				}
				if ( in_array( $key, array( 'ok', 'code', 'message', 'source', 'content_html', 'api_key', 'token' ), true ) ) {
					continue;
				}
				if ( is_int( $value ) || is_float( $value ) || is_bool( $value ) || is_string( $value ) ) {
					$fail[ $key ] = $value;
				}
			}
			return $fail;
		}
		$proposal = $result->proposal();
		$data     = $proposal->to_array();
		$context  = $result->context();

		$gaps = $context instanceof ArticleGenerationContext ? $context->content_gap_hints() : array();

		$out = array(
			'ok'                    => true,
			'source'                => 'article_generation_preview',
			'apply_allowed'         => false,
			'draft_allowed'         => false,
			'create_draft'          => false,
			'proposal_ticket'       => '',
			'validation_status'     => 'valid',
			'proposal_id'           => (string) ( $store_meta['proposal_id'] ?? '' ),
			'fingerprint'           => (string) ( $store_meta['fingerprint'] ?? '' ),
			'expires_at'            => isset( $store_meta['expires_at'] ) ? (int) $store_meta['expires_at'] : 0,
			'ttl_seconds'           => ArticleGenerationProposalStore::TTL_SECONDS,
			'title'                 => $data['title'],
			'excerpt'               => $data['excerpt'],
			'meta_description'      => $data['meta_description'],
			'primary_focus_keyword' => $data['primary_focus_keyword'],
			'secondary_keywords'    => $data['secondary_keywords'],
			'entities'              => $data['entities'],
			'search_intent'         => $data['search_intent'],
			'recipe_name'           => $data['recipe_name'],
			'recipe_entity'         => $context instanceof ArticleGenerationContext
				? $context->canonical_recipe_entity()
				: (string) ( $data['recipe_name'] ?? '' ),
			'recipe_facts'          => $data['recipe_facts'],
			'headings'              => $data['headings'],
			'faq'                   => $data['faq'],
			'image_plans'           => $data['image_plans'],
			'content_html'          => $data['content_html'],
			'template'              => $data['template'],
			'requested_word_count'  => $data['requested_word_count'],
			'actual_word_count'     => $data['actual_word_count'],
			'word_count_in_band'    => $data['word_count_in_band'],
			'word_count'            => $data['actual_word_count'],
			'content_gaps'          => $gaps,
		);

		// Explicitly never expose mutation / Rank Math score fields.
		unset( $out['post_id'], $out['seo_owner'], $out['meta_key'], $out['mutation_type'], $out['rank_math_score'] );

		return $out;
	}

	/**
	 * Generate, validate, and store a preview proposal. No WordPress writes.
	 *
	 * @param array<string, mixed> $brief Server brief.
	 * @return array<string, mixed>
	 */
	public function generate_preview_from_brief( array $brief, int $user_id ): array {
		$user_id = max( 0, $user_id );
		if ( $user_id <= 0 ) {
			return array(
				'ok'            => false,
				'code'          => 'rsaip_article_preview_unauthorized',
				'message'       => 'You must be logged in to generate a preview.',
				'source'        => 'article_generation_preview',
				'apply_allowed' => false,
				'draft_allowed' => false,
				'create_draft'  => false,
			);
		}

		$result = $this->generate_from_brief( $brief );
		if ( ! $result->ok() || ! $result->proposal() instanceof ArticleGenerationProposal || ! $result->context() instanceof ArticleGenerationContext ) {
			return $this->to_preview_response( $result );
		}

		$stored = ArticleGenerationProposalStore::put( $user_id, $result->proposal(), $result->context() );
		if ( empty( $stored['ok'] ) ) {
			return array(
				'ok'            => false,
				'code'          => (string) ( $stored['code'] ?? 'rsaip_article_preview_store_failed' ),
				'message'       => (string) ( $stored['message'] ?? 'Could not store preview proposal.' ),
				'source'        => 'article_generation_preview',
				'apply_allowed' => false,
				'draft_allowed' => false,
				'create_draft'  => false,
			);
		}

		return $this->to_preview_response(
			$result,
			array(
				'proposal_id' => (string) ( $stored['proposal_id'] ?? '' ),
				'fingerprint' => (string) ( $stored['fingerprint'] ?? '' ),
				'expires_at'  => (int) ( $stored['expires_at'] ?? 0 ),
			)
		);
	}

	/**
	 * Resolve a stored preview by id + fingerprint (prep for Milestone E). No WP writes.
	 *
	 * @return array<string, mixed>
	 */
	public function retrieve_preview( string $proposal_id, string $fingerprint, int $user_id ): array {
		$got = ArticleGenerationProposalStore::get( $user_id, $proposal_id, $fingerprint );
		if ( empty( $got['ok'] ) || ! ( $got['proposal'] ?? null ) instanceof ArticleGenerationProposal ) {
			return array(
				'ok'            => false,
				'code'          => (string) ( $got['code'] ?? 'rsaip_article_preview_not_found' ),
				'message'       => (string) ( $got['message'] ?? 'Preview not found.' ),
				'source'        => 'article_generation_preview',
				'apply_allowed' => false,
				'draft_allowed' => false,
				'create_draft'  => false,
			);
		}

		$result = ArticleGenerationValidationResult::success( $got['proposal'], $got['context'] ?? null );
		return $this->to_preview_response(
			$result,
			array(
				'proposal_id' => (string) ( $got['proposal_id'] ?? $proposal_id ),
				'fingerprint' => (string) ( $got['fingerprint'] ?? $fingerprint ),
				'expires_at'  => (int) ( $got['expires_at'] ?? 0 ),
			)
		);
	}

	/**
	 * Explicit Create Draft from stored preview (Milestone E). NEW draft only.
	 *
	 * @param array<string, mixed> $request proposal_id + fingerprint only.
	 * @return array<string, mixed>
	 */
	public function create_draft_from_preview( array $request, int $user_id ): array {
		$creator = new ArticleGenerationDraftCreator();
		return $creator->create_from_preview( $request, $user_id );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_complete_options( ArticleGenerationContext $context ): array {
		// Budget for structured JSON envelope + ~1000-word content_html (not unlimited).
		$options = array(
			'max_tokens'  => 8000,
			'temperature' => 0.3,
			'system'      => $this->prompt_builder->system_prompt(),
		);
		$provider_id = $this->active_hub_provider_id();
		if ( $provider_id !== '' && ProviderCatalog::supports_json_response_format( $provider_id ) ) {
			$options['response_format'] = 'json_object';
		}
		unset( $context );
		return $options;
	}

	/**
	 * @param array<string, mixed> $options Options.
	 * @return string|ArticleGenerationValidationResult
	 */
	private function complete( string $prompt, array $options ) {
		if ( is_callable( $this->completer ) ) {
			return $this->normalize_complete_result( call_user_func( $this->completer, $prompt, $options ) );
		}

		if ( class_exists( ProviderManager::class ) && function_exists( 'get_option' ) ) {
			$manager  = ProviderManager::instance();
			$provider = $manager->resolve_active();
			if ( $provider ) {
				return $this->normalize_complete_result( $provider->complete( $prompt, $options ) );
			}
		}

		if ( function_exists( 'rsaip_ai_provider_registry' ) ) {
			$registry = rsaip_ai_provider_registry();
			$provider = $registry->get( 'openai_compatible' );
			return $this->normalize_complete_result( $provider->complete( $prompt, $options ) );
		}

		return ArticleGenerationValidationResult::failure( 'rsaip_article_ai_unavailable', 'AI provider is unavailable.' );
	}

	/**
	 * @param mixed $result Provider result.
	 * @return string|ArticleGenerationValidationResult
	 */
	private function normalize_complete_result( $result ) {
		if ( is_string( $result ) ) {
			$trim = trim( $result );
			if ( $trim === '' ) {
				return ArticleGenerationValidationResult::failure( 'rsaip_article_ai_empty', 'AI returned an empty response.' );
			}
			return $trim;
		}
		if ( is_object( $result ) && method_exists( $result, 'get_error_message' ) ) {
			$code = method_exists( $result, 'get_error_code' ) ? (string) $result->get_error_code() : 'rsaip_article_ai_failed';
			$msg  = (string) $result->get_error_message();
			if ( $code === '' ) {
				$code = 'rsaip_article_ai_failed';
			}
			return ArticleGenerationValidationResult::failure( $code, $msg !== '' ? $msg : 'AI request failed.' );
		}
		return ArticleGenerationValidationResult::failure( 'rsaip_article_ai_failed', 'AI request failed.' );
	}

	private function assert_ai_enabled(): ?ArticleGenerationValidationResult {
		if ( is_callable( $this->completer ) ) {
			return null;
		}
		if ( ! function_exists( 'rsaip_get_settings' ) ) {
			return ArticleGenerationValidationResult::failure(
				'rsaip_article_ai_disabled',
				'AI settings are unavailable.'
			);
		}
		$settings = rsaip_get_settings();
		$provider = is_array( $settings ) ? (string) ( $settings['ai_provider'] ?? '' ) : '';
		$key      = is_array( $settings ) ? trim( (string) ( $settings['ai_api_key'] ?? '' ) ) : '';
		if ( $provider !== 'openai_compatible' ) {
			return ArticleGenerationValidationResult::failure(
				'rsaip_article_ai_disabled',
				'Enable an OpenAI-compatible AI provider in Settings to generate articles.'
			);
		}
		if ( $key === '' ) {
			return ArticleGenerationValidationResult::failure(
				'rsaip_article_ai_disabled',
				'Configure an AI API key in Settings to generate articles.'
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
}
