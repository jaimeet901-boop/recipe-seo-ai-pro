<?php
declare(strict_types=1);

/**
 * AI Recipe Assistant run DTO (Phase 5.2).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\RecipeAI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeAiDTO
 */
final class RecipeAiDTO {

	public int $id = 0;

	public int $rb_recipe_id = 0;

	public string $action_type = '';

	public string $mode = '';

	public string $status = 'draft';

	/** @var array<string, mixed> */
	public array $original = array();

	/** @var array<string, mixed> */
	public array $optimized = array();

	/** @var array<string, mixed> */
	public array $analysis = array();

	/** @var array<string, mixed> */
	public array $diff = array();

	public string $summary = '';

	public int $user_id = 0;

	public string $created_at = '';

	public string $updated_at = '';

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'           => $this->id,
			'rb_recipe_id' => $this->rb_recipe_id,
			'action_type'  => $this->action_type,
			'mode'         => $this->mode,
			'status'       => $this->status,
			'original'     => $this->original,
			'optimized'    => $this->optimized,
			'analysis'     => $this->analysis,
			'diff'         => $this->diff,
			'summary'      => $this->summary,
			'user_id'      => $this->user_id,
			'created_at'   => $this->created_at,
			'updated_at'   => $this->updated_at,
		);
	}
}
