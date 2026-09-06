<?php
declare(strict_types=1);

/**
 * Validated article candidate (data only — never a WordPress post).
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\ArticleGeneration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ArticleGenerationProposal
 */
final class ArticleGenerationProposal {

	private string $title;
	private string $excerpt;
	private string $meta_description;
	private string $primary_focus_keyword;
	/** @var list<string> */
	private array $secondary_keywords;
	/** @var list<string> */
	private array $entities;
	private string $search_intent;
	private ?string $recipe_name;
	/** @var array<string, array{status: string, value: mixed}> */
	private array $recipe_facts;
	/** @var list<array{level: string, text: string}> */
	private array $headings;
	/** @var list<array{q: string, a: string}> */
	private array $faq;
	/** @var list<array{placeholder: string, alt: string, note: string}> */
	private array $image_plans;
	private string $content_html;
	private string $template;
	private int $requested_word_count;
	private int $actual_word_count;
	private bool $word_count_in_band;

	/**
	 * @param list<string>                                              $secondary_keywords
	 * @param list<string>                                              $entities
	 * @param array<string, array{status: string, value: mixed}>        $recipe_facts
	 * @param list<array{level: string, text: string}>                  $headings
	 * @param list<array{q: string, a: string}>                         $faq
	 * @param list<array{placeholder: string, alt: string, note: string}> $image_plans
	 */
	public function __construct(
		string $title,
		string $excerpt,
		string $meta_description,
		string $primary_focus_keyword,
		array $secondary_keywords,
		array $entities,
		string $search_intent,
		?string $recipe_name,
		array $recipe_facts,
		array $headings,
		array $faq,
		array $image_plans,
		string $content_html,
		string $template,
		int $requested_word_count,
		int $actual_word_count,
		bool $word_count_in_band
	) {
		$this->title                  = $title;
		$this->excerpt                = $excerpt;
		$this->meta_description       = $meta_description;
		$this->primary_focus_keyword  = $primary_focus_keyword;
		$this->secondary_keywords     = $secondary_keywords;
		$this->entities               = $entities;
		$this->search_intent          = $search_intent;
		$this->recipe_name            = $recipe_name;
		$this->recipe_facts           = $recipe_facts;
		$this->headings               = $headings;
		$this->faq                    = $faq;
		$this->image_plans            = $image_plans;
		$this->content_html           = $content_html;
		$this->template               = $template;
		$this->requested_word_count   = $requested_word_count;
		$this->actual_word_count      = $actual_word_count;
		$this->word_count_in_band     = $word_count_in_band;
	}

	public function title(): string {
		return $this->title;
	}

	public function excerpt(): string {
		return $this->excerpt;
	}

	public function meta_description(): string {
		return $this->meta_description;
	}

	public function primary_focus_keyword(): string {
		return $this->primary_focus_keyword;
	}

	/** @return list<string> */
	public function secondary_keywords(): array {
		return $this->secondary_keywords;
	}

	/** @return list<string> */
	public function entities(): array {
		return $this->entities;
	}

	public function search_intent(): string {
		return $this->search_intent;
	}

	public function recipe_name(): ?string {
		return $this->recipe_name;
	}

	/** @return array<string, array{status: string, value: string}> */
	public function recipe_facts(): array {
		return $this->recipe_facts;
	}

	/** @return list<array{level: string, text: string}> */
	public function headings(): array {
		return $this->headings;
	}

	/** @return list<array{q: string, a: string}> */
	public function faq(): array {
		return $this->faq;
	}

	/** @return list<array{placeholder: string, alt: string, note: string}> */
	public function image_plans(): array {
		return $this->image_plans;
	}

	public function content_html(): string {
		return $this->content_html;
	}

	public function template(): string {
		return $this->template;
	}

	public function requested_word_count(): int {
		return $this->requested_word_count;
	}

	public function actual_word_count(): int {
		return $this->actual_word_count;
	}

	public function word_count_in_band(): bool {
		return $this->word_count_in_band;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'title'                 => $this->title,
			'excerpt'               => $this->excerpt,
			'meta_description'      => $this->meta_description,
			'primary_focus_keyword' => $this->primary_focus_keyword,
			'secondary_keywords'    => $this->secondary_keywords,
			'entities'              => $this->entities,
			'search_intent'         => $this->search_intent,
			'recipe_name'           => $this->recipe_name,
			'recipe_facts'          => $this->recipe_facts,
			'headings'              => $this->headings,
			'faq'                   => $this->faq,
			'image_plans'           => $this->image_plans,
			'content_html'          => $this->content_html,
			'template'              => $this->template,
			'requested_word_count'  => $this->requested_word_count,
			'actual_word_count'     => $this->actual_word_count,
			'word_count_in_band'    => $this->word_count_in_band,
		);
	}
}
