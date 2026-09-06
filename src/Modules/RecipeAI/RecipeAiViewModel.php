<?php
declare(strict_types=1);

/**
 * View model for AI Recipe Assistant (Phase 5.2).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\RecipeAI;

use RecipeSeoAiPro\Contracts\SettingsServiceInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeAiViewModel
 */
final class RecipeAiViewModel {

	private SettingsServiceInterface $settings;

	private RecipeAiService $service;

	public function __construct( SettingsServiceInterface $settings, RecipeAiService $service ) {
		$this->settings = $settings;
		$this->service  = $service;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function for_admin_page( int $rb_recipe_id = 0, string $source_type = 'builder', int $post_id = 0 ): array {
		$all      = $this->settings->all();
		$provider = isset( $all['ai_provider'] ) ? sanitize_key( (string) $all['ai_provider'] ) : '';
		$endpoint = isset( $all['ai_endpoint'] ) ? trim( (string) $all['ai_endpoint'] ) : '';
		$model    = isset( $all['ai_model'] ) ? trim( (string) $all['ai_model'] ) : '';
		$has_key  = function_exists( 'rsaip_setting_has_secret' )
			? rsaip_setting_has_secret( $all, 'ai_api_key' )
			: ( isset( $all['ai_api_key'] ) && trim( (string) $all['ai_api_key'] ) !== '' );
		$ai_ready = ( $provider === 'openai_compatible' ) && $endpoint !== '' && $model !== '' && $has_key;

		$source_type = sanitize_key( $source_type );
		if ( ! in_array( $source_type, array( 'builder', 'post' ), true ) ) {
			$source_type = $post_id > 0 ? 'post' : 'builder';
		}

		return array(
			'title'            => __( 'AI Recipe Assistant', 'recipe-seo-ai-pro' ),
			'ai_ready'         => $ai_ready,
			'ai_provider'      => $provider,
			'rb_recipe_id'     => $rb_recipe_id,
			'post_id'          => $post_id,
			'source_type'      => $source_type,
			'recipes'          => $this->service->list_builder_recipes( 50 ),
			'runs'             => $this->service->list_runs( $rb_recipe_id, 20 ),
			'actions'          => $this->action_labels(),
			'ingredient_modes' => $this->ingredient_mode_labels(),
			'instruction_modes'=> $this->instruction_mode_labels(),
			'rewrite_modes'    => $this->rewrite_mode_labels(),
			'variations'       => $this->variation_labels(),
			'note'             => __( 'AI-powered recipe optimization from Recipe Builder or WordPress posts. Apply saves to the selected source after confirmation.', 'recipe-seo-ai-pro' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function present( RecipeAiDTO $dto ): array {
		return $dto->to_array();
	}

	/**
	 * @return array<string, string>
	 */
	public function action_labels(): array {
		return array(
			'ingredients'  => __( 'Ingredient Assistant', 'recipe-seo-ai-pro' ),
			'instructions' => __( 'Instruction Assistant', 'recipe-seo-ai-pro' ),
			'rewrite'      => __( 'Recipe Rewrite', 'recipe-seo-ai-pro' ),
			'variation'    => __( 'Recipe Variation', 'recipe-seo-ai-pro' ),
			'improve'      => __( 'Recipe Improvements', 'recipe-seo-ai-pro' ),
			'analyze'      => __( 'Quality Analyzer', 'recipe-seo-ai-pro' ),
			'nutrition'    => __( 'Nutrition Suggestions', 'recipe-seo-ai-pro' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function ingredient_mode_labels(): array {
		return array(
			'all'         => __( 'All suggestions', 'recipe-seo-ai-pro' ),
			'missing'     => __( 'Missing ingredients', 'recipe-seo-ai-pro' ),
			'substitutes' => __( 'Substitutes', 'recipe-seo-ai-pro' ),
			'premium'     => __( 'Premium alternatives', 'recipe-seo-ai-pro' ),
			'budget'      => __( 'Budget alternatives', 'recipe-seo-ai-pro' ),
			'duplicates'  => __( 'Detect duplicates', 'recipe-seo-ai-pro' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function instruction_mode_labels(): array {
		return array(
			'all'      => __( 'Full improve', 'recipe-seo-ai-pro' ),
			'improve'  => __( 'Improve instructions', 'recipe-seo-ai-pro' ),
			'rewrite'  => __( 'Rewrite steps', 'recipe-seo-ai-pro' ),
			'simplify' => __( 'Simplify process', 'recipe-seo-ai-pro' ),
			'tips'     => __( 'Add cooking tips', 'recipe-seo-ai-pro' ),
			'chef'     => __( 'Add chef notes', 'recipe-seo-ai-pro' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function rewrite_mode_labels(): array {
		return array(
			'beginner_friendly' => __( 'Beginner Friendly', 'recipe-seo-ai-pro' ),
			'professional_chef' => __( 'Professional Chef', 'recipe-seo-ai-pro' ),
			'restaurant_style'  => __( 'Restaurant Style', 'recipe-seo-ai-pro' ),
			'seo_optimized'     => __( 'SEO Optimized', 'recipe-seo-ai-pro' ),
			'family_friendly'   => __( 'Family Friendly', 'recipe-seo-ai-pro' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function variation_labels(): array {
		return array(
			'keto'         => __( 'Keto', 'recipe-seo-ai-pro' ),
			'low_carb'     => __( 'Low Carb', 'recipe-seo-ai-pro' ),
			'gluten_free'  => __( 'Gluten Free', 'recipe-seo-ai-pro' ),
			'vegan'        => __( 'Vegan', 'recipe-seo-ai-pro' ),
			'vegetarian'   => __( 'Vegetarian', 'recipe-seo-ai-pro' ),
			'dairy_free'   => __( 'Dairy Free', 'recipe-seo-ai-pro' ),
			'high_protein' => __( 'High Protein', 'recipe-seo-ai-pro' ),
			'low_fat'      => __( 'Low Fat', 'recipe-seo-ai-pro' ),
		);
	}
}
