<?php
declare(strict_types=1);

/**
 * Admin UI + AJAX for Content Brief Library (Phase 3.2).
 *
 * Isolated from ContentBriefService generation and RSAIP_AJAX.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentBrief;

use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LibraryController
 */
final class LibraryController {

	public const PAGE_SLUG = 'rsaip-content-brief-library';

	public const NONCE_ACTION = 'rsaip_content_brief_library';

	private BriefManagerService $manager;

	private BriefSearchService $search;

	private BriefExportService $export;

	private BriefViewModel $view_model;

	public function __construct(
		BriefManagerService $manager,
		BriefSearchService $search,
		BriefExportService $export,
		BriefViewModel $view_model
	) {
		$this->manager    = $manager;
		$this->search     = $search;
		$this->export     = $export;
		$this->view_model = $view_model;
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 21 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		$actions = array(
			'rsaip_brief_library_list'      => 'ajax_list',
			'rsaip_brief_library_get'       => 'ajax_get',
			'rsaip_brief_library_save'      => 'ajax_save',
			'rsaip_brief_library_update'    => 'ajax_update',
			'rsaip_brief_library_duplicate' => 'ajax_duplicate',
			'rsaip_brief_library_delete'    => 'ajax_delete',
			'rsaip_brief_library_archive'   => 'ajax_archive',
			'rsaip_brief_library_restore'   => 'ajax_restore',
			'rsaip_brief_library_complete'  => 'ajax_complete',
			'rsaip_brief_library_export'    => 'ajax_export',
		);

		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( $this, $method ) );
		}
	}

	public function register_menu(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			return;
		}

		add_submenu_page(
			'rsaip-dashboard',
			__( 'AI Content Brief Library', 'recipe-seo-ai-pro' ),
			__( 'Brief Library', 'recipe-seo-ai-pro' ),
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

		$on_library = ( $page === self::PAGE_SLUG );
		$on_brief   = ( $page === BriefController::PAGE_SLUG );
		if ( ! $on_library && ! $on_brief ) {
			return;
		}

		$css_path = RSAIP_PLUGIN_DIR . 'assets/admin.css';
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : RSAIP_VERSION;
		wp_enqueue_style( 'rsaip-admin', RSAIP_PLUGIN_URL . 'assets/admin.css', array(), $css_ver );

		$localize = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			'labels'  => $this->view_model->section_labels(),
			'i18n'    => array(
				'saved'     => __( 'Brief saved to library.', 'recipe-seo-ai-pro' ),
				'updated'   => __( 'Brief updated.', 'recipe-seo-ai-pro' ),
				'deleted'   => __( 'Brief deleted.', 'recipe-seo-ai-pro' ),
				'archived'  => __( 'Brief archived.', 'recipe-seo-ai-pro' ),
				'restored'  => __( 'Brief restored.', 'recipe-seo-ai-pro' ),
				'copied'    => __( 'Copied to clipboard.', 'recipe-seo-ai-pro' ),
				'confirmDel'=> __( 'Delete this brief permanently?', 'recipe-seo-ai-pro' ),
				'error'     => __( 'Request failed.', 'recipe-seo-ai-pro' ),
			),
			'actions' => array(
				'list'      => 'rsaip_brief_library_list',
				'get'       => 'rsaip_brief_library_get',
				'save'      => 'rsaip_brief_library_save',
				'update'    => 'rsaip_brief_library_update',
				'duplicate' => 'rsaip_brief_library_duplicate',
				'delete'    => 'rsaip_brief_library_delete',
				'archive'   => 'rsaip_brief_library_archive',
				'restore'   => 'rsaip_brief_library_restore',
				'complete'  => 'rsaip_brief_library_complete',
				'export'    => 'rsaip_brief_library_export',
			),
		);

		if ( $on_library ) {
			$js_path = RSAIP_PLUGIN_DIR . 'assets/content-brief-library.js';
			$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : RSAIP_VERSION;
			wp_enqueue_script( 'rsaip-content-brief-library', RSAIP_PLUGIN_URL . 'assets/content-brief-library.js', array( 'jquery' ), $js_ver, true );
			wp_localize_script( 'rsaip-content-brief-library', 'RSAIP_BRIEF_LIBRARY', $localize );
		}

		if ( $on_brief ) {
			// Ensure generator script is present, then attach library save config.
			if ( ! wp_script_is( 'rsaip-content-brief', 'enqueued' ) && ! wp_script_is( 'rsaip-content-brief', 'registered' ) ) {
				$brief_js = RSAIP_PLUGIN_DIR . 'assets/content-brief.js';
				$brief_ver = file_exists( $brief_js ) ? (string) filemtime( $brief_js ) : RSAIP_VERSION;
				wp_enqueue_script(
					'rsaip-content-brief',
					RSAIP_PLUGIN_URL . 'assets/content-brief.js',
					array( 'jquery' ),
					$brief_ver,
					true
				);
			}
			wp_localize_script( 'rsaip-content-brief', 'RSAIP_BRIEF_LIBRARY', $localize );
		}

		unset( $hook_suffix );
	}

	public function render_page(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'Access denied', 'recipe-seo-ai-pro' ) );
		}

		View::render(
			'admin/content-brief-library',
			array(
				'title'    => __( 'AI Content Brief Library', 'recipe-seo-ai-pro' ),
				'labels'   => $this->view_model->section_labels(),
				'statuses' => array(
					'all'       => __( 'All statuses', 'recipe-seo-ai-pro' ),
					'draft'     => __( 'Draft', 'recipe-seo-ai-pro' ),
					'completed' => __( 'Completed', 'recipe-seo-ai-pro' ),
					'archived'  => __( 'Archived', 'recipe-seo-ai-pro' ),
				),
			)
		);
	}

	public function ajax_list(): void {
		$this->guard();
		$result = $this->search->search(
			array(
				'q'        => $this->post_text( 'q' ),
				'status'   => $this->post_text( 'status', 'all' ),
				'sort'     => $this->post_text( 'sort', 'updated_at' ),
				'order'    => $this->post_text( 'order', 'DESC' ),
				'page'     => $this->post_int( 'page', 1 ),
				'per_page' => $this->post_int( 'per_page', 20 ),
				'bypass_cache' => true,
			)
		);
		wp_send_json_success( $result );
	}

	public function ajax_get(): void {
		$this->guard();
		$result = $this->manager->get( $this->post_int( 'id' ) );
		$this->respond( $result );
	}

	public function ajax_save(): void {
		$this->guard();
		$brief = $this->post_brief_payload();
		$result = $this->manager->save(
			$brief,
			array(
				'title'   => $this->post_text( 'title' ),
				'status'  => $this->post_text( 'status', BriefStorageService::STATUS_DRAFT ),
				'user_id' => get_current_user_id(),
			)
		);
		$this->search->bust_cache_hint();
		$this->respond( $result );
	}

	public function ajax_update(): void {
		$this->guard();
		$id    = $this->post_int( 'id' );
		$brief = $this->post_brief_payload();
		$result = $this->manager->update(
			$id,
			$brief,
			array(
				'title'   => $this->post_text( 'title' ),
				'status'  => $this->post_text( 'status', '' ),
				'user_id' => get_current_user_id(),
			)
		);
		$this->search->bust_cache_hint();
		$this->respond( $result );
	}

	public function ajax_duplicate(): void {
		$this->guard();
		$result = $this->manager->duplicate( $this->post_int( 'id' ), get_current_user_id() );
		$this->search->bust_cache_hint();
		$this->respond( $result );
	}

	public function ajax_delete(): void {
		$this->guard();
		$result = $this->manager->delete( $this->post_int( 'id' ) );
		$this->search->bust_cache_hint();
		if ( is_wp_error( $result ) ) {
			$this->respond( $result );
		}
		wp_send_json_success( array( 'ok' => true ) );
	}

	public function ajax_archive(): void {
		$this->guard();
		$result = $this->manager->archive( $this->post_int( 'id' ) );
		$this->search->bust_cache_hint();
		$this->respond( $result );
	}

	public function ajax_restore(): void {
		$this->guard();
		$result = $this->manager->restore( $this->post_int( 'id' ) );
		$this->search->bust_cache_hint();
		$this->respond( $result );
	}

	public function ajax_complete(): void {
		$this->guard();
		$result = $this->manager->mark_completed( $this->post_int( 'id' ) );
		$this->search->bust_cache_hint();
		$this->respond( $result );
	}

	public function ajax_export(): void {
		$this->guard();
		$result = $this->export->export( $this->post_int( 'id' ), $this->post_text( 'format', 'markdown' ) );
		$this->respond( $result );
	}

	/**
	 * @param mixed $result Presented data or WP_Error.
	 */
	private function respond( $result ): void {
		if ( is_wp_error( $result ) ) {
			$status = 400;
			$data   = $result->get_error_data();
			if ( is_array( $data ) && isset( $data['status'] ) ) {
				$status = (int) $data['status'];
			}
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				$status
			);
		}
		wp_send_json_success( $result );
	}

	private function guard(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'recipe-seo-ai-pro' ) ), 403 );
		}
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function post_brief_payload(): array {
		if ( isset( $_POST['brief'] ) ) {
			$raw = wp_unslash( $_POST['brief'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded then sanitized via storage.
			if ( is_string( $raw ) ) {
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					return $decoded;
				}
			}
			if ( is_array( $raw ) ) {
				return $raw;
			}
		}
		return array();
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
