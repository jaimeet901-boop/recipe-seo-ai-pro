<?php
declare(strict_types=1);

/**
 * LmStudioHubProvider — AI Hub adapter.
 *
 * Discovers loaded models via GET /v1/models on the local server.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LmStudioHubProvider
 */
final class LmStudioHubProvider extends OpenAiStyleHubProvider {

	/**
	 * @inheritDoc
	 */
	public function id(): string {
		return 'lmstudio';
	}

	/**
	 * @inheritDoc
	 */
	public function connect( array $config = array() ) {
		if ( ! empty( $config ) ) {
			$this->config = array_merge( $this->config, $config );
		}
		// LM Studio typically needs no API key.
		$endpoint = trim( (string) ( $this->config['endpoint'] ?? '' ) );
		$model    = trim( (string) ( $this->config['model'] ?? '' ) );
		if ( $endpoint === '' ) {
			return new \WP_Error( 'rsaip_ai_config', 'Missing AI endpoint' );
		}
		if ( ! rsaip_is_safe_ai_endpoint( $endpoint ) ) {
			return new \WP_Error( 'rsaip_ai_config', 'Invalid endpoint' );
		}
		if ( $model === '' ) {
			return new \WP_Error( 'rsaip_ai_config', 'Missing AI model' );
		}
		return true;
	}
}
