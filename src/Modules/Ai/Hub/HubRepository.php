<?php
declare(strict_types=1);

/**
 * Persistence for AI Provider Hub configuration.
 *
 * Stored in option rsaip_ai_hub (separate from rsaip_settings for BC).
 * API keys are encrypted via SecretGuard. Legacy ai_* settings are synced
 * so existing modules keep working unchanged.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub;

use RecipeSeoAiPro\Support\Security\SecretGuard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HubRepository
 */
final class HubRepository {

	public const OPTION = 'rsaip_ai_hub';

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, null );
		if ( ! is_array( $stored ) ) {
			$stored = $this->seed_from_legacy();
			$this->write( $stored );
		}
		return $this->normalize( $stored );
	}

	/**
	 * @param array<string, mixed> $data Full hub payload.
	 */
	public function write( array $data ): void {
		update_option( self::OPTION, $this->normalize( $data ), false );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_provider_config( string $provider_id ): array {
		$data = $this->all();
		$providers = isset( $data['providers'] ) && is_array( $data['providers'] ) ? $data['providers'] : array();
		$id = sanitize_key( $provider_id );
		$config = isset( $providers[ $id ] ) && is_array( $providers[ $id ] ) ? $providers[ $id ] : array();
		return $this->normalize_provider( $id, $config );
	}

	/**
	 * @param array<string, mixed> $config Partial config (api_key may be plaintext).
	 */
	public function save_provider_config( string $provider_id, array $config ): array {
		$id   = sanitize_key( $provider_id );
		$data = $this->all();
		$prev = $this->get_provider_config( $id );
		$merged = array_merge( $prev, $config );

		if ( array_key_exists( 'api_key', $config ) ) {
			$key = is_string( $config['api_key'] ) ? trim( $config['api_key'] ) : '';
			if ( $key === '' || $key === SecretGuard::mask() ) {
				$merged['api_key'] = $prev['api_key'];
			} else {
				$merged['api_key'] = SecretGuard::encrypt( $key );
			}
		}

		$data['providers'][ $id ] = $this->normalize_provider( $id, $merged );
		$this->write( $data );
		return $data['providers'][ $id ];
	}

	public function set_active( string $provider_id ): void {
		$data           = $this->all();
		$data['active'] = sanitize_key( $provider_id );
		$this->write( $data );
		// Auto-default: sync selected model to legacy ai_model for BC.
		$this->sync_legacy_settings();
	}

	public function get_active_id(): string {
		$data = $this->all();
		$active = is_string( $data['active'] ?? null ) ? sanitize_key( (string) $data['active'] ) : 'openai';
		return $active !== '' ? $active : 'openai';
	}

	/**
	 * Decrypted API key for a provider (never for HTML output).
	 */
	public function get_api_key_plain( string $provider_id ): string {
		$config = $this->get_provider_config( $provider_id );
		return SecretGuard::decrypt( (string) ( $config['api_key'] ?? '' ) );
	}

	/**
	 * Absorb classic rsaip_settings ai_* into the hub so legacy Settings remain effective.
	 */
	public function absorb_legacy_settings(): void {
		if ( ! function_exists( 'rsaip_get_settings' ) ) {
			return;
		}

		$settings = rsaip_get_settings();
		if ( ( $settings['ai_provider'] ?? '' ) === 'disabled' ) {
			return;
		}

		$endpoint = is_string( $settings['ai_endpoint'] ?? null ) ? trim( (string) $settings['ai_endpoint'] ) : '';
		$model    = is_string( $settings['ai_model'] ?? null ) ? trim( (string) $settings['ai_model'] ) : '';
		$key      = is_string( $settings['ai_api_key'] ?? null ) ? trim( (string) $settings['ai_api_key'] ) : '';
		$timeout  = isset( $settings['ai_timeout_seconds'] ) ? (int) $settings['ai_timeout_seconds'] : 0;

		if ( $endpoint === '' && $model === '' && $key === '' ) {
			return;
		}

		$detected = $endpoint !== '' ? EndpointDetector::detect( $endpoint ) : '';
		$active   = $detected !== '' ? $detected : $this->get_active_id();

		$current  = $this->get_provider_config( $active );
		$cur_key  = SecretGuard::decrypt( (string) ( $current['api_key'] ?? '' ) );
		$changed  = false;
		$patch    = array();

		if ( $endpoint !== '' && $endpoint !== (string) ( $current['endpoint'] ?? '' ) ) {
			$patch['endpoint'] = $endpoint;
			$changed           = true;
		}
		if ( $model !== '' && $model !== (string) ( $current['model'] ?? '' ) ) {
			$patch['model'] = $model;
			$changed        = true;
		}
		if ( $timeout > 0 && $timeout !== (int) ( $current['timeout'] ?? 0 ) ) {
			$patch['timeout'] = $timeout;
			$changed          = true;
		}
		if ( $key !== '' && $key !== $cur_key ) {
			$patch['api_key'] = $key;
			$changed          = true;
		}

		if ( $changed ) {
			$this->save_provider_config( $active, $patch );
		}

		if ( $detected !== '' && $detected !== $this->get_active_id() ) {
			$data           = $this->all();
			$data['active'] = $detected;
			$this->write( $data );
		}
	}

	/**
	 * Push active hub provider into classic ai_* settings for BC.
	 */
	public function sync_legacy_settings(): void {
		if ( ! function_exists( 'rsaip_get_settings' ) || ! function_exists( 'rsaip_update_settings' ) ) {
			return;
		}

		$settings = rsaip_get_settings();
		if ( ( $settings['ai_provider'] ?? '' ) === 'disabled' ) {
			return;
		}

		$active = $this->get_active_id();
		$config = $this->get_provider_config( $active );
		$key    = SecretGuard::decrypt( (string) ( $config['api_key'] ?? '' ) );

		$patch = array(
			'ai_provider'        => 'openai_compatible',
			'ai_endpoint'        => (string) ( $config['endpoint'] ?? '' ),
			'ai_model'           => (string) ( $config['model'] ?? '' ),
			'ai_timeout_seconds' => (int) ( $config['timeout'] ?? 25 ),
		);
		if ( $key !== '' ) {
			$patch['ai_api_key'] = $key;
		}

		rsaip_update_settings( $patch );
	}

	/**
	 * Import classic settings into hub when hub option is missing.
	 *
	 * @return array<string, mixed>
	 */
	private function seed_from_legacy(): array {
		$settings = function_exists( 'rsaip_get_settings' ) ? rsaip_get_settings() : array();
		$endpoint = is_string( $settings['ai_endpoint'] ?? null ) ? (string) $settings['ai_endpoint'] : '';
		$model    = is_string( $settings['ai_model'] ?? null ) ? (string) $settings['ai_model'] : 'gpt-4o-mini';
		$key      = is_string( $settings['ai_api_key'] ?? null ) ? (string) $settings['ai_api_key'] : '';
		$timeout  = isset( $settings['ai_timeout_seconds'] ) ? (int) $settings['ai_timeout_seconds'] : 25;

		$detected = EndpointDetector::detect( $endpoint );
		$active   = $detected !== '' ? $detected : 'openai';

		$data = $this->defaults();
		$data['active'] = $active;
		$data['providers'][ $active ] = $this->normalize_provider(
			$active,
			array(
				'endpoint'  => $endpoint !== '' ? $endpoint : ProviderCatalog::default_endpoint( $active ),
				'model'     => $model,
				'api_key'   => $key !== '' ? SecretGuard::encrypt( $key ) : '',
				'timeout'   => $timeout,
				'status'    => $key !== '' ? 'configured' : 'disconnected',
			)
		);

		return $data;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		$providers = array();
		foreach ( ProviderCatalog::ids() as $id ) {
			$providers[ $id ] = $this->normalize_provider( $id, array() );
		}

		return array(
			'active'           => 'openai',
			'failover_enabled' => false,
			'failover'         => array( 'deepseek', 'openrouter' ),
			'favorites'        => array(),
			'providers'        => $providers,
			'stats'            => array(
				'requests_today'   => 0,
				'tokens_today'     => 0,
				'cost_today'       => 0.0,
				'latency_samples'  => array(),
				'last_error'       => '',
				'stats_day'        => gmdate( 'Y-m-d' ),
			),
		);
	}

	/**
	 * @param array<string, mixed> $data Raw.
	 * @return array<string, mixed>
	 */
	private function normalize( array $data ): array {
		$base = $this->defaults();
		$out  = array_merge( $base, $data );

		$out['active']           = sanitize_key( (string) $out['active'] );
		$out['failover_enabled'] = ! empty( $out['failover_enabled'] );
		$failover = array();
		if ( isset( $out['failover'] ) && is_array( $out['failover'] ) ) {
			foreach ( $out['failover'] as $fid ) {
				$fid = sanitize_key( (string) $fid );
				if ( $fid !== '' ) {
					$failover[] = $fid;
				}
			}
		}
		$out['failover'] = array_values( array_unique( $failover ) );

		$favorites = array();
		if ( isset( $out['favorites'] ) && is_array( $out['favorites'] ) ) {
			foreach ( $out['favorites'] as $fav ) {
				$favorites[] = sanitize_text_field( (string) $fav );
			}
		}
		$out['favorites'] = array_values( array_unique( array_filter( $favorites ) ) );

		$providers = array();
		foreach ( ProviderCatalog::ids() as $id ) {
			$raw = isset( $out['providers'][ $id ] ) && is_array( $out['providers'][ $id ] )
				? $out['providers'][ $id ]
				: array();
			$providers[ $id ] = $this->normalize_provider( $id, $raw );
		}
		// Preserve unknown custom keys if any.
		if ( isset( $out['providers'] ) && is_array( $out['providers'] ) ) {
			foreach ( $out['providers'] as $pid => $pcfg ) {
				$pid = sanitize_key( (string) $pid );
				if ( $pid === '' || isset( $providers[ $pid ] ) || ! is_array( $pcfg ) ) {
					continue;
				}
				$providers[ $pid ] = $this->normalize_provider( $pid, $pcfg );
			}
		}
		$out['providers'] = $providers;

		$stats = isset( $out['stats'] ) && is_array( $out['stats'] ) ? $out['stats'] : array();
		$out['stats'] = array_merge( $base['stats'], $stats );

		return $out;
	}

	/**
	 * @param array<string, mixed> $config Raw.
	 * @return array<string, mixed>
	 */
	private function normalize_provider( string $id, array $config ): array {
		$defaults = array(
			'api_key'        => '',
			'endpoint'       => ProviderCatalog::default_endpoint( $id ),
			'model'          => ProviderCatalog::default_model( $id ),
			'temperature'    => 0.2,
			'top_p'          => 1.0,
			'max_tokens'     => 1200,
			'timeout'        => 25,
			'organization'   => '',
			'custom_headers' => array(),
			'streaming'      => false,
			'retry_count'    => 1,
			'status'         => 'disconnected',
			'latency_ms'     => 0,
			'last_check'     => '',
			'last_error'     => '',
			'manual_model'   => '',
			'connected'      => false,
		);

		$merged = array_merge( $defaults, $config );
		$merged['endpoint']     = esc_url_raw( (string) $merged['endpoint'] );
		$merged['model']        = sanitize_text_field( (string) $merged['model'] );
		$merged['manual_model'] = sanitize_text_field( (string) $merged['manual_model'] );
		$merged['organization'] = sanitize_text_field( (string) $merged['organization'] );
		$merged['temperature']  = max( 0, min( 2, (float) $merged['temperature'] ) );
		$merged['top_p']        = max( 0, min( 1, (float) $merged['top_p'] ) );
		$merged['max_tokens']   = max( 64, min( 128000, (int) $merged['max_tokens'] ) );
		$merged['timeout']      = max( 5, min( 120, (int) $merged['timeout'] ) );
		$merged['retry_count']  = max( 0, min( 5, (int) $merged['retry_count'] ) );
		$merged['streaming']    = ! empty( $merged['streaming'] );
		$merged['connected']    = ! empty( $merged['connected'] );
		$merged['latency_ms']   = max( 0, (int) $merged['latency_ms'] );
		$merged['status']       = sanitize_key( (string) $merged['status'] );
		$merged['last_check']   = sanitize_text_field( (string) $merged['last_check'] );
		$merged['last_error']   = sanitize_text_field( (string) $merged['last_error'] );
		$merged['api_key']      = is_string( $merged['api_key'] ) ? $merged['api_key'] : '';

		$headers = array();
		if ( isset( $merged['custom_headers'] ) && is_array( $merged['custom_headers'] ) ) {
			foreach ( $merged['custom_headers'] as $hk => $hv ) {
				$hk = sanitize_text_field( (string) $hk );
				$hv = sanitize_text_field( (string) $hv );
				if ( $hk !== '' && $hv !== '' && ! preg_match( '/authorization/i', $hk ) ) {
					$headers[ $hk ] = $hv;
				}
			}
		}
		$merged['custom_headers'] = $headers;

		return $merged;
	}

	/**
	 * Update rolling dashboard stats after a request.
	 */
	public function record_stats( int $latency_ms, int $total_tokens, float $cost, bool $success, string $error = '' ): void {
		$data  = $this->all();
		$stats = is_array( $data['stats'] ?? null ) ? $data['stats'] : array();
		$day   = gmdate( 'Y-m-d' );
		if ( ( $stats['stats_day'] ?? '' ) !== $day ) {
			$stats['requests_today']  = 0;
			$stats['tokens_today']    = 0;
			$stats['cost_today']      = 0.0;
			$stats['latency_samples'] = array();
			$stats['stats_day']       = $day;
		}
		$stats['requests_today'] = (int) $stats['requests_today'] + 1;
		$stats['tokens_today']   = (int) $stats['tokens_today'] + max( 0, $total_tokens );
		$stats['cost_today']     = (float) $stats['cost_today'] + max( 0, $cost );
		$samples = isset( $stats['latency_samples'] ) && is_array( $stats['latency_samples'] ) ? $stats['latency_samples'] : array();
		$samples[] = max( 0, $latency_ms );
		$stats['latency_samples'] = array_slice( $samples, -50 );
		if ( ! $success && $error !== '' ) {
			$stats['last_error'] = sanitize_text_field( $error );
		}
		$data['stats'] = $stats;
		$this->write( $data );
	}
}
