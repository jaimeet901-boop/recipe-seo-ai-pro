<?php
declare(strict_types=1);

/**
 * Meta description writer — owner key only.
 *
 * Normalize semantics match RSAIP_AI::fit_length( $text, 80, 170 ) for Apply:
 * space normalize, max 170 with word-boundary trim + punctuation rtrim.
 * The $min=80 argument is not enforced (same as legacy fit_length).
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation\Writers;

use RecipeSeoAiPro\Modules\PostMutation\MutationType;
use RecipeSeoAiPro\Modules\PostMutation\MutationWriter;
use RecipeSeoAiPro\Modules\PostMutation\PostMutationEnvironment;
use RecipeSeoAiPro\Modules\PostMutation\SeoOwnershipResolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MetaDescriptionWriter
 */
final class MetaDescriptionWriter implements MutationWriter {

	private const MAX_LENGTH = 170;

	private PostMutationEnvironment $env;
	private SeoOwnershipResolver $ownership;

	public function __construct( PostMutationEnvironment $env, SeoOwnershipResolver $ownership ) {
		$this->env       = $env;
		$this->ownership = $ownership;
	}

	public function mutation_type(): string {
		return MutationType::META_DESCRIPTION;
	}

	public function read_current( int $post_id, string $owner ) {
		$keys = $this->ownership->allowed_meta_keys( $owner, MutationType::META_DESCRIPTION );
		if ( $keys === array() ) {
			return '';
		}
		return $this->env->get_post_meta( $post_id, $keys[0] );
	}

	public function write( int $post_id, string $owner, $new_value ): bool {
		$value = $this->normalize_new_value( $new_value );
		if ( ! is_string( $value ) || $value === '' ) {
			return false;
		}
		$keys = $this->ownership->allowed_meta_keys( $owner, MutationType::META_DESCRIPTION );
		if ( count( $keys ) !== 1 ) {
			return false;
		}
		return $this->env->update_post_meta( $post_id, $keys[0], $value );
	}

	public function values_match( $expected, $actual ): bool {
		return trim( (string) $expected ) === trim( (string) $actual );
	}

	/**
	 * Port of RSAIP_AI::fit_length( $text, 80, 170 ) Apply semantics.
	 * Does not call or alter the legacy private fit_length() method.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null Normalized text or null when empty/invalid.
	 */
	public function normalize_new_value( $value ) {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return null;
		}

		$text = $this->normalize_spaces( (string) $value );
		if ( $text === '' ) {
			return null;
		}

		$max = self::MAX_LENGTH;
		if ( $this->text_length( $text ) > $max ) {
			$text = $this->text_substr( $text, 0, $max );
			$text = preg_replace( '/\s+\S*$/u', '', (string) $text );
			$text = rtrim( (string) $text, " \t\n\r\0\x0B,.-–—|" );
		}

		// Legacy fit_length min=80 does not pad or reject short strings.
		$text = $this->normalize_spaces( $text );
		return $text === '' ? null : $text;
	}

	private function normalize_spaces( string $text ): string {
		$text = preg_replace( '/\s+/u', ' ', $text );
		return trim( (string) $text );
	}

	private function text_length( string $text ): int {
		return function_exists( 'mb_strlen' ) ? (int) mb_strlen( $text ) : strlen( $text );
	}

	private function text_substr( string $text, int $start, int $length ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $text, $start, $length );
		}
		return substr( $text, $start, $length );
	}
}
