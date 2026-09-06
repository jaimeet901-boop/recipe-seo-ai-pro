<?php
declare(strict_types=1);

/**
 * Automatic model discovery for AI Hub providers.
 *
 * Returns rich model metadata for the Hub UI. Feature modules are unaffected;
 * they continue to use the configured model string via complete().
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub;

use RecipeSeoAiPro\Contracts\AiHubProviderInterface;
use RecipeSeoAiPro\Modules\Ai\Hub\Http\AiHttpClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ModelDiscovery
 */
final class ModelDiscovery {

	public const CACHE_TTL = 900; // 15 minutes.

	private AiHttpClient $http;

	public function __construct( ?AiHttpClient $http = null ) {
		$this->http = $http instanceof AiHttpClient ? $http : new AiHttpClient();
	}

	/**
	 * Discover models for a configured provider instance.
	 *
	 * Never hard-fails the UI: on API errors returns fallback/manual payload
	 * with an exact error message.
	 *
	 * @return array{
	 *   models: list<array<string,mixed>>,
	 *   discovery: string,
	 *   manual_required: bool,
	 *   supports_discovery: bool,
	 *   cached: bool,
	 *   error: string,
	 *   http_status: int|null
	 * }
	 */
	public function discover( AiHubProviderInterface $provider, bool $refresh = false ): array {
		$id     = $provider->id();
		$config = method_exists( $provider, 'get_config' ) ? $provider->get_config() : array();
		$endpoint = trim( (string) ( $config['endpoint'] ?? '' ) );
		$cache_key = $this->cache_key( $id, $endpoint );

		if ( $refresh ) {
			$this->bust_cache( $id, $endpoint );
		} else {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) && isset( $cached['models'] ) && is_array( $cached['models'] ) ) {
				$cached['cached'] = true;
				return $cached;
			}
		}

		$supports = $this->supports_discovery( $id );
		$result   = $this->fetch_from_api( $provider, $config );

		if ( is_wp_error( $result ) ) {
			$fallback = $this->fallback_models( $id, $config );
			$payload  = array(
				'models'             => $fallback,
				'discovery'          => empty( $fallback ) ? 'manual' : 'fallback',
				'manual_required'    => true,
				'supports_discovery' => $supports,
				'cached'             => false,
				'error'              => $this->normalize_error( $result ),
				'http_status'        => $this->error_status( $result ),
			);
			// Do not cache failures.
			return $payload;
		}

		$models = $this->enrich_list( $id, $result );
		$payload = array(
			'models'             => $models,
			'discovery'          => 'api',
			'manual_required'    => ! $supports || empty( $models ),
			'supports_discovery' => $supports,
			'cached'             => false,
			'error'              => '',
			'http_status'        => 200,
		);

		set_transient( $cache_key, $payload, self::CACHE_TTL );
		return $payload;
	}

	/**
	 * Whether the provider is expected to expose a models API.
	 */
	public function supports_discovery( string $provider_id ): bool {
		$manual_only = array( 'azure_openai' ); // Prefer deployments/manual; still try API.
		// All listed providers support some form of discovery or curated fallback.
		unset( $manual_only );
		return in_array(
			sanitize_key( $provider_id ),
			array(
				'openai',
				'deepseek',
				'gemini',
				'anthropic',
				'openrouter',
				'groq',
				'xai',
				'together',
				'mistral',
				'cohere',
				'ollama',
				'lmstudio',
				'azure_openai',
				'custom',
			),
			true
		);
	}

	/**
	 * Clear model caches for a provider (and optionally a specific endpoint).
	 */
	public function bust_cache( string $provider_id, string $endpoint = '' ): void {
		delete_transient( $this->cache_key( $provider_id, $endpoint ) );
		delete_transient( 'rsaip_ai_models_' . sanitize_key( $provider_id ) );
		delete_transient( 'rsaip_ai_models_anthropic' );
		delete_transient( 'rsaip_ai_models_gemini' );
		delete_transient( 'rsaip_ai_models_cohere' );

		// Clear OpenAI-style string-list caches for this provider.
		global $wpdb;
		$like = $wpdb->esc_like( '_transient_rsaip_ai_models_' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$keys = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
		if ( is_array( $keys ) ) {
			foreach ( $keys as $option_name ) {
				$key = (string) $option_name;
				if ( strpos( $key, '_transient_timeout_' ) === 0 ) {
					continue;
				}
				$transient = substr( $key, strlen( '_transient_' ) );
				if ( strpos( $transient, 'rsaip_ai_models_' ) === 0 ) {
					delete_transient( $transient );
				}
			}
		}
	}

	/**
	 * @param array<string, mixed> $config Runtime config (plaintext key).
	 * @return list<array<string,mixed>>|\WP_Error Raw model rows (at least id/name).
	 */
	private function fetch_from_api( AiHubProviderInterface $provider, array $config ) {
		$id = $provider->id();

		if ( method_exists( $provider, 'connect' ) ) {
			$ready = $provider->connect( $config );
			if ( is_wp_error( $ready ) ) {
				return $ready;
			}
		}

		switch ( $id ) {
			case 'ollama':
				return $this->fetch_ollama( $config );
			case 'lmstudio':
			case 'openai':
			case 'deepseek':
			case 'openrouter':
			case 'groq':
			case 'xai':
			case 'together':
			case 'mistral':
			case 'custom':
				return $this->fetch_openai_style( $provider, $config );
			case 'gemini':
				return $this->fetch_gemini( $config );
			case 'anthropic':
				return $this->fetch_anthropic( $config );
			case 'cohere':
				return $this->fetch_cohere( $config );
			case 'azure_openai':
				return $this->fetch_azure( $config );
			default:
				return new \WP_Error( 'rsaip_ai_models', 'Unknown provider for model discovery' );
		}
	}

	/**
	 * @param array<string, mixed> $config Config.
	 * @return list<array<string,mixed>>|\WP_Error
	 */
	private function fetch_openai_style( AiHubProviderInterface $provider, array $config ) {
		$endpoint = trim( (string) ( $config['endpoint'] ?? '' ) );
		if ( $endpoint === '' || ! rsaip_is_safe_ai_endpoint( $endpoint ) ) {
			return new \WP_Error( 'rsaip_ai_config', 'Invalid endpoint' );
		}

		$models_url = $this->derive_models_url( $endpoint );
		$key        = trim( (string) ( $config['api_key'] ?? '' ) );
		$timeout    = max( 5, min( 120, (int) ( $config['timeout'] ?? 25 ) ) );

		$headers = array( 'Content-Type' => 'application/json' );
		if ( $key !== '' ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}
		$org = trim( (string) ( $config['organization'] ?? '' ) );
		if ( $org !== '' ) {
			$headers['OpenAI-Organization'] = $org;
		}
		if ( $provider->id() === 'openrouter' ) {
			$headers['HTTP-Referer'] = home_url( '/' );
			$headers['X-Title']      = 'Recipe SEO AI Pro';
		}
		if ( isset( $config['custom_headers'] ) && is_array( $config['custom_headers'] ) ) {
			foreach ( $config['custom_headers'] as $hk => $hv ) {
				$hk = (string) $hk;
				if ( $hk !== '' && ! preg_match( '/authorization/i', $hk ) ) {
					$headers[ $hk ] = (string) $hv;
				}
			}
		}

		$resp = $this->http->get(
			$models_url,
			array(
				'timeout' => $timeout,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $resp ) ) {
			return $this->map_wp_http_error( $resp );
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$raw  = (string) wp_remote_retrieve_body( $resp );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return $this->http_error( $code, $data, $raw );
		}

		$rows = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array();
		$out  = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['id'] ) ) {
				continue;
			}
			$out[] = $row;
		}

		if ( empty( $out ) && in_array( $provider->id(), array( 'deepseek', 'custom' ), true ) ) {
			return new \WP_Error( 'rsaip_ai_models', 'No models returned by provider. Use Manual Entry.', array( 'status' => $code ) );
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $config Config.
	 * @return list<array<string,mixed>>|\WP_Error
	 */
	private function fetch_ollama( array $config ) {
		$endpoint = trim( (string) ( $config['endpoint'] ?? 'http://localhost:11434/v1/chat/completions' ) );
		$parts    = wp_parse_url( $endpoint );
		$scheme   = is_array( $parts ) ? (string) ( $parts['scheme'] ?? 'http' ) : 'http';
		$host     = is_array( $parts ) ? (string) ( $parts['host'] ?? 'localhost' ) : 'localhost';
		$port     = is_array( $parts ) && ! empty( $parts['port'] ) ? (int) $parts['port'] : 11434;
		$tags_url = $scheme . '://' . $host . ( $port ? ':' . $port : '' ) . '/api/tags';

		if ( ! rsaip_is_safe_ai_endpoint( $tags_url ) ) {
			return new \WP_Error( 'rsaip_ai_config', 'Invalid endpoint' );
		}

		$resp = $this->http->get(
			$tags_url,
			array( 'timeout' => max( 5, min( 120, (int) ( $config['timeout'] ?? 25 ) ) ) )
		);
		if ( is_wp_error( $resp ) ) {
			return $this->map_wp_http_error( $resp );
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return $this->http_error( $code, $data, (string) wp_remote_retrieve_body( $resp ) );
		}

		$out  = array();
		$rows = isset( $data['models'] ) && is_array( $data['models'] ) ? $data['models'] : array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = (string) ( $row['name'] ?? $row['model'] ?? '' );
			if ( $name === '' ) {
				continue;
			}
			$out[] = array(
				'id'      => $name,
				'name'    => $name,
				'details' => isset( $row['details'] ) && is_array( $row['details'] ) ? $row['details'] : array(),
				'size'    => $row['size'] ?? null,
			);
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $config Config.
	 * @return list<array<string,mixed>>|\WP_Error
	 */
	private function fetch_gemini( array $config ) {
		$key = trim( (string) ( $config['api_key'] ?? '' ) );
		if ( $key === '' ) {
			return new \WP_Error( 'rsaip_ai_config', 'Invalid API key' );
		}

		$url  = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . rawurlencode( $key );
		$resp = $this->http->get(
			$url,
			array( 'timeout' => max( 5, min( 120, (int) ( $config['timeout'] ?? 25 ) ) ) )
		);
		if ( is_wp_error( $resp ) ) {
			return $this->map_wp_http_error( $resp );
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$raw  = (string) wp_remote_retrieve_body( $resp );
		$data = json_decode( $raw, true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return $this->http_error( $code, $data, $raw );
		}

		$out  = array();
		$rows = isset( $data['models'] ) && is_array( $data['models'] ) ? $data['models'] : array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['name'] ) ) {
				continue;
			}
			$name = preg_replace( '#^models/#', '', (string) $row['name'] );
			$methods = isset( $row['supportedGenerationMethods'] ) && is_array( $row['supportedGenerationMethods'] )
				? $row['supportedGenerationMethods']
				: array();
			if ( ! empty( $methods ) && ! in_array( 'generateContent', $methods, true ) ) {
				continue;
			}
			$out[] = array(
				'id'           => $name,
				'name'         => $name,
				'display_name' => (string) ( $row['displayName'] ?? $name ),
				'input_token_limit'  => isset( $row['inputTokenLimit'] ) ? (int) $row['inputTokenLimit'] : null,
				'output_token_limit' => isset( $row['outputTokenLimit'] ) ? (int) $row['outputTokenLimit'] : null,
				'methods'      => $methods,
			);
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $config Config.
	 * @return list<array<string,mixed>>|\WP_Error
	 */
	private function fetch_anthropic( array $config ) {
		$key = trim( (string) ( $config['api_key'] ?? '' ) );
		if ( $key === '' ) {
			return new \WP_Error( 'rsaip_ai_config', 'Invalid API key' );
		}

		$resp = $this->http->get(
			'https://api.anthropic.com/v1/models',
			array(
				'timeout' => max( 5, min( 120, (int) ( $config['timeout'] ?? 25 ) ) ),
				'headers' => array(
					'x-api-key'         => $key,
					'anthropic-version' => '2023-06-01',
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $this->map_wp_http_error( $resp );
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$raw  = (string) wp_remote_retrieve_body( $resp );
		$data = json_decode( $raw, true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return $this->http_error( $code, $data, $raw );
		}

		$out  = array();
		$rows = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) && ! empty( $row['id'] ) ) {
				$out[] = $row;
			}
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $config Config.
	 * @return list<array<string,mixed>>|\WP_Error
	 */
	private function fetch_cohere( array $config ) {
		$key = trim( (string) ( $config['api_key'] ?? '' ) );
		if ( $key === '' ) {
			return new \WP_Error( 'rsaip_ai_config', 'Invalid API key' );
		}

		$resp = $this->http->get(
			'https://api.cohere.ai/v1/models',
			array(
				'timeout' => max( 5, min( 120, (int) ( $config['timeout'] ?? 25 ) ) ),
				'headers' => array( 'Authorization' => 'Bearer ' . $key ),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $this->map_wp_http_error( $resp );
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$raw  = (string) wp_remote_retrieve_body( $resp );
		$data = json_decode( $raw, true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return $this->http_error( $code, $data, $raw );
		}

		$out  = array();
		$rows = isset( $data['models'] ) && is_array( $data['models'] ) ? $data['models'] : array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = (string) ( $row['name'] ?? $row['id'] ?? '' );
			if ( $name === '' ) {
				continue;
			}
			$out[] = array_merge( $row, array( 'id' => $name, 'name' => $name ) );
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $config Config.
	 * @return list<array<string,mixed>>|\WP_Error
	 */
	private function fetch_azure( array $config ) {
		$endpoint = trim( (string) ( $config['endpoint'] ?? '' ) );
		$key      = trim( (string) ( $config['api_key'] ?? '' ) );

		if ( $endpoint === '' || ! rsaip_is_safe_ai_endpoint( $endpoint ) ) {
			return new \WP_Error( 'rsaip_ai_config', 'Invalid endpoint' );
		}
		if ( $key === '' ) {
			return new \WP_Error( 'rsaip_ai_config', 'Invalid API key' );
		}

		// Try resource-level deployments list when endpoint contains azure host.
		$parts = wp_parse_url( $endpoint );
		$host  = is_array( $parts ) ? (string) ( $parts['host'] ?? '' ) : '';
		$query = array();
		if ( is_array( $parts ) && ! empty( $parts['query'] ) ) {
			parse_str( (string) $parts['query'], $query );
		}
		$api_version = isset( $query['api-version'] ) ? (string) $query['api-version'] : '2024-02-15-preview';

		if ( $host !== '' && ( strpos( $host, 'openai.azure.com' ) !== false || strpos( $host, 'cognitiveservices.azure.com' ) !== false ) ) {
			$list_url = 'https://' . $host . '/openai/deployments?api-version=' . rawurlencode( $api_version );
			$resp     = $this->http->get(
				$list_url,
				array(
					'timeout' => max( 5, min( 120, (int) ( $config['timeout'] ?? 25 ) ) ),
					'headers' => array( 'api-key' => $key ),
				)
			);
			if ( ! is_wp_error( $resp ) ) {
				$code = (int) wp_remote_retrieve_response_code( $resp );
				$raw  = (string) wp_remote_retrieve_body( $resp );
				$data = json_decode( $raw, true );
				if ( $code >= 200 && $code < 300 && is_array( $data ) ) {
					$rows = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : ( isset( $data['value'] ) && is_array( $data['value'] ) ? $data['value'] : array() );
					$out  = array();
					foreach ( $rows as $row ) {
						if ( ! is_array( $row ) ) {
							continue;
						}
						$dep = (string) ( $row['id'] ?? $row['name'] ?? '' );
						if ( $dep === '' ) {
							continue;
						}
						$out[] = array(
							'id'       => $dep,
							'name'     => $dep,
							'model'    => (string) ( $row['model'] ?? $row['properties']['model']['format'] ?? '' ),
							'azure'    => true,
						);
					}
					if ( ! empty( $out ) ) {
						return $out;
					}
				}
			}
		}

		// Manual / configured deployment names — not an error.
		$model  = trim( (string) ( $config['model'] ?? '' ) );
		$manual = trim( (string) ( $config['manual_model'] ?? '' ) );
		$list   = array_values( array_unique( array_filter( array( $model, $manual ) ) ) );
		if ( empty( $list ) ) {
			return new \WP_Error(
				'rsaip_ai_models',
				'Azure OpenAI could not list deployments. Enter your deployment name under Manual Entry.',
				array( 'status' => 404 )
			);
		}

		$out = array();
		foreach ( $list as $id ) {
			$out[] = array( 'id' => $id, 'name' => $id, 'azure' => true );
		}
		return $out;
	}

	/**
	 * @param list<array<string,mixed>> $rows Raw rows.
	 * @return list<array<string,mixed>>
	 */
	private function enrich_list( string $provider_id, array $rows ): array {
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = (string) ( $row['id'] ?? $row['name'] ?? '' );
			if ( $id === '' ) {
				continue;
			}
			$out[] = $this->enrich_one( $provider_id, $id, $row );
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return strcasecmp( (string) $a['name'], (string) $b['name'] );
			}
		);

		return $out;
	}

	/**
	 * @param array<string, mixed> $row Raw.
	 * @return array<string, mixed>
	 */
	private function enrich_one( string $provider_id, string $id, array $row ): array {
		$lower = strtolower( $id );
		$name  = (string) ( $row['display_name'] ?? $row['name'] ?? $id );

		$context = null;
		if ( isset( $row['context_window'] ) ) {
			$context = (int) $row['context_window'];
		} elseif ( isset( $row['context_length'] ) ) {
			$context = (int) $row['context_length'];
		} elseif ( isset( $row['input_token_limit'] ) ) {
			$context = (int) $row['input_token_limit'];
		} elseif ( isset( $row['top_provider']['context_length'] ) ) {
			$context = (int) $row['top_provider']['context_length'];
		} else {
			$context = $this->guess_context( $lower );
		}

		$caps = array(
			'chat'       => true,
			'vision'     => false,
			'reasoning'  => false,
			'embeddings' => false,
		);

		if ( strpos( $lower, 'embed' ) !== false || strpos( $lower, 'embedding' ) !== false ) {
			$caps['chat']       = false;
			$caps['embeddings'] = true;
		}
		if ( preg_match( '/vision|gpt-4o|gpt-4\.1|claude-3|gemini|llava|pixtral/i', $lower ) ) {
			$caps['vision'] = true;
		}
		if ( preg_match( '/o1|o3|reason|r1|thinking/i', $lower ) ) {
			$caps['reasoning'] = true;
		}
		if ( isset( $row['architecture']['modality'] ) && is_string( $row['architecture']['modality'] ) ) {
			if ( strpos( $row['architecture']['modality'], 'image' ) !== false ) {
				$caps['vision'] = true;
			}
		}
		if ( isset( $row['methods'] ) && is_array( $row['methods'] ) && in_array( 'embedContent', $row['methods'], true ) ) {
			$caps['embeddings'] = true;
		}

		$deprecated = ! empty( $row['deprecated'] );
		if ( preg_match( '/deprecated|instruct-0|davinci|text-davinci|gpt-3\.5-turbo-0/i', $lower ) ) {
			$deprecated = true;
		}

		// Prefer chat-capable models in default browsing; keep embeddings visible.
		return array(
			'id'             => $id,
			'name'           => $name,
			'provider'       => $provider_id,
			'provider_label' => ProviderCatalog::label( $provider_id ),
			'context_window' => $context,
			'capabilities'   => $caps,
			'deprecated'     => $deprecated,
		);
	}

	private function guess_context( string $model ): ?int {
		$map = array(
			'gpt-4o'           => 128000,
			'gpt-4.1'          => 1047576,
			'gpt-4-turbo'      => 128000,
			'gpt-3.5'          => 16385,
			'deepseek'         => 64000,
			'claude-3-5'       => 200000,
			'claude-3'         => 200000,
			'gemini-1.5'       => 1000000,
			'gemini-2'         => 1000000,
			'llama-3.3'        => 128000,
			'llama-3.1'        => 128000,
			'command-r'        => 128000,
			'mistral-large'    => 128000,
			'mistral-small'    => 32000,
		);
		foreach ( $map as $needle => $ctx ) {
			if ( strpos( $model, $needle ) !== false ) {
				return $ctx;
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $config Config.
	 * @return list<array<string,mixed>>
	 */
	private function fallback_models( string $provider_id, array $config ): array {
		$catalog = array(
			'openai'     => array( 'gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini', 'o3-mini' ),
			'deepseek'   => array( 'deepseek-chat', 'deepseek-reasoner' ),
			'anthropic'  => array( 'claude-3-5-haiku-latest', 'claude-3-5-sonnet-latest', 'claude-3-opus-latest' ),
			'gemini'     => array( 'gemini-1.5-flash', 'gemini-1.5-pro', 'gemini-2.0-flash' ),
			'groq'       => array( 'llama-3.3-70b-versatile', 'llama-3.1-8b-instant', 'mixtral-8x7b-32768' ),
			'mistral'    => array( 'mistral-small-latest', 'mistral-large-latest', 'open-mistral-nemo' ),
			'cohere'     => array( 'command-r-plus', 'command-r', 'command-light' ),
			'xai'        => array( 'grok-2-latest', 'grok-2-vision-latest' ),
			'openrouter' => array( 'openai/gpt-4o-mini', 'anthropic/claude-3.5-sonnet', 'google/gemini-flash-1.5' ),
			'together'   => array( 'meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo', 'mistralai/Mixtral-8x7B-Instruct-v0.1' ),
			'ollama'     => array( 'llama3.2', 'mistral', 'qwen2.5' ),
			'lmstudio'   => array( 'local-model' ),
			'azure_openai' => array(),
			'custom'     => array(),
		);

		$ids = $catalog[ $provider_id ] ?? array();
		$model = trim( (string) ( $config['model'] ?? '' ) );
		$manual = trim( (string) ( $config['manual_model'] ?? '' ) );
		if ( $model !== '' ) {
			array_unshift( $ids, $model );
		}
		if ( $manual !== '' ) {
			array_unshift( $ids, $manual );
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );

		$rows = array();
		foreach ( $ids as $mid ) {
			$rows[] = array( 'id' => $mid, 'name' => $mid );
		}
		return $this->enrich_list( $provider_id, $rows );
	}

	private function derive_models_url( string $endpoint ): string {
		$endpoint = rtrim( $endpoint, '/' );
		if ( preg_match( '#/chat/completions/?$#', $endpoint ) ) {
			return (string) preg_replace( '#/chat/completions/?$#', '/models', $endpoint );
		}
		if ( preg_match( '#/completions/?$#', $endpoint ) ) {
			return (string) preg_replace( '#/completions/?$#', '/models', $endpoint );
		}
		if ( preg_match( '#/v1$#', $endpoint ) ) {
			return $endpoint . '/models';
		}
		return $endpoint . '/models';
	}

	private function cache_key( string $provider_id, string $endpoint ): string {
		return 'rsaip_ai_model_meta_' . md5( sanitize_key( $provider_id ) . '|' . $endpoint );
	}

	/**
	 * @param mixed $data Decoded body.
	 */
	private function http_error( int $code, $data, string $raw = '' ): \WP_Error {
		$api_msg = '';
		if ( is_array( $data ) ) {
			if ( isset( $data['error']['message'] ) && is_string( $data['error']['message'] ) ) {
				$api_msg = $data['error']['message'];
			} elseif ( isset( $data['message'] ) && is_string( $data['message'] ) ) {
				$api_msg = $data['message'];
			} elseif ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
				$api_msg = $data['error'];
			}
		}

		$map = array(
			401 => '401 Unauthorized — Invalid API key',
			403 => '403 Forbidden — Access denied for this API key',
			404 => '404 Not Found — Models endpoint not available for this provider',
			429 => '429 Too Many Requests — Rate limit exceeded',
			500 => '500 Server Error — Provider is unavailable',
			502 => '502 Bad Gateway — Provider gateway error',
			503 => '503 Service Unavailable — Provider is temporarily down',
			504 => '504 Gateway Timeout — Provider timed out',
		);

		$base = $map[ $code ] ?? ( 'HTTP ' . $code . ' — Model discovery failed' );
		if ( $api_msg !== '' ) {
			$base .= ': ' . $api_msg;
		} elseif ( $code === 0 && $raw !== '' ) {
			$base .= ': ' . substr( wp_strip_all_tags( $raw ), 0, 180 );
		}

		return new \WP_Error( 'rsaip_ai_http', $base, array( 'status' => $code ) );
	}

	private function map_wp_http_error( \WP_Error $error ): \WP_Error {
		$code = $error->get_error_code();
		$msg  = $error->get_error_message();
		$lower = strtolower( $msg );

		if ( strpos( $lower, 'timed out' ) !== false || strpos( $lower, 'timeout' ) !== false || $code === 'http_request_failed' && strpos( $lower, 'curl' ) !== false && strpos( $lower, 'timed' ) !== false ) {
			return new \WP_Error( 'rsaip_ai_timeout', 'Timeout — The provider did not respond in time', array( 'status' => 0 ) );
		}
		if ( strpos( $lower, 'invalid ai endpoint' ) !== false || strpos( $lower, 'invalid endpoint' ) !== false ) {
			return new \WP_Error( 'rsaip_ai_config', 'Invalid endpoint', array( 'status' => 0 ) );
		}
		if ( strpos( $lower, 'missing ai api key' ) !== false || strpos( $lower, 'invalid api key' ) !== false ) {
			return new \WP_Error( 'rsaip_ai_config', 'Invalid API key', array( 'status' => 0 ) );
		}
		if ( strpos( $lower, 'could not resolve' ) !== false || strpos( $lower, 'failed to connect' ) !== false ) {
			return new \WP_Error( 'rsaip_ai_http', 'Connection failed — ' . $msg, array( 'status' => 0 ) );
		}

		return new \WP_Error( $code, $msg !== '' ? $msg : 'Model discovery failed', $error->get_error_data() );
	}

	private function normalize_error( \WP_Error $error ): string {
		$msg = $error->get_error_message();
		return $msg !== '' ? $msg : 'Model discovery failed';
	}

	/**
	 * @return int|null
	 */
	private function error_status( \WP_Error $error ) {
		$data = $error->get_error_data();
		if ( is_array( $data ) && isset( $data['status'] ) ) {
			return (int) $data['status'];
		}
		return null;
	}
}
