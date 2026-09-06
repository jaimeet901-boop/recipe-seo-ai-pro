<?php
declare(strict_types=1);

/**
 * Picks a specific recipe/topic phrase for title generation.
 *
 * Generic one-word focus keywords must not replace a more specific
 * multi-word phrase already present in the post title.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\TitleGeneration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TitleTopicSelector
 */
final class TitleTopicSelector {

	/**
	 * @return array<string, true>
	 */
	private function generic_tokens(): array {
		static $tokens = null;
		if ( is_array( $tokens ) ) {
			return $tokens;
		}
		$list = array(
			'salad',
			'recipe',
			'recipes',
			'food',
			'dish',
			'meal',
			'chicken',
			'dessert',
			'soup',
			'pasta',
			'cake',
			'bread',
			'drink',
			'snack',
			'dinner',
			'lunch',
			'breakfast',
			'vegan',
			'vegetarian',
			'healthy',
			'easy',
			'delicious',
			'homemade',
			'cooking',
			'cook',
			'kitchen',
			'guide',
			'tips',
			'best',
			'make',
			'how',
			'tasty',
			'yummy',
			'quick',
			'simple',
			'ideas',
			'idea',
		);
		$tokens = array();
		foreach ( $list as $word ) {
			$tokens[ $word ] = true;
		}
		return $tokens;
	}

	public function normalize( string $text ): string {
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/<[^>]*>/u', ' ', $text );
		$text = preg_replace( '/\s+/u', ' ', (string) $text );
		return trim( (string) $text );
	}

	public function extract_anchor( string $title ): string {
		$title = $this->normalize( $title );
		if ( $title === '' ) {
			return '';
		}

		if ( $this->looks_arabic( $title ) ) {
			$parts = preg_split( '/\s*[-–—|:]\s*/u', $title, 2 );
			return $this->normalize( is_array( $parts ) && isset( $parts[0] ) ? (string) $parts[0] : $title );
		}

		$parts   = preg_split( '/\s*[-–—|:]\s*/u', $title, 2 );
		$primary = $this->normalize( is_array( $parts ) && isset( $parts[0] ) ? (string) $parts[0] : $title );
		$primary = preg_replace( '/^(how\s+to\s+(make|cook)\s+)/iu', '', $primary );
		$primary = $this->normalize( (string) $primary );

		$leading = array(
			'the',
			'a',
			'an',
			'delicious',
			'tasty',
			'yummy',
			'amazing',
			'awesome',
			'great',
			'super',
			'best',
			'easy',
			'easiest',
			'quick',
			'quickly',
			'healthy',
			'healthier',
			'simple',
			'simply',
			'perfect',
			'ultimate',
			'homemade',
			'classic',
			'authentic',
			'traditional',
			'creamy',
			'crispy',
			'fresh',
			'juicy',
			'spicy',
			'sweet',
			'savory',
			'wonderful',
			'incredible',
			'fantastic',
			'lovely',
		);
		$trailing = array(
			'recipe',
			'recipes',
			'ideas',
			'idea',
			'tutorial',
			'guide',
			'howto',
		);

		$changed = true;
		while ( $changed ) {
			$changed = false;
			$tokens  = $this->split_words( $primary );
			if ( $tokens === array() ) {
				break;
			}
			$first = $this->lower( $tokens[0] );
			if ( in_array( $first, $leading, true ) && count( $tokens ) > 1 ) {
				array_shift( $tokens );
				$primary = implode( ' ', $tokens );
				$changed = true;
				continue;
			}
			$last = $this->lower( (string) end( $tokens ) );
			if ( in_array( $last, $trailing, true ) && count( $tokens ) > 1 ) {
				array_pop( $tokens );
				$primary = implode( ' ', $tokens );
				$changed = true;
			}
		}

		return $this->normalize( $primary );
	}

	public function resolve( string $title, string $keyword ): TitleTopic {
		$title   = $this->normalize( $title );
		$keyword = $this->normalize( $keyword );
		$anchor  = $this->extract_anchor( $title );

		if ( $keyword === '' ) {
			$base = $anchor !== '' ? $anchor : $title;
			return new TitleTopic( $base, $base, $anchor );
		}

		if ( $this->is_generic_focus_keyword( $keyword, $anchor ) ) {
			$base = $anchor !== '' ? $anchor : $title;
			return new TitleTopic( $base, $base, $anchor );
		}

		if ( $anchor !== '' && $this->contains_all_tokens( $anchor, $keyword ) ) {
			$base = $this->phrase_casing( $anchor, $title );
			return new TitleTopic( $base, $base, $anchor );
		}

		$base = $this->phrase_casing( $keyword, $title );
		return new TitleTopic( $base, $base, $anchor );
	}

	/**
	 * @return list<string>
	 */
	public function build_heuristic_titles( TitleTopic $topic ): array {
		$base = $this->normalize( $topic->base() );
		if ( $base === '' ) {
			return array();
		}

		$ar = $this->looks_arabic( $base . ' ' . $topic->title_anchor() );
		if ( $ar ) {
			return array(
				$base,
				$base . ' بطريقة سهلة',
				'أفضل ' . $base,
				$base . ' خطوة بخطوة',
				'طريقة عمل ' . $base,
			);
		}

		$ends_recipe = (bool) preg_match( '/\brecipe\s*$/iu', $base );
		$out         = array( $base );
		if ( ! $ends_recipe ) {
			$out[] = $base . ' Recipe';
			$out[] = 'Easy ' . $base . ' Recipe';
		} else {
			$out[] = 'Easy ' . $base;
		}
		$out[] = 'Best ' . $base;
		$out[] = 'How to Make ' . $base;

		$unique = array();
		foreach ( $out as $title ) {
			$title = $this->normalize( $title );
			if ( $title === '' || isset( $unique[ $this->lower( $title ) ] ) ) {
				continue;
			}
			$unique[ $this->lower( $title ) ] = $title;
		}
		return array_values( $unique );
	}

	/**
	 * Drop suggestions that omit the required topic or exceed 60 characters.
	 *
	 * @param list<string> $titles
	 * @return list<string>
	 */
	public function keep_titles_with_topic( array $titles, TitleTopic $topic ): array {
		$need = $this->lower( $this->normalize( $topic->required_phrase() ) );
		$out  = array();
		foreach ( $titles as $title ) {
			if ( ! is_string( $title ) && ! is_numeric( $title ) ) {
				continue;
			}
			$title = $this->normalize( (string) $title );
			if ( $title === '' ) {
				continue;
			}
			if ( $this->str_len( $title ) > 60 ) {
				continue;
			}
			if ( $need !== '' && $this->str_pos( $this->lower( $title ), $need ) === false ) {
				continue;
			}
			$out[] = $title;
		}
		return array_values( array_unique( $out ) );
	}

	public function looks_arabic( string $text ): bool {
		return preg_match( '/\p{Arabic}/u', $text ) === 1;
	}

	/**
	 * Whether $phrase is too generic relative to a more specific recipe/topic entity.
	 * Public gate for SeoOptimizationProposal validation (Milestone 5A).
	 */
	public function is_generic_relative_to( string $phrase, string $specific_entity ): bool {
		$phrase          = $this->normalize( $phrase );
		$specific_entity = $this->normalize( $specific_entity );
		if ( $phrase === '' ) {
			return true;
		}
		if ( $specific_entity === '' ) {
			return false;
		}
		return $this->is_generic_focus_keyword( $phrase, $specific_entity );
	}

	/**
	 * Milestone 5E: whether phrase fails to cover the specific recipe entity.
	 * Accepts natural variations ("Easy Quinoa Black Bean Salad Recipe") that still include the entity.
	 * Rejects truncated hypernyms ("Salad", "Black Bean Salad") when a fuller entity is known.
	 */
	public function covers_specific_entity( string $phrase, string $specific_entity ): bool {
		$phrase          = $this->normalize( $phrase );
		$specific_entity = $this->normalize( $specific_entity );
		if ( $specific_entity === '' ) {
			return $phrase !== '';
		}
		if ( $phrase === '' ) {
			return false;
		}
		if ( $this->is_generic_relative_to( $phrase, $specific_entity ) ) {
			return false;
		}
		// Exact / substring containment (case-insensitive).
		if ( $this->str_pos( $this->lower( $phrase ), $this->lower( $specific_entity ) ) !== false ) {
			return true;
		}
		if ( $this->contains_all_tokens( $phrase, $specific_entity ) ) {
			return true;
		}
		// Require all distinctive (non-generic) entity tokens to appear in the phrase.
		$need = $this->distinctive_tokens( $specific_entity );
		if ( $need === array() ) {
			return ! $this->is_generic_relative_to( $phrase, $specific_entity );
		}
		$have = array_fill_keys( $this->split_words( $this->lower( $phrase ) ), true );
		foreach ( $need as $token ) {
			if ( ! isset( $have[ $token ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * True when phrase is weaker/less specific than the known entity (5E quality gate).
	 */
	public function is_weaker_than_entity( string $phrase, string $specific_entity ): bool {
		return ! $this->covers_specific_entity( $phrase, $specific_entity );
	}

	/**
	 * Prefer a cleaned recipe entity phrase from candidates (schema / card / title).
	 * Never prefers a generic focus keyword when a stronger title/schema entity exists.
	 *
	 * @param list<string> $candidates Ordered preference (first wins if specific enough).
	 */
	public function pick_canonical_entity( array $candidates, string $fallback_title = '' ): string {
		$title_anchor = $this->extract_anchor( $fallback_title );
		$best         = '';
		$best_score   = -1;
		foreach ( $candidates as $raw ) {
			if ( ! is_string( $raw ) && ! is_numeric( $raw ) ) {
				continue;
			}
			$cleaned = $this->extract_anchor( (string) $raw );
			if ( $cleaned === '' ) {
				$cleaned = $this->normalize( (string) $raw );
			}
			if ( $cleaned === '' ) {
				continue;
			}
			if ( $title_anchor !== '' && $this->is_generic_relative_to( $cleaned, $title_anchor ) ) {
				continue;
			}
			$score = count( $this->distinctive_tokens( $cleaned ) );
			if ( $score > $best_score ) {
				$best_score = $score;
				$best       = $cleaned;
			} elseif ( $score === $best_score && $best !== '' && $this->str_len( $cleaned ) < $this->str_len( $best ) ) {
				// Prefer tighter phrase at equal distinctiveness.
				$best = $cleaned;
			}
		}
		if ( $best !== '' ) {
			return $best;
		}
		if ( $title_anchor !== '' ) {
			return $title_anchor;
		}
		return $this->normalize( $fallback_title );
	}

	/**
	 * Distinctive (non-generic) tokens for specificity comparisons.
	 *
	 * @return list<string>
	 */
	public function distinctive_tokens( string $phrase ): array {
		$generic = $this->generic_tokens();
		$out     = array();
		foreach ( $this->split_words( $this->lower( $this->normalize( $phrase ) ) ) as $word ) {
			if ( isset( $generic[ $word ] ) || $this->str_len( $word ) < 3 ) {
				continue;
			}
			$out[] = $word;
		}
		return array_values( array_unique( $out ) );
	}

	private function is_generic_focus_keyword( string $keyword, string $title_anchor ): bool {
		$kw_words = $this->split_words( $this->lower( $keyword ) );
		if ( $kw_words === array() ) {
			return true;
		}

		$generic      = $this->generic_tokens();
		$anchor_words = $this->split_words( $this->lower( $title_anchor ) );
		$specific     = 0;
		foreach ( $kw_words as $word ) {
			if ( ! isset( $generic[ $word ] ) && $this->str_len( $word ) >= 3 ) {
				$specific++;
			}
		}

		if ( count( $kw_words ) === 1 ) {
			$word = $kw_words[0];
			if ( isset( $generic[ $word ] ) ) {
				return true;
			}
			if ( count( $anchor_words ) >= 2 && in_array( $word, $anchor_words, true ) ) {
				return true;
			}
			return false;
		}

		if ( $specific === 0 ) {
			return true;
		}

		return false;
	}

	private function contains_all_tokens( string $haystack, string $needle ): bool {
		$hay   = $this->split_words( $this->lower( $haystack ) );
		$need  = $this->split_words( $this->lower( $needle ) );
		$index = array_fill_keys( $hay, true );
		foreach ( $need as $word ) {
			if ( ! isset( $index[ $word ] ) ) {
				return false;
			}
		}
		return $need !== array();
	}

	private function phrase_casing( string $phrase, string $title ): string {
		$phrase = $this->normalize( $phrase );
		if ( $phrase === '' ) {
			return '';
		}
		$pos = $this->str_ipos( $title, $phrase );
		if ( $pos !== false ) {
			return $this->str_sub( $title, $pos, $this->str_len( $phrase ) );
		}
		return $phrase;
	}

	/**
	 * @return list<string>
	 */
	private function split_words( string $text ): array {
		$parts = preg_split( '/\s+/u', $this->normalize( $text ) );
		if ( ! is_array( $parts ) ) {
			return array();
		}
		$out = array();
		foreach ( $parts as $part ) {
			$part = trim( (string) $part );
			if ( $part !== '' ) {
				$out[] = $part;
			}
		}
		return $out;
	}

	private function lower( string $text ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	private function str_len( string $text ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	private function str_sub( string $text, int $start, int $length ): string {
		return function_exists( 'mb_substr' ) ? (string) mb_substr( $text, $start, $length, 'UTF-8' ) : substr( $text, $start, $length );
	}

	/**
	 * @return int|false
	 */
	private function str_pos( string $haystack, string $needle ) {
		if ( function_exists( 'mb_strpos' ) ) {
			return mb_strpos( $haystack, $needle, 0, 'UTF-8' );
		}
		return strpos( $haystack, $needle );
	}

	/**
	 * @return int|false
	 */
	private function str_ipos( string $haystack, string $needle ) {
		if ( function_exists( 'mb_stripos' ) ) {
			return mb_stripos( $haystack, $needle, 0, 'UTF-8' );
		}
		return stripos( $haystack, $needle );
	}
}
