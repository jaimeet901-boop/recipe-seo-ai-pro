<?php
declare(strict_types=1);

/**
 * Scoped rewrite helpers (Phase 4.1).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentOptimizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RewriteEngine
 */
final class RewriteEngine {

	public const SCOPES = array(
		'full_article',
		'selected_paragraph',
		'introduction',
		'conclusion',
		'heading',
		'meta_description',
		'recipe_card',
	);

	public function normalize_scope( string $scope ): string {
		$scope = sanitize_key( $scope );
		return in_array( $scope, self::SCOPES, true ) ? $scope : 'full_article';
	}

	/**
	 * Extract the text/HTML segment for a scope.
	 *
	 * @param array<string, mixed> $context selected_text, meta, title.
	 */
	public function extract_scope( string $content, string $scope, array $context = array() ): string {
		$scope = $this->normalize_scope( $scope );
		if ( $scope === 'meta_description' ) {
			return (string) ( $context['meta'] ?? '' );
		}
		if ( $scope === 'heading' ) {
			if ( ! empty( $context['selected_text'] ) ) {
				return (string) $context['selected_text'];
			}
			if ( preg_match( '/<h1\b[^>]*>(.*?)<\/h1>/is', $content, $m ) ) {
				return $m[0];
			}
			return (string) ( $context['title'] ?? '' );
		}
		if ( $scope === 'selected_paragraph' ) {
			return (string) ( $context['selected_text'] ?? '' );
		}
		if ( $scope === 'introduction' ) {
			return $this->extract_introduction( $content );
		}
		if ( $scope === 'conclusion' ) {
			return $this->extract_conclusion( $content );
		}
		if ( $scope === 'recipe_card' ) {
			return $this->extract_recipe_card( $content );
		}
		return $content;
	}

	/**
	 * Merge optimized scope back into full content.
	 *
	 * @param array<string, mixed> $context selected_text, etc.
	 */
	public function merge_scope( string $original, string $optimized_scope, string $scope, array $context = array() ): string {
		$scope = $this->normalize_scope( $scope );
		if ( in_array( $scope, array( 'full_article', 'meta_description' ), true ) ) {
			return $scope === 'full_article' ? $optimized_scope : $original;
		}
		if ( $scope === 'selected_paragraph' || $scope === 'heading' ) {
			$needle = (string) ( $context['selected_text'] ?? '' );
			if ( $needle !== '' && false !== strpos( $original, $needle ) ) {
				return (string) preg_replace( '/' . preg_quote( $needle, '/' ) . '/', $optimized_scope, $original, 1 );
			}
			return $original . "\n\n" . $optimized_scope;
		}
		if ( $scope === 'introduction' ) {
			$intro = $this->extract_introduction( $original );
			if ( $intro !== '' && false !== strpos( $original, $intro ) ) {
				return (string) preg_replace( '/' . preg_quote( $intro, '/' ) . '/s', $optimized_scope, $original, 1 );
			}
			return $optimized_scope . "\n\n" . $original;
		}
		if ( $scope === 'conclusion' ) {
			$outro = $this->extract_conclusion( $original );
			if ( $outro !== '' && false !== strpos( $original, $outro ) ) {
				return (string) preg_replace( '/' . preg_quote( $outro, '/' ) . '/s', $optimized_scope, $original, 1 );
			}
			return $original . "\n\n" . $optimized_scope;
		}
		if ( $scope === 'recipe_card' ) {
			$card = $this->extract_recipe_card( $original );
			if ( $card !== '' && false !== strpos( $original, $card ) ) {
				return (string) preg_replace( '/' . preg_quote( $card, '/' ) . '/s', $optimized_scope, $original, 1 );
			}
			return $original . "\n\n" . $optimized_scope;
		}
		return $optimized_scope;
	}

	/**
	 * Word-level diff tokens for Compare Mode.
	 *
	 * @return list<array{type: string, text: string}>
	 */
	public function build_diff( string $original, string $optimized ): array {
		$a = preg_split( '/(\s+)/u', $original, -1, PREG_SPLIT_DELIM_CAPTURE ) ?: array();
		$b = preg_split( '/(\s+)/u', $optimized, -1, PREG_SPLIT_DELIM_CAPTURE ) ?: array();
		$a = array_values( array_filter( $a, static fn( $t ) => $t !== '' ) );
		$b = array_values( array_filter( $b, static fn( $t ) => $t !== '' ) );

		$matrix = array();
		$n      = count( $a );
		$m      = count( $b );
		// Cap for safety on huge posts.
		if ( $n > 2500 || $m > 2500 ) {
			return array(
				array( 'type' => 'removed', 'text' => mb_substr( $original, 0, 2000 ) ),
				array( 'type' => 'added', 'text' => mb_substr( $optimized, 0, 2000 ) ),
			);
		}

		for ( $i = 0; $i <= $n; $i++ ) {
			$matrix[ $i ][0] = 0;
		}
		for ( $j = 0; $j <= $m; $j++ ) {
			$matrix[0][ $j ] = 0;
		}
		for ( $i = 1; $i <= $n; $i++ ) {
			for ( $j = 1; $j <= $m; $j++ ) {
				if ( $a[ $i - 1 ] === $b[ $j - 1 ] ) {
					$matrix[ $i ][ $j ] = $matrix[ $i - 1 ][ $j - 1 ] + 1;
				} else {
					$matrix[ $i ][ $j ] = max( $matrix[ $i - 1 ][ $j ], $matrix[ $i ][ $j - 1 ] );
				}
			}
		}

		$diff = array();
		$i    = $n;
		$j    = $m;
		while ( $i > 0 && $j > 0 ) {
			if ( $a[ $i - 1 ] === $b[ $j - 1 ] ) {
				array_unshift( $diff, array( 'type' => 'same', 'text' => $a[ $i - 1 ] ) );
				--$i;
				--$j;
			} elseif ( $matrix[ $i - 1 ][ $j ] >= $matrix[ $i ][ $j - 1 ] ) {
				array_unshift( $diff, array( 'type' => 'removed', 'text' => $a[ $i - 1 ] ) );
				--$i;
			} else {
				array_unshift( $diff, array( 'type' => 'added', 'text' => $b[ $j - 1 ] ) );
				--$j;
			}
		}
		while ( $i > 0 ) {
			array_unshift( $diff, array( 'type' => 'removed', 'text' => $a[ $i - 1 ] ) );
			--$i;
		}
		while ( $j > 0 ) {
			array_unshift( $diff, array( 'type' => 'added', 'text' => $b[ $j - 1 ] ) );
			--$j;
		}

		// Collapse consecutive same-type tokens.
		$out = array();
		foreach ( $diff as $token ) {
			$last = $out ? $out[ count( $out ) - 1 ] : null;
			if ( $last && $last['type'] === $token['type'] ) {
				$out[ count( $out ) - 1 ]['text'] .= $token['text'];
			} else {
				$out[] = $token;
			}
		}
		return $out;
	}

	private function extract_introduction( string $content ): string {
		if ( preg_match( '/^(.*?)(?=<h2\b)/is', $content, $m ) && trim( $m[1] ) !== '' ) {
			return trim( $m[1] );
		}
		$parts = preg_split( '/\n\s*\n/', wp_strip_all_tags( $content ) ) ?: array();
		return trim( (string) ( $parts[0] ?? '' ) );
	}

	private function extract_conclusion( string $content ): string {
		if ( preg_match_all( '/<h2\b[^>]*>.*?<\/h2>(.*)$/is', $content, $m ) && ! empty( $m[1] ) ) {
			$last = end( $m[1] );
			return trim( (string) $last );
		}
		$parts = preg_split( '/\n\s*\n/', wp_strip_all_tags( $content ) ) ?: array();
		return trim( (string) ( end( $parts ) ?: '' ) );
	}

	private function extract_recipe_card( string $content ): string {
		if ( preg_match( '/(<div[^>]+class=["\'][^"\']*rsaip-recipe[^"\']*["\'][^>]*>.*?<\/div>)/is', $content, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '/(\[rsaip_recipe[^\]]*\].*?\[\/rsaip_recipe\])/is', $content, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '/(<[^>]+itemtype=["\'][^"\']*Recipe["\'][^>]*>.*?<\/[^>]+>)/is', $content, $m ) ) {
			return $m[1];
		}
		// Fallback: ingredients + instructions region.
		if ( preg_match( '/(ingredients?.*?(?:instructions?|directions|method).{0,8000})/is', wp_strip_all_tags( $content ), $m ) ) {
			return trim( $m[1] );
		}
		return '';
	}
}
