<?php
declare(strict_types=1);

/**
 * Admin + AJAX for SERP Intelligence (Phase 3.6).
 *
 * Isolated from Projects / Keyword Workspace / Content Brief / Keyword Research.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Seo\SerpIntelligence;

use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SerpIntelligenceController
 */
final class SerpIntelligenceController {

	public const PAGE_SLUG    = 'rsaip-serp-intelligence';
	public const NONCE_ACTION = 'rsaip_serp_intelligence';

	private SerpIntelligenceService $service;

	private SerpIntelligenceViewModel $view_model;

	private SerpAnalyzer $analyzer;

	public function __construct(
		SerpIntelligenceService $service,
		SerpIntelligenceViewModel $view_model,
		SerpAnalyzer $analyzer
	) {
		$this->service    = $service;
		$this->view_model = $view_model;
		$this->analyzer   = $analyzer;
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 25 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		$map = array(
			'rsaip_serp_analyze'          => 'ajax_analyze',
			'rsaip_serp_save'             => 'ajax_save',
			'rsaip_serp_get'              => 'ajax_get',
			'rsaip_serp_list'             => 'ajax_list',
			'rsaip_serp_attach_project'   => 'ajax_attach_project',
			'rsaip_serp_attach_keyword'   => 'ajax_attach_keyword',
			'rsaip_serp_attach_brief'     => 'ajax_attach_brief',
		);

		foreach ( $map as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( $this, $method ) );
		}
	}

	public function register_menu(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			return;
		}

		add_submenu_page(
			'rsaip-dashboard',
			__( 'SERP Intelligence', 'recipe-seo-ai-pro' ),
			__( 'SERP Intelligence', 'recipe-seo-ai-pro' ),
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

		$js_path = RSAIP_PLUGIN_DIR . 'assets/serp-intelligence.js';
		$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : RSAIP_VERSION;
		wp_enqueue_script( 'rsaip-serp-intelligence', RSAIP_PLUGIN_URL . 'assets/serp-intelligence.js', array( 'jquery' ), $js_ver, true );

		$project_id = isset( $_GET['project_id'] ) ? absint( wp_unslash( $_GET['project_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		wp_localize_script(
			'rsaip-serp-intelligence',
			'RSAIP_SERP_INTELLIGENCE',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
				'projectId' => $project_id,
				'actions'   => array(
					'analyze'       => 'rsaip_serp_analyze',
					'save'          => 'rsaip_serp_save',
					'get'           => 'rsaip_serp_get',
					'list'          => 'rsaip_serp_list',
					'attachProject' => 'rsaip_serp_attach_project',
					'attachKeyword' => 'rsaip_serp_attach_keyword',
					'attachBrief'   => 'rsaip_serp_attach_brief',
				),
				'labels'    => $this->view_model->section_labels(),
				'i18n'      => array(
					'analyzing' => __( 'Analyzing SERP…', 'recipe-seo-ai-pro' ),
					'saving'    => __( 'Saving…', 'recipe-seo-ai-pro' ),
					'saved'     => __( 'Analysis saved.', 'recipe-seo-ai-pro' ),
					'error'     => __( 'Request failed.', 'recipe-seo-ai-pro' ),
					'needQuery' => __( 'Enter a query first.', 'recipe-seo-ai-pro' ),
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
		View::render( 'admin/serp-intelligence', $this->view_model->for_admin_page( $project_id ) );
	}

	public function ajax_analyze(): void {
		$this->guard();
		$result = $this->service->analyze(
			$this->post_text( 'query' ),
			array(
				'language' => $this->post_text( 'language', (string) get_locale() ),
				'country'  => $this->post_text( 'country' ),
				'audience' => $this->post_text( 'audience' ),
				'notes'    => isset( $_POST['notes'] ) ? sanitize_textarea_field( (string) wp_unslash( $_POST['notes'] ) ) : '',
			)
		);
		$this->respond_dto( $result );
	}

	public function ajax_save(): void {
		$this->guard();

		$analysis = $this->post_analysis_payload();
		if ( is_wp_error( $analysis ) ) {
			$this->respond_error( $analysis );
		}

		$result = $this->service->save(
			$analysis,
			array(
				'project_id' => $this->post_int( 'project_id' ),
				'keyword_id' => $this->post_int( 'keyword_id' ),
				'brief_id'   => $this->post_int( 'brief_id' ),
			),
			get_current_user_id()
		);
		$this->respond_dto( $result );
	}

	public function ajax_get(): void {
		$this->guard();
		$this->respond_dto( $this->service->get( $this->post_int( 'id' ) ) );
	}

	public function ajax_list(): void {
		$this->guard();
		wp_send_json_success(
			$this->service->list_analyses(
				array(
					'q'          => $this->post_text( 'q' ),
					'project_id' => $this->post_int( 'project_id' ),
					'keyword_id' => $this->post_int( 'keyword_id' ),
					'brief_id'   => $this->post_int( 'brief_id' ),
					'page'       => $this->post_int( 'page', 1 ),
					'per_page'   => $this->post_int( 'per_page', 20 ),
				)
			)
		);
	}

	public function ajax_attach_project(): void {
		$this->guard();
		$this->respond_dto(
			$this->service->attach_to_project( $this->post_int( 'id' ), $this->post_int( 'project_id' ), get_current_user_id() )
		);
	}

	public function ajax_attach_keyword(): void {
		$this->guard();
		$this->respond_dto(
			$this->service->attach_to_keyword( $this->post_int( 'id' ), $this->post_int( 'keyword_id' ), get_current_user_id() )
		);
	}

	public function ajax_attach_brief(): void {
		$this->guard();
		$this->respond_dto(
			$this->service->attach_to_brief( $this->post_int( 'id' ), $this->post_int( 'brief_id' ), get_current_user_id() )
		);
	}

	/**
	 * @return SerpIntelligenceDTO|\WP_Error
	 */
	private function post_analysis_payload() {
		$id = $this->post_int( 'id' );
		if ( $id > 0 && ! isset( $_POST['analysis'] ) ) {
			return $this->service->get( $id );
		}

		$raw = isset( $_POST['analysis'] ) ? wp_unslash( $_POST['analysis'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = array();
		if ( is_string( $raw ) && $raw !== '' ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$data = $decoded;
			}
		} elseif ( is_array( $raw ) ) {
			$data = $raw;
		}

		if ( ! $data ) {
			return new \WP_Error( 'rsaip_serp_payload', 'Analysis payload is required.' );
		}

		$query = isset( $data['query'] ) ? sanitize_text_field( (string) $data['query'] ) : $this->post_text( 'query' );
		$dto   = $this->analyzer->from_ai_response(
			$query,
			(string) wp_json_encode( $data ),
			array(
				'language' => isset( $data['language'] ) ? (string) $data['language'] : $this->post_text( 'language' ),
				'country'  => isset( $data['country'] ) ? (string) $data['country'] : $this->post_text( 'country' ),
			)
		);
		if ( is_wp_error( $dto ) ) {
			return $dto;
		}
		$dto->id = $id;
		return $dto;
	}

	/**
	 * @param SerpIntelligenceDTO|\WP_Error $result Result.
	 */
	private function respond_dto( $result ): void {
		if ( is_wp_error( $result ) ) {
			$this->respond_error( $result );
		}
		wp_send_json_success( $this->view_model->present( $result ) );
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
