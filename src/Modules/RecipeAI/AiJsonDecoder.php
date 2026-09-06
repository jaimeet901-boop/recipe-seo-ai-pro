<?php
declare(strict_types=1);

/**
 * Shared AI JSON decode helper (Phase 5.2).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\RecipeAI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AiJsonDecoder
 */
final class AiJsonDecoder {

	/**
	 * @return array<string, mixed>|null
	 */
	public function decode( string $raw ): ?array {
		$raw = trim( $raw );
		if ( preg_match( '/^```(?:json)?\s*(.*?)\s*```$/is', $raw, $m ) ) {
			$raw = trim( $m[1] );
		}
		$data = json_decode( $raw, true );
		if ( is_array( $data ) ) {
			return $data;
		}
		$start = strpos( $raw, '{' );
		$end   = strrpos( $raw, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$data = json_decode( substr( $raw, $start, $end - $start + 1 ), true );
			return is_array( $data ) ? $data : null;
		}
		return null;
	}
}
