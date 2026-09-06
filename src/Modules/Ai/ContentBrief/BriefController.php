<?php
declare(strict_types=1);

/**
 * Admin + AJAX controller for AI Content Brief (isolated from RSAIP_AJAX).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentBrief;

use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BriefController
 */
final class BriefController {

	public const PAGE_SLUG = 'rsaip-content-brief';

	public const AJAX_ACTION = 'rsaip_content_brief_generate';

	public const NONCE_ACTION = 'rsaip_content_brief';

	private ContentBriefService $service;

	private BriefViewModel $view_model;

	public function __construct( ContentBriefService $service, BriefViewModel $view_model ) {
		$this->service    = $service;
		$this->view_model = $view_model;
	}

	/**
	 * Register menu, assets, and AJAX (called from ContentBriefModule::boot).
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_generate' ) );
	}

	/**
	 * Add submenu under the existing AI SEO Assistant menu — does not alter the AI Recommendations page.
	 */
	public function register_menu(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			return;
		}

		add_submenu_page(
			'rsaip-dashboard',
			__( 'AI Content Brief', 'recipe-seo-ai-pro' ),
			__( 'AI Content Brief', 'recipe-seo-ai-pro' ),
			$this->capability(),
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$page = sanitize_key( (string) wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $page !== self::PAGE_SLUG ) {
			return;
		}

		$js_path = RSAIP_PLUGIN_DIR . 'assets/content-brief.js';
		$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : RSAIP_VERSION;

		// Reuse shared admin CSS look; do not alter admin.js behavior.
		$css_path = RSAIP_PLUGIN_DIR . 'assets/admin.css';
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : RSAIP_VERSION;
		wp_enqueue_style(
			'rsaip-admin',
			RSAIP_PLUGIN_URL . 'assets/admin.css',
			array(),
			$css_ver
		);

		wp_enqueue_script(
			'rsaip-content-brief',
			RSAIP_PLUGIN_URL . 'assets/content-brief.js',
			array( 'jquery' ),
			$js_ver,
			true
		);

		wp_localize_script(
			'rsaip-content-brief',
			'RSAIP_CONTENT_BRIEF',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
				'action'   => self::AJAX_ACTION,
				'i18n'     => array(
					'generating' => __( 'Generating brief…', 'recipe-seo-ai-pro' ),
					'error'      => __( 'Could not generate brief.', 'recipe-seo-ai-pro' ),
					'copied'     => __( 'Copied.', 'recipe-seo-ai-pro' ),
				),
				'labels'   => $this->view_model->section_labels(),
			)
		);

		unset( $hook_suffix );
	}

	/**
	 * Render admin page via View layer.
	 */
	public function render_page(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'Access denied', 'recipe-seo-ai-pro' ) );
		}

		View::render( 'admin/content-brief', $this->view_model->for_admin_page() );
	}

	/**
	 * AJAX: generate content brief.
	 */
	public function ajax_generate(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'recipe-seo-ai-pro' ) ), 403 );
		}

		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$topic    = isset( $_POST['topic'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['topic'] ) ) : '';
		$audience = isset( $_POST['audience'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['audience'] ) ) : '';
		$locale   = isset( $_POST['locale'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['locale'] ) ) : '';
		$notes    = isset( $_POST['notes'] ) ? sanitize_textarea_field( (string) wp_unslash( $_POST['notes'] ) ) : '';

		$result = $this->service->generate(
			$topic,
			array(
				'audience' => $audience,
				'locale'   => $locale !== '' ? $locale : (string) get_locale(),
				'notes'    => $notes,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				400
			);
		}

		wp_send_json_success( $this->view_model->present( $result ) );
	}

	private function capability(): string {
		return function_exists( 'rsaip_capability' ) ? rsaip_capability() : 'manage_options';
	}
}
