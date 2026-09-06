<?php
declare(strict_types=1);

/**
 * Result of authoritative Recipe Schema detection.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeSchemaDetectionResult
 */
final class RecipeSchemaDetectionResult {

	private string $status;
	private string $source;
	private string $recipe_name;
	private int $count;
	private string $details;
	private bool $authoritative;
	private bool $has_rsaip_card;
	private bool $repair_allowed;

	public function __construct(
		string $status,
		string $source,
		string $recipe_name,
		int $count,
		string $details,
		bool $authoritative,
		bool $has_rsaip_card,
		bool $repair_allowed
	) {
		$this->status         = RecipeSchemaStatus::is_known( $status ) ? $status : RecipeSchemaStatus::EXTERNAL_OR_UNKNOWN;
		$this->source         = $source;
		$this->recipe_name    = $recipe_name;
		$this->count          = max( 0, $count );
		$this->details        = $details;
		$this->authoritative  = $authoritative;
		$this->has_rsaip_card = $has_rsaip_card;
		$this->repair_allowed = $repair_allowed;
	}

	public function status(): string {
		return $this->status;
	}

	public function source(): string {
		return $this->source;
	}

	public function recipe_name(): string {
		return $this->recipe_name;
	}

	public function count(): int {
		return $this->count;
	}

	public function details(): string {
		return $this->details;
	}

	public function authoritative(): bool {
		return $this->authoritative;
	}

	public function has_rsaip_card(): bool {
		return $this->has_rsaip_card;
	}

	/**
	 * Whether a future explicit repair MAY be offered (never auto-write).
	 * True only for definitive missing with no external owner uncertainty.
	 */
	public function repair_allowed(): bool {
		return $this->repair_allowed;
	}

	public function has_valid_recipe_schema(): bool {
		return $this->status === RecipeSchemaStatus::VALID_RECIPE;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'recipe_schema_status'         => $this->status,
			'recipe_schema_source'         => $this->source,
			'recipe_schema_recipe_name'    => $this->recipe_name,
			'recipe_schema_count'          => $this->count,
			'recipe_schema_details'        => $this->details,
			'recipe_schema_authoritative'  => $this->authoritative,
			'recipe_schema_has_rsaip_card' => $this->has_rsaip_card,
			'recipe_schema_repair_allowed' => $this->repair_allowed,
			'has_recipe_schema'            => $this->has_valid_recipe_schema(),
		);
	}
}
