<?php
declare(strict_types=1);

/**
 * Ingredient Assistant (Phase 5.2).
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
 * Class IngredientAssistant
 */
final class IngredientAssistant {

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
	public function run( array $recipe, string $mode = 'all', string $extra = '' ) {
		return $this->complete( 'ingredients', $mode, $recipe, $extra );
	}

	/**
	 * Local duplicate detection (no AI).
	 *
	 * @param array<string, mixed> $recipe Recipe.
	 * @return list<string>
	 */
	public function detect_duplicates( array $recipe ): array {
		$names = array();
		$dups  = array();
		$ings  = isset( $recipe['ingredients'] ) && is_array( $recipe['ingredients'] ) ? $recipe['ingredients'] : array();
		foreach ( $ings as $ing ) {
			if ( ! is_array( $ing ) ) {
				continue;
			}
			$key = strtolower( trim( (string) ( $ing['name'] ?? '' ) ) );
			if ( $key === '' ) {
				continue;
			}
			if ( isset( $names[ $key ] ) ) {
				$dups[] = (string) ( $ing['name'] ?? $key );
			}
			$names[ $key ] = true;
		}
		return array_values( array_unique( $dups ) );
	}

	/**
	 * @param array<string, mixed> $recipe Recipe.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function complete( string $action, string $mode, array $recipe, string $extra ) {
		$gate = $this->gate();
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$prompt   = $this->prompts->build( $action, $mode, $recipe, $extra );
		$provider = $this->providers->get( 'openai_compatible' );
		$result   = $provider->complete( $prompt, array( 'max_tokens' => 1200 ) );
		if ( is_wp_error( $result ) ) {
			$this->logger->error( 'recipe_ai.ingredients_failed', array( 'message' => $result->get_error_message() ) );
			return $result;
		}
		$data = $this->decoder->decode( (string) $result );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'rsaip_recipe_ai_parse', 'Could not parse ingredient assistant response.' );
		}
		$local_dups = $this->detect_duplicates( $recipe );
		if ( $local_dups && empty( $data['duplicates'] ) ) {
			$data['duplicates'] = $local_dups;
		} elseif ( $local_dups ) {
			$existing = is_array( $data['duplicates'] ?? null ) ? $data['duplicates'] : array();
			$data['duplicates'] = array_values( array_unique( array_merge( $existing, $local_dups ) ) );
		}
		return $data;
	}

	/**
	 * @return true|\WP_Error
	 */
	private function gate() {
		$all = $this->settings->all();
		if ( ( $all['ai_provider'] ?? '' ) !== 'openai_compatible' ) {
			return new \WP_Error( 'rsaip_recipe_ai_provider', 'Configure an OpenAI-compatible AI provider in Settings.' );
		}
		return true;
	}
}
