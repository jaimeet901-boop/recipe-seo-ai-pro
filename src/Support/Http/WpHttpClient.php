<?php
declare(strict_types=1);

/**
 * WordPress HTTP API implementation of HttpClientInterface.
 *
 * Thin wrapper around wp_remote_request / wp_remote_*. Not used by legacy
 * modules in Phase 2E — available via DI for future approved migrations.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Support\Http;

use RecipeSeoAiPro\Contracts\HttpClientInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WpHttpClient
 */
final class WpHttpClient implements HttpClientInterface {

	/**
	 * @inheritDoc
	 */
	public function request( string $method, string $url, array $args = array() ) {
		$args['method'] = strtoupper( $method );

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! is_array( $response ) ) {
			return new \WP_Error( 'rsaip_http_invalid', 'Invalid HTTP response' );
		}

		return HttpResponse::from_wp_response( $response );
	}

	/**
	 * @inheritDoc
	 */
	public function get( string $url, array $args = array() ) {
		return $this->request( 'GET', $url, $args );
	}

	/**
	 * @inheritDoc
	 */
	public function post( string $url, array $args = array() ) {
		return $this->request( 'POST', $url, $args );
	}

	/**
	 * @inheritDoc
	 */
	public function head( string $url, array $args = array() ) {
		return $this->request( 'HEAD', $url, $args );
	}
}
