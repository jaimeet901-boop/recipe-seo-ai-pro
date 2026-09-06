<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSAIP_Plugin {
	private static ?RSAIP_Plugin $instance = null;

	private RSAIP_Admin $admin;
	private RSAIP_AJAX $ajax;
	private RSAIP_Link_Graph $link_graph;
	private RSAIP_Internal_Link_Suggester $suggester;
	private RSAIP_Auto_Linker $auto_linker;
	private RSAIP_Audit $audit;
	private RSAIP_Image_Optimizer $image_optimizer;
	private RSAIP_GSC $gsc;
	private RSAIP_Schema_Validator $schema_validator;
	private RSAIP_Recipe_Optimizer $recipe_optimizer;
	private RSAIP_Sitemap_Auditor $sitemap_auditor;
	private RSAIP_Performance $performance;
	private RSAIP_AI $ai;
	private RSAIP_Bulk_Optimizer $bulk_optimizer;
	private RSAIP_Reports $reports;

	public static function instance(): RSAIP_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		$this->ai              = new RSAIP_AI();
		$this->link_graph      = new RSAIP_Link_Graph();
		$this->suggester       = new RSAIP_Internal_Link_Suggester( $this->link_graph );
		$this->auto_linker     = new RSAIP_Auto_Linker( $this->suggester );
		$this->audit           = new RSAIP_Audit( $this->link_graph );
		$this->image_optimizer = new RSAIP_Image_Optimizer( $this->ai );
		$this->gsc             = new RSAIP_GSC();
		$this->schema_validator = new RSAIP_Schema_Validator();
		$this->recipe_optimizer = new RSAIP_Recipe_Optimizer();
		$this->sitemap_auditor  = new RSAIP_Sitemap_Auditor();
		$this->performance      = new RSAIP_Performance();
		$this->bulk_optimizer   = new RSAIP_Bulk_Optimizer( $this->ai, $this->audit, $this->suggester, $this->auto_linker, $this->recipe_optimizer, $this->schema_validator );
		$this->reports          = new RSAIP_Reports( $this->audit, $this->gsc );
		$this->admin            = new RSAIP_Admin( $this );
		$this->ajax             = new RSAIP_AJAX( $this );

		add_action( 'init', array( $this, 'register_hooks' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
		add_action( 'rsaip_cron_rebuild_link_graph', array( $this->link_graph, 'cron_rebuild' ) );
		add_action( 'rsaip_cron_check_broken_links', array( $this->link_graph, 'cron_check_broken_links' ) );
		add_action( 'rsaip_cron_process_bulk_queue', array( $this->bulk_optimizer, 'cron_process_queue' ) );
		add_action( 'update_option_rsaip_settings', array( $this, 'on_rsaip_settings_updated' ), 10, 2 );
		add_action( 'add_option_rsaip_settings', array( $this, 'on_rsaip_settings_added' ), 10, 2 );
	}

	/**
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 */
	public function on_rsaip_settings_updated( $old_value, $new_value ): void {
		$this->maybe_neutralize_mutation_queue( $old_value, $new_value );
	}

	/**
	 * @param string $option Option name.
	 * @param mixed  $value  New option value.
	 */
	public function on_rsaip_settings_added( $option, $value ): void {
		unset( $option );
		$this->maybe_neutralize_mutation_queue( array(), $value );
	}

	/**
	 * Retire existing-post mutation jobs when Safe Mode is ON or is being turned OFF.
	 *
	 * @param mixed $old_value Previous settings.
	 * @param mixed $new_value New settings.
	 */
	private function maybe_neutralize_mutation_queue( $old_value, $new_value ): void {
		$old_on = $this->settings_safe_mode_on( $old_value );
		$new_on = $this->settings_safe_mode_on( $new_value );
		if ( $old_on || $new_on ) {
			$this->bulk_optimizer->neutralize_existing_post_mutation_jobs();
		}
	}

	/**
	 * Missing key is treated as ON (fail-closed).
	 *
	 * @param mixed $settings Settings array or unknown.
	 */
	private function settings_safe_mode_on( $settings ): bool {
		if ( ! is_array( $settings ) ) {
			return true;
		}
		if ( ! array_key_exists( 'never_modify_posts', $settings ) ) {
			return true;
		}
		return ! empty( $settings['never_modify_posts'] );
	}

	public function register_hooks(): void {
		$settings = rsaip_get_settings();
		$frozen   = ! function_exists( 'rsaip_never_modify_posts' ) || rsaip_never_modify_posts();
		if ( $frozen ) {
			$this->bulk_optimizer->neutralize_existing_post_mutation_jobs();
			return;
		}
		if ( ! empty( $settings['auto_insert_internal_links'] ) ) {
			add_action( 'save_post', array( $this->auto_linker, 'on_save_post' ), 20, 3 );
		}
	}

	public function enqueue_public_assets(): void {
		if ( is_admin() || ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$content = (string) $post->post_content;
		if ( strpos( $content, 'RSAIP_RECIPE_CARD_START' ) === false && strpos( $content, 'rsaip-recipe-card' ) === false ) {
			return;
		}

		$public_css_path = RSAIP_PLUGIN_DIR . 'assets/public.css';
		$public_css_url  = file_exists( $public_css_path ) ? RSAIP_PLUGIN_URL . 'assets/public.css' : RSAIP_PLUGIN_URL . 'assets/admin.css';
		$public_css_ver  = file_exists( $public_css_path ) ? (string) filemtime( $public_css_path ) : (string) filemtime( RSAIP_PLUGIN_DIR . 'assets/admin.css' );
		wp_enqueue_style(
			'rsaip-public',
			$public_css_url,
			array(),
			$public_css_ver
		);

		$js_path = RSAIP_PLUGIN_DIR . 'assets/public.js';
		wp_enqueue_script(
			'rsaip-recipe-card',
			RSAIP_PLUGIN_URL . 'assets/public.js',
			array(),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : RSAIP_VERSION,
			true
		);

		$this->maybe_issue_guest_voter_cookie();

		wp_localize_script(
			'rsaip-recipe-card',
			'RSAIP',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'rsaip_public_rating' ),
				'ratingAction' => 'rsaip_public_save_recipe_rating',
			)
		);
	}

	/**
	 * Ensure anonymous visitors have a stable voter cookie before rating.
	 */
	private function maybe_issue_guest_voter_cookie(): void {
		if ( is_user_logged_in() ) {
			return;
		}

		$cookie_name = 'rsaip_voter';
		if ( isset( $_COOKIE[ $cookie_name ] ) ) {
			$raw = sanitize_text_field( (string) wp_unslash( $_COOKIE[ $cookie_name ] ) );
			if ( preg_match( '/^[a-f0-9]{32,64}$/', $raw ) ) {
				return;
			}
		}

		try {
			$token = bin2hex( random_bytes( 16 ) );
		} catch ( Exception $e ) {
			$token = wp_generate_password( 32, false, false );
		}

		$secure  = is_ssl();
		$expires = time() + YEAR_IN_SECONDS;
		$path    = COOKIEPATH ? COOKIEPATH : '/';

		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie(
				$cookie_name,
				$token,
				array(
					'expires'  => $expires,
					'path'     => $path,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => $secure,
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		} else {
			setcookie( $cookie_name, $token, $expires, $path, COOKIE_DOMAIN, $secure, true );
		}

		$_COOKIE[ $cookie_name ] = $token;
	}

	public function admin(): RSAIP_Admin {
		return $this->admin;
	}

	public function ajax(): RSAIP_AJAX {
		return $this->ajax;
	}

	public function link_graph(): RSAIP_Link_Graph {
		return $this->link_graph;
	}

	public function suggester(): RSAIP_Internal_Link_Suggester {
		return $this->suggester;
	}

	public function auto_linker(): RSAIP_Auto_Linker {
		return $this->auto_linker;
	}

	public function audit(): RSAIP_Audit {
		return $this->audit;
	}

	public function image_optimizer(): RSAIP_Image_Optimizer {
		return $this->image_optimizer;
	}

	public function gsc(): RSAIP_GSC {
		return $this->gsc;
	}

	public function schema_validator(): RSAIP_Schema_Validator {
		return $this->schema_validator;
	}

	public function recipe_optimizer(): RSAIP_Recipe_Optimizer {
		return $this->recipe_optimizer;
	}

	public function sitemap_auditor(): RSAIP_Sitemap_Auditor {
		return $this->sitemap_auditor;
	}

	public function performance(): RSAIP_Performance {
		return $this->performance;
	}

	public function ai(): RSAIP_AI {
		return $this->ai;
	}

	public function reports(): RSAIP_Reports {
		return $this->reports;
	}

	public function bulk_optimizer(): RSAIP_Bulk_Optimizer {
		return $this->bulk_optimizer;
	}
}
