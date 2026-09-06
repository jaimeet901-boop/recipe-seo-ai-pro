<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin controller / facade.
 *
 * Phase 2D: menus, assets, settings registration, and capability checks stay here.
 * HTML is rendered via RecipeSeoAiPro\Views\View templates under src/Views/admin/.
 */
class RSAIP_Admin {
	private RSAIP_Plugin $plugin;

	public function __construct( RSAIP_Plugin $plugin ) {
		$this->plugin = $plugin;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'manage_posts_columns', array( $this, 'add_post_id_column' ) );
		add_filter( 'manage_pages_columns', array( $this, 'add_post_id_column' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'render_post_id_column' ), 10, 2 );
		add_action( 'manage_pages_custom_column', array( $this, 'render_post_id_column' ), 10, 2 );
	}

	public function register_menu(): void {
		if ( ! current_user_can( rsaip_capability() ) ) {
			return;
		}

		add_menu_page(
			__( 'AI SEO Assistant', 'recipe-seo-ai-pro' ),
			__( 'AI SEO Assistant', 'recipe-seo-ai-pro' ),
			rsaip_capability(),
			'rsaip-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-chart-area',
			58
		);

		add_submenu_page( 'rsaip-dashboard', __( 'Dashboard', 'recipe-seo-ai-pro' ), __( 'Dashboard', 'recipe-seo-ai-pro' ), rsaip_capability(), 'rsaip-dashboard', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'rsaip-dashboard', __( 'Internal Linking', 'recipe-seo-ai-pro' ), __( 'Internal Linking', 'recipe-seo-ai-pro' ), rsaip_capability(), 'rsaip-internal-linking', array( $this, 'render_internal_linking' ) );
		add_submenu_page( 'rsaip-dashboard', __( 'Auto Internal Linking', 'recipe-seo-ai-pro' ), __( 'Auto Internal Linking', 'recipe-seo-ai-pro' ), rsaip_capability(), 'rsaip-auto-linking', array( $this, 'render_auto_linking' ) );
		add_submenu_page( 'rsaip-dashboard', __( 'Orphan Posts', 'recipe-seo-ai-pro' ), __( 'Orphan Posts', 'recipe-seo-ai-pro' ), rsaip_capability(), 'rsaip-orphans', array( $this, 'render_orphans' ) );
		add_submenu_page( 'rsaip-dashboard', __( 'SEO Audit', 'recipe-seo-ai-pro' ), __( 'SEO Audit', 'recipe-seo-ai-pro' ), rsaip_capability(), 'rsaip-audit', array( $this, 'render_audit' ) );
		add_submenu_page( 'rsaip-dashboard', __( 'Image SEO', 'recipe-seo-ai-pro' ), __( 'Image SEO', 'recipe-seo-ai-pro' ), rsaip_capability(), 'rsaip-image-seo', array( $this, 'render_image_seo' ) );
		add_submenu_page( 'rsaip-dashboard', __( 'Google Search Console', 'recipe-seo-ai-pro' ), __( 'GSC', 'recipe-seo-ai-pro' ), rsaip_capability(), 'rsaip-gsc', array( $this, 'render_gsc' ) );
		add_submenu_page( 'rsaip-dashboard', __( 'Low Hanging Fruits', 'recipe-seo-ai-pro' ), __( 'Low Hanging Fruits', 'recipe-seo-ai-pro' ), rsaip_capability(), 'rsaip-low-hanging', array( $this, 'render_low_hanging' ) );
		add_submenu_page( 'rsaip-dashboard', __( 'Recipe SEO Optimizer', 'recipe-seo-ai-pro' ), __( 'Recipe Optimizer', 'recipe-seo-ai-pro' ), rsaip_capability(), 'rsaip-recipe-optimizer', array( $this, 'render_recipe_optimizer' ) );
		add_submenu_page( 'rsaip-dashboard', __( 'Schema Validator', 'recipe-seo-ai-pro' ), __( 'Schema Validator', 'recipe-seo-ai-pro' ), rsaip_capability(), 'rsaip-schema', array( $this, 'render_schema' ) );
		add_submenu_page( 'rsaip-dashboard', __( 'XML Sitemap Auditor', 'recipe-seo-ai-pro' ), __( 'Sitemap Auditor', 'recipe-seo-ai-pro' ), rsaip_capability(), 'rsaip-sitemap', array( $this, 'render_sitemap' ) );
		add_submenu_page( 'rsaip-dashboard', __( 'Performance Analyzer', 'recipe-seo-ai-pro' ), __( 'Performance', 'recipe-seo-ai-pro' ), rsaip_capability(), 'rsaip-performance', array( $this, 'render_performance' ) );
		add_submenu_page( 'rsaip-dashboard', __( 'AI SEO Recommendations', 'recipe-seo-ai-pro' ), __( 'AI Recommendations', 'recipe-seo-ai-pro' ), rsaip_capability(), 'rsaip-ai', array( $this, 'render_ai' ) );
		add_submenu_page( 'rsaip-dashboard', __( 'Reports', 'recipe-seo-ai-pro' ), __( 'Reports', 'recipe-seo-ai-pro' ), rsaip_capability(), 'rsaip-reports', array( $this, 'render_reports' ) );
		add_submenu_page( 'rsaip-dashboard', __( 'Settings', 'recipe-seo-ai-pro' ), __( 'Settings', 'recipe-seo-ai-pro' ), rsaip_capability(), 'rsaip-settings', array( $this, 'render_settings' ) );
	}

	/**
	 * Mark plugin admin screens for design-system / dark-mode styling.
	 *
	 * @param string $classes Body classes.
	 */
	public function admin_body_class( string $classes ): string {
		if ( ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $classes;
		}
		$page = sanitize_key( (string) wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( rsaip_string_starts_with( $page, 'rsaip-' ) ) {
			$classes .= ' rsaip-admin-body';
		}
		return $classes;
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! isset( $_GET['page'] ) ) {
			return;
		}
		$page = sanitize_key( (string) wp_unslash( $_GET['page'] ) );
		if ( ! rsaip_string_starts_with( $page, 'rsaip-' ) ) {
			return;
		}

		$css_path    = RSAIP_PLUGIN_DIR . 'assets/admin.css';
		$polish_css  = RSAIP_PLUGIN_DIR . 'assets/ui-polish.css';
		$js_path     = RSAIP_PLUGIN_DIR . 'assets/admin.js';
		$polish_js   = RSAIP_PLUGIN_DIR . 'assets/ui-polish.js';
		$css_ver     = file_exists( $css_path ) ? (string) filemtime( $css_path ) : RSAIP_VERSION;
		$polish_cver = file_exists( $polish_css ) ? (string) filemtime( $polish_css ) : RSAIP_VERSION;
		$js_ver      = file_exists( $js_path ) ? (string) filemtime( $js_path ) : RSAIP_VERSION;
		$polish_jver = file_exists( $polish_js ) ? (string) filemtime( $polish_js ) : RSAIP_VERSION;

		wp_enqueue_style(
			'rsaip-admin',
			RSAIP_PLUGIN_URL . 'assets/admin.css',
			array(),
			$css_ver
		);

		wp_enqueue_style(
			'rsaip-ui-polish',
			RSAIP_PLUGIN_URL . 'assets/ui-polish.css',
			array( 'rsaip-admin' ),
			$polish_cver
		);

		wp_enqueue_script(
			'rsaip-admin',
			RSAIP_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery' ),
			$js_ver,
			true
		);

		wp_enqueue_script(
			'rsaip-ui-polish',
			RSAIP_PLUGIN_URL . 'assets/ui-polish.js',
			array( 'jquery', 'rsaip-admin' ),
			$polish_jver,
			true
		);

		wp_localize_script(
			'rsaip-admin',
			'RSAIP',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'rsaip_nonce' ),
				'neverModifyPosts'  => ( ! function_exists( 'rsaip_never_modify_posts' ) || rsaip_never_modify_posts() ) ? '1' : '0',
				'fixWithAiBlocked'  => ( ! function_exists( 'rsaip_fix_with_ai_blocked' ) || rsaip_fix_with_ai_blocked() ) ? '1' : '0',
			)
		);
	}

	public function register_settings(): void {
		register_setting(
			'rsaip_settings_group',
			rsaip_option_key(),
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => rsaip_default_settings(),
			)
		);
	}

	/**
	 * Settings API sanitize callback (facade).
	 *
	 * Business logic lives in RecipeSeoAiPro\Modules\Settings\SettingsService::sanitize()
	 * so Fix 1 (partial merge) and Fix 5 (secret blank-keep) stay centralized.
	 *
	 * @param mixed $value Submitted option value.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( $value ): array {
		return rsaip_settings_service()->sanitize( $value );
	}

	public function add_post_id_column( array $columns ): array {
		$updated = array();

		foreach ( $columns as $key => $label ) {
			$updated[ $key ] = $label;

			if ( 'title' === $key ) {
				$updated['rsaip_post_id'] = __( 'Post ID', 'recipe-seo-ai-pro' );
			}
		}

		if ( ! isset( $updated['rsaip_post_id'] ) ) {
			$updated['rsaip_post_id'] = __( 'Post ID', 'recipe-seo-ai-pro' );
		}

		return $updated;
	}

	public function render_post_id_column( string $column, int $post_id ): void {
		if ( 'rsaip_post_id' !== $column ) {
			return;
		}

		echo '<code>' . esc_html( (string) $post_id ) . '</code>';
	}

	/**
	 * Capability gate shared by all admin page facades.
	 */
	private function guard(): void {
		if ( ! current_user_can( rsaip_capability() ) ) {
			wp_die( esc_html__( 'Access denied', 'recipe-seo-ai-pro' ) );
		}
	}

	/**
	 * Render an admin view template.
	 *
	 * @param string               $template Relative path under src/Views/ without .php.
	 * @param array<string, mixed> $data     Template variables.
	 */
	private function view( string $template, array $data = array() ): void {
		\RecipeSeoAiPro\Views\View::render( $template, $data );
	}

	public function render_dashboard(): void {
		$this->guard();
		$this->view(
			'admin/dashboard',
			array(
				'title' => __( 'SEO Dashboard', 'recipe-seo-ai-pro' ),
			)
		);
	}

	public function render_internal_linking(): void {
		$this->guard();
		$this->view(
			'admin/internal-linking',
			array(
				'title'    => __( 'Internal Linking Engine', 'recipe-seo-ai-pro' ),
				'settings' => rsaip_get_settings(),
			)
		);
	}

	public function render_auto_linking(): void {
		$this->guard();
		$this->view(
			'admin/auto-linking',
			array(
				'title'      => __( 'Auto Internal Linking', 'recipe-seo-ai-pro' ),
				'settings'   => rsaip_get_settings(),
				'option_key' => rsaip_option_key(),
				'post_types' => get_post_types( array( 'public' => true ), 'objects' ),
				'statuses'   => array(
					'publish' => __( 'Publish', 'recipe-seo-ai-pro' ),
					'draft'   => __( 'Draft', 'recipe-seo-ai-pro' ),
					'future'  => __( 'Scheduled', 'recipe-seo-ai-pro' ),
					'private' => __( 'Private', 'recipe-seo-ai-pro' ),
				),
			)
		);
	}

	public function render_orphans(): void {
		$this->guard();
		$this->view(
			'admin/orphans',
			array(
				'title' => __( 'Orphan Posts Detector', 'recipe-seo-ai-pro' ),
			)
		);
	}

	public function render_audit(): void {
		$this->guard();
		$this->view(
			'admin/audit',
			array(
				'title' => __( 'SEO Audit', 'recipe-seo-ai-pro' ),
			)
		);
	}

	public function render_image_seo(): void {
		$this->guard();
		$this->view(
			'admin/image-seo',
			array(
				'title'    => __( 'Image SEO Optimizer', 'recipe-seo-ai-pro' ),
				'settings' => rsaip_get_settings(),
			)
		);
	}

	public function render_gsc(): void {
		$this->guard();
		$settings = rsaip_get_settings();
		$this->view(
			'admin/gsc',
			array(
				'title'              => __( 'Google Search Console Integration', 'recipe-seo-ai-pro' ),
				'settings'           => $settings,
				'option_key'         => rsaip_option_key(),
				'secret_placeholder' => rsaip_secret_placeholder(),
				'has_pem_secret'     => rsaip_setting_has_secret( $settings, 'gsc_private_key_pem' ),
			)
		);
	}

	public function render_low_hanging(): void {
		$this->guard();
		$this->view(
			'admin/low-hanging',
			array(
				'title' => __( 'Low Hanging Fruits Finder', 'recipe-seo-ai-pro' ),
			)
		);
	}

	public function render_recipe_optimizer(): void {
		$this->guard();
		$this->view(
			'admin/recipe-optimizer',
			array(
				'title' => __( 'Recipe SEO Optimizer', 'recipe-seo-ai-pro' ),
			)
		);
	}

	public function render_schema(): void {
		$this->guard();
		$this->view(
			'admin/schema',
			array(
				'title' => __( 'Schema Validator', 'recipe-seo-ai-pro' ),
			)
		);
	}

	public function render_sitemap(): void {
		$this->guard();
		$this->view(
			'admin/sitemap',
			array(
				'title'               => __( 'XML Sitemap Auditor', 'recipe-seo-ai-pro' ),
				'sitemap_placeholder' => home_url( '/sitemap_index.xml' ),
			)
		);
	}

	public function render_performance(): void {
		$this->guard();
		$settings = rsaip_get_settings();
		$this->view(
			'admin/performance',
			array(
				'title'                => __( 'Performance Analyzer', 'recipe-seo-ai-pro' ),
				'settings'             => $settings,
				'option_key'           => rsaip_option_key(),
				'secret_placeholder'   => rsaip_secret_placeholder(),
				'has_psi_secret'       => rsaip_setting_has_secret( $settings, 'psi_api_key' ),
				'perf_url_placeholder' => home_url( '/' ),
			)
		);
	}

	public function render_ai(): void {
		$this->guard();
		$this->view(
			'admin/ai',
			array(
				'title' => __( 'AI SEO Recommendations', 'recipe-seo-ai-pro' ),
			)
		);
	}

	public function render_reports(): void {
		$this->guard();
		$this->view(
			'admin/reports',
			array(
				'title' => __( 'Reports', 'recipe-seo-ai-pro' ),
			)
		);
	}

	public function render_settings(): void {
		$this->guard();
		$settings = rsaip_get_settings();
		$this->view(
			'admin/settings',
			array(
				'title'              => __( 'Settings', 'recipe-seo-ai-pro' ),
				'settings'           => $settings,
				'option_key'         => rsaip_option_key(),
				'secret_placeholder' => rsaip_secret_placeholder(),
				'has_ai_secret'      => rsaip_setting_has_secret( $settings, 'ai_api_key' ),
				'providers'          => array(
					'openai_compatible' => __( 'OpenAI Compatible', 'recipe-seo-ai-pro' ),
					'disabled'          => __( 'Disabled', 'recipe-seo-ai-pro' ),
				),
			)
		);
	}
}
