<?php
declare(strict_types=1);

/**
 * Admin + AJAX for AI Keyword Research (Phase 3.5).
 *
 * Does not alter Keyword Workspace, Projects, or Content Brief controllers.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Keywords\Research;

use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KeywordResearchController
 */
final class KeywordResearchController {

	public const PAGE_SLUG    = 'rsaip-keyword-research';
	public const NONCE_ACTION = 'rsaip_keyword_research';

	private KeywordResearchService $service;

	private KeywordResearchViewModel $view_model;

	public function __construct( KeywordResearchService $service, KeywordResearchViewModel $view_model ) {
		$this->service    = $service;
		$this->view_model = $view_model;
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 24 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_rsaip_keyword_research_generate', array( $this, 'ajax_generate' ) );
		add_action( 'wp_ajax_rsaip_keyword_research_save', array( $this, 'ajax_save' ) );
	}

	public function register_menu(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			return;
		}

		add_submenu_page(
			'rsaip-dashboard',
			__( 'AI Keyword Research', 'recipe-seo-ai-pro' ),
			__( 'AI Keyword Research', 'recipe-seo-ai-pro' ),
			$this->capability(),
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * @param string $hook_suffix Admin hook.
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
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : RSAIP_VERSION;
		wp_enqueue_style( 'rsaip-admin', RSAIP_PLUGIN_URL . 'assets/admin.css', array(), $css_ver );

		$js_path = RSAIP_PLUGIN_DIR . 'assets/keyword-research.js';
		$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : RSAIP_VERSION;
		wp_enqueue_script( 'rsaip-keyword-research', RSAIP_PLUGIN_URL . 'assets/keyword-research.js', array( 'jquery' ), $js_ver, true );

		$project_id = isset( $_GET['project_id'] ) ? absint( wp_unslash( $_GET['project_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		wp_localize_script(
			'rsaip-keyword-research',
			'RSAIP_KEYWORD_RESEARCH',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
				'projectId' => $project_id,
				'workspaceUrl' => admin_url( 'admin.php?page=rsaip-keyword-workspace' ),
				'actions'   => array(
					'generate' => 'rsaip_keyword_research_generate',
					'save'     => 'rsaip_keyword_research_save',
				),
				'i18n'      => array(
					'generating' => __( 'Generating keywords…', 'recipe-seo-ai-pro' ),
					'saving'     => __( 'Saving selected keywords…', 'recipe-seo-ai-pro' ),
					'saved'      => __( 'Keywords saved to Keyword Workspace.', 'recipe-seo-ai-pro' ),
					'error'      => __( 'Request failed.', 'recipe-seo-ai-pro' ),
					'selectOne'  => __( 'Select at least one keyword.', 'recipe-seo-ai-pro' ),
					'needProject'=> __( 'Select a project first.', 'recipe-seo-ai-pro' ),
				),
			)
		);

		unset( $hook_suffix );
	}

	public function render_page(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'Access denied', 'recipe-seo-ai-pro' ) );
		}
		$project_id = isset( $_GET['project_id'] ) ? absint( wp_unslash( $_GET['project_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		View::render( 'admin/keyword-research', $this->view_model->for_admin_page( $project_id ) );
	}

	public function ajax_generate(): void {
		$this->guard();

		$result = $this->service->generate(
			$this->post_text( 'seed' ),
			array(
				'language' => $this->post_text( 'language', (string) get_locale() ),
				'country'  => $this->post_text( 'country' ),
				'audience' => $this->post_text( 'audience' ),
				'niche'    => $this->post_text( 'niche' ),
				'notes'    => isset( $_POST['notes'] ) ? sanitize_textarea_field( (string) wp_unslash( $_POST['notes'] ) ) : '',
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->respond_error( $result );
		}

		wp_send_json_success( $this->view_model->present( $result ) );
	}

	public function ajax_save(): void {
		$this->guard();

		$selected = array();
		if ( isset( $_POST['keywords'] ) ) {
			$raw = wp_unslash( $_POST['keywords'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( is_string( $raw ) ) {
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					$selected = $decoded;
				}
			} elseif ( is_array( $raw ) ) {
				$selected = $raw;
			}
		}

		$clean = array();
		foreach ( $selected as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$keyword = isset( $row['keyword'] ) ? sanitize_text_field( (string) $row['keyword'] ) : '';
			if ( $keyword === '' ) {
				continue;
			}
			$clean[] = array(
				'keyword'           => $keyword,
				'intent'            => sanitize_text_field( (string) ( $row['intent'] ?? 'informational' ) ),
				'difficulty'        => absint( $row['difficulty'] ?? 0 ),
				'priority'          => absint( $row['priority'] ?? 50 ),
				'suggested_cluster' => sanitize_text_field( (string) ( $row['suggested_cluster'] ?? '' ) ),
				'language'          => $this->post_text( 'language' ),
				'country'           => $this->post_text( 'country' ),
			);
		}

		$result = $this->service->save_selected( $this->post_int( 'project_id' ), $clean, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$this->respond_error( $result );
		}

		wp_send_json_success( $result );
	}

	private function respond_error( \WP_Error $error ): void {
		$status = 400;
		$data   = $error->get_error_data();
		if ( is_array( $data ) && isset( $data['status'] ) ) {
			$status = (int) $data['status'];
		}
		wp_send_json_error(
			array(
				'message' => $error->get_error_message(),
				'code'    => $error->get_error_code(),
			),
			$status
		);
	}

	private function guard(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'recipe-seo-ai-pro' ) ), 403 );
		}
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
	}

	private function post_text( string $key, string $default = '' ): string {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}
		return sanitize_text_field( (string) wp_unslash( $_POST[ $key ] ) );
	}

	private function post_int( string $key, int $default = 0 ): int {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}
		return absint( wp_unslash( $_POST[ $key ] ) );
	}

	private function capability(): string {
		return function_exists( 'rsaip_capability' ) ? rsaip_capability() : 'manage_options';
	}
}
