<?php
declare(strict_types=1);

/**
 * Cohere Chat API v2 adapter.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CohereHubProvider
 */
final class CohereHubProvider extends AbstractHubProvider {

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
		return 'cohere';
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

		$cohere_messages = array();
		foreach ( $messages as $msg ) {
			$role = (string) ( $msg['role'] ?? 'user' );
			if ( $role === 'system' ) {
				$role = 'system';
			} elseif ( $role === 'assistant' ) {
				$role = 'assistant';
			} else {
				$role = 'user';
			}
			$cohere_messages[] = array(
				'role'    => $role,
				'content' => (string) ( $msg['content'] ?? '' ),
			);
		}

		$body = array(
			'model'       => $model,
			'messages'    => $cohere_messages,
			'temperature' => $this->resolve_temperature( $options ),
			'max_tokens'  => $this->resolve_max_tokens( $options ),
			'p'           => max( 0, min( 1, (float) ( $this->config['top_p'] ?? 1 ) ) ),
		);

		$started = microtime( true );
		$resp    = $this->http->post(
			$endpoint,
			array(
				'timeout' => (int) ( $this->config['timeout'] ?? 25 ),
				'headers' => array_merge(
					array(
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $key,
					),
					$this->custom_headers()
				),
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
			$msg = is_array( $data ) && isset( $data['message'] )
				? (string) $data['message']
				: 'Cohere request failed (HTTP ' . $code . ')';
			$this->mark_health( 'error', $latency, $msg );
			return new \WP_Error( 'rsaip_ai_http', $msg, array( 'status' => $code ) );
		}

		$text = '';
		if ( isset( $data['message']['content'] ) && is_array( $data['message']['content'] ) ) {
			foreach ( $data['message']['content'] as $part ) {
				if ( is_array( $part ) && ( $part['type'] ?? '' ) === 'text' ) {
					$text .= (string) ( $part['text'] ?? '' );
				}
			}
		}
		if ( $text === '' && isset( $data['text'] ) && is_string( $data['text'] ) ) {
			$text = $data['text'];
		}

		if ( trim( $text ) === '' ) {
			$this->mark_health( 'error', $latency, 'AI response parse failed' );
			return new \WP_Error( 'rsaip_ai_parse', 'AI response parse failed' );
		}

		$usage = isset( $data['usage'] ) && is_array( $data['usage'] ) ? $data['usage'] : array();
		$billed = isset( $usage['billed_units'] ) && is_array( $usage['billed_units'] ) ? $usage['billed_units'] : array();
		$prompt = (int) ( $billed['input_tokens'] ?? 0 );
		$completion = (int) ( $billed['output_tokens'] ?? 0 );
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

		$cache_key = 'rsaip_ai_models_cohere';
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$resp = $this->http->get(
			'https://api.cohere.ai/v1/models',
			array(
				'timeout' => (int) ( $this->config['timeout'] ?? 25 ),
				'headers' => array( 'Authorization' => 'Bearer ' . $key ),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return array( $this->defaultModel(), 'command-r', 'command-r-plus', 'command-light' );
		}

		$list = array();
		$rows = isset( $data['models'] ) && is_array( $data['models'] ) ? $data['models'] : array();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) && ! empty( $row['name'] ) ) {
				$list[] = (string) $row['name'];
			}
		}
		if ( empty( $list ) ) {
			$list = array( $this->defaultModel(), 'command-r', 'command-r-plus' );
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
