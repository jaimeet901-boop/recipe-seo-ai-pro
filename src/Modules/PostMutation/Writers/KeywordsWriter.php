<?php
declare(strict_types=1);

/**
 * Keywords list writer — separate from focus_keyword mutation.
 *
 * Writes the single ownership-resolved key only:
 * Rank Math => rank_math_focus_keyword; Yoast => _yoast_wpseo_focuskw;
 * RSAIP (no SEO plugin) => rsaip_generated_keywords.
 * Does not mirror rsaip_generated_keywords when Rank Math or Yoast owns the field.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation\Writers;

use RecipeSeoAiPro\Modules\PostMutation\MutationType;
use RecipeSeoAiPro\Modules\PostMutation\MutationWriter;
use RecipeSeoAiPro\Modules\PostMutation\PostMutationEnvironment;
use RecipeSeoAiPro\Modules\PostMutation\SeoOwner;
use RecipeSeoAiPro\Modules\PostMutation\SeoOwnershipResolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KeywordsWriter
 */
final class KeywordsWriter implements MutationWriter {

	private PostMutationEnvironment $env;
	private SeoOwnershipResolver $ownership;

	public function __construct( PostMutationEnvironment $env, SeoOwnershipResolver $ownership ) {
		$this->env       = $env;
		$this->ownership = $ownership;
	}

	public function mutation_type(): string {
		return MutationType::KEYWORDS;
	}

	/**
	 * @return list<string>
	 */
	public function read_current( int $post_id, string $owner ) {
		$keys = $this->ownership->allowed_meta_keys( $owner, MutationType::KEYWORDS );
		if ( $keys === array() ) {
			return array();
		}
		$raw = $this->env->get_post_meta( $post_id, $keys[0] );
		if ( $owner === SeoOwner::RSAIP ) {
			$decoded = json_decode( $raw, true );
			return $this->normalize_list( is_array( $decoded ) ? $decoded : array() );
		}
		if ( $owner === SeoOwner::RANKMATH ) {
			return $this->normalize_list( explode( ',', $raw ) );
		}
		// Yoast primary only for owned key.
		return $this->normalize_list( array( $raw ) );
	}

	public function write( int $post_id, string $owner, $new_value ): bool {
		$list = $this->normalize_new_value( $new_value );
		if ( ! is_array( $list ) || $list === array() ) {
			return false;
		}
		$keys = $this->ownership->allowed_meta_keys( $owner, MutationType::KEYWORDS );
		if ( count( $keys ) !== 1 ) {
			return false;
		}
		if ( $owner === SeoOwner::RANKMATH ) {
			$stored = implode( ', ', $list );
		} elseif ( $owner === SeoOwner::YOAST ) {
			$stored = (string) $list[0];
		} else {
			$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $list ) : json_encode( $list );
			$stored  = is_string( $encoded ) ? $encoded : '[]';
		}
		return $this->env->update_post_meta( $post_id, $keys[0], $stored );
	}

	public function values_match( $expected, $actual ): bool {
		$a = $this->normalize_list( is_array( $expected ) ? $expected : array( $expected ) );
		$b = $this->normalize_list( is_array( $actual ) ? $actual : array( $actual ) );
		return $a === $b;
	}

	public function normalize_new_value( $value ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[,|\n]+/u', $value ) ?: array();
		}
		if ( ! is_array( $value ) ) {
			return null;
		}
		$list = $this->normalize_list( $value );
		return $list === array() ? null : $list;
	}

	/**
	 * @param array<int, mixed> $items Items.
	 * @return list<string>
	 */
	private function normalize_list( array $items ): array {
		$out = array();
		foreach ( $items as $item ) {
			if ( ! is_string( $item ) && ! is_numeric( $item ) ) {
				continue;
			}
			$text = trim( (string) $item );
			if ( $text !== '' ) {
				$out[] = $text;
			}
		}
		return array_values( array_unique( $out ) );
	}
}
