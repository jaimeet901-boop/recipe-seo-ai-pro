<?php
declare(strict_types=1);

/**
 * AI Provider Hub manager — register, resolve, failover, health, models.
 *
 * Feature modules never call this directly; OpenAiCompatibleProvider delegates
 * complete() here so existing call sites stay unchanged.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub;

use RecipeSeoAiPro\Contracts\AiHubProviderInterface;
use RecipeSeoAiPro\Modules\Ai\Hub\Http\AiHttpClient;
use RecipeSeoAiPro\Modules\Ai\Hub\Providers\AnthropicHubProvider;
use RecipeSeoAiPro\Modules\Ai\Hub\Providers\AzureOpenAiHubProvider;
use RecipeSeoAiPro\Modules\Ai\Hub\Providers\CohereHubProvider;
use RecipeSeoAiPro\Modules\Ai\Hub\Providers\CustomOpenAiHubProvider;
use RecipeSeoAiPro\Modules\Ai\Hub\Providers\DeepSeekHubProvider;
use RecipeSeoAiPro\Modules\Ai\Hub\Providers\GeminiHubProvider;
use RecipeSeoAiPro\Modules\Ai\Hub\Providers\GroqHubProvider;
use RecipeSeoAiPro\Modules\Ai\Hub\Providers\LmStudioHubProvider;
use RecipeSeoAiPro\Modules\Ai\Hub\Providers\MistralHubProvider;
use RecipeSeoAiPro\Modules\Ai\Hub\Providers\OllamaHubProvider;
use RecipeSeoAiPro\Modules\Ai\Hub\Providers\OpenAiHubProvider;
use RecipeSeoAiPro\Modules\Ai\Hub\Providers\OpenRouterHubProvider;
use RecipeSeoAiPro\Modules\Ai\Hub\Providers\TogetherHubProvider;
use RecipeSeoAiPro\Modules\Ai\Hub\Providers\XaiHubProvider;
use RecipeSeoAiPro\Support\Security\SecretGuard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProviderManager
 */
final class ProviderManager {

	/** @var array<string, AiHubProviderInterface> */
	private array $providers = array();

	private HubRepository $repository;

	private AiRequestLogger $logger;

	private AiHttpClient $http;

	private static ?self $instance = null;

	public function __construct( ?HubRepository $repository = null, ?AiRequestLogger $logger = null, ?AiHttpClient $http = null ) {
		$this->repository = $repository instanceof HubRepository ? $repository : new HubRepository();
		$this->logger     = $logger instanceof AiRequestLogger ? $logger : new AiRequestLogger();
		$this->http       = $http instanceof AiHttpClient ? $http : new AiHttpClient();
		$this->register_builtins();
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Bind the DI-constructed manager for facades created outside the container.
	 */
	public static function set_instance( self $manager ): void {
		self::$instance = $manager;
	}

	/**
	 * Register (or replace) a hub provider adapter.
	 */
	public function register( AiHubProviderInterface $provider ): void {
		$this->providers[ $provider->id() ] = $provider;
	}

	/**
	 * @return array<string, AiHubProviderInterface>
	 */
	public function all(): array {
		return $this->providers;
	}

	public function has( string $id ): bool {
		return isset( $this->providers[ sanitize_key( $id ) ] );
	}

	/**
	 * Get a configured provider instance (api key decrypted for runtime use).
	 */
	public function get( string $id ): ?AiHubProviderInterface {
		$id = sanitize_key( $id );
		if ( ! isset( $this->providers[ $id ] ) ) {
			return null;
		}

		$provider = $this->providers[ $id ];
		$config   = $this->repository->get_provider_config( $id );
		$config['api_key'] = $this->repository->get_api_key_plain( $id );

		if ( method_exists( $provider, 'with_config' ) ) {
			/** @var \RecipeSeoAiPro\Modules\Ai\Hub\Providers\AbstractHubProvider $provider */
			$provider->with_config( $config );
		}

		return $provider;
	}

	/**
	 * Resolve the active hub provider.
	 */
	public function resolve_active(): ?AiHubProviderInterface {
		return $this->get( $this->repository->get_active_id() );
	}

	public function repository(): HubRepository {
		return $this->repository;
	}

	public function logger(): AiRequestLogger {
		return $this->logger;
	}

	/**
	 * Discover models for Hub UI (rich metadata + cache + safe fallbacks).
	 *
	 * @return array<string, mixed>
	 */
	public function discover_models( string $provider_id, bool $refresh = false ): array {
		$provider = $this->get( $provider_id );
		if ( ! $provider instanceof AiHubProviderInterface ) {
			return array(
				'models'             => array(),
				'discovery'          => 'manual',
				'manual_required'    => true,
				'supports_discovery' => false,
				'cached'             => false,
				'error'              => 'Unknown provider',
				'http_status'        => null,
			);
		}

		$discovery = new ModelDiscovery( $this->http );
		$result    = $discovery->discover( $provider, $refresh );
		$config    = $this->repository->get_provider_config( $provider_id );
		$hub       = $this->repository->all();

		$result['selected']   = (string) ( $config['model'] ?? '' );
		$result['favorites']  = is_array( $hub['favorites'] ?? null ) ? $hub['favorites'] : array();
		$result['provider']   = $provider_id;
		$result['provider_label'] = ProviderCatalog::label( $provider_id );

		return $result;
	}

	/**
	 * Set default model on a provider and sync legacy ai_model when active.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function set_default_model( string $provider_id, string $model, bool $activate = true ) {
		$model = sanitize_text_field( $model );
		if ( $model === '' ) {
			return new \WP_Error( 'rsaip_ai_config', 'Model name is required' );
		}

		$saved = $this->configure_provider(
			$provider_id,
			array(
				'model'    => $model,
				'activate' => $activate,
			)
		);

		return $saved;
	}

	/**
	 * Complete with retry + optional failover. Primary entry for the BC facade.
	 *
	 * @param array<string, mixed> $options Options.
	 * @return string|\WP_Error
	 */
	public function complete_with_failover( string $prompt, array $options = array() ) {
		$settings = function_exists( 'rsaip_get_settings' ) ? rsaip_get_settings() : array();
		if ( ( $settings['ai_provider'] ?? '' ) === 'disabled' ) {
			return new \WP_Error( 'rsaip_ai_config', 'Missing AI configuration' );
		}

		// Keep classic Settings form values effective without module changes.
		$this->repository->absorb_legacy_settings();

		$hub = $this->repository->all();
		$chain = array( $this->repository->get_active_id() );
		if ( ! empty( $hub['failover_enabled'] ) && ! empty( $hub['failover'] ) && is_array( $hub['failover'] ) ) {
			foreach ( $hub['failover'] as $fid ) {
				$fid = sanitize_key( (string) $fid );
				if ( $fid !== '' && ! in_array( $fid, $chain, true ) ) {
					$chain[] = $fid;
				}
			}
		}

		$last_error = new \WP_Error( 'rsaip_ai_config', 'Missing AI configuration' );

		foreach ( $chain as $index => $provider_id ) {
			$provider = $this->get( $provider_id );
			if ( ! $provider instanceof AiHubProviderInterface ) {
				continue;
			}

			$result = $this->run_complete( $provider, $prompt, $options );
			if ( is_string( $result ) ) {
				return $result;
			}

			$last_error = $result;

			// On first provider: honor retry_count inside adapter already; then move to failover.
			if ( $index === 0 && empty( $hub['failover_enabled'] ) ) {
				break;
			}
		}

		return $last_error;
	}

	/**
	 * @param array<string, mixed> $options Options.
	 * @return string|\WP_Error
	 */
	private function run_complete( AiHubProviderInterface $provider, string $prompt, array $options ) {
		$started = microtime( true );
		$request_size = strlen( $prompt ) + strlen( (string) wp_json_encode( $options ) );

		$result = $provider->complete( $prompt, $options );
		$latency = (int) round( ( microtime( true ) - $started ) * 1000 );

		$usage = array(
			'prompt'     => 0,
			'completion' => 0,
			'total'      => 0,
		);
		if ( property_exists( $provider, 'last_usage' ) && is_array( $provider->last_usage ) ) {
			$usage = $provider->last_usage;
		}

		$success = is_string( $result );
		$error   = $success ? '' : ( is_wp_error( $result ) ? $result->get_error_message() : 'Unknown error' );
		$cost    = $provider->estimateCost( (int) $usage['prompt'], (int) $usage['completion'] );
		$response_size = $success ? strlen( $result ) : 0;

		$config = method_exists( $provider, 'get_config' ) ? $provider->get_config() : array();
		$model  = (string) ( $config['model'] ?? '' );

		$this->logger->log(
			array(
				'provider'          => $provider->id(),
				'model'             => $model,
				'latency_ms'        => $latency,
				'request_size'      => $request_size,
				'response_size'     => $response_size,
				'prompt_tokens'     => (int) $usage['prompt'],
				'completion_tokens' => (int) $usage['completion'],
				'total_tokens'      => (int) $usage['total'],
				'estimated_cost'    => $cost,
				'success'           => $success,
				'error_message'     => $error,
			)
		);

		$this->repository->record_stats( $latency, (int) $usage['total'], $cost, $success, $error );

		// Persist health onto provider config.
		$health = $provider->health();
		$this->repository->save_provider_config(
			$provider->id(),
			array(
				'status'     => $success ? 'connected' : 'error',
				'latency_ms' => (int) ( $health['latency_ms'] ?? $latency ),
				'last_check' => gmdate( 'c' ),
				'last_error' => $error,
				'connected'  => $success,
			)
		);

		return $result;
	}

	/**
	 * Test a provider connection and persist health fields.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function test_provider( string $provider_id ) {
		$provider = $this->get( $provider_id );
		if ( ! $provider instanceof AiHubProviderInterface ) {
			return new \WP_Error( 'rsaip_ai_config', 'Unknown provider' );
		}

		$result = $provider->testConnection();
		if ( is_wp_error( $result ) ) {
			$this->repository->save_provider_config(
				$provider_id,
				array(
					'status'     => 'error',
					'connected'  => false,
					'last_check' => gmdate( 'c' ),
					'last_error' => $result->get_error_message(),
				)
			);
			return $result;
		}

		$ok = ! empty( $result['connected'] );
		$this->repository->save_provider_config(
			$provider_id,
			array(
				'status'     => $ok ? 'connected' : 'error',
				'connected'  => $ok,
				'latency_ms' => (int) ( $result['latency_ms'] ?? 0 ),
				'last_check' => gmdate( 'c' ),
				'last_error' => (string) ( $result['error'] ?? '' ),
			)
		);

		if ( $ok ) {
			$this->repository->set_active( $provider_id );
		}

		return $result;
	}

	/**
	 * Save provider settings from admin UI.
	 *
	 * @param array<string, mixed> $input Sanitized-ish input.
	 * @return array<string, mixed>|\WP_Error Saved config (api_key masked).
	 */
	public function configure_provider( string $provider_id, array $input ) {
		$provider_id = sanitize_key( $provider_id );
		if ( ! $this->has( $provider_id ) ) {
			return new \WP_Error( 'rsaip_ai_config', 'Unknown provider' );
		}

		if ( ! empty( $input['endpoint'] ) && is_string( $input['endpoint'] ) ) {
			$detected = EndpointDetector::detect( $input['endpoint'] );
			// Auto-detect only suggests; does not force switch when configuring a specific card.
			unset( $detected );
		}

		$allowed = array(
			'api_key',
			'endpoint',
			'model',
			'temperature',
			'top_p',
			'max_tokens',
			'timeout',
			'organization',
			'custom_headers',
			'streaming',
			'retry_count',
			'manual_model',
		);

		$patch = array();
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$patch[ $key ] = $input[ $key ];
			}
		}

		if ( isset( $patch['manual_model'] ) && is_string( $patch['manual_model'] ) && trim( $patch['manual_model'] ) !== '' ) {
			$patch['model'] = sanitize_text_field( $patch['manual_model'] );
		}

		if ( isset( $patch['endpoint'] ) ) {
			$endpoint = esc_url_raw( (string) $patch['endpoint'] );
			if ( $endpoint !== '' && ! rsaip_is_safe_ai_endpoint( $endpoint ) ) {
				return new \WP_Error( 'rsaip_ai_config', 'Invalid AI endpoint' );
			}
			$patch['endpoint'] = $endpoint;
		}

		$saved = $this->repository->save_provider_config( $provider_id, $patch );

		if ( ! empty( $input['activate'] ) ) {
			$this->repository->set_active( $provider_id );
		} elseif ( $this->repository->get_active_id() === $provider_id ) {
			$this->repository->sync_legacy_settings();
		}

		// Never return ciphertext/plaintext key to the browser.
		$saved['api_key']      = '';
		$saved['has_api_key']  = $this->repository->get_api_key_plain( $provider_id ) !== '';
		$saved['api_key_mask'] = SecretGuard::mask();

		return $saved;
	}

	public function disconnect_provider( string $provider_id ): void {
		$this->repository->save_provider_config(
			$provider_id,
			array(
				'api_key'    => '',
				'connected'  => false,
				'status'     => 'disconnected',
				'last_error' => '',
			)
		);

		// Explicit clear — save_provider_config blank-keeps keys; force wipe.
		$data = $this->repository->all();
		if ( isset( $data['providers'][ $provider_id ] ) ) {
			$data['providers'][ $provider_id ]['api_key']   = '';
			$data['providers'][ $provider_id ]['connected'] = false;
			$data['providers'][ $provider_id ]['status']    = 'disconnected';
			$this->repository->write( $data );
		}

		if ( $this->repository->get_active_id() === $provider_id ) {
			// Keep active id but clear legacy key.
			if ( function_exists( 'rsaip_update_settings' ) ) {
				rsaip_update_settings( array( 'ai_api_key' => '' ) );
			}
		}
	}

	/**
	 * Dashboard payload for the AI Hub UI.
	 *
	 * @return array<string, mixed>
	 */
	public function dashboard(): array {
		$hub      = $this->repository->all();
		$active   = $this->repository->get_active_id();
		$config   = $this->repository->get_provider_config( $active );
		$summary  = $this->logger->today_summary();
		$stats    = is_array( $hub['stats'] ?? null ) ? $hub['stats'] : array();
		$samples  = isset( $stats['latency_samples'] ) && is_array( $stats['latency_samples'] ) ? $stats['latency_samples'] : array();
		$avg_hub  = ! empty( $samples ) ? (int) round( array_sum( $samples ) / count( $samples ) ) : 0;

		return array(
			'current_provider' => ProviderCatalog::label( $active ),
			'current_provider_id' => $active,
			'current_model'    => (string) ( $config['model'] ?? '' ),
			'average_latency'  => $summary['avg_latency'] > 0 ? $summary['avg_latency'] : $avg_hub,
			'today_requests'   => max( (int) $summary['requests'], (int) ( $stats['requests_today'] ?? 0 ) ),
			'token_usage'      => max( (int) $summary['tokens'], (int) ( $stats['tokens_today'] ?? 0 ) ),
			'estimated_cost'   => max( (float) $summary['cost'], (float) ( $stats['cost_today'] ?? 0 ) ),
			'last_error'       => (string) ( $stats['last_error'] ?? $config['last_error'] ?? '' ),
			'provider_health'  => (string) ( $config['status'] ?? 'unknown' ),
			'failover_enabled' => ! empty( $hub['failover_enabled'] ),
			'failover'         => is_array( $hub['failover'] ?? null ) ? $hub['failover'] : array(),
			'favorites'        => is_array( $hub['favorites'] ?? null ) ? $hub['favorites'] : array(),
		);
	}

	/**
	 * Card data for the providers settings page (no secrets).
	 *
	 * @return list<array<string, mixed>>
	 */
	public function provider_cards(): array {
		$cards  = array();
		$active = $this->repository->get_active_id();

		foreach ( ProviderCatalog::ids() as $id ) {
			$config = $this->repository->get_provider_config( $id );
			$cards[] = array(
				'id'           => $id,
				'label'        => ProviderCatalog::label( $id ),
				'status'       => (string) ( $config['status'] ?? 'disconnected' ),
				'model'        => (string) ( $config['model'] ?? '' ),
				'latency_ms'   => (int) ( $config['latency_ms'] ?? 0 ),
				'last_check'   => (string) ( $config['last_check'] ?? '' ),
				'endpoint'     => (string) ( $config['endpoint'] ?? '' ),
				'is_active'    => $id === $active,
				'has_api_key'  => $this->repository->get_api_key_plain( $id ) !== '',
				'requires_key' => ProviderCatalog::requires_api_key( $id ),
				'connected'    => ! empty( $config['connected'] ),
				'temperature'  => (float) ( $config['temperature'] ?? 0.2 ),
				'top_p'        => (float) ( $config['top_p'] ?? 1 ),
				'max_tokens'   => (int) ( $config['max_tokens'] ?? 1200 ),
				'timeout'      => (int) ( $config['timeout'] ?? 25 ),
				'organization' => (string) ( $config['organization'] ?? '' ),
				'streaming'    => ! empty( $config['streaming'] ),
				'retry_count'  => (int) ( $config['retry_count'] ?? 1 ),
				'manual_model' => (string) ( $config['manual_model'] ?? '' ),
			);
		}

		return $cards;
	}

	private function register_builtins(): void {
		$http = $this->http;
		$this->register( new OpenAiHubProvider( $http ) );
		$this->register( new DeepSeekHubProvider( $http ) );
		$this->register( new GeminiHubProvider( $http ) );
		$this->register( new AnthropicHubProvider( $http ) );
		$this->register( new OpenRouterHubProvider( $http ) );
		$this->register( new GroqHubProvider( $http ) );
		$this->register( new XaiHubProvider( $http ) );
		$this->register( new TogetherHubProvider( $http ) );
		$this->register( new MistralHubProvider( $http ) );
		$this->register( new CohereHubProvider( $http ) );
		$this->register( new OllamaHubProvider( $http ) );
		$this->register( new LmStudioHubProvider( $http ) );
		$this->register( new AzureOpenAiHubProvider( $http ) );
		$this->register( new CustomOpenAiHubProvider( $http ) );
	}
}
