<?php
declare(strict_types=1);

/**
 * HMAC-signed proposal ticket for Preview → Apply (no DB).
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProposalTicket
 */
final class ProposalTicket {

	/**
	 * @param mixed $normalized Server-normalized new value.
	 */
	public static function value_hash( $normalized ): string {
		$json = self::encode( $normalized );
		return hash( 'sha256', $json );
	}

	/**
	 * @param array<string, mixed> $claims Ticket claims.
	 */
	public static function issue( array $claims, string $key ): string {
		if ( $key === '' ) {
			return '';
		}
		ksort( $claims );
		$json = self::encode( $claims );
		if ( $json === '' ) {
			return '';
		}
		$sig = hash_hmac( 'sha256', $json, $key );
		return self::b64url_encode( $json ) . '.' . $sig;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function verify( string $ticket, string $key ): ?array {
		if ( $key === '' || $ticket === '' ) {
			return null;
		}
		$parts = explode( '.', $ticket, 2 );
		if ( count( $parts ) !== 2 || $parts[0] === '' || $parts[1] === '' ) {
			return null;
		}
		$json = self::b64url_decode( $parts[0] );
		if ( $json === '' ) {
			return null;
		}
		$expected = hash_hmac( 'sha256', $json, $key );
		if ( ! hash_equals( $expected, $parts[1] ) ) {
			return null;
		}
		$claims = json_decode( $json, true );
		return is_array( $claims ) ? $claims : null;
	}

	/**
	 * @param mixed $value Value.
	 */
	private static function encode( $value ): string {
		if ( function_exists( 'wp_json_encode' ) ) {
			$json = wp_json_encode( $value );
			return is_string( $json ) ? $json : '';
		}
		$json = json_encode( $value );
		return is_string( $json ) ? $json : '';
	}

	private static function b64url_encode( string $raw ): string {
		return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
	}

	private static function b64url_decode( string $encoded ): string {
		$padded = strtr( $encoded, '-_', '+/' );
		$pad    = strlen( $padded ) % 4;
		if ( $pad > 0 ) {
			$padded .= str_repeat( '=', 4 - $pad );
		}
		$out = base64_decode( $padded, true );
		return is_string( $out ) ? $out : '';
	}
}
