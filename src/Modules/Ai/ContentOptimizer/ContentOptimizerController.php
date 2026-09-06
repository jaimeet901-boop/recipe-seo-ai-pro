<?php
declare(strict_types=1);

/**
 * Admin + AJAX + Posts list quick actions (Phase 4.1).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentOptimizer;

use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ContentOptimizerController
 */
final class ContentOptimizerController {

	public const PAGE_SLUG    = 'rsaip-content-optimizer';
	public const NONCE_ACTION = 'rsaip_content_optimizer';

	private ContentOptimizerService $service;

	private ContentOptimizerViewModel $view_model;

	public function __construct( ContentOptimizerService $service, ContentOptimizerViewModel $view_model ) {
		$this->service    = $service;
		$this->view_model = $view_model;
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 27 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'post_row_actions', array( $this, 'post_row_actions' ), 20, 2 );
		add_filter( 'page_row_actions', array( $this, 'post_row_actions' ), 20, 2 );

		$map = array(
			'rsaip_optimizer_analyze'  => 'ajax_analyze',
			'rsaip_optimizer_optimize' => 'ajax_optimize',
			'rsaip_optimizer_get'      => 'ajax_get',
			'rsaip_optimizer_compare'  => 'ajax_compare',
			'rsaip_optimizer_apply'    => 'ajax_apply',
			'rsaip_optimizer_undo'     => 'ajax_undo',
			'rsaip_optimizer_restore'  => 'ajax_restore',
			'rsaip_optimizer_history'  => 'ajax_history',
			'rsaip_optimizer_load_post'=> 'ajax_load_post',
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
			__( 'AI Content Optimizer', 'recipe-seo-ai-pro' ),
			__( 'AI Content Optimizer', 'recipe-seo-ai-pro' ),
			$this->capability(),
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Quick actions on Posts list — opens Optimizer for that post.
	 *
	 * @param array<string, string> $actions Actions.
	 * @param \WP_Post              $post    Post.
	 * @return array<string, string>
	 */
	public function post_row_actions( array $actions, $post ): array {
		if ( ! $post instanceof \WP_Post || ! current_user_can( $this->capability() ) ) {
			return $actions;
		}
		if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return $actions;
		}

		$base = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&post_id=' . (int) $post->ID );
		$map  = array(
			'analyze'  => array( 'Analyze', 'analyze' ),
			'seo'      => array( 'SEO Optimize', 'seo_recovery' ),
			'humanize' => array( 'Humanize', 'human_rewrite' ),
			'recipe'   => array( 'Recipe Optimize', 'recipe_optimization' ),
			'rewrite'  => array( 'Rewrite', 'human_rewrite' ),
			'expand'   => array( 'Expand Content', 'content_expansion' ),
		);
		foreach ( $map as $key => $pair ) {
			$url = add_query_arg(
				array(
					'workflow' => $pair[1],
					'autoload' => 1,
				),
				$base
			);
			$actions[ 'rsaip_opt_' . $key ] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				esc_html( $pair[0] )
			);
		}
		return $actions;
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
		wp_enqueue_style( 'rsaip-admin', RSAIP_PLUGIN_URL . 'assets/admin.css', array(), file_exists( $css_path ) ? (string) filemtime( $css_path ) : RSAIP_VERSION );

		$opt_css = RSAIP_PLUGIN_DIR . 'assets/content-optimizer.css';
		wp_enqueue_style( 'rsaip-content-optimizer', RSAIP_PLUGIN_URL . 'assets/content-optimizer.css', array( 'rsaip-admin' ), file_exists( $opt_css ) ? (string) filemtime( $opt_css ) : RSAIP_VERSION );

		$js_path = RSAIP_PLUGIN_DIR . 'assets/content-optimizer.js';
		wp_enqueue_script( 'rsaip-content-optimizer', RSAIP_PLUGIN_URL . 'assets/content-optimizer.js', array( 'jquery' ), file_exists( $js_path ) ? (string) filemtime( $js_path ) : RSAIP_VERSION, true );

		$post_id  = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$workflow = isset( $_GET['workflow'] ) ? sanitize_key( (string) wp_unslash( $_GET['workflow'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		wp_localize_script(
			'rsaip-content-optimizer',
			'RSAIP_CONTENT_OPTIMIZER',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
				'postId'   => $post_id,
				'workflow' => $workflow,
				'autoload' => ! empty( $_GET['autoload'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'actions'  => array(
					'analyze'  => 'rsaip_optimizer_analyze',
					'optimize' => 'rsaip_optimizer_optimize',
					'get'      => 'rsaip_optimizer_get',
					'compare'  => 'rsaip_optimizer_compare',
					'apply'    => 'rsaip_optimizer_apply',
					'undo'     => 'rsaip_optimizer_undo',
					'restore'  => 'rsaip_optimizer_restore',
					'history'  => 'rsaip_optimizer_history',
					'loadPost' => 'rsaip_optimizer_load_post',
				),
				'i18n'     => array(
					'analyzing'  => __( 'Analyzing…', 'recipe-seo-ai-pro' ),
					'optimizing' => __( 'Optimizing…', 'recipe-seo-ai-pro' ),
					'applying'   => __( 'Applying (backup first)…', 'recipe-seo-ai-pro' ),
					'saved'      => __( 'Saved.', 'recipe-seo-ai-pro' ),
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
		$post_id = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		View::render( 'admin/content-optimizer', $this->view_model->for_admin_page( $post_id ) );
	}

	public function ajax_analyze(): void {
		$this->guard();
		$post_id = $this->post_int( 'post_id' );
		$content = isset( $_POST['content'] ) ? wp_kses_post( (string) wp_unslash( $_POST['content'] ) ) : '';
		$options = array(
			'title'    => $this->post_text( 'title' ),
			'meta'     => $this->post_text( 'meta' ),
			'keywords' => $this->post_text( 'keywords' ),
			'enrich'   => ! empty( $_POST['enrich'] ),
			'post_id'  => $post_id,
		);
		$result  = $post_id > 0 && $content === ''
			? $this->service->analyze_post( $post_id, $options )
			: $this->service->analyze_content( $content, $options );
		$this->respond_dto( $result );
	}

	public function ajax_optimize(): void {
		$this->guard();
		$content = isset( $_POST['content'] ) ? wp_kses_post( (string) wp_unslash( $_POST['content'] ) ) : '';
		$result  = $this->service->optimize(
			$this->post_text( 'workflow', 'human_rewrite' ),
			$this->post_text( 'scope', 'full_article' ),
			array(
				'post_id'        => $this->post_int( 'post_id' ),
				'content'        => $content,
				'title'          => $this->post_text( 'title' ),
				'meta'           => $this->post_text( 'meta' ),
				'keywords'       => $this->post_text( 'keywords' ),
				'notes'          => isset( $_POST['notes'] ) ? sanitize_textarea_field( (string) wp_unslash( $_POST['notes'] ) ) : '',
				'selected_text'  => isset( $_POST['selected_text'] ) ? wp_kses_post( (string) wp_unslash( $_POST['selected_text'] ) ) : '',
			)
		);
		$this->respond_dto( $result );
	}

	public function ajax_get(): void {
		$this->guard();
		$this->respond_dto( $this->service->get( $this->post_int( 'id' ) ) );
	}

	public function ajax_compare(): void {
		$this->guard();
		$result = $this->service->compare( $this->post_int( 'id' ) );
		if ( is_wp_error( $result ) ) {
			$this->respond_error( $result );
		}
		wp_send_json_success( $result );
	}

	public function ajax_apply(): void {
		$this->guard();
		$this->respond_dto( $this->service->apply( $this->post_int( 'id' ), get_current_user_id() ) );
	}

	public function ajax_undo(): void {
		$this->guard();
		$this->respond_dto( $this->service->undo( $this->post_int( 'id' ), get_current_user_id() ) );
	}

	public function ajax_restore(): void {
		$this->guard();
		$this->respond_dto( $this->service->restore_version( $this->post_int( 'version_id' ), get_current_user_id() ) );
	}

	public function ajax_history(): void {
		$this->guard();
		wp_send_json_success( $this->service->history( $this->post_int( 'post_id' ) ) );
	}

	public function ajax_load_post(): void {
		$this->guard();
		$result = $this->service->load_post_source( $this->post_int( 'post_id' ) );
		if ( is_wp_error( $result ) ) {
			$this->respond_error( $result );
		}
		wp_send_json_success( $result );
	}

	/**
	 * @param ContentOptimizerDTO|\WP_Error $result Result.
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
