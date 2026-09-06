<?php
declare(strict_types=1);

/**
 * Admin + AJAX for Keyword Workspace (Phase 3.4).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Keywords;

use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KeywordController
 */
final class KeywordController {

	public const PAGE_SLUG    = 'rsaip-keyword-workspace';
	public const NONCE_ACTION = 'rsaip_keyword_workspace';

	private KeywordService $service;

	private KeywordManager $manager;

	private KeywordViewModel $view_model;

	public function __construct(
		KeywordService $service,
		KeywordManager $manager,
		KeywordViewModel $view_model
	) {
		$this->service    = $service;
		$this->manager    = $manager;
		$this->view_model = $view_model;
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 23 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		$map = array(
			'rsaip_keyword_list'           => 'ajax_list',
			'rsaip_keyword_get'            => 'ajax_get',
			'rsaip_keyword_create'         => 'ajax_create',
			'rsaip_keyword_update'         => 'ajax_update',
			'rsaip_keyword_delete'         => 'ajax_delete',
			'rsaip_keyword_bulk'           => 'ajax_bulk',
			'rsaip_keyword_assign_brief'   => 'ajax_assign_brief',
			'rsaip_keyword_assign_article' => 'ajax_assign_article',
			'rsaip_keyword_change_status'  => 'ajax_change_status',
			'rsaip_keyword_clusters'       => 'ajax_clusters',
			'rsaip_keyword_cluster_view'   => 'ajax_cluster_view',
			'rsaip_keyword_create_cluster' => 'ajax_create_cluster',
			'rsaip_keyword_roadmap'        => 'ajax_roadmap',
			'rsaip_keyword_timeline'       => 'ajax_timeline',
			'rsaip_keyword_add_note'       => 'ajax_add_note',
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
			__( 'Keyword Workspace', 'recipe-seo-ai-pro' ),
			__( 'Keyword Workspace', 'recipe-seo-ai-pro' ),
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

		$js_path = RSAIP_PLUGIN_DIR . 'assets/keyword-workspace.js';
		$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : RSAIP_VERSION;
		wp_enqueue_script( 'rsaip-keyword-workspace', RSAIP_PLUGIN_URL . 'assets/keyword-workspace.js', array( 'jquery' ), $js_ver, true );

		$project_id = isset( $_GET['project_id'] ) ? absint( wp_unslash( $_GET['project_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		wp_localize_script(
			'rsaip-keyword-workspace',
			'RSAIP_KEYWORD_WORKSPACE',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
				'projectId' => $project_id,
				'actions'   => array(
					'list'          => 'rsaip_keyword_list',
					'get'           => 'rsaip_keyword_get',
					'create'        => 'rsaip_keyword_create',
					'update'        => 'rsaip_keyword_update',
					'delete'        => 'rsaip_keyword_delete',
					'bulk'          => 'rsaip_keyword_bulk',
					'assignBrief'   => 'rsaip_keyword_assign_brief',
					'assignArticle' => 'rsaip_keyword_assign_article',
					'changeStatus'  => 'rsaip_keyword_change_status',
					'clusters'      => 'rsaip_keyword_clusters',
					'clusterView'   => 'rsaip_keyword_cluster_view',
					'createCluster' => 'rsaip_keyword_create_cluster',
					'roadmap'       => 'rsaip_keyword_roadmap',
					'timeline'      => 'rsaip_keyword_timeline',
					'addNote'       => 'rsaip_keyword_add_note',
				),
				'i18n'      => array(
					'saved'      => __( 'Saved.', 'recipe-seo-ai-pro' ),
					'deleted'    => __( 'Deleted.', 'recipe-seo-ai-pro' ),
					'confirmDel' => __( 'Delete selected keyword(s)?', 'recipe-seo-ai-pro' ),
					'error'      => __( 'Request failed.', 'recipe-seo-ai-pro' ),
					'empty'      => __( 'No keywords yet.', 'recipe-seo-ai-pro' ),
					'open'       => __( 'Open', 'recipe-seo-ai-pro' ),
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
		View::render( 'admin/keyword-workspace', $this->view_model->for_admin_page( $project_id ) );
	}

	public function ajax_list(): void {
		$this->guard();
		wp_send_json_success(
			$this->service->search(
				array(
					'q'            => $this->post_text( 'q' ),
					'status'       => $this->post_text( 'status', 'all' ),
					'intent'       => $this->post_text( 'intent', 'all' ),
					'project_id'   => $this->post_int( 'project_id' ),
					'cluster_id'   => $this->post_int( 'cluster_id' ),
					'sort'         => $this->post_text( 'sort', 'updated_at' ),
					'order'        => $this->post_text( 'order', 'DESC' ),
					'page'         => $this->post_int( 'page', 1 ),
					'per_page'     => $this->post_int( 'per_page', 25 ),
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
		$result = $this->manager->create( $this->post_keyword_fields( true ), get_current_user_id() );
		$this->respond_dto( $result );
	}

	public function ajax_update(): void {
		$this->guard();
		$result = $this->manager->update( $this->post_int( 'id' ), $this->post_keyword_fields( false ), get_current_user_id() );
		$this->respond_dto( $result );
	}

	public function ajax_delete(): void {
		$this->guard();
		$ids = $this->post_ids();
		if ( count( $ids ) === 1 ) {
			$result = $this->manager->delete( $ids[0], get_current_user_id() );
			if ( is_wp_error( $result ) ) {
				$this->respond_error( $result );
			}
			wp_send_json_success( array( 'deleted' => 1 ) );
		}
		$deleted = 0;
		foreach ( $ids as $id ) {
			$r = $this->manager->delete( $id, get_current_user_id() );
			if ( ! is_wp_error( $r ) ) {
				++$deleted;
			}
		}
		wp_send_json_success( array( 'deleted' => $deleted ) );
	}

	public function ajax_bulk(): void {
		$this->guard();
		$ids          = $this->post_ids();
		$bulk_action  = $this->post_text( 'bulk_action' );
		if ( $bulk_action === 'delete' ) {
			$deleted = 0;
			foreach ( $ids as $id ) {
				$r = $this->manager->delete( $id, get_current_user_id() );
				if ( ! is_wp_error( $r ) ) {
					++$deleted;
				}
			}
			wp_send_json_success( array( 'deleted' => $deleted ) );
		}

		$data = array();
		foreach ( array( 'status', 'roadmap_phase', 'intent' ) as $key ) {
			if ( isset( $_POST[ $key ] ) && (string) wp_unslash( $_POST[ $key ] ) !== '' ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$data[ $key ] = $this->post_text( $key );
			}
		}
		foreach ( array( 'cluster_id', 'project_id', 'priority' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$data[ $key ] = $this->post_int( $key );
			}
		}

		$result = $this->manager->bulk_update( $ids, $data, get_current_user_id() );
		wp_send_json_success( $result );
	}

	public function ajax_assign_brief(): void {
		$this->guard();
		$result = $this->manager->assign_brief( $this->post_int( 'id' ), $this->post_int( 'brief_id' ), get_current_user_id() );
		$this->respond_dto( $result );
	}

	public function ajax_assign_article(): void {
		$this->guard();
		$result = $this->manager->assign_article( $this->post_int( 'id' ), $this->post_int( 'article_id' ), get_current_user_id() );
		$this->respond_dto( $result );
	}

	public function ajax_change_status(): void {
		$this->guard();
		$result = $this->manager->change_status( $this->post_int( 'id' ), $this->post_text( 'status' ), get_current_user_id() );
		$this->respond_dto( $result );
	}

	public function ajax_clusters(): void {
		$this->guard();
		wp_send_json_success( array( 'clusters' => $this->service->list_clusters( $this->post_int( 'project_id' ) ) ) );
	}

	public function ajax_cluster_view(): void {
		$this->guard();
		wp_send_json_success( $this->service->cluster_view( $this->post_int( 'project_id' ) ) );
	}

	public function ajax_create_cluster(): void {
		$this->guard();
		$result = $this->manager->create_cluster(
			array(
				'name'        => $this->post_text( 'name' ),
				'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( (string) wp_unslash( $_POST['description'] ) ) : '',
				'project_id'  => $this->post_int( 'project_id' ),
				'color'       => $this->post_text( 'color' ),
			)
		);
		if ( is_wp_error( $result ) ) {
			$this->respond_error( $result );
		}
		wp_send_json_success( $result );
	}

	public function ajax_roadmap(): void {
		$this->guard();
		wp_send_json_success( $this->service->roadmap_view( $this->post_int( 'project_id' ) ) );
	}

	public function ajax_timeline(): void {
		$this->guard();
		wp_send_json_success(
			array(
				'items' => $this->service->timeline( $this->post_int( 'project_id' ), $this->post_int( 'id' ) ),
			)
		);
	}

	public function ajax_add_note(): void {
		$this->guard();
		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( (string) wp_unslash( $_POST['note'] ) ) : '';
		$result = $this->manager->add_note( $this->post_int( 'id' ), $note, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$this->respond_error( $result );
		}
		wp_send_json_success( $result );
	}

	/**
	 * @param KeywordDTO|\WP_Error $result Result.
	 */
	private function respond_dto( $result ): void {
		if ( is_wp_error( $result ) ) {
			$this->respond_error( $result );
		}
		/** @var KeywordDTO $result */
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
	private function post_keyword_fields( bool $for_create ): array {
		$keys = array(
			'primary_keyword',
			'intent',
			'difficulty',
			'priority',
			'status',
			'project_id',
			'brief_id',
			'article_id',
			'target_url',
			'cluster_id',
			'parent_keyword_id',
			'language',
			'country',
			'roadmap_phase',
			'roadmap_order',
		);
		$out = array();
		foreach ( $keys as $key ) {
			if ( ! $for_create && ! isset( $_POST[ $key ] ) ) {
				continue;
			}
			if ( $key === 'target_url' ) {
				$out[ $key ] = isset( $_POST['target_url'] ) ? esc_url_raw( (string) wp_unslash( $_POST['target_url'] ) ) : '';
				continue;
			}
			if ( in_array( $key, array( 'difficulty', 'priority', 'project_id', 'brief_id', 'article_id', 'cluster_id', 'parent_keyword_id', 'roadmap_order' ), true ) ) {
				$default     = ( 'priority' === $key ) ? 50 : 0;
				$out[ $key ] = $this->post_int( $key, $default );
				continue;
			}
			$default     = ( 'status' === $key ) ? 'idea' : ( ( 'roadmap_phase' === $key ) ? 'backlog' : '' );
			$out[ $key ] = $this->post_text( $key, $default );
		}
		return $out;
	}

	/**
	 * @return list<int>
	 */
	private function post_ids(): array {
		if ( isset( $_POST['ids'] ) ) {
			$raw = wp_unslash( $_POST['ids'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( is_string( $raw ) ) {
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					return array_values( array_filter( array_map( 'absint', $decoded ) ) );
				}
				$parts = preg_split( '/[\s,]+/', $raw ) ?: array();
				return array_values( array_filter( array_map( 'absint', $parts ) ) );
			}
			if ( is_array( $raw ) ) {
				return array_values( array_filter( array_map( 'absint', $raw ) ) );
			}
		}
		$id = $this->post_int( 'id' );
		return $id > 0 ? array( $id ) : array();
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
