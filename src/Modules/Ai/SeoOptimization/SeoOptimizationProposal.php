<?php
declare(strict_types=1);

/**
 * Validated SEO optimization proposal (data only).
 *
 * Never writes posts/meta, never decides ownership, post_id, or Apply.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\SeoOptimization;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SeoOptimizationProposal
 */
final class SeoOptimizationProposal {

	private string $topic;
	private ?string $recipe_name;
	private string $search_intent;
	private string $primary_focus_keyword;
	/** @var list<string> */
	private array $secondary_keywords;
	/** @var list<string> */
	private array $entities;
	/** @var list<string> */
	private array $seo_title_suggestions;
	private string $recommended_title;
	private string $meta_description;
	/** @var list<array{level: string, text: string, rationale: string}> */
	private array $heading_suggestions;
	/** @var list<array{q: string, a: string}> */
	private array $faq_suggestions;
	/** @var list<array{target: string, alt: string}> */
	private array $image_alt_suggestions;
	/** @var list<array{anchor: string, target_hint: string, reason: string}> */
	private array $internal_link_suggestions;
	/** @var list<string> */
	private array $content_gaps;
	/** @var list<array{code: string, category: string, severity: string}> */
	private array $seo_issues;
	/** @var list<array{action: string, applies_via: string, note: string}> */
	private array $recommendations;
	private ?float $confidence;
	private string $reasoning_summary;

	/**
	 * @param list<string>                                                        $secondary_keywords
	 * @param list<string>                                                        $entities
	 * @param list<string>                                                        $seo_title_suggestions
	 * @param list<array{level: string, text: string, rationale: string}>         $heading_suggestions
	 * @param list<array{q: string, a: string}>                                   $faq_suggestions
	 * @param list<array{target: string, alt: string}>                            $image_alt_suggestions
	 * @param list<array{anchor: string, target_hint: string, reason: string}>    $internal_link_suggestions
	 * @param list<string>                                                        $content_gaps
	 * @param list<array{code: string, category: string, severity: string}>       $seo_issues
	 * @param list<array{action: string, applies_via: string, note: string}>      $recommendations
	 */
	public function __construct(
		string $topic,
		?string $recipe_name,
		string $search_intent,
		string $primary_focus_keyword,
		array $secondary_keywords,
		array $entities,
		array $seo_title_suggestions,
		string $recommended_title,
		string $meta_description,
		array $heading_suggestions,
		array $faq_suggestions,
		array $image_alt_suggestions,
		array $internal_link_suggestions,
		array $content_gaps,
		array $seo_issues,
		array $recommendations,
		?float $confidence,
		string $reasoning_summary
	) {
		$this->topic                   = $topic;
		$this->recipe_name             = $recipe_name;
		$this->search_intent           = $search_intent;
		$this->primary_focus_keyword   = $primary_focus_keyword;
		$this->secondary_keywords      = $secondary_keywords;
		$this->entities                = $entities;
		$this->seo_title_suggestions   = $seo_title_suggestions;
		$this->recommended_title       = $recommended_title;
		$this->meta_description        = $meta_description;
		$this->heading_suggestions     = $heading_suggestions;
		$this->faq_suggestions         = $faq_suggestions;
		$this->image_alt_suggestions   = $image_alt_suggestions;
		$this->internal_link_suggestions = $internal_link_suggestions;
		$this->content_gaps            = $content_gaps;
		$this->seo_issues              = $seo_issues;
		$this->recommendations         = $recommendations;
		$this->confidence              = $confidence;
		$this->reasoning_summary       = $reasoning_summary;
	}

	public function topic(): string {
		return $this->topic;
	}

	public function recipe_name(): ?string {
		return $this->recipe_name;
	}

	public function search_intent(): string {
		return $this->search_intent;
	}

	public function primary_focus_keyword(): string {
		return $this->primary_focus_keyword;
	}

	/**
	 * @return list<string>
	 */
	public function secondary_keywords(): array {
		return $this->secondary_keywords;
	}

	/**
	 * @return list<string>
	 */
	public function entities(): array {
		return $this->entities;
	}

	/**
	 * @return list<string>
	 */
	public function seo_title_suggestions(): array {
		return $this->seo_title_suggestions;
	}

	public function recommended_title(): string {
		return $this->recommended_title;
	}

	public function meta_description(): string {
		return $this->meta_description;
	}

	/**
	 * @return list<array{level: string, text: string, rationale: string}>
	 */
	public function heading_suggestions(): array {
		return $this->heading_suggestions;
	}

	/**
	 * @return list<array{q: string, a: string}>
	 */
	public function faq_suggestions(): array {
		return $this->faq_suggestions;
	}

	/**
	 * @return list<array{target: string, alt: string}>
	 */
	public function image_alt_suggestions(): array {
		return $this->image_alt_suggestions;
	}

	/**
	 * @return list<array{anchor: string, target_hint: string, reason: string}>
	 */
	public function internal_link_suggestions(): array {
		return $this->internal_link_suggestions;
	}

	/**
	 * @return list<string>
	 */
	public function content_gaps(): array {
		return $this->content_gaps;
	}

	/**
	 * @return list<array{code: string, category: string, severity: string}>
	 */
	public function seo_issues(): array {
		return $this->seo_issues;
	}

	/**
	 * @return list<array{action: string, applies_via: string, note: string}>
	 */
	public function recommendations(): array {
		return $this->recommendations;
	}

	public function confidence(): ?float {
		return $this->confidence;
	}

	public function reasoning_summary(): string {
		return $this->reasoning_summary;
	}

	/**
	 * Trusted export for UI / later Preview mapping. No mutation instructions.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'topic'                      => $this->topic,
			'recipe_name'                => $this->recipe_name,
			'search_intent'              => $this->search_intent,
			'primary_focus_keyword'      => $this->primary_focus_keyword,
			'secondary_keywords'         => $this->secondary_keywords,
			'entities'                   => $this->entities,
			'seo_title_suggestions'      => $this->seo_title_suggestions,
			'recommended_title'          => $this->recommended_title,
			'meta_description'           => $this->meta_description,
			'heading_suggestions'        => $this->heading_suggestions,
			'faq_suggestions'            => $this->faq_suggestions,
			'image_alt_suggestions'      => $this->image_alt_suggestions,
			'internal_link_suggestions'  => $this->internal_link_suggestions,
			'content_gaps'               => $this->content_gaps,
			'seo_issues'                 => $this->seo_issues,
			'recommendations'            => $this->recommendations,
			'confidence'                 => $this->confidence,
			'reasoning_summary'          => $this->reasoning_summary,
		);
	}
}
