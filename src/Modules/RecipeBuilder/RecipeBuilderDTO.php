<?php
declare(strict_types=1);

/**
 * Recipe Builder 2.0 DTO (Phase 5.1).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\RecipeBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeBuilderDTO
 */
final class RecipeBuilderDTO {

	public int $id = 0;

	public int $post_id = 0;

	public string $title = '';

	public string $description = '';

	public float $servings = 4.0;

	public int $prep_time = 0;

	public int $cook_time = 0;

	public int $total_time = 0;

	public string $notes = '';

	public string $tips = '';

	/** @var list<string> */
	public array $equipment = array();

	public string $unit_system = 'metric';

	public string $status = 'draft';

	/** @var list<array<string, mixed>> */
	public array $sections = array();

	/** @var list<array<string, mixed>> */
	public array $ingredients = array();

	/** @var list<array<string, mixed>> */
	public array $steps = array();

	public int $user_id = 0;

	public string $created_at = '';

	public string $updated_at = '';

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'           => $this->id,
			'post_id'      => $this->post_id,
			'title'        => $this->title,
			'description'  => $this->description,
			'servings'     => $this->servings,
			'prep_time'    => $this->prep_time,
			'cook_time'    => $this->cook_time,
			'total_time'   => $this->total_time > 0 ? $this->total_time : ( $this->prep_time + $this->cook_time ),
			'notes'        => $this->notes,
			'tips'         => $this->tips,
			'equipment'    => $this->equipment,
			'unit_system'  => $this->unit_system,
			'status'       => $this->status,
			'sections'     => $this->sections,
			'ingredients'  => $this->ingredients,
			'steps'        => $this->steps,
			'user_id'      => $this->user_id,
			'created_at'   => $this->created_at,
			'updated_at'   => $this->updated_at,
		);
	}
}
