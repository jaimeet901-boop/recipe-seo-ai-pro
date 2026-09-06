<?php
declare(strict_types=1);

/**
 * Shared HTTP client for AI Hub providers.
 *
 * Reuses WordPress remote APIs, enforces AI-endpoint SSRF rules (including
 * localhost for Ollama / LM Studio), and never logs Authorization headers.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub\Http;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AiHttpClient
 */
final class AiHttpClient {

	/**
	 * @param array<string, mixed> $args wp_remote_* args.
	 * @return array<string, mixed>|\WP_Error WP response array or error.
	 */
	public function request( string $method, string $url, array $args = array() ) {
		$url = trim( $url );
		if ( $url === '' || ! rsaip_is_safe_ai_endpoint( $url ) ) {
			return new \WP_Error( 'rsaip_ai_config', 'Invalid AI endpoint' );
		}

		$args['method']      = strtoupper( $method );
		$args['redirection'] = isset( $args['redirection'] ) ? (int) $args['redirection'] : 0;
		if ( ! isset( $args['timeout'] ) ) {
			$args['timeout'] = 25;
		}

		$response = function_exists( 'wp_safe_remote_request' )
			? wp_safe_remote_request( $url, $args )
			: wp_remote_request( $url, $args );

		// wp_safe_remote_* blocks localhost; retry with wp_remote_* only for allowed local AI hosts.
		if ( is_wp_error( $response ) && self::is_local_ai_url( $url ) ) {
			$response = wp_remote_request( $url, $args );
		}

		return $response;
	}

	/**
	 * @param array<string, mixed> $args Args.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get( string $url, array $args = array() ) {
		return $this->request( 'GET', $url, $args );
	}

	/**
	 * @param array<string, mixed> $args Args.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function post( string $url, array $args = array() ) {
		return $this->request( 'POST', $url, $args );
	}

	/**
	 * Whether URL is a permitted local AI endpoint.
	 */
	public static function is_local_ai_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return false;
		}
		$host = strtolower( (string) ( $parts['host'] ?? '' ) );
		return in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true );
	}
}
