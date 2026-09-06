<?php
declare(strict_types=1);

/**
 * Azure OpenAI Chat Completions adapter.
 *
 * Endpoint should be the full deployment URL, e.g.
 * https://{resource}.openai.azure.com/openai/deployments/{deployment}/chat/completions?api-version=2024-02-15-preview
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AzureOpenAiHubProvider
 */
final class AzureOpenAiHubProvider extends OpenAiStyleHubProvider {

	/**
	 * @inheritDoc
	 */
	public function id(): string {
		return 'azure_openai';
	}

	/**
	 * Azure uses api-key header instead of Bearer.
	 *
	 * @return array<string, string>
	 */
	protected function auth_headers( string $key ): array {
		$headers = array();
		if ( $key !== '' ) {
			$headers['api-key'] = $key;
		}
		return $headers;
	}

	/**
	 * List deployments when possible; otherwise configured/manual names.
	 *
	 * @return list<string>|\WP_Error
	 */
	public function models() {
		$endpoint = trim( (string) ( $this->config['endpoint'] ?? '' ) );
		$key      = trim( (string) ( $this->config['api_key'] ?? '' ) );
		$model    = trim( (string) ( $this->config['model'] ?? '' ) );
		$manual   = trim( (string) ( $this->config['manual_model'] ?? '' ) );

		$parts = wp_parse_url( $endpoint );
		$host  = is_array( $parts ) ? (string) ( $parts['host'] ?? '' ) : '';
		$query = array();
		if ( is_array( $parts ) && ! empty( $parts['query'] ) ) {
			parse_str( (string) $parts['query'], $query );
		}
		$api_version = isset( $query['api-version'] ) ? (string) $query['api-version'] : '2024-02-15-preview';

		if ( $host !== '' && $key !== '' && rsaip_is_safe_ai_endpoint( 'https://' . $host ) ) {
			$list_url  = 'https://' . $host . '/openai/deployments?api-version=' . rawurlencode( $api_version );
			$cache_key = 'rsaip_ai_models_' . md5( $this->id() . '|' . $list_url );
			$cached    = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}

			$resp = $this->http->get(
				$list_url,
				array(
					'timeout' => (int) ( $this->config['timeout'] ?? 25 ),
					'headers' => array( 'api-key' => $key ),
				)
			);
			if ( ! is_wp_error( $resp ) ) {
				$code = (int) wp_remote_retrieve_response_code( $resp );
				$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
				if ( $code >= 200 && $code < 300 && is_array( $data ) ) {
					$rows = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : ( isset( $data['value'] ) && is_array( $data['value'] ) ? $data['value'] : array() );
					$list = array();
					foreach ( $rows as $row ) {
						if ( ! is_array( $row ) ) {
							continue;
						}
						$dep = (string) ( $row['id'] ?? $row['name'] ?? '' );
						if ( $dep !== '' ) {
							$list[] = $dep;
						}
					}
					$list = array_values( array_unique( $list ) );
					if ( ! empty( $list ) ) {
						set_transient( $cache_key, $list, 15 * MINUTE_IN_SECONDS );
						return $list;
					}
				}
			}
		}

		$list = array_values( array_unique( array_filter( array( $model, $manual ) ) ) );
		return ! empty( $list ) ? $list : array( $this->defaultModel() );
	}
}
