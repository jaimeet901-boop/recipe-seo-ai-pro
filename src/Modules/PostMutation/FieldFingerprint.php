<?php
declare(strict_types=1);

/**
 * Deterministic optimistic-lock fingerprint for mutation proposals.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FieldFingerprint
 */
final class FieldFingerprint {

	/**
	 * @param mixed $current_value Canonical current field value.
	 */
	public function build(
		int $post_id,
		string $mutation_type,
		string $owner,
		$current_value,
		string $post_modified_gmt,
		string $content_hash = ''
	): string {
		$payload = array(
			'post_id'           => $post_id,
			'mutation_type'     => $mutation_type,
			'owner'             => $owner,
			'current'           => $this->canonicalize( $current_value ),
			'post_modified_gmt' => $post_modified_gmt,
			'content_hash'      => $content_hash,
		);

		$json = wp_json_encode( $payload );
		if ( ! is_string( $json ) ) {
			$json = (string) json_encode( $payload );
		}

		return hash( 'sha256', $json );
	}

	public function matches( string $expected, string $actual ): bool {
		if ( $expected === '' || $actual === '' ) {
			return false;
		}
		return hash_equals( $expected, $actual );
	}

	/**
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public function canonicalize( $value ) {
		if ( is_array( $value ) ) {
			$normalized = array();
			foreach ( $value as $item ) {
				if ( is_string( $item ) || is_numeric( $item ) ) {
					$normalized[] = trim( (string) $item );
				}
			}
			$normalized = array_values( array_filter( $normalized, static function ( string $v ): bool {
				return $v !== '';
			} ) );
			sort( $normalized );
			return $normalized;
		}
		if ( is_string( $value ) || is_numeric( $value ) ) {
			return trim( (string) $value );
		}
		return '';
	}

	public function content_hash( string $content ): string {
		return hash( 'sha256', $content );
	}
}
