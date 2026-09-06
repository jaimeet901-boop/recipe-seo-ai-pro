<?php
declare(strict_types=1);

/**
 * Server-side SEO analysis context (never taken from AI output).
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\SeoOptimization;

use RecipeSeoAiPro\Modules\Schema\RecipeSchemaStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SeoOptimizationContext
 */
final class SeoOptimizationContext {

	private int $post_id;
	private string $current_title;
	private string $content_excerpt;
	private string $existing_focus_keyword;
	/** @var list<string> */
	private array $categories;
	/** @var list<string> */
	private array $tags;
	private string $detected_recipe_name;
	private string $recipe_summary;
	/** @var list<string> */
	private array $recipe_ingredients;
	/** @var array<string, string> Known facts only (never invent). */
	private array $known_recipe_facts;
	private string $seo_owner_label;
	private bool $has_recipe_card;
	private string $recipe_schema_status;
	private string $recipe_schema_source;
	private bool $recipe_schema_authoritative;
	private string $recipe_schema_details;
	private bool $recipe_schema_repair_allowed;

	/**
	 * @param list<string>            $categories
	 * @param list<string>            $tags
	 * @param list<string>            $recipe_ingredients
	 * @param array<string, string>   $known_recipe_facts
	 */
	public function __construct(
		int $post_id,
		string $current_title,
		string $content_excerpt,
		string $existing_focus_keyword,
		array $categories,
		array $tags,
		string $detected_recipe_name,
		string $recipe_summary,
		string $seo_owner_label,
		bool $has_recipe_card,
		string $recipe_schema_status = RecipeSchemaStatus::EXTERNAL_OR_UNKNOWN,
		string $recipe_schema_source = 'unknown',
		bool $recipe_schema_authoritative = false,
		string $recipe_schema_details = '',
		bool $recipe_schema_repair_allowed = false,
		array $recipe_ingredients = array(),
		array $known_recipe_facts = array()
	) {
		$this->post_id                      = $post_id;
		$this->current_title                = $current_title;
		$this->content_excerpt              = $content_excerpt;
		$this->existing_focus_keyword       = $existing_focus_keyword;
		$this->categories                   = $categories;
		$this->tags                         = $tags;
		$this->detected_recipe_name         = $detected_recipe_name;
		$this->recipe_summary               = $recipe_summary;
		$this->recipe_ingredients           = array_values( $recipe_ingredients );
		$this->known_recipe_facts           = $known_recipe_facts;
		$this->seo_owner_label              = $seo_owner_label;
		$this->has_recipe_card              = $has_recipe_card;
		$this->recipe_schema_status         = RecipeSchemaStatus::is_known( $recipe_schema_status )
			? $recipe_schema_status
			: RecipeSchemaStatus::EXTERNAL_OR_UNKNOWN;
		$this->recipe_schema_source         = $recipe_schema_source;
		$this->recipe_schema_authoritative  = $recipe_schema_authoritative;
		$this->recipe_schema_details        = $recipe_schema_details;
		$this->recipe_schema_repair_allowed = $recipe_schema_repair_allowed;
	}

	public function post_id(): int {
		return $this->post_id;
	}

	public function current_title(): string {
		return $this->current_title;
	}

	public function content_excerpt(): string {
		return $this->content_excerpt;
	}

	public function existing_focus_keyword(): string {
		return $this->existing_focus_keyword;
	}

	/**
	 * @return list<string>
	 */
	public function categories(): array {
		return $this->categories;
	}

	/**
	 * @return list<string>
	 */
	public function tags(): array {
		return $this->tags;
	}

	public function detected_recipe_name(): string {
		return $this->detected_recipe_name;
	}

	/** Alias for Milestone 5E canonical entity. */
	public function canonical_recipe_entity(): string {
		return $this->detected_recipe_name !== '' ? $this->detected_recipe_name : $this->specific_entity_hint();
	}

	public function recipe_summary(): string {
		return $this->recipe_summary;
	}

	/**
	 * @return list<string>
	 */
	public function recipe_ingredients(): array {
		return $this->recipe_ingredients;
	}

	/**
	 * @return array<string, string>
	 */
	public function known_recipe_facts(): array {
		return $this->known_recipe_facts;
	}

	public function seo_owner_label(): string {
		return $this->seo_owner_label;
	}

	public function has_recipe_card(): bool {
		return $this->has_recipe_card;
	}

	/**
	 * Backward-compatible boolean: only true for valid_recipe.
	 */
	public function has_recipe_schema(): bool {
		return $this->recipe_schema_status === RecipeSchemaStatus::VALID_RECIPE;
	}

	public function recipe_schema_status(): string {
		return $this->recipe_schema_status;
	}

	public function recipe_schema_source(): string {
		return $this->recipe_schema_source;
	}

	public function recipe_schema_authoritative(): bool {
		return $this->recipe_schema_authoritative;
	}

	public function recipe_schema_details(): string {
		return $this->recipe_schema_details;
	}

	public function recipe_schema_repair_allowed(): bool {
		return $this->recipe_schema_repair_allowed;
	}

	public function specific_entity_hint(): string {
		if ( $this->detected_recipe_name !== '' ) {
			return $this->detected_recipe_name;
		}
		return $this->current_title;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_prompt_array(): array {
		$facts = $this->known_recipe_facts;
		// Explicit unavailable markers help the model omit rather than invent.
		$fact_keys = array( 'prep_time', 'cook_time', 'total_time', 'servings', 'cuisine', 'dietary' );
		$facts_out = array();
		foreach ( $fact_keys as $key ) {
			$facts_out[ $key ] = isset( $facts[ $key ] ) && $facts[ $key ] !== ''
				? $facts[ $key ]
				: 'unavailable';
		}

		return array(
			'current_title'                => $this->current_title,
			'content_excerpt'              => $this->content_excerpt,
			'existing_focus_keyword'       => $this->existing_focus_keyword,
			'existing_focus_keyword_note'  => 'Informational only. Do not prefer a generic focus keyword over detected_recipe_name.',
			'categories'                   => $this->categories,
			'tags'                         => $this->tags,
			'detected_recipe_name'         => $this->detected_recipe_name,
			'canonical_recipe_entity'      => $this->canonical_recipe_entity(),
			'recipe_summary'               => $this->recipe_summary,
			'recipe_ingredients'           => $this->recipe_ingredients,
			'known_recipe_facts'           => $facts_out,
			'seo_owner_label'              => $this->seo_owner_label,
			'has_recipe_card'              => $this->has_recipe_card,
			'has_recipe_schema'            => $this->has_recipe_schema(),
			'recipe_schema_status'         => $this->recipe_schema_status,
			'recipe_schema_source'         => $this->recipe_schema_source,
			'recipe_schema_authoritative'  => $this->recipe_schema_authoritative,
			'recipe_schema_details'        => $this->recipe_schema_details,
			'recipe_schema_repair_allowed' => false, // Never let AI treat repair as permitted write.
		);
	}

	/**
	 * Session/store serialization (includes post_id for rebuild; not sent to the model).
	 *
	 * @return array<string, mixed>
	 */
	public function to_server_array(): array {
		$data                                   = $this->to_prompt_array();
		$data['post_id']                        = $this->post_id;
		$data['recipe_schema_repair_allowed']   = false;
		$data['recipe_ingredients']             = $this->recipe_ingredients;
		$data['known_recipe_facts']             = $this->known_recipe_facts;
		return $data;
	}
}
