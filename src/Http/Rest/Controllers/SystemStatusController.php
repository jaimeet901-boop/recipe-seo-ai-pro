<?php
declare(strict_types=1);

/**
 * Read-only system status endpoint (Phase 2F).
 *
 * GET /wp-json/rsaip/v1/system/status
 *
 * Returns non-sensitive diagnostics only — never API keys, PEM, tokens, or secrets.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Http\Rest\Controllers;

use RecipeSeoAiPro\Http\Rest\BaseRestController;
use RecipeSeoAiPro\Http\Rest\RestBootstrap;
use RecipeSeoAiPro\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SystemStatusController
 */
final class SystemStatusController extends BaseRestController {

	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * @inheritDoc
	 */
	public function register_routes( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/system/status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'permission_manage' ),
			)
		);
	}

	/**
	 * Build the status payload.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function get_status( \WP_REST_Request $request ): \WP_REST_Response {
		unset( $request );

		$settings = function_exists( 'rsaip_get_settings' ) ? rsaip_get_settings() : array();

		$ai_provider = isset( $settings['ai_provider'] ) ? (string) $settings['ai_provider'] : '';
		$ai_provider = sanitize_key( $ai_provider );

		$endpoint = isset( $settings['ai_endpoint'] ) ? trim( (string) $settings['ai_endpoint'] ) : '';
		$model    = isset( $settings['ai_model'] ) ? trim( (string) $settings['ai_model'] ) : '';
		$has_key  = function_exists( 'rsaip_setting_has_secret' )
			? rsaip_setting_has_secret( $settings, 'ai_api_key' )
			: ( isset( $settings['ai_api_key'] ) && trim( (string) $settings['ai_api_key'] ) !== '' );

		// Configured = provider enabled + non-empty endpoint/model + key present.
		// Does not reveal endpoint, model name, or key material.
		$ai_configured = ( $ai_provider === 'openai_compatible' )
			&& $endpoint !== ''
			&& $model !== ''
			&& $has_key;

		$db_version = get_option( 'rsaip_db_version', '' );
		if ( ! is_string( $db_version ) ) {
			$db_version = '';
		}

		$db_schema_target = '';
		if ( class_exists( 'RSAIP_DB' ) && method_exists( 'RSAIP_DB', 'schema_version' ) ) {
			$db_schema_target = (string) \RSAIP_DB::schema_version();
		}

		$active_modules = array_keys( $this->plugin->modules() );
		sort( $active_modules );

		global $wp_version;

		return $this->success(
			array(
				'plugin_version'       => defined( 'RSAIP_VERSION' ) ? (string) RSAIP_VERSION : '',
				'db_version'           => $db_version,
				'db_schema_target'     => $db_schema_target,
				'architecture_version' => RestBootstrap::ARCHITECTURE_VERSION,
				'php_version'          => PHP_VERSION,
				'wordpress_version'    => is_string( $wp_version ) ? $wp_version : '',
				'active_modules'       => array_values( $active_modules ),
				'ai_provider'          => $ai_provider,
				'ai_configured'        => $ai_configured,
				'rest_version'         => RestBootstrap::REST_VERSION,
				'rest_namespace'       => RestBootstrap::NAMESPACE,
			)
		);
	}
}
