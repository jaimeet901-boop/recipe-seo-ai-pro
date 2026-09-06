<?php
declare(strict_types=1);

/**
 * Admin + AJAX for AI Recipe Assistant (Phase 5.2).
 *
 * Isolated namespace rsaip_recipe_ai_* — does not touch Recipe Builder or legacy engine code.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\RecipeAI;

use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeAiController
 */
final class RecipeAiController {

	public const PAGE_SLUG    = 'rsaip-recipe-ai';
	public const NONCE_ACTION = 'rsaip_recipe_ai';

	private RecipeAiService $service;

	private RecipeAiViewModel $view_model;

	private PostPickerService $picker;

	public function __construct( RecipeAiService $service, RecipeAiViewModel $view_model, ?PostPickerService $picker = null ) {
		$this->service    = $service;
		$this->view_model = $view_model;
		$this->picker     = $picker instanceof PostPickerService ? $picker : new PostPickerService();
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 25 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		$map = array(
			'rsaip_recipe_ai_run'          => 'ajax_run',
			'rsaip_recipe_ai_get'          => 'ajax_get',
			'rsaip_recipe_ai_compare'      => 'ajax_compare',
			'rsaip_recipe_ai_apply'        => 'ajax_apply',
			'rsaip_recipe_ai_undo'         => 'ajax_undo',
			'rsaip_recipe_ai_restore'      => 'ajax_restore',
			'rsaip_recipe_ai_history'      => 'ajax_history',
			'rsaip_recipe_ai_list'         => 'ajax_list',
			'rsaip_recipe_ai_load'         => 'ajax_load_recipe',
			'rsaip_recipe_ai_search_posts'   => 'ajax_search_posts',
			'rsaip_recipe_ai_post_preview'   => 'ajax_post_preview',
			'rsaip_recipe_ai_picker_shelves' => 'ajax_picker_shelves',
			'rsaip_recipe_ai_picker_fav'     => 'ajax_picker_favorite',
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
			__( 'AI Recipe Assistant', 'recipe-seo-ai-pro' ),
			__( 'AI Recipe Assistant', 'recipe-seo-ai-pro' ),
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
		wp_enqueue_style( 'rsaip-admin', RSAIP_PLUGIN_URL . 'assets/admin.css', array(), file_exists( $css_path ) ? (string) filemtime( $css_path ) : RSAIP_VERSION );

		$ai_css = RSAIP_PLUGIN_DIR . 'assets/recipe-ai.css';
		wp_enqueue_style( 'rsaip-recipe-ai', RSAIP_PLUGIN_URL . 'assets/recipe-ai.css', array( 'rsaip-admin' ), file_exists( $ai_css ) ? (string) filemtime( $ai_css ) : RSAIP_VERSION );

		$js_path = RSAIP_PLUGIN_DIR . 'assets/recipe-ai.js';
		$picker_js = RSAIP_PLUGIN_DIR . 'assets/recipe-ai-picker.js';
		wp_enqueue_script( 'rsaip-recipe-ai', RSAIP_PLUGIN_URL . 'assets/recipe-ai.js', array( 'jquery' ), file_exists( $js_path ) ? (string) filemtime( $js_path ) : RSAIP_VERSION, true );
		wp_enqueue_script(
			'rsaip-recipe-ai-picker',
			RSAIP_PLUGIN_URL . 'assets/recipe-ai-picker.js',
			array( 'jquery', 'rsaip-recipe-ai' ),
			file_exists( $picker_js ) ? (string) filemtime( $picker_js ) : RSAIP_VERSION,
			true
		);

		$rb_id   = isset( $_GET['rb_recipe_id'] ) ? absint( wp_unslash( $_GET['rb_recipe_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$source  = isset( $_GET['source'] ) ? sanitize_key( (string) wp_unslash( $_GET['source'] ) ) : 'builder'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $source, array( 'builder', 'post' ), true ) ) {
			$source = $post_id > 0 ? 'post' : 'builder';
		}

		wp_localize_script(
			'rsaip-recipe-ai',
			'RSAIP_RECIPE_AI',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( self::NONCE_ACTION ),
				'rbRecipeId' => $rb_id,
				'postId'     => $post_id,
				'sourceType' => $source,
				'actions'    => array(
					'run'         => 'rsaip_recipe_ai_run',
					'get'         => 'rsaip_recipe_ai_get',
					'compare'     => 'rsaip_recipe_ai_compare',
					'apply'       => 'rsaip_recipe_ai_apply',
					'undo'        => 'rsaip_recipe_ai_undo',
					'restore'     => 'rsaip_recipe_ai_restore',
					'history'     => 'rsaip_recipe_ai_history',
					'list'        => 'rsaip_recipe_ai_list',
					'load'          => 'rsaip_recipe_ai_load',
					'searchPosts'   => 'rsaip_recipe_ai_search_posts',
					'postPreview'   => 'rsaip_recipe_ai_post_preview',
					'pickerShelves' => 'rsaip_recipe_ai_picker_shelves',
					'pickerFav'     => 'rsaip_recipe_ai_picker_fav',
				),
				'i18n'       => array(
					'running'  => __( 'Running AI…', 'recipe-seo-ai-pro' ),
					'applied'  => __( 'Applied successfully (backup created).', 'recipe-seo-ai-pro' ),
					'appliedBuilder' => __( 'Applied to Recipe Builder (backup created).', 'recipe-seo-ai-pro' ),
					'appliedPost'    => __( 'Saved back to the WordPress post (backup created).', 'recipe-seo-ai-pro' ),
					'undone'   => __( 'Restored from backup.', 'recipe-seo-ai-pro' ),
					'error'    => __( 'Request failed.', 'recipe-seo-ai-pro' ),
					'confirmApply' => __( 'Apply optimized recipe? A backup will be created first.', 'recipe-seo-ai-pro' ),
					'confirmApplyBuilder' => __( 'Apply optimized recipe to Recipe Builder? A backup will be created first.', 'recipe-seo-ai-pro' ),
					'confirmApplyPost'    => __( 'Save optimized recipe back to the original WordPress post? A backup will be created first.', 'recipe-seo-ai-pro' ),
					'selectSource' => __( 'Select a recipe or post first.', 'recipe-seo-ai-pro' ),
					'searching'    => __( 'Searching posts…', 'recipe-seo-ai-pro' ),
					'loadedPost'   => __( 'Post loaded.', 'recipe-seo-ai-pro' ),
					'noPosts'      => __( 'No posts found.', 'recipe-seo-ai-pro' ),
					'emptySearch'  => __( 'Start typing to search posts by title, ID, or slug.', 'recipe-seo-ai-pro' ),
					'loadPreview'  => __( 'Select a post to preview.', 'recipe-seo-ai-pro' ),
					'recipeYes'    => __( 'Yes', 'recipe-seo-ai-pro' ),
					'recipeNo'     => __( 'No', 'recipe-seo-ai-pro' ),
					'prev'         => __( 'Previous', 'recipe-seo-ai-pro' ),
					'next'         => __( 'Next', 'recipe-seo-ai-pro' ),
					'favorites'    => __( 'Favorites', 'recipe-seo-ai-pro' ),
					'recent'       => __( 'Recently opened', 'recipe-seo-ai-pro' ),
					'optimized'    => __( 'Recently optimized', 'recipe-seo-ai-pro' ),
				),
				'labels'     => array(
					'actions'     => $this->view_model->action_labels(),
					'rewrite'     => $this->view_model->rewrite_mode_labels(),
					'variations'  => $this->view_model->variation_labels(),
					'ingredients' => $this->view_model->ingredient_mode_labels(),
					'instructions'=> $this->view_model->instruction_mode_labels(),
				),
			)
		);

		unset( $hook_suffix );
	}

	public function render_page(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'Access denied', 'recipe-seo-ai-pro' ) );
		}
		$rb_id   = isset( $_GET['rb_recipe_id'] ) ? absint( wp_unslash( $_GET['rb_recipe_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$source  = isset( $_GET['source'] ) ? sanitize_key( (string) wp_unslash( $_GET['source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		View::render( 'admin/recipe-ai', $this->view_model->for_admin_page( $rb_id, $source, $post_id ) );
	}

	public function ajax_run(): void {
		$this->guard();
		$action = $this->post_text( 'ai_action', 'analyze' );
		$recipe = $this->post_json_array( 'recipe' );
		$source = $this->post_text( 'source_type', 'builder' );
		if ( ! in_array( $source, array( 'builder', 'post' ), true ) ) {
			$source = 'builder';
		}
		$this->respond_dto(
			$this->service->run_action(
				$action,
				array(
					'mode'         => $this->post_text( 'mode' ),
					'extra'        => isset( $_POST['extra'] ) ? sanitize_textarea_field( (string) wp_unslash( $_POST['extra'] ) ) : '',
					'rb_recipe_id' => $this->post_int( 'rb_recipe_id' ),
					'post_id'      => $this->post_int( 'post_id' ),
					'source_type'  => $source,
					'user_id'      => get_current_user_id(),
					'recipe'       => $recipe,
				)
			)
		);
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
		$this->respond_dto(
			$this->service->restore_version(
				$this->post_int( 'version_id' ),
				get_current_user_id(),
				$this->post_int( 'id' )
			)
		);
	}

	public function ajax_history(): void {
		$this->guard();
		wp_send_json_success(
			$this->service->history( $this->post_int( 'rb_recipe_id' ), $this->post_int( 'id' ) )
		);
	}

	public function ajax_list(): void {
		$this->guard();
		wp_send_json_success(
			array(
				'runs'    => $this->service->list_runs( $this->post_int( 'rb_recipe_id' ), 40 ),
				'recipes' => $this->service->list_builder_recipes( 50 ),
			)
		);
	}

	public function ajax_load_recipe(): void {
		$this->guard();
		$source  = $this->post_text( 'source_type', 'builder' );
		$post_id = $this->post_int( 'post_id' );
		$rb_id   = $this->post_int( 'rb_recipe_id' );
		if ( ! in_array( $source, array( 'builder', 'post' ), true ) ) {
			$source = 'builder';
		}
		$result = $this->service->resolve_recipe( $rb_id, array(), $source, $post_id );
		if ( is_wp_error( $result ) ) {
			$this->respond_error( $result );
		}
		if ( $source === 'post' && $post_id > 0 ) {
			$this->picker->remember_recent( $post_id );
		}
		wp_send_json_success(
			array(
				'recipe'      => $result,
				'source_type' => $source,
			)
		);
	}

	public function ajax_search_posts(): void {
		$this->guard();
		$result = $this->picker->search(
			array(
				'q'           => $this->post_text( 'q' ),
				'page'        => $this->post_int( 'page', 1 ),
				'per_page'    => $this->post_int( 'per_page', 10 ),
				'status'      => $this->post_text( 'status' ),
				'category'    => $this->post_int( 'category' ),
				'date'        => $this->post_text( 'date' ),
				'recipe_only' => ! empty( $_POST['recipe_only'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			)
		);

		// BC: keep `posts` key as a flat list of result cards.
		wp_send_json_success(
			array_merge(
				$result,
				array(
					'posts' => $result['items'],
				)
			)
		);
	}

	public function ajax_post_preview(): void {
		$this->guard();
		$result = $this->picker->preview( $this->post_int( 'post_id' ) );
		if ( is_wp_error( $result ) ) {
			$this->respond_error( $result );
		}
		wp_send_json_success( array( 'preview' => $result ) );
	}

	public function ajax_picker_shelves(): void {
		$this->guard();
		wp_send_json_success( $this->picker->shelves( get_current_user_id() ) );
	}

	public function ajax_picker_favorite(): void {
		$this->guard();
		$favs = $this->picker->toggle_favorite( $this->post_int( 'post_id' ), get_current_user_id() );
		wp_send_json_success(
			array(
				'favorites' => $favs,
				'shelves'   => $this->picker->shelves( get_current_user_id() ),
			)
		);
	}

	/**
	 * @param RecipeAiDTO|\WP_Error $result Result.
	 */
	private function respond_dto( $result ): void {
		if ( is_wp_error( $result ) ) {
			$this->respond_error( $result );
		}
		wp_send_json_success( array( 'run' => $this->view_model->present( $result ) ) );
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
	private function post_json_array( string $key ): array {
		if ( ! isset( $_POST[ $key ] ) ) {
			return array();
		}
		$raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( is_string( $raw ) && $raw !== '' ) {
			$decoded = json_decode( $raw, true );
			return is_array( $decoded ) ? $decoded : array();
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
