<?php
declare(strict_types=1);

/**
 * Primary focus keyword writer — separate from keywords list mutation.
 *
 * Not wired to existing AI Apply Keywords UI/AJAX (those use MutationType::KEYWORDS).
 * Writes the single ownership-resolved key only; no rsaip_generated_keywords mirror
 * when Rank Math or Yoast owns the field.
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
 * Class FocusKeywordWriter
 */
final class FocusKeywordWriter implements MutationWriter {

	private PostMutationEnvironment $env;
	private SeoOwnershipResolver $ownership;

	public function __construct( PostMutationEnvironment $env, SeoOwnershipResolver $ownership ) {
		$this->env       = $env;
		$this->ownership = $ownership;
	}

	public function mutation_type(): string {
		return MutationType::FOCUS_KEYWORD;
	}

	public function read_current( int $post_id, string $owner ) {
		$keys = $this->ownership->allowed_meta_keys( $owner, MutationType::FOCUS_KEYWORD );
		if ( $keys === array() ) {
			return '';
		}
		$raw = $this->env->get_post_meta( $post_id, $keys[0] );
		if ( $owner === SeoOwner::RSAIP ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) && isset( $decoded[0] ) ) {
				return trim( (string) $decoded[0] );
			}
		}
		if ( $owner === SeoOwner::RANKMATH && strpos( $raw, ',' ) !== false ) {
			$parts = array_map( 'trim', explode( ',', $raw ) );
			return (string) ( $parts[0] ?? '' );
		}
		return trim( $raw );
	}

	public function write( int $post_id, string $owner, $new_value ): bool {
		$value = $this->normalize_new_value( $new_value );
		if ( ! is_string( $value ) || $value === '' ) {
			return false;
		}
		$keys = $this->ownership->allowed_meta_keys( $owner, MutationType::FOCUS_KEYWORD );
		if ( count( $keys ) !== 1 ) {
			return false;
		}
		$stored = $value;
		if ( $owner === SeoOwner::RSAIP ) {
			$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( array( $value ) ) : json_encode( array( $value ) );
			$stored  = is_string( $encoded ) ? $encoded : '[]';
		}
		return $this->env->update_post_meta( $post_id, $keys[0], $stored );
	}

	public function values_match( $expected, $actual ): bool {
		return trim( (string) $expected ) === trim( (string) $actual );
	}

	public function normalize_new_value( $value ) {
		if ( is_array( $value ) ) {
			$value = $value[0] ?? '';
		}
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return null;
		}
		$text = trim( (string) $value );
		return $text === '' ? null : $text;
	}
}
