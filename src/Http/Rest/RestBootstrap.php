<?php
declare(strict_types=1);

/**
 * REST API foundation bootstrap (Phase 2F).
 *
 * Registers namespace rsaip/v1 and read-only foundation routes only.
 * Does not touch admin-ajax.php or legacy RSAIP_AJAX.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Http\Rest;

use RecipeSeoAiPro\Http\Rest\Controllers\SystemStatusController;
use RecipeSeoAiPro\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RestBootstrap
 */
final class RestBootstrap {

	public const NAMESPACE = 'rsaip/v1';

	/** REST API contract version exposed in /system/status. */
	public const REST_VERSION = '1';

	/** Modern architecture phase marker (not the plugin marketing version). */
	public const ARCHITECTURE_VERSION = '5.2';

	private Plugin $plugin;

	private bool $hooked = false;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Hook into rest_api_init. Idempotent.
	 */
	public function register(): void {
		if ( $this->hooked ) {
			return;
		}
		$this->hooked = true;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register foundation controllers for rsaip/v1.
	 *
	 * Feature endpoints must not be added here without explicit approval.
	 */
	public function register_routes(): void {
		$controllers = $this->controllers();
		foreach ( $controllers as $controller ) {
			$controller->register_routes( self::NAMESPACE );
		}
	}

	/**
	 * @return list<RestControllerInterface>
	 */
	private function controllers(): array {
		return array(
			new SystemStatusController( $this->plugin ),
		);
	}
}
