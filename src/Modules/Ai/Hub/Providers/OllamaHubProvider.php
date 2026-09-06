<?php
declare(strict_types=1);

/**
 * OllamaHubProvider — AI Hub adapter.
 *
 * Chat via OpenAI-compatible /v1/chat/completions.
 * Model discovery via native GET /api/tags.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OllamaHubProvider
 */
final class OllamaHubProvider extends OpenAiStyleHubProvider {

	/**
	 * @inheritDoc
	 */
	public function id(): string {
		return 'ollama';
	}

	/**
	 * List installed Ollama models from /api/tags.
	 *
	 * @return list<string>|\WP_Error
	 */
	public function models() {
		$endpoint = trim( (string) ( $this->config['endpoint'] ?? $this->defaultEndpoint() ) );
		$parts    = wp_parse_url( $endpoint );
		$scheme   = is_array( $parts ) ? (string) ( $parts['scheme'] ?? 'http' ) : 'http';
		$host     = is_array( $parts ) ? (string) ( $parts['host'] ?? 'localhost' ) : 'localhost';
		$port     = is_array( $parts ) && ! empty( $parts['port'] ) ? (int) $parts['port'] : 11434;
		$tags_url = $scheme . '://' . $host . ( $port ? ':' . $port : '' ) . '/api/tags';

		$cache_key = 'rsaip_ai_models_' . md5( $this->id() . '|' . $tags_url );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		if ( ! rsaip_is_safe_ai_endpoint( $tags_url ) ) {
			return new \WP_Error( 'rsaip_ai_config', 'Invalid endpoint' );
		}

		$resp = $this->http->get(
			$tags_url,
			array( 'timeout' => (int) ( $this->config['timeout'] ?? 25 ) )
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return new \WP_Error( 'rsaip_ai_http', 'Failed to list Ollama models (HTTP ' . $code . ')', array( 'status' => $code ) );
		}

		$list = array();
		$rows = isset( $data['models'] ) && is_array( $data['models'] ) ? $data['models'] : array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = (string) ( $row['name'] ?? $row['model'] ?? '' );
			if ( $name !== '' ) {
				$list[] = $name;
			}
		}
		$list = array_values( array_unique( $list ) );
		set_transient( $cache_key, $list, 15 * MINUTE_IN_SECONDS );
		return $list;
	}
}
