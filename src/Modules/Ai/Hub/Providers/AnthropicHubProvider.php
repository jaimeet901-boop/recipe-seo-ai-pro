<?php
declare(strict_types=1);

/**
 * Anthropic Claude Messages API adapter.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AnthropicHubProvider
 */
final class AnthropicHubProvider extends AbstractHubProvider {

	/** @var array{prompt: int, completion: int, total: int} */
	public array $last_usage = array(
		'prompt'     => 0,
		'completion' => 0,
		'total'      => 0,
	);

	public int $last_http_status = 0;

	public string $last_raw_excerpt = '';

	/**
	 * @inheritDoc
	 */
	public function id(): string {
		return 'anthropic';
	}

	/**
	 * @inheritDoc
	 */
	public function chat( array $messages, array $options = array() ) {
		$ready = $this->connect( $this->config );
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		$endpoint = trim( (string) $this->config['endpoint'] );
		$model    = trim( (string) ( $options['model'] ?? $this->config['model'] ) );
		$key      = trim( (string) $this->config['api_key'] );
		$system   = '';
		$anth_messages = array();

		foreach ( $messages as $msg ) {
			$role = (string) ( $msg['role'] ?? 'user' );
			$content = (string) ( $msg['content'] ?? '' );
			if ( $role === 'system' ) {
				$system .= ( $system === '' ? '' : "\n" ) . $content;
				continue;
			}
			if ( $role !== 'assistant' ) {
				$role = 'user';
			}
			$anth_messages[] = array(
				'role'    => $role,
				'content' => $content,
			);
		}

		$body = array(
			'model'      => $model,
			'max_tokens' => $this->resolve_max_tokens( $options ),
			'messages'   => $anth_messages,
		);
		if ( $system !== '' ) {
			$body['system'] = $system;
		}
		$temp = $this->resolve_temperature( $options );
		if ( $temp > 0 ) {
			$body['temperature'] = $temp;
		}

		$headers = array_merge(
			array(
				'Content-Type'      => 'application/json',
				'x-api-key'         => $key,
				'anthropic-version' => '2023-06-01',
			),
			$this->custom_headers()
		);

		$started = microtime( true );
		$resp    = $this->http->post(
			$endpoint,
			array(
				'timeout' => (int) ( $this->config['timeout'] ?? 25 ),
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);
		$latency = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $resp ) ) {
			$this->mark_health( 'error', $latency, $resp->get_error_message() );
			return $resp;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$raw  = (string) wp_remote_retrieve_body( $resp );
		$this->last_http_status = $code;
		$this->last_raw_excerpt = substr( $raw, 0, 500 );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			$msg = is_array( $data ) && isset( $data['error']['message'] )
				? (string) $data['error']['message']
				: 'Anthropic request failed (HTTP ' . $code . ')';
			$this->mark_health( 'error', $latency, $msg );
			return new \WP_Error( 'rsaip_ai_http', $msg, array( 'status' => $code ) );
		}

		$text = '';
		$content = $data['content'] ?? array();
		if ( is_array( $content ) ) {
			foreach ( $content as $block ) {
				if ( is_array( $block ) && ( $block['type'] ?? '' ) === 'text' ) {
					$text .= (string) ( $block['text'] ?? '' );
				}
			}
		}

		if ( trim( $text ) === '' ) {
			$this->mark_health( 'error', $latency, 'AI response parse failed' );
			return new \WP_Error( 'rsaip_ai_parse', 'AI response parse failed' );
		}

		$usage = isset( $data['usage'] ) && is_array( $data['usage'] ) ? $data['usage'] : array();
		$prompt = (int) ( $usage['input_tokens'] ?? 0 );
		$completion = (int) ( $usage['output_tokens'] ?? 0 );
		$this->last_usage = array(
			'prompt'     => $prompt,
			'completion' => $completion,
			'total'      => $prompt + $completion,
		);
		$this->mark_health( 'connected', $latency, 'ok' );
		return $text;
	}

	/**
	 * @inheritDoc
	 */
	public function models() {
		$key = trim( (string) $this->config['api_key'] );
		if ( $key === '' ) {
			return new \WP_Error( 'rsaip_ai_config', 'Missing AI API key' );
		}

		$cache_key = 'rsaip_ai_models_anthropic';
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$url  = 'https://api.anthropic.com/v1/models';
		$resp = $this->http->get(
			$url,
			array(
				'timeout' => (int) ( $this->config['timeout'] ?? 25 ),
				'headers' => array(
					'x-api-key'         => $key,
					'anthropic-version' => '2023-06-01',
				),
			)
		);

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return new \WP_Error( 'rsaip_ai_http', 'Failed to list Anthropic models', array( 'status' => $code ) );
		}

		$list = array();
		$rows = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) && ! empty( $row['id'] ) ) {
				$list[] = (string) $row['id'];
			}
		}
		if ( empty( $list ) ) {
			$list = array( $this->defaultModel(), 'claude-3-5-sonnet-latest', 'claude-3-opus-latest' );
		}
		set_transient( $cache_key, $list, 15 * MINUTE_IN_SECONDS );
		return $list;
	}

	/**
	 * @inheritDoc
	 */
	public function testConnection() {
		$started = microtime( true );
		$models  = $this->models();
		$ping    = $this->chat(
			array( array( 'role' => 'user', 'content' => 'Reply with the single word: pong' ) ),
			array( 'max_tokens' => 16 )
		);
		$latency = (int) round( ( microtime( true ) - $started ) * 1000 );
		$ok      = is_string( $ping );

		return array(
			'connected'         => $ok,
			'latency_ms'        => $latency,
			'detected_model'    => (string) ( $this->config['model'] ?? '' ),
			'available_models'  => is_array( $models ) ? $models : array(),
			'remaining_quota'   => null,
			'http_status'       => $this->last_http_status,
			'provider_response' => $ok ? substr( $ping, 0, 200 ) : $this->last_raw_excerpt,
			'error'             => $ok ? '' : ( is_wp_error( $ping ) ? $ping->get_error_message() : 'Connection failed' ),
		);
	}
}
