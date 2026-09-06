<?php
declare(strict_types=1);

/**
 * Contract for outbound HTTP requests.
 *
 * Phase 2E: interface + WpHttpClient bound in DI. Legacy modules still call
 * wp_remote_* directly until a later approved migration.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Contracts;

use RecipeSeoAiPro\Support\Http\HttpResponse;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface HttpClientInterface
 */
interface HttpClientInterface {

	/**
	 * Perform an HTTP request.
	 *
	 * @param string               $method HTTP method (GET, POST, HEAD, …).
	 * @param string               $url    Absolute URL.
	 * @param array<string, mixed> $args   wp_remote_request-compatible args
	 *                                     (timeout, headers, body, redirection, …).
	 * @return HttpResponse|\WP_Error
	 */
	public function request( string $method, string $url, array $args = array() );

	/**
	 * @param array<string, mixed> $args Request args.
	 * @return HttpResponse|\WP_Error
	 */
	public function get( string $url, array $args = array() );

	/**
	 * @param array<string, mixed> $args Request args.
	 * @return HttpResponse|\WP_Error
	 */
	public function post( string $url, array $args = array() );

	/**
	 * @param array<string, mixed> $args Request args.
	 * @return HttpResponse|\WP_Error
	 */
	public function head( string $url, array $args = array() );
}
