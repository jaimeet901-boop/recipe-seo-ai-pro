<?php
declare(strict_types=1);

/**
 * Admin controller for Unified SEO Workspace (Phase 4.2).
 *
 * Navigation hub only — no new AJAX, no business logic, no DB writes.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Workspace;

use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WorkspaceController
 */
final class WorkspaceController {

	public const PAGE_SLUG = 'rsaip-seo-workspace';

	private WorkspaceViewModel $view_model;

	public function __construct( WorkspaceViewModel $view_model ) {
		$this->view_model = $view_model;
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 16 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			return;
		}

		add_submenu_page(
			'rsaip-dashboard',
			__( 'SEO Workspace', 'recipe-seo-ai-pro' ),
			__( 'SEO Workspace', 'recipe-seo-ai-pro' ),
			$this->capability(),
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * @param string $hook_suffix Hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$page = sanitize_key( (string) wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $page !== self::PAGE_SLUG ) {
			return;
		}

		$css_path = RSAIP_PLUGIN_DIR . 'assets/admin.css';
		wp_enqueue_style(
			'rsaip-admin',
			RSAIP_PLUGIN_URL . 'assets/admin.css',
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : RSAIP_VERSION
		);

		$ws_css = RSAIP_PLUGIN_DIR . 'assets/seo-workspace.css';
		wp_enqueue_style(
			'rsaip-seo-workspace',
			RSAIP_PLUGIN_URL . 'assets/seo-workspace.css',
			array( 'rsaip-admin' ),
			file_exists( $ws_css ) ? (string) filemtime( $ws_css ) : RSAIP_VERSION
		);

		unset( $hook_suffix );
	}

	public function render_page(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'Access denied', 'recipe-seo-ai-pro' ) );
		}
		$project_id = isset( $_GET['project_id'] ) ? absint( wp_unslash( $_GET['project_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		View::render( 'admin/seo-workspace', $this->view_model->for_admin_page( $project_id ) );
	}

	private function capability(): string {
		return function_exists( 'rsaip_capability' ) ? rsaip_capability() : 'manage_options';
	}
}
