<?php
declare(strict_types=1);

/**
 * View model for Recipe Builder 2.0 (Phase 5.1).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\RecipeBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeBuilderViewModel
 */
final class RecipeBuilderViewModel {

	private RecipeBuilderService $service;

	public function __construct( RecipeBuilderService $service ) {
		$this->service = $service;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function for_admin_page( int $recipe_id = 0 ): array {
		$recipe = null;
		if ( $recipe_id > 0 ) {
			$dto = $this->service->get( $recipe_id );
			if ( ! is_wp_error( $dto ) ) {
				$recipe = $this->present( $dto );
			}
		}

		return array(
			'title'      => __( 'Recipe Builder 2.0', 'recipe-seo-ai-pro' ),
			'recipe_id'  => $recipe_id,
			'recipe'     => $recipe,
			'recipes'    => $this->service->list_recipes( 50 ),
			'unit_systems' => array(
				'metric'   => __( 'Metric', 'recipe-seo-ai-pro' ),
				'imperial' => __( 'Imperial', 'recipe-seo-ai-pro' ),
			),
			'statuses'   => array(
				'draft'     => __( 'Draft', 'recipe-seo-ai-pro' ),
				'published' => __( 'Published', 'recipe-seo-ai-pro' ),
			),
			'note'       => __( 'Visual recipe builder with drag-and-drop ingredients and steps. Separate from the legacy Recipe Engine — existing recipe cards are unchanged.', 'recipe-seo-ai-pro' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function present( RecipeBuilderDTO $dto ): array {
		return $dto->to_array();
	}
}
