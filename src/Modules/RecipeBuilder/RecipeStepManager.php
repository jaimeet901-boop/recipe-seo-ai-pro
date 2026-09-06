<?php
declare(strict_types=1);

/**
 * Recipe Builder steps (Phase 5.1).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\RecipeBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeStepManager
 */
final class RecipeStepManager {

	private RecipeBuilderRepository $repository;

	public function __construct( RecipeBuilderRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * @param list<array<string, mixed>> $steps Steps.
	 * @return list<array<string, mixed>>
	 */
	public function sync( int $recipe_id, array $steps ): array {
		$this->repository->delete_steps_for_recipe( $recipe_id );
		$now   = \RSAIP_DB::now_gmt_sql();
		$saved = array();
		$order = 0;
		foreach ( $steps as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$instruction = sanitize_textarea_field( (string) ( $row['instruction'] ?? '' ) );
			if ( $instruction === '' ) {
				continue;
			}
			$image = isset( $row['image_url'] ) ? esc_url_raw( (string) $row['image_url'] ) : '';
			$id    = $this->repository->insert_step(
				array(
					'recipe_id'   => $recipe_id,
					'section_id'  => absint( $row['section_id'] ?? 0 ),
					'instruction' => $instruction,
					'image_url'   => $image,
					'sort_order'  => isset( $row['sort_order'] ) ? absint( $row['sort_order'] ) : $order,
					'created_at'  => $now,
					'updated_at'  => $now,
				)
			);
			if ( $id > 0 ) {
				$saved[] = array(
					'id'          => $id,
					'instruction' => $instruction,
					'image_url'   => $image,
					'sort_order'  => $order,
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
		return $this->repository->list_steps( $recipe_id );
	}
}
