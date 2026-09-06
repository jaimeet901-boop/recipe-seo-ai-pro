<?php
declare(strict_types=1);

/**
 * Recipe Builder sections / ingredient groups (Phase 5.1).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\RecipeBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeSectionManager
 */
final class RecipeSectionManager {

	private RecipeBuilderRepository $repository;

	public function __construct( RecipeBuilderRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Replace all sections for a recipe from payload.
	 *
	 * @param list<array<string, mixed>> $sections Sections.
	 * @return list<array<string, mixed>> Saved rows with ids.
	 */
	public function sync( int $recipe_id, array $sections ): array {
		$this->repository->delete_sections_for_recipe( $recipe_id );
		$now  = \RSAIP_DB::now_gmt_sql();
		$saved = array();
		$order = 0;
		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			$title = sanitize_text_field( (string) ( $section['title'] ?? '' ) );
			$type  = sanitize_key( (string) ( $section['section_type'] ?? 'ingredient_group' ) );
			if ( ! in_array( $type, array( 'ingredient_group', 'steps', 'custom' ), true ) ) {
				$type = 'ingredient_group';
			}
			$id = $this->repository->insert_section(
				array(
					'recipe_id'    => $recipe_id,
					'section_type' => $type,
					'title'        => mb_substr( $title !== '' ? $title : __( 'Group', 'recipe-seo-ai-pro' ), 0, 255 ),
					'sort_order'   => isset( $section['sort_order'] ) ? absint( $section['sort_order'] ) : $order,
					'created_at'   => $now,
					'updated_at'   => $now,
				)
			);
			if ( $id > 0 ) {
				$saved[] = array(
					'id'           => $id,
					'client_key'   => (string) ( $section['client_key'] ?? '' ),
					'title'        => $title,
					'section_type' => $type,
					'sort_order'   => $order,
				);
			}
			++$order;
		}
		return $saved;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_for( int $recipe_id ): array {
		return $this->repository->list_sections( $recipe_id );
	}
}
