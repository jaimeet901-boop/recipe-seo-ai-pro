<?php
declare(strict_types=1);

/**
 * Encrypt / decrypt plugin secrets at rest (API keys).
 *
 * Uses OpenSSL AES-256-CBC with a key derived from WordPress salts.
 * Ciphertext is stored as: rsaip_enc:<base64(iv.ciphertext)>
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Support\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SecretGuard
 */
final class SecretGuard {

	public const PREFIX = 'rsaip_enc:';

	/**
	 * Encrypt a plaintext secret. Empty input returns empty string.
	 */
	public static function encrypt( string $plaintext ): string {
		$plaintext = trim( $plaintext );
		if ( $plaintext === '' ) {
			return '';
		}
		if ( self::is_encrypted( $plaintext ) ) {
			return $plaintext;
		}
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return $plaintext;
		}

		$key = self::key();
		$iv  = random_bytes( 16 );
		$raw = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		if ( ! is_string( $raw ) || $raw === '' ) {
			return $plaintext;
		}

		return self::PREFIX . base64_encode( $iv . $raw );
	}

	/**
	 * Decrypt a secret. Plaintext passthrough for legacy unencrypted values.
	 */
	public static function decrypt( string $value ): string {
		$value = trim( $value );
		if ( $value === '' || ! self::is_encrypted( $value ) ) {
			return $value;
		}
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$payload = base64_decode( substr( $value, strlen( self::PREFIX ) ), true );
		if ( ! is_string( $payload ) || strlen( $payload ) < 17 ) {
			return '';
		}

		$iv  = substr( $payload, 0, 16 );
		$raw = substr( $payload, 16 );
		$out = openssl_decrypt( $raw, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv );

		return is_string( $out ) ? $out : '';
	}

	/**
	 * Whether the value looks like an encrypted payload.
	 */
	public static function is_encrypted( string $value ): bool {
		return strpos( $value, self::PREFIX ) === 0;
	}

	/**
	 * Mask for UI — never echo real keys.
	 */
	public static function mask(): string {
		return '••••••••';
	}

	/**
	 * @return string Binary 32-byte key.
	 */
	private static function key(): string {
		$material = '';
		if ( function_exists( 'wp_salt' ) ) {
			$material = (string) wp_salt( 'auth' ) . (string) wp_salt( 'secure_auth' );
		}
		if ( $material === '' ) {
			$material = 'rsaip-fallback-secret-key';
		}
		return hash( 'sha256', $material, true );
	}
}
