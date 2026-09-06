<?php
declare(strict_types=1);

/**
 * Admin + AJAX for Recipe Builder 2.0 (Phase 5.1).
 *
 * Isolated namespace rsaip_rb_* — does not touch legacy recipe card AJAX.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\RecipeBuilder;

use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeBuilderController
 */
final class RecipeBuilderController {

	public const PAGE_SLUG    = 'rsaip-recipe-builder';
	public const NONCE_ACTION = 'rsaip_recipe_builder';

	private RecipeBuilderService $service;

	private RecipeBuilderViewModel $view_model;

	private RecipeIngredientManager $ingredients;

	public function __construct(
		RecipeBuilderService $service,
		RecipeBuilderViewModel $view_model,
		RecipeIngredientManager $ingredients
	) {
		$this->service     = $service;
		$this->view_model  = $view_model;
		$this->ingredients = $ingredients;
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 24 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		$map = array(
			'rsaip_rb_list'            => 'ajax_list',
			'rsaip_rb_get'             => 'ajax_get',
			'rsaip_rb_save'            => 'ajax_save',
			'rsaip_rb_delete'          => 'ajax_delete',
			'rsaip_rb_preview'         => 'ajax_preview',
			'rsaip_rb_scale_servings'  => 'ajax_scale_servings',
			'rsaip_rb_convert_units'   => 'ajax_convert_units',
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
			__( 'Recipe Builder 2.0', 'recipe-seo-ai-pro' ),
			__( 'Recipe Builder 2.0', 'recipe-seo-ai-pro' ),
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

		$rb_css = RSAIP_PLUGIN_DIR . 'assets/recipe-builder.css';
		wp_enqueue_style( 'rsaip-recipe-builder', RSAIP_PLUGIN_URL . 'assets/recipe-builder.css', array( 'rsaip-admin' ), file_exists( $rb_css ) ? (string) filemtime( $rb_css ) : RSAIP_VERSION );

		wp_enqueue_media();

		$js_path = RSAIP_PLUGIN_DIR . 'assets/recipe-builder.js';
		wp_enqueue_script( 'rsaip-recipe-builder', RSAIP_PLUGIN_URL . 'assets/recipe-builder.js', array( 'jquery' ), file_exists( $js_path ) ? (string) filemtime( $js_path ) : RSAIP_VERSION, true );

		$recipe_id = isset( $_GET['recipe_id'] ) ? absint( wp_unslash( $_GET['recipe_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		wp_localize_script(
			'rsaip-recipe-builder',
			'RSAIP_RECIPE_BUILDER',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
				'recipeId' => $recipe_id,
				'actions'  => array(
					'list'           => 'rsaip_rb_list',
					'get'            => 'rsaip_rb_get',
					'save'           => 'rsaip_rb_save',
					'delete'         => 'rsaip_rb_delete',
					'preview'        => 'rsaip_rb_preview',
					'scaleServings'  => 'rsaip_rb_scale_servings',
					'convertUnits'   => 'rsaip_rb_convert_units',
				),
				'i18n'     => array(
					'saved'      => __( 'Recipe saved.', 'recipe-seo-ai-pro' ),
					'deleted'    => __( 'Recipe deleted.', 'recipe-seo-ai-pro' ),
					'error'      => __( 'Request failed.', 'recipe-seo-ai-pro' ),
					'confirmDel' => __( 'Delete this recipe builder draft?', 'recipe-seo-ai-pro' ),
					'group'      => __( 'Ingredient group', 'recipe-seo-ai-pro' ),
					'untitled'   => __( 'Untitled recipe', 'recipe-seo-ai-pro' ),
				),
			)
		);

		unset( $hook_suffix );
	}

	public function render_page(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'Access denied', 'recipe-seo-ai-pro' ) );
		}
		$recipe_id = isset( $_GET['recipe_id'] ) ? absint( wp_unslash( $_GET['recipe_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		View::render( 'admin/recipe-builder', $this->view_model->for_admin_page( $recipe_id ) );
	}

	public function ajax_list(): void {
		$this->guard();
		wp_send_json_success( array( 'recipes' => $this->service->list_recipes( 50 ) ) );
	}

	public function ajax_get(): void {
		$this->guard();
		$this->respond_dto( $this->service->get( $this->post_int( 'id' ) ) );
	}

	public function ajax_save(): void {
		$this->guard();
		$this->respond_dto( $this->service->save( $this->post_recipe_payload(), get_current_user_id() ) );
	}

	public function ajax_delete(): void {
		$this->guard();
		$result = $this->service->delete( $this->post_int( 'id' ) );
		if ( is_wp_error( $result ) ) {
			$this->respond_error( $result );
		}
		wp_send_json_success( array( 'deleted' => 1 ) );
	}

	public function ajax_preview(): void {
		$this->guard();
		$id = $this->post_int( 'id' );
		if ( $id > 0 ) {
			$dto = $this->service->preview(
				$id,
				array(
					'servings'    => isset( $_POST['servings'] ) ? (float) wp_unslash( $_POST['servings'] ) : 0,
					'unit_system' => $this->post_text( 'unit_system' ),
				)
			);
		} else {
			$payload = $this->post_recipe_payload();
			$dto     = new RecipeBuilderDTO();
			$dto->title       = (string) ( $payload['title'] ?? '' );
			$dto->description = (string) ( $payload['description'] ?? '' );
			$dto->servings    = (float) ( $payload['servings'] ?? 4 );
			$dto->prep_time   = (int) ( $payload['prep_time'] ?? 0 );
			$dto->cook_time   = (int) ( $payload['cook_time'] ?? 0 );
			$dto->total_time  = (int) ( $payload['total_time'] ?? 0 );
			$dto->notes       = (string) ( $payload['notes'] ?? '' );
			$dto->tips        = (string) ( $payload['tips'] ?? '' );
			$dto->equipment   = is_array( $payload['equipment'] ?? null ) ? $payload['equipment'] : array();
			$dto->unit_system = (string) ( $payload['unit_system'] ?? 'metric' );
			$dto->sections    = is_array( $payload['sections'] ?? null ) ? $payload['sections'] : array();
			$dto->ingredients = is_array( $payload['ingredients'] ?? null ) ? $payload['ingredients'] : array();
			$dto->steps       = is_array( $payload['steps'] ?? null ) ? $payload['steps'] : array();

			$preview_servings = isset( $_POST['preview_servings'] ) ? (float) wp_unslash( $_POST['preview_servings'] ) : 0;
			if ( $preview_servings > 0 && abs( $preview_servings - $dto->servings ) > 0.001 ) {
				$dto->ingredients = $this->ingredients->scale_for_servings( $dto->ingredients, $dto->servings, $preview_servings );
				$dto->servings    = $preview_servings;
			}
			$preview_units = $this->post_text( 'preview_unit_system' );
			if ( $preview_units !== '' && $preview_units !== $dto->unit_system ) {
				$dto->ingredients = $this->ingredients->convert_system( $dto->ingredients, $preview_units );
				$dto->unit_system = $preview_units === 'imperial' ? 'imperial' : 'metric';
			}
		}

		if ( is_wp_error( $dto ) ) {
			$this->respond_error( $dto );
		}

		wp_send_json_success(
			array(
				'recipe' => $this->view_model->present( $dto ),
				'html'   => $this->service->render_preview_html( $dto ),
			)
		);
	}

	public function ajax_scale_servings(): void {
		$this->guard();
		$ings = $this->post_json_array( 'ingredients' );
		$from = isset( $_POST['from_servings'] ) ? (float) wp_unslash( $_POST['from_servings'] ) : 0;
		$to   = isset( $_POST['to_servings'] ) ? (float) wp_unslash( $_POST['to_servings'] ) : 0;
		wp_send_json_success(
			array(
				'ingredients' => $this->ingredients->scale_for_servings( $ings, $from, $to ),
			)
		);
	}

	public function ajax_convert_units(): void {
		$this->guard();
		$ings   = $this->post_json_array( 'ingredients' );
		$system = $this->post_text( 'unit_system', 'metric' );
		wp_send_json_success(
			array(
				'ingredients' => $this->ingredients->convert_system( $ings, $system ),
			)
		);
	}

	/**
	 * @param RecipeBuilderDTO|\WP_Error $result Result.
	 */
	private function respond_dto( $result ): void {
		if ( is_wp_error( $result ) ) {
			$this->respond_error( $result );
		}
		wp_send_json_success( array( 'recipe' => $this->view_model->present( $result ) ) );
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
	private function post_recipe_payload(): array {
		$payload = array(
			'id'          => $this->post_int( 'id' ),
			'post_id'     => $this->post_int( 'post_id' ),
			'title'       => $this->post_text( 'title' ),
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( (string) wp_unslash( $_POST['description'] ) ) : '',
			'servings'    => isset( $_POST['servings'] ) ? (float) wp_unslash( $_POST['servings'] ) : 4,
			'prep_time'   => $this->post_int( 'prep_time' ),
			'cook_time'   => $this->post_int( 'cook_time' ),
			'total_time'  => $this->post_int( 'total_time' ),
			'notes'       => isset( $_POST['notes'] ) ? sanitize_textarea_field( (string) wp_unslash( $_POST['notes'] ) ) : '',
			'tips'        => isset( $_POST['tips'] ) ? sanitize_textarea_field( (string) wp_unslash( $_POST['tips'] ) ) : '',
			'unit_system' => $this->post_text( 'unit_system', 'metric' ),
			'status'      => $this->post_text( 'status', 'draft' ),
			'equipment'   => $this->post_json_array( 'equipment' ),
			'sections'    => $this->post_json_array( 'sections' ),
			'ingredients' => $this->post_json_array( 'ingredients' ),
			'steps'       => $this->post_json_array( 'steps' ),
		);
		return $payload;
	}

	/**
	 * @return list<mixed>|array<string, mixed>
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
