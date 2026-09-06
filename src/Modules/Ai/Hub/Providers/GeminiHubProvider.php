<?php
declare(strict_types=1);

/**
 * Google Gemini generateContent API adapter.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GeminiHubProvider
 */
final class GeminiHubProvider extends AbstractHubProvider {

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
		return 'gemini';
	}

	/**
	 * @inheritDoc
	 */
	public function chat( array $messages, array $options = array() ) {
		$ready = $this->connect( $this->config );
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		$key   = trim( (string) $this->config['api_key'] );
		$model = trim( (string) ( $options['model'] ?? $this->config['model'] ) );
		$base  = rtrim( (string) $this->config['endpoint'], '/' );
		if ( strpos( $base, 'generativelanguage.googleapis.com' ) === false ) {
			$base = 'https://generativelanguage.googleapis.com/v1beta';
		}

		$url = $base . '/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $key );

		$system = '';
		$contents = array();
		foreach ( $messages as $msg ) {
			$role = (string) ( $msg['role'] ?? 'user' );
			$text = (string) ( $msg['content'] ?? '' );
			if ( $role === 'system' ) {
				$system .= ( $system === '' ? '' : "\n" ) . $text;
				continue;
			}
			$gemini_role = ( $role === 'assistant' ) ? 'model' : 'user';
			$contents[]  = array(
				'role'  => $gemini_role,
				'parts' => array( array( 'text' => $text ) ),
			);
		}

		$body = array(
			'contents'         => $contents,
			'generationConfig' => array(
				'temperature'     => $this->resolve_temperature( $options ),
				'maxOutputTokens' => $this->resolve_max_tokens( $options ),
				'topP'            => max( 0, min( 1, (float) ( $this->config['top_p'] ?? 1 ) ) ),
			),
		);
		if ( $system !== '' ) {
			$body['systemInstruction'] = array(
				'parts' => array( array( 'text' => $system ) ),
			);
		}

		$started = microtime( true );
		$resp    = $this->http->post(
			$url,
			array(
				'timeout' => (int) ( $this->config['timeout'] ?? 25 ),
				'headers' => array_merge(
					array( 'Content-Type' => 'application/json' ),
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
			$msg = is_array( $data ) && isset( $data['error']['message'] )
				? (string) $data['error']['message']
				: 'Gemini request failed (HTTP ' . $code . ')';
			$this->mark_health( 'error', $latency, $msg );
			return new \WP_Error( 'rsaip_ai_http', $msg, array( 'status' => $code ) );
		}

		$text = (string) ( $data['candidates'][0]['content']['parts'][0]['text'] ?? '' );
		if ( trim( $text ) === '' ) {
			$this->mark_health( 'error', $latency, 'AI response parse failed' );
			return new \WP_Error( 'rsaip_ai_parse', 'AI response parse failed' );
		}

		$meta = isset( $data['usageMetadata'] ) && is_array( $data['usageMetadata'] ) ? $data['usageMetadata'] : array();
		$prompt = (int) ( $meta['promptTokenCount'] ?? 0 );
		$completion = (int) ( $meta['candidatesTokenCount'] ?? 0 );
		$this->last_usage = array(
			'prompt'     => $prompt,
			'completion' => $completion,
			'total'      => (int) ( $meta['totalTokenCount'] ?? ( $prompt + $completion ) ),
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

		$cache_key = 'rsaip_ai_models_gemini';
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$url  = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . rawurlencode( $key );
		$resp = $this->http->get( $url, array( 'timeout' => (int) ( $this->config['timeout'] ?? 25 ) ) );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return new \WP_Error( 'rsaip_ai_http', 'Failed to list Gemini models', array( 'status' => $code ) );
		}

		$list = array();
		$rows = isset( $data['models'] ) && is_array( $data['models'] ) ? $data['models'] : array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['name'] ) ) {
				continue;
			}
			$name = (string) $row['name'];
			$name = preg_replace( '#^models/#', '', $name );
			$methods = isset( $row['supportedGenerationMethods'] ) && is_array( $row['supportedGenerationMethods'] )
				? $row['supportedGenerationMethods']
				: array();
			if ( in_array( 'generateContent', $methods, true ) || empty( $methods ) ) {
				$list[] = $name;
			}
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
