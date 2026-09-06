<?php
declare(strict_types=1);

/**
 * Best-effort USD cost estimates per provider/model family.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CostEstimator
 */
final class CostEstimator {

	/**
	 * @param string $provider_id Hub provider id.
	 * @param string $model       Model id.
	 */
	public static function estimate( string $provider_id, string $model, int $prompt_tokens, int $completion_tokens ): float {
		$rates = self::rates( $provider_id, $model );
		$prompt_tokens     = max( 0, $prompt_tokens );
		$completion_tokens = max( 0, $completion_tokens );

		return ( $prompt_tokens / 1000000 ) * $rates['input']
			+ ( $completion_tokens / 1000000 ) * $rates['output'];
	}

	/**
	 * @return array{input: float, output: float} USD per 1M tokens.
	 */
	private static function rates( string $provider_id, string $model ): array {
		$model = strtolower( $model );
		$provider_id = sanitize_key( $provider_id );

		// Local / free tiers.
		if ( in_array( $provider_id, array( 'ollama', 'lmstudio' ), true ) ) {
			return array( 'input' => 0.0, 'output' => 0.0 );
		}

		if ( strpos( $model, 'gpt-4o-mini' ) !== false ) {
			return array( 'input' => 0.15, 'output' => 0.60 );
		}
		if ( strpos( $model, 'gpt-4o' ) !== false ) {
			return array( 'input' => 2.50, 'output' => 10.0 );
		}
		if ( strpos( $model, 'deepseek' ) !== false ) {
			return array( 'input' => 0.14, 'output' => 0.28 );
		}
		if ( strpos( $model, 'claude-3-5-haiku' ) !== false || strpos( $model, 'haiku' ) !== false ) {
			return array( 'input' => 0.80, 'output' => 4.0 );
		}
		if ( strpos( $model, 'claude' ) !== false ) {
			return array( 'input' => 3.0, 'output' => 15.0 );
		}
		if ( strpos( $model, 'gemini' ) !== false && strpos( $model, 'flash' ) !== false ) {
			return array( 'input' => 0.10, 'output' => 0.40 );
		}
		if ( strpos( $model, 'gemini' ) !== false ) {
			return array( 'input' => 1.25, 'output' => 5.0 );
		}
		if ( $provider_id === 'groq' ) {
			return array( 'input' => 0.05, 'output' => 0.08 );
		}
		if ( $provider_id === 'mistral' ) {
			return array( 'input' => 0.20, 'output' => 0.60 );
		}
		if ( $provider_id === 'cohere' ) {
			return array( 'input' => 0.30, 'output' => 1.20 );
		}
		if ( $provider_id === 'xai' ) {
			return array( 'input' => 2.0, 'output' => 10.0 );
		}

		// Conservative default.
		return array( 'input' => 0.50, 'output' => 1.50 );
	}
}
