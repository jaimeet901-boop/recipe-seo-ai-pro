<?php
declare(strict_types=1);

/**
 * Recipe variation engine (Phase 5.2).
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
 * Class RecipeVariationEngine
 */
final class RecipeVariationEngine {

	public const VARIATIONS = array(
		'keto',
		'low_carb',
		'gluten_free',
		'vegan',
		'vegetarian',
		'dairy_free',
		'high_protein',
		'low_fat',
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
	public function run( array $recipe, string $variation = 'gluten_free' ) {
		if ( ! in_array( $variation, self::VARIATIONS, true ) ) {
			$variation = 'gluten_free';
		}
		$all = $this->settings->all();
		if ( ( $all['ai_provider'] ?? '' ) !== 'openai_compatible' ) {
			return new \WP_Error( 'rsaip_recipe_ai_provider', 'Configure an OpenAI-compatible AI provider in Settings.' );
		}
		$prompt   = $this->prompts->build( 'variation', $variation, $recipe );
		$provider = $this->providers->get( 'openai_compatible' );
		$result   = $provider->complete( $prompt, array( 'max_tokens' => 1200 ) );
		if ( is_wp_error( $result ) ) {
			$this->logger->error( 'recipe_ai.variation_failed', array( 'message' => $result->get_error_message() ) );
			return $result;
		}
		$data = $this->decoder->decode( (string) $result );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'rsaip_recipe_ai_parse', 'Could not parse variation response.' );
		}
		$data['variation'] = $variation;
		return $data;
	}
}
