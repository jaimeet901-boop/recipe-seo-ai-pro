<?php
declare(strict_types=1);

/**
 * Recipe rewrite engine (Phase 5.2).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\RecipeAI;

use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Contracts\SettingsServiceInterface;
use RecipeSeoAiPro\Modules\Ai\AiProviderRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeRewriteEngine
 */
final class RecipeRewriteEngine {

	public const MODES = array(
		'beginner_friendly',
		'professional_chef',
		'restaurant_style',
		'seo_optimized',
		'family_friendly',
	);

	private AiProviderRegistry $providers;

	private SettingsServiceInterface $settings;

	private LoggerInterface $logger;

	private RecipePromptBuilder $prompts;

	private AiJsonDecoder $decoder;

	public function __construct(
		AiProviderRegistry $providers,
		SettingsServiceInterface $settings,
		LoggerInterface $logger,
		RecipePromptBuilder $prompts,
		AiJsonDecoder $decoder
	) {
		$this->providers = $providers;
		$this->settings  = $settings;
		$this->logger    = $logger;
		$this->prompts   = $prompts;
		$this->decoder   = $decoder;
	}

	/**
	 * @param array<string, mixed> $recipe Recipe.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function run( array $recipe, string $mode = 'beginner_friendly' ) {
		if ( ! in_array( $mode, self::MODES, true ) ) {
			$mode = 'beginner_friendly';
		}
		$all = $this->settings->all();
		if ( ( $all['ai_provider'] ?? '' ) !== 'openai_compatible' ) {
			return new \WP_Error( 'rsaip_recipe_ai_provider', 'Configure an OpenAI-compatible AI provider in Settings.' );
		}
		$prompt   = $this->prompts->build( 'rewrite', $mode, $recipe );
		$provider = $this->providers->get( 'openai_compatible' );
		$result   = $provider->complete( $prompt, array( 'max_tokens' => 1200 ) );
		if ( is_wp_error( $result ) ) {
			$this->logger->error( 'recipe_ai.rewrite_failed', array( 'message' => $result->get_error_message() ) );
			return $result;
		}
		$data = $this->decoder->decode( (string) $result );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'rsaip_recipe_ai_parse', 'Could not parse rewrite response.' );
		}
		$data['mode'] = $mode;
		return $data;
	}
}
