<?php
declare(strict_types=1);

/**
 * Contract for WP REST controllers under rsaip/v1.
 *
 * Phase 2F: SystemStatusController is the only registered controller.
 * Feature endpoints require separate approval.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Http\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface RestControllerInterface
 */
interface RestControllerInterface {

	/**
	 * Register routes for this controller under the given namespace.
	 *
	 * @param string $namespace REST namespace (e.g. rsaip/v1).
	 */
	public function register_routes( string $namespace ): void;
}
