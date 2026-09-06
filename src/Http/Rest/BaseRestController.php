<?php
declare(strict_types=1);

/**
 * Shared base for rsaip/v1 REST controllers.
 *
 * Phase 2F: permission helpers and response helpers only. No feature routes.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Http\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BaseRestController
 */
abstract class BaseRestController implements RestControllerInterface {

	/**
	 * @inheritDoc
	 */
	abstract public function register_routes( string $namespace ): void;

	/**
	 * Default permission: same capability as the admin UI (manage_options).
	 *
	 * Status and future admin diagnostics must not be public.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 */
	public function permission_manage( \WP_REST_Request $request ): bool {
		unset( $request );
		return function_exists( 'rsaip_capability' )
			? current_user_can( rsaip_capability() )
			: current_user_can( 'manage_options' );
	}

	/**
	 * @param array<string, mixed> $data Response body.
	 */
	protected function success( array $data, int $status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response( $data, $status );
	}

	/**
	 * @param string               $code    Error code.
	 * @param string               $message Human message.
	 * @param int                  $status  HTTP status.
	 * @param array<string, mixed> $data    Extra error data.
	 */
	protected function error( string $code, string $message, int $status = 400, array $data = array() ): \WP_Error {
		return new \WP_Error(
			$code,
			$message,
			array_merge( array( 'status' => $status ), $data )
		);
	}
}
