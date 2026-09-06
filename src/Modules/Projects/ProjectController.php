<?php
declare(strict_types=1);

/**
 * Admin + AJAX controller for AI SEO Projects (Phase 3.3).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Projects;

use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProjectController
 */
final class ProjectController {

	public const PAGE_SLUG    = 'rsaip-seo-projects';
	public const NONCE_ACTION = 'rsaip_seo_projects';

	private ProjectService $service;

	private ProjectManager $manager;

	private ProjectViewModel $view_model;

	public function __construct(
		ProjectService $service,
		ProjectManager $manager,
		ProjectViewModel $view_model
	) {
		$this->service    = $service;
		$this->manager    = $manager;
		$this->view_model = $view_model;
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 22 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		$map = array(
			'rsaip_project_list'           => 'ajax_list',
			'rsaip_project_get'            => 'ajax_get',
			'rsaip_project_create'         => 'ajax_create',
			'rsaip_project_update'         => 'ajax_update',
			'rsaip_project_delete'         => 'ajax_delete',
			'rsaip_project_add_member'     => 'ajax_add_member',
			'rsaip_project_remove_member'  => 'ajax_remove_member',
			'rsaip_project_attach_asset'   => 'ajax_attach_asset',
			'rsaip_project_detach_asset'   => 'ajax_detach_asset',
			'rsaip_project_add_task'       => 'ajax_add_task',
			'rsaip_project_refresh_stats'  => 'ajax_refresh_stats',
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
			__( 'AI SEO Projects', 'recipe-seo-ai-pro' ),
			__( 'SEO Projects', 'recipe-seo-ai-pro' ),
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

		$js_path = RSAIP_PLUGIN_DIR . 'assets/seo-projects.js';
		$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : RSAIP_VERSION;
		wp_enqueue_script( 'rsaip-seo-projects', RSAIP_PLUGIN_URL . 'assets/seo-projects.js', array( 'jquery' ), $js_ver, true );

		wp_localize_script(
			'rsaip-seo-projects',
			'RSAIP_SEO_PROJECTS',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( self::NONCE_ACTION ),
				'keywordWorkspaceUrl' => admin_url( 'admin.php?page=rsaip-keyword-workspace' ),
				'actions'       => array(
					'list'          => 'rsaip_project_list',
					'get'           => 'rsaip_project_get',
					'create'        => 'rsaip_project_create',
					'update'        => 'rsaip_project_update',
					'delete'        => 'rsaip_project_delete',
					'addMember'     => 'rsaip_project_add_member',
					'removeMember'  => 'rsaip_project_remove_member',
					'attachAsset'   => 'rsaip_project_attach_asset',
					'detachAsset'   => 'rsaip_project_detach_asset',
					'addTask'       => 'rsaip_project_add_task',
					'refreshStats'  => 'rsaip_project_refresh_stats',
				),
				'i18n'    => array(
					'saved'      => __( 'Project saved.', 'recipe-seo-ai-pro' ),
					'deleted'    => __( 'Project deleted.', 'recipe-seo-ai-pro' ),
					'confirmDel' => __( 'Delete this project and its project links?', 'recipe-seo-ai-pro' ),
					'error'      => __( 'Request failed.', 'recipe-seo-ai-pro' ),
				),
			)
		);

		unset( $hook_suffix );
	}

	public function render_page(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'Access denied', 'recipe-seo-ai-pro' ) );
		}
		View::render( 'admin/seo-projects', $this->view_model->for_admin_page() );
	}

	public function ajax_list(): void {
		$this->guard();
		wp_send_json_success(
			$this->service->list_projects(
				array(
					'q'            => $this->post_text( 'q' ),
					'status'       => $this->post_text( 'status', 'all' ),
					'sort'         => $this->post_text( 'sort', 'updated_at' ),
					'order'        => $this->post_text( 'order', 'DESC' ),
					'page'         => $this->post_int( 'page', 1 ),
					'per_page'     => $this->post_int( 'per_page', 20 ),
					'bypass_cache' => true,
				)
			)
		);
	}

	public function ajax_get(): void {
		$this->guard();
		$result = $this->service->get( $this->post_int( 'id' ), true );
		$this->respond_dto( $result );
	}

	public function ajax_create(): void {
		$this->guard();
		$result = $this->manager->create( $this->post_project_fields(), get_current_user_id() );
		$this->respond_dto( $result );
	}

	public function ajax_update(): void {
		$this->guard();
		$result = $this->manager->update( $this->post_int( 'id' ), $this->post_project_fields(), get_current_user_id() );
		$this->respond_dto( $result );
	}

	public function ajax_delete(): void {
		$this->guard();
		$result = $this->manager->delete( $this->post_int( 'id' ), get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$this->respond_error( $result );
		}
		wp_send_json_success( array( 'ok' => true ) );
	}

	public function ajax_add_member(): void {
		$this->guard();
		$result = $this->manager->add_member(
			$this->post_int( 'id' ),
			$this->post_int( 'user_id' ),
			$this->post_text( 'role', 'member' ),
			get_current_user_id()
		);
		$this->respond_dto( $result );
	}

	public function ajax_remove_member(): void {
		$this->guard();
		$result = $this->manager->remove_member(
			$this->post_int( 'id' ),
			$this->post_int( 'user_id' ),
			get_current_user_id()
		);
		$this->respond_dto( $result );
	}

	public function ajax_attach_asset(): void {
		$this->guard();
		$result = $this->manager->attach_asset(
			$this->post_int( 'id' ),
			$this->post_text( 'asset_type', ProjectService::ASSET_BRIEF ),
			$this->post_int( 'asset_id' ),
			$this->post_text( 'title' ),
			get_current_user_id()
		);
		$this->respond_dto( $result );
	}

	public function ajax_detach_asset(): void {
		$this->guard();
		$result = $this->manager->detach_asset(
			$this->post_int( 'id' ),
			$this->post_text( 'asset_type', ProjectService::ASSET_BRIEF ),
			$this->post_int( 'asset_id' ),
			get_current_user_id()
		);
		$this->respond_dto( $result );
	}

	public function ajax_add_task(): void {
		$this->guard();
		$result = $this->manager->add_task(
			$this->post_int( 'id' ),
			$this->post_text( 'title' ),
			$this->post_text( 'due_at' ),
			get_current_user_id()
		);
		if ( is_wp_error( $result ) ) {
			$this->respond_error( $result );
		}
		wp_send_json_success( $result );
	}

	public function ajax_refresh_stats(): void {
		$this->guard();
		$id  = $this->post_int( 'id' );
		$dto = $this->service->get( $id, false );
		if ( is_wp_error( $dto ) ) {
			$this->respond_error( $dto );
		}
		$this->service->refresh_statistics( $id );
		$dto = $this->service->get( $id, true );
		$this->respond_dto( $dto );
	}

	/**
	 * @param ProjectDTO|\WP_Error $result Result.
	 */
	private function respond_dto( $result ): void {
		if ( is_wp_error( $result ) ) {
			$this->respond_error( $result );
		}
		/** @var ProjectDTO $result */
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

	/**
	 * @return array<string, mixed>
	 */
	private function post_project_fields(): array {
		return array(
			'name'           => $this->post_text( 'name' ),
			'description'    => isset( $_POST['description'] ) ? sanitize_textarea_field( (string) wp_unslash( $_POST['description'] ) ) : '',
			'target_country' => $this->post_text( 'target_country' ),
			'language'       => $this->post_text( 'language' ),
			'niche'          => $this->post_text( 'niche' ),
			'status'         => $this->post_text( 'status', ProjectManager::STATUS_ACTIVE ),
		);
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
