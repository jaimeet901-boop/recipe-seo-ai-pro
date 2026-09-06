<?php
declare(strict_types=1);

/**
 * OpenAI Chat Completions–compatible Hub adapter.
 *
 * Used by OpenAI, DeepSeek, OpenRouter, Groq, xAI, Together, Mistral,
 * Ollama, LM Studio, and Custom providers.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OpenAiStyleHubProvider
 */
abstract class OpenAiStyleHubProvider extends AbstractHubProvider {

	/**
	 * Last usage from the most recent request (for logging).
	 *
	 * @var array{prompt: int, completion: int, total: int}
	 */
	public array $last_usage = array(
		'prompt'     => 0,
		'completion' => 0,
		'total'      => 0,
	);

	/** @var int */
	public int $last_http_status = 0;

	/** @var string */
	public string $last_raw_excerpt = '';

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
		$timeout  = (int) ( $this->config['timeout'] ?? 25 );
		$stream   = ! empty( $options['stream'] ) || ! empty( $this->config['streaming'] );

		if ( $stream ) {
			return $this->stream( $messages, $options );
		}

		$body = array(
			'model'       => $model,
			'temperature' => $this->resolve_temperature( $options ),
			'top_p'       => max( 0, min( 1, (float) ( $this->config['top_p'] ?? 1 ) ) ),
			'max_tokens'  => $this->resolve_max_tokens( $options ),
			'messages'    => $messages,
		);

		// Optional structured-output hint. Only when caller opts in AND catalog says safe.
		// Existing callers omit response_format → body unchanged. DeepSeek etc. ignored.
		if ( isset( $options['response_format'] ) && \RecipeSeoAiPro\Modules\Ai\Hub\ProviderCatalog::supports_json_response_format( $this->id() ) ) {
			$format = $this->normalize_response_format( $options['response_format'] );
			if ( is_array( $format ) ) {
				$body['response_format'] = $format;
			}
		}

		$headers = array_merge(
			array(
				'Content-Type' => 'application/json',
			),
			$this->auth_headers( $key ),
			$this->custom_headers()
		);

		$retries = max( 0, (int) ( $this->config['retry_count'] ?? 1 ) );
		$attempt = 0;
		$resp    = null;
		$started = microtime( true );

		do {
			$resp = $this->http->post(
				$endpoint,
				array(
					'timeout' => $timeout,
					'headers' => $headers,
					'body'    => wp_json_encode( $body ),
				)
			);
			$attempt++;
			if ( ! is_wp_error( $resp ) ) {
				$code = (int) wp_remote_retrieve_response_code( $resp );
				if ( $code >= 200 && $code < 300 ) {
					break;
				}
				// Retry transient errors.
				if ( ! in_array( $code, array( 408, 429, 500, 502, 503, 504 ), true ) ) {
					break;
				}
			}
		} while ( $attempt <= $retries );

		$latency = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $resp ) ) {
			$this->mark_health( 'error', $latency, $resp->get_error_message() );
			return $resp;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$raw  = (string) wp_remote_retrieve_body( $resp );
		$this->last_http_status  = $code;
		$this->last_raw_excerpt  = substr( $raw, 0, 500 );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			$msg = $this->extract_error_message( $data, $code );
			$this->mark_health( 'error', $latency, $msg );
			return new \WP_Error( 'rsaip_ai_http', $msg, array( 'status' => $code ) );
		}

		$content = $data['choices'][0]['message']['content'] ?? '';
		if ( is_array( $content ) ) {
			// Some providers return content parts.
			$parts = array();
			foreach ( $content as $part ) {
				if ( is_array( $part ) && isset( $part['text'] ) ) {
					$parts[] = (string) $part['text'];
				} elseif ( is_string( $part ) ) {
					$parts[] = $part;
				}
			}
			$content = implode( '', $parts );
		}

		if ( ! is_string( $content ) || trim( $content ) === '' ) {
			$content = $data['choices'][0]['text'] ?? '';
		}

		if ( ! is_string( $content ) || trim( $content ) === '' ) {
			$this->mark_health( 'error', $latency, 'AI response parse failed' );
			return new \WP_Error( 'rsaip_ai_parse', 'AI response parse failed' );
		}

		$this->last_usage = $this->extract_usage( $data );
		$this->mark_health( 'connected', $latency, 'ok' );
		return $content;
	}

	/**
	 * @inheritDoc
	 */
	public function stream( array $messages, array $options = array() ) {
		$ready = $this->connect( $this->config );
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		// WordPress HTTP API does not expose true SSE streaming reliably.
		// Perform a streamed request flag when the provider accepts it, then
		// fall back to assembling a normal completion if parsing fails.
		$endpoint = trim( (string) $this->config['endpoint'] );
		$model    = trim( (string) ( $options['model'] ?? $this->config['model'] ) );
		$key      = trim( (string) $this->config['api_key'] );
		$timeout  = (int) ( $this->config['timeout'] ?? 25 );

		$body = array(
			'model'       => $model,
			'temperature' => $this->resolve_temperature( $options ),
			'max_tokens'  => $this->resolve_max_tokens( $options ),
			'messages'    => $messages,
			'stream'      => true,
		);

		$headers = array_merge(
			array( 'Content-Type' => 'application/json', 'Accept' => 'text/event-stream' ),
			$this->auth_headers( $key ),
			$this->custom_headers()
		);

		$started = microtime( true );
		$resp    = $this->http->post(
			$endpoint,
			array(
				'timeout' => $timeout,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);
		$latency = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $resp ) ) {
			// Graceful fallback to non-streaming.
			$options['stream'] = false;
			$this->config['streaming'] = false;
			return $this->chat( $messages, $options );
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$raw  = (string) wp_remote_retrieve_body( $resp );
		$this->last_http_status = $code;

		if ( $code < 200 || $code >= 300 ) {
			$options['stream'] = false;
			$this->config['streaming'] = false;
			return $this->chat( $messages, $options );
		}

		$assembled = $this->parse_sse_content( $raw );
		if ( $assembled === '' ) {
			// Maybe provider ignored stream and returned JSON.
			$data = json_decode( $raw, true );
			if ( is_array( $data ) ) {
				$content = $data['choices'][0]['message']['content'] ?? '';
				if ( is_string( $content ) && trim( $content ) !== '' ) {
					$assembled = $content;
					$this->last_usage = $this->extract_usage( $data );
				}
			}
		}

		if ( $assembled === '' ) {
			$options['stream'] = false;
			$this->config['streaming'] = false;
			return $this->chat( $messages, $options );
		}

		if ( isset( $options['on_chunk'] ) && is_callable( $options['on_chunk'] ) ) {
			call_user_func( $options['on_chunk'], $assembled );
		}

		$this->mark_health( 'connected', $latency, 'ok' );
		return $assembled;
	}

	/**
	 * @inheritDoc
	 */
	public function models() {
		$ready = $this->connect( $this->config );
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		$models_url = $this->models_endpoint();
		$key        = trim( (string) $this->config['api_key'] );
		$timeout    = (int) ( $this->config['timeout'] ?? 25 );

		$cache_key = 'rsaip_ai_models_' . md5( $this->id() . '|' . $models_url );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$resp = $this->http->get(
			$models_url,
			array(
				'timeout' => $timeout,
				'headers' => array_merge( $this->auth_headers( $key ), $this->custom_headers() ),
			)
		);

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$raw  = (string) wp_remote_retrieve_body( $resp );
		$data = json_decode( $raw, true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return new \WP_Error( 'rsaip_ai_http', $this->extract_error_message( $data, $code ), array( 'status' => $code ) );
		}

		$list = array();
		$rows = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) && ! empty( $row['id'] ) ) {
				$list[] = (string) $row['id'];
			}
		}
		$list = array_values( array_unique( array_filter( $list ) ) );
		set_transient( $cache_key, $list, 15 * MINUTE_IN_SECONDS );
		return $list;
	}

	/**
	 * @inheritDoc
	 */
	public function testConnection() {
		$ready = $this->connect( $this->config );
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		$started = microtime( true );
		$models  = $this->models();
		$model_list = is_array( $models ) ? $models : array();

		$ping = $this->chat(
			array(
				array(
					'role'    => 'user',
					'content' => 'Reply with the single word: pong',
				),
			),
			array( 'max_tokens' => 16 )
		);
		$latency = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $ping ) ) {
			$this->mark_health( 'error', $latency, $ping->get_error_message() );
			return array(
				'connected'        => false,
				'latency_ms'       => $latency,
				'detected_model'   => (string) ( $this->config['model'] ?? '' ),
				'available_models' => $model_list,
				'remaining_quota'  => null,
				'http_status'      => $this->last_http_status,
				'provider_response'=> $this->last_raw_excerpt,
				'error'            => $ping->get_error_message(),
			);
		}

		$this->mark_health( 'connected', $latency, 'ok' );
		return array(
			'connected'         => true,
			'latency_ms'        => $latency,
			'detected_model'    => (string) ( $this->config['model'] ?? '' ),
			'available_models'  => $model_list,
			'remaining_quota'   => null,
			'http_status'       => $this->last_http_status,
			'provider_response' => is_string( $ping ) ? substr( $ping, 0, 200 ) : '',
			'error'             => '',
		);
	}

	/**
	 * @inheritDoc
	 */
	public function embeddings( string $input, array $options = array() ) {
		$ready = $this->connect( $this->config );
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		$url = $this->embeddings_endpoint();
		if ( $url === '' ) {
			return new \WP_Error( 'rsaip_ai_unsupported', 'Embeddings endpoint unavailable' );
		}

		$key   = trim( (string) $this->config['api_key'] );
		$model = (string) ( $options['model'] ?? 'text-embedding-3-small' );
		$body  = array(
			'model' => $model,
			'input' => $input,
		);

		$resp = $this->http->post(
			$url,
			array(
				'timeout' => (int) ( $this->config['timeout'] ?? 25 ),
				'headers' => array_merge(
					array( 'Content-Type' => 'application/json' ),
					$this->auth_headers( $key ),
					$this->custom_headers()
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return new \WP_Error( 'rsaip_ai_http', $this->extract_error_message( $data, $code ), array( 'status' => $code ) );
		}

		$vector = $data['data'][0]['embedding'] ?? null;
		if ( ! is_array( $vector ) ) {
			return new \WP_Error( 'rsaip_ai_parse', 'Embeddings parse failed' );
		}

		return array_map( 'floatval', $vector );
	}

	/**
	 * @return array<string, string>
	 */
	protected function auth_headers( string $key ): array {
		$headers = array();
		if ( $key !== '' ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}
		$org = trim( (string) ( $this->config['organization'] ?? '' ) );
		if ( $org !== '' ) {
			$headers['OpenAI-Organization'] = $org;
		}
		return $headers;
	}

	protected function models_endpoint(): string {
		$endpoint = rtrim( (string) $this->config['endpoint'], '/' );
		// .../v1/chat/completions → .../v1/models
		if ( preg_match( '#/chat/completions/?$#', $endpoint ) ) {
			return (string) preg_replace( '#/chat/completions/?$#', '/models', $endpoint );
		}
		if ( preg_match( '#/v1$#', $endpoint ) ) {
			return $endpoint . '/models';
		}
		return $endpoint . '/models';
	}

	protected function embeddings_endpoint(): string {
		$endpoint = rtrim( (string) $this->config['endpoint'], '/' );
		if ( preg_match( '#/chat/completions/?$#', $endpoint ) ) {
			return (string) preg_replace( '#/chat/completions/?$#', '/embeddings', $endpoint );
		}
		return '';
	}

	/**
	 * @param mixed $data Decoded JSON or null.
	 */
	protected function extract_error_message( $data, int $code ): string {
		if ( is_array( $data ) ) {
			if ( isset( $data['error']['message'] ) && is_string( $data['error']['message'] ) ) {
				return $data['error']['message'];
			}
			if ( isset( $data['message'] ) && is_string( $data['message'] ) ) {
				return $data['message'];
			}
		}
		return 'AI request failed (HTTP ' . $code . ')';
	}

	protected function parse_sse_content( string $raw ): string {
		$out  = '';
		$lines = preg_split( '/\r\n|\n|\r/', $raw );
		if ( ! is_array( $lines ) ) {
			return '';
		}
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( $line === '' || strpos( $line, 'data:' ) !== 0 ) {
				continue;
			}
			$payload = trim( substr( $line, 5 ) );
			if ( $payload === '[DONE]' ) {
				break;
			}
			$json = json_decode( $payload, true );
			if ( ! is_array( $json ) ) {
				continue;
			}
			$delta = $json['choices'][0]['delta']['content'] ?? '';
			if ( is_string( $delta ) ) {
				$out .= $delta;
			}
		}
		return $out;
	}

	/**
	 * Normalize optional response_format for OpenAI-compatible chat bodies.
	 *
	 * Accepts:
	 * - string "json_object"
	 * - array{type: "json_object"}
	 * - array{type: "json_schema", json_schema: array} (passed through only if well-formed)
	 *
	 * @param mixed $format Caller option.
	 * @return array<string, mixed>|null
	 */
	protected function normalize_response_format( $format ): ?array {
		if ( is_string( $format ) ) {
			$format = strtolower( trim( $format ) );
			if ( $format === 'json_object' || $format === 'json' ) {
				return array( 'type' => 'json_object' );
			}
			return null;
		}
		if ( ! is_array( $format ) ) {
			return null;
		}
		$type = isset( $format['type'] ) ? strtolower( trim( (string) $format['type'] ) ) : '';
		if ( $type === 'json_object' ) {
			return array( 'type' => 'json_object' );
		}
		if ( $type === 'json_schema' && isset( $format['json_schema'] ) && is_array( $format['json_schema'] ) ) {
			return array(
				'type'        => 'json_schema',
				'json_schema' => $format['json_schema'],
			);
		}
		return null;
	}
}
