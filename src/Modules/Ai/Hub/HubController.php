<?php
declare(strict_types=1);

/**
 * Admin UI + AJAX for the AI Provider Hub.
 *
 * New AJAX actions only — does not modify existing feature AJAX handlers.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub;

use RecipeSeoAiPro\Support\Security\SecretGuard;
use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HubController
 */
final class HubController {

	public const PAGE_SLUG = 'rsaip-ai-providers';

	public const NONCE_ACTION = 'rsaip_ai_hub';

	private ProviderManager $manager;

	public function __construct( ProviderManager $manager ) {
		$this->manager = $manager;
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 18 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'wp_ajax_rsaip_ai_hub_save', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_rsaip_ai_hub_test', array( $this, 'ajax_test' ) );
		add_action( 'wp_ajax_rsaip_ai_hub_disconnect', array( $this, 'ajax_disconnect' ) );
		add_action( 'wp_ajax_rsaip_ai_hub_activate', array( $this, 'ajax_activate' ) );
		add_action( 'wp_ajax_rsaip_ai_hub_models', array( $this, 'ajax_models' ) );
		add_action( 'wp_ajax_rsaip_ai_hub_detect', array( $this, 'ajax_detect' ) );
		add_action( 'wp_ajax_rsaip_ai_hub_failover', array( $this, 'ajax_failover' ) );
		add_action( 'wp_ajax_rsaip_ai_hub_favorite', array( $this, 'ajax_favorite' ) );
	}

	public function register_menu(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			return;
		}

		add_submenu_page(
			'rsaip-dashboard',
			__( 'AI Providers', 'recipe-seo-ai-pro' ),
			__( 'AI Providers', 'recipe-seo-ai-pro' ),
			$this->capability(),
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( string $hook_suffix ): void {
		unset( $hook_suffix );
		if ( ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$page = sanitize_key( (string) wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $page !== self::PAGE_SLUG ) {
			return;
		}

		$css = RSAIP_PLUGIN_DIR . 'assets/ai-hub.css';
		$js  = RSAIP_PLUGIN_DIR . 'assets/ai-hub.js';
		$css_ver = file_exists( $css ) ? (string) filemtime( $css ) : RSAIP_VERSION;
		$js_ver  = file_exists( $js ) ? (string) filemtime( $js ) : RSAIP_VERSION;

		wp_enqueue_style( 'rsaip-admin', RSAIP_PLUGIN_URL . 'assets/admin.css', array(), RSAIP_VERSION );
		wp_enqueue_style( 'rsaip-ai-hub', RSAIP_PLUGIN_URL . 'assets/ai-hub.css', array( 'rsaip-admin' ), $css_ver );
		wp_enqueue_script( 'rsaip-ai-hub', RSAIP_PLUGIN_URL . 'assets/ai-hub.js', array( 'jquery' ), $js_ver, true );

		wp_localize_script(
			'rsaip-ai-hub',
			'RSAIP_AI_HUB',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'i18n'    => array(
					'saved'        => __( 'Provider settings saved.', 'recipe-seo-ai-pro' ),
					'testing'      => __( 'Testing connection…', 'recipe-seo-ai-pro' ),
					'error'        => __( 'Request failed.', 'recipe-seo-ai-pro' ),
					'disconnected' => __( 'Provider disconnected.', 'recipe-seo-ai-pro' ),
					'activated'    => __( 'Provider activated.', 'recipe-seo-ai-pro' ),
					'fetching'     => __( 'Fetching models…', 'recipe-seo-ai-pro' ),
					'loaded'       => __( 'Models loaded.', 'recipe-seo-ai-pro' ),
					'default_set'  => __( 'Default model set and synced.', 'recipe-seo-ai-pro' ),
					'favorited'    => __( 'Added to favorites.', 'recipe-seo-ai-pro' ),
					'unfavorited'  => __( 'Removed from favorites.', 'recipe-seo-ai-pro' ),
					'manual_on'    => __( 'Manual entry enabled.', 'recipe-seo-ai-pro' ),
					'no_models'    => __( 'No models found. Use Manual Entry.', 'recipe-seo-ai-pro' ),
				),
			)
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'recipe-seo-ai-pro' ) );
		}

		View::render(
			'admin/ai-providers',
			array(
				'title'     => __( 'AI Provider Hub', 'recipe-seo-ai-pro' ),
				'cards'     => $this->manager->provider_cards(),
				'dashboard' => $this->manager->dashboard(),
				'logs'      => $this->manager->logger()->recent( 40 ),
				'mask'      => SecretGuard::mask(),
			)
		);
	}

	public function ajax_save(): void {
		$this->guard();
		$provider = sanitize_key( (string) ( $_POST['provider'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$input    = array(
			'api_key'      => isset( $_POST['api_key'] ) ? (string) wp_unslash( $_POST['api_key'] ) : SecretGuard::mask(), // phpcs:ignore
			'endpoint'     => isset( $_POST['endpoint'] ) ? (string) wp_unslash( $_POST['endpoint'] ) : null, // phpcs:ignore
			'model'        => isset( $_POST['model'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['model'] ) ) : null, // phpcs:ignore
			'manual_model' => isset( $_POST['manual_model'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['manual_model'] ) ) : null, // phpcs:ignore
			'temperature'  => isset( $_POST['temperature'] ) ? (float) $_POST['temperature'] : null, // phpcs:ignore
			'top_p'        => isset( $_POST['top_p'] ) ? (float) $_POST['top_p'] : null, // phpcs:ignore
			'max_tokens'   => isset( $_POST['max_tokens'] ) ? (int) $_POST['max_tokens'] : null, // phpcs:ignore
			'timeout'      => isset( $_POST['timeout'] ) ? (int) $_POST['timeout'] : null, // phpcs:ignore
			'organization' => isset( $_POST['organization'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['organization'] ) ) : null, // phpcs:ignore
			'streaming'    => ! empty( $_POST['streaming'] ), // phpcs:ignore
			'retry_count'  => isset( $_POST['retry_count'] ) ? (int) $_POST['retry_count'] : null, // phpcs:ignore
			'activate'     => ! empty( $_POST['activate'] ), // phpcs:ignore
		);

		if ( isset( $_POST['custom_headers'] ) ) { // phpcs:ignore
			$raw = (string) wp_unslash( $_POST['custom_headers'] ); // phpcs:ignore
			$decoded = json_decode( $raw, true );
			$input['custom_headers'] = is_array( $decoded ) ? $decoded : array();
		}

		// Drop nulls so blank-keep / partial updates work.
		$input = array_filter(
			$input,
			static function ( $v ) {
				return null !== $v;
			}
		);

		$result = $this->manager->configure_provider( $provider, $input );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'config'    => $result,
				'dashboard' => $this->manager->dashboard(),
				'cards'     => $this->manager->provider_cards(),
			)
		);
	}

	public function ajax_test(): void {
		$this->guard();
		$provider = sanitize_key( (string) ( $_POST['provider'] ?? '' ) ); // phpcs:ignore
		$result   = $this->manager->test_provider( $provider );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'cards'   => $this->manager->provider_cards(),
				),
				400
			);
		}
		wp_send_json_success(
			array(
				'test'      => $result,
				'dashboard' => $this->manager->dashboard(),
				'cards'     => $this->manager->provider_cards(),
			)
		);
	}

	public function ajax_disconnect(): void {
		$this->guard();
		$provider = sanitize_key( (string) ( $_POST['provider'] ?? '' ) ); // phpcs:ignore
		$this->manager->disconnect_provider( $provider );
		wp_send_json_success(
			array(
				'cards'     => $this->manager->provider_cards(),
				'dashboard' => $this->manager->dashboard(),
			)
		);
	}

	public function ajax_activate(): void {
		$this->guard();
		$provider = sanitize_key( (string) ( $_POST['provider'] ?? '' ) ); // phpcs:ignore
		if ( ! $this->manager->has( $provider ) ) {
			wp_send_json_error( array( 'message' => 'Unknown provider' ), 400 );
		}
		$this->manager->repository()->set_active( $provider );
		wp_send_json_success(
			array(
				'cards'     => $this->manager->provider_cards(),
				'dashboard' => $this->manager->dashboard(),
			)
		);
	}

	public function ajax_models(): void {
		$this->guard();
		$provider_id = sanitize_key( (string) ( $_POST['provider'] ?? '' ) ); // phpcs:ignore
		$refresh     = ! empty( $_POST['refresh'] ); // phpcs:ignore

		if ( ! $this->manager->has( $provider_id ) ) {
			wp_send_json_error(
				array(
					'message'         => 'Unknown provider',
					'manual_required' => true,
					'models'          => array(),
				),
				400
			);
		}

		// Always return a usable payload (manual entry never blocked).
		$result = $this->manager->discover_models( $provider_id, $refresh );

		// BC: also expose flat id list for any older JS expecting string[].
		$flat = array();
		foreach ( $result['models'] as $row ) {
			if ( is_array( $row ) && ! empty( $row['id'] ) ) {
				$flat[] = (string) $row['id'];
			} elseif ( is_string( $row ) ) {
				$flat[] = $row;
			}
		}

		$payload = array_merge(
			$result,
			array(
				'models_flat' => $flat,
				// Keep `models` as rich objects for Phase 6.2 UI; flat list in models_flat.
			)
		);

		// Soft-warn when discovery failed but fallbacks exist.
		if ( ( $result['error'] ?? '' ) !== '' && ( $result['discovery'] ?? '' ) !== 'api' ) {
			$payload['message'] = (string) $result['error'];
		}

		wp_send_json_success( $payload );
	}

	public function ajax_detect(): void {
		$this->guard();
		$endpoint = isset( $_POST['endpoint'] ) ? (string) wp_unslash( $_POST['endpoint'] ) : ''; // phpcs:ignore
		$id       = EndpointDetector::detect( $endpoint );
		wp_send_json_success(
			array(
				'provider' => $id,
				'label'    => $id !== '' ? ProviderCatalog::label( $id ) : '',
			)
		);
	}

	public function ajax_failover(): void {
		$this->guard();
		$enabled = ! empty( $_POST['failover_enabled'] ); // phpcs:ignore
		$chain   = array();
		if ( isset( $_POST['failover'] ) && is_array( $_POST['failover'] ) ) { // phpcs:ignore
			foreach ( $_POST['failover'] as $fid ) { // phpcs:ignore
				$fid = sanitize_key( (string) $fid );
				if ( $fid !== '' ) {
					$chain[] = $fid;
				}
			}
		} elseif ( isset( $_POST['failover'] ) && is_string( $_POST['failover'] ) ) { // phpcs:ignore
			foreach ( explode( ',', (string) wp_unslash( $_POST['failover'] ) ) as $fid ) { // phpcs:ignore
				$fid = sanitize_key( trim( $fid ) );
				if ( $fid !== '' ) {
					$chain[] = $fid;
				}
			}
		}
		$data = $this->manager->repository()->all();
		$data['failover_enabled'] = $enabled;
		$data['failover']         = array_values( array_unique( $chain ) );
		$this->manager->repository()->write( $data );
		wp_send_json_success( array( 'dashboard' => $this->manager->dashboard() ) );
	}

	public function ajax_favorite(): void {
		$this->guard();
		$model = isset( $_POST['model'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['model'] ) ) : ''; // phpcs:ignore
		$add   = ! empty( $_POST['add'] ); // phpcs:ignore
		$data  = $this->manager->repository()->all();
		$favs  = is_array( $data['favorites'] ?? null ) ? $data['favorites'] : array();
		if ( $add && $model !== '' ) {
			$favs[] = $model;
		} else {
			$favs = array_values(
				array_filter(
					$favs,
					static function ( $m ) use ( $model ) {
						return (string) $m !== $model;
					}
				)
			);
		}
		$data['favorites'] = array_values( array_unique( $favs ) );
		$this->manager->repository()->write( $data );
		wp_send_json_success( array( 'favorites' => $data['favorites'] ) );
	}

	private function guard(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
	}

	private function capability(): string {
		return function_exists( 'rsaip_capability' ) ? rsaip_capability() : 'manage_options';
	}
}
