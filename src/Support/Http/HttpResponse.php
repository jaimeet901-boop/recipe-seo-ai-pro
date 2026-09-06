<?php
declare(strict_types=1);

/**
 * Normalized HTTP response for HttpClientInterface implementations.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Support\Http;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HttpResponse
 */
final class HttpResponse {

	private int $status_code;

	private string $body;

	/** @var array<string, mixed> */
	private array $headers;

	/**
	 * @param int                  $status_code HTTP status code.
	 * @param string               $body        Raw response body.
	 * @param array<string, mixed> $headers     Header map (WP-style).
	 */
	public function __construct( int $status_code, string $body, array $headers = array() ) {
		$this->status_code = $status_code;
		$this->body        = $body;
		$this->headers     = $headers;
	}

	/**
	 * Build from a successful wp_remote_* array response.
	 *
	 * @param array<string, mixed> $response wp_remote_* success array.
	 */
	public static function from_wp_response( array $response ): self {
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		$headers = array();
		if ( isset( $response['headers'] ) ) {
			if ( is_array( $response['headers'] ) ) {
				$headers = $response['headers'];
			} elseif ( is_object( $response['headers'] ) && method_exists( $response['headers'], 'getAll' ) ) {
				$headers = (array) $response['headers']->getAll();
			}
		}

		return new self( $code, $body, $headers );
	}

	public function status_code(): int {
		return $this->status_code;
	}

	public function body(): string {
		return $this->body;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function headers(): array {
		return $this->headers;
	}

	/**
	 * Case-insensitive single header lookup (first value as string).
	 */
	public function header( string $name ): string {
		$needle = strtolower( $name );
		foreach ( $this->headers as $key => $value ) {
			if ( strtolower( (string) $key ) !== $needle ) {
				continue;
			}
			if ( is_array( $value ) ) {
				return isset( $value[0] ) ? (string) $value[0] : '';
			}
			return (string) $value;
		}

		// Fallback to WP helper when headers object was normalized poorly.
		return '';
	}

	/**
	 * Whether the status is 2xx.
	 */
	public function is_success(): bool {
		return $this->status_code >= 200 && $this->status_code < 300;
	}
}
