<?php
declare(strict_types=1);

/**
 * Server-owned brief/context for article generation (no existing post required).
 *
 * A: foundation · B: recipe facts · C: SEO strategy (read-only M5 recommendations).
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\ArticleGeneration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ArticleGenerationContext
 */
final class ArticleGenerationContext {

	public const WORD_COUNT_MIN = 400;
	public const WORD_COUNT_MAX = 4000;
	public const TEMPLATE_GENERAL = 'general';
	public const TEMPLATE_RECIPE_MIDJOURNEY = 'recipe_seo_midjourney';

	private string $title;
	private string $template;
	private int $requested_word_count;
	/** @var list<string> */
	private array $supplied_keywords;
	/** @var list<string> */
	private array $image_urls;
	private string $canonical_recipe_entity;
	private string $search_intent_hint;
	private string $primary_focus_keyword_hint;
	/** @var list<string> */
	private array $secondary_keyword_hints;
	/** @var list<string> */
	private array $entity_hints;
	/** @var array<string, array{status: string, value: mixed}> */
	private array $recipe_facts;
	/** @var list<string> */
	private array $content_gap_hints;
	private string $recommended_title_hint;
	private string $meta_description_hint;
	/** @var array<string, mixed>|null Optional opaque M5 seed snapshot (never trusted as write instructions). */
	private ?array $seo_seed;
	private string $recipe_schema_status;
	private string $recipe_schema_source;
	private string $recipe_schema_details;
	private string $recipe_fact_source;
	private bool $has_rsaip_card;
	/** @var list<array{level: string, text: string}> */
	private array $heading_hints;
	/** @var list<array{q: string, a: string}> */
	private array $faq_hints;
	/** @var list<string> */
	private array $image_alt_hints;
	/** @var list<array{anchor: string, target_hint: string}> */
	private array $internal_link_hints;
	/** @var list<string> */
	private array $recommendation_hints;
	/** @var list<string> */
	private array $seo_title_hints;

	/**
	 * @param list<string>                                              $supplied_keywords
	 * @param list<string>                                              $image_urls
	 * @param list<string>                                              $secondary_keyword_hints
	 * @param list<string>                                              $entity_hints
	 * @param array<string, array{status: string, value: mixed}>        $recipe_facts
	 * @param list<string>                                              $content_gap_hints
	 * @param array<string, mixed>|null                                 $seo_seed
	 * @param list<array{level: string, text: string}>                  $heading_hints
	 * @param list<array{q: string, a: string}>                         $faq_hints
	 * @param list<string>                                              $image_alt_hints
	 * @param list<array{anchor: string, target_hint: string}>          $internal_link_hints
	 * @param list<string>                                              $recommendation_hints
	 * @param list<string>                                              $seo_title_hints
	 */
	public function __construct(
		string $title,
		string $template,
		int $requested_word_count,
		array $supplied_keywords,
		array $image_urls,
		string $canonical_recipe_entity = '',
		string $search_intent_hint = '',
		string $primary_focus_keyword_hint = '',
		array $secondary_keyword_hints = array(),
		array $entity_hints = array(),
		array $recipe_facts = array(),
		array $content_gap_hints = array(),
		string $recommended_title_hint = '',
		string $meta_description_hint = '',
		?array $seo_seed = null,
		string $recipe_schema_status = 'none',
		string $recipe_schema_source = 'none',
		string $recipe_schema_details = '',
		string $recipe_fact_source = 'none',
		bool $has_rsaip_card = false,
		array $heading_hints = array(),
		array $faq_hints = array(),
		array $image_alt_hints = array(),
		array $internal_link_hints = array(),
		array $recommendation_hints = array(),
		array $seo_title_hints = array()
	) {
		$this->title                      = $title;
		$this->template                   = $template;
		$this->requested_word_count       = $requested_word_count;
		$this->supplied_keywords          = $supplied_keywords;
		$this->image_urls                 = $image_urls;
		$this->canonical_recipe_entity    = $canonical_recipe_entity;
		$this->search_intent_hint         = $search_intent_hint;
		$this->primary_focus_keyword_hint = $primary_focus_keyword_hint;
		$this->secondary_keyword_hints    = $secondary_keyword_hints;
		$this->entity_hints               = $entity_hints;
		$this->recipe_facts               = $recipe_facts;
		$this->content_gap_hints          = $content_gap_hints;
		$this->recommended_title_hint     = $recommended_title_hint;
		$this->meta_description_hint      = $meta_description_hint;
		$this->seo_seed                   = $seo_seed;
		$this->recipe_schema_status       = $recipe_schema_status;
		$this->recipe_schema_source       = $recipe_schema_source;
		$this->recipe_schema_details      = $recipe_schema_details;
		$this->recipe_fact_source         = $recipe_fact_source;
		$this->has_rsaip_card             = $has_rsaip_card;
		$this->heading_hints              = $heading_hints;
		$this->faq_hints                  = $faq_hints;
		$this->image_alt_hints            = $image_alt_hints;
		$this->internal_link_hints        = $internal_link_hints;
		$this->recommendation_hints       = $recommendation_hints;
		$this->seo_title_hints            = $seo_title_hints;
	}

	public function title(): string {
		return $this->title;
	}

	public function template(): string {
		return $this->template;
	}

	public function requested_word_count(): int {
		return $this->requested_word_count;
	}

	/**
	 * @return list<string>
	 */
	public function supplied_keywords(): array {
		return $this->supplied_keywords;
	}

	/**
	 * @return list<string>
	 */
	public function image_urls(): array {
		return $this->image_urls;
	}

	public function canonical_recipe_entity(): string {
		return $this->canonical_recipe_entity !== '' ? $this->canonical_recipe_entity : $this->title;
	}

	public function search_intent_hint(): string {
		return $this->search_intent_hint;
	}

	public function primary_focus_keyword_hint(): string {
		return $this->primary_focus_keyword_hint;
	}

	/**
	 * @return list<string>
	 */
	public function secondary_keyword_hints(): array {
		return $this->secondary_keyword_hints;
	}

	/**
	 * @return list<string>
	 */
	public function entity_hints(): array {
		return $this->entity_hints;
	}

	/**
	 * @return array<string, array{status: string, value: mixed}>
	 */
	public function recipe_facts(): array {
		return $this->recipe_facts;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function known_recipe_facts(): array {
		$out = array();
		foreach ( $this->recipe_facts as $key => $row ) {
			if ( ( $row['status'] ?? '' ) !== RecipeFactAvailability::KNOWN ) {
				continue;
			}
			$out[ $key ] = $row['value'] ?? ( $key === 'ingredients' ? array() : '' );
		}
		return $out;
	}

	/**
	 * @return list<string>
	 */
	public function unavailable_recipe_fact_keys(): array {
		$out = array();
		foreach ( $this->recipe_facts as $key => $row ) {
			if ( ( $row['status'] ?? '' ) !== RecipeFactAvailability::KNOWN ) {
				$out[] = (string) $key;
			}
		}
		return $out;
	}

	/**
	 * @return list<string>
	 */
	public function content_gap_hints(): array {
		return $this->content_gap_hints;
	}

	public function recommended_title_hint(): string {
		return $this->recommended_title_hint;
	}

	public function meta_description_hint(): string {
		return $this->meta_description_hint;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function seo_seed(): ?array {
		return $this->seo_seed;
	}

	public function recipe_schema_status(): string {
		return $this->recipe_schema_status;
	}

	public function recipe_schema_source(): string {
		return $this->recipe_schema_source;
	}

	public function recipe_schema_details(): string {
		return $this->recipe_schema_details;
	}

	public function recipe_fact_source(): string {
		return $this->recipe_fact_source;
	}

	public function has_rsaip_card(): bool {
		return $this->has_rsaip_card;
	}

	/**
	 * @return list<array{level: string, text: string}>
	 */
	public function heading_hints(): array {
		return $this->heading_hints;
	}

	/**
	 * Server-restorable brief shape for preview store (no secrets, no write authority).
	 *
	 * @return array<string, mixed>
	 */
	public function to_server_array(): array {
		return array(
			'title'                      => $this->title,
			'template'                   => $this->template,
			'requested_word_count'       => $this->requested_word_count,
			'supplied_keywords'          => $this->supplied_keywords,
			'image_urls'                 => $this->image_urls,
			'canonical_recipe_entity'    => $this->canonical_recipe_entity,
			'search_intent_hint'         => $this->search_intent_hint,
			'primary_focus_keyword_hint' => $this->primary_focus_keyword_hint,
			'secondary_keyword_hints'    => $this->secondary_keyword_hints,
			'entity_hints'               => $this->entity_hints,
			'recipe_facts'               => $this->recipe_facts,
			'content_gap_hints'          => $this->content_gap_hints,
			'recommended_title_hint'     => $this->recommended_title_hint,
			'meta_description_hint'      => $this->meta_description_hint,
			'seo_seed'                   => $this->seo_seed,
			'recipe_schema_status'       => $this->recipe_schema_status,
			'recipe_schema_source'       => $this->recipe_schema_source,
			'recipe_fact_source'         => $this->recipe_fact_source,
			'has_rsaip_card'             => $this->has_rsaip_card,
			'heading_hints'              => $this->heading_hints,
			'faq_hints'                  => $this->faq_hints,
			'image_alt_hints'            => $this->image_alt_hints,
			'internal_link_hints'        => $this->internal_link_hints,
			'recommendation_hints'       => $this->recommendation_hints,
			'seo_title_hints'            => $this->seo_title_hints,
		);
	}

	/**
	 * @return list<array{q: string, a: string}>
	 */
	public function faq_hints(): array {
		return $this->faq_hints;
	}

	/**
	 * @return list<string>
	 */
	public function image_alt_hints(): array {
		return $this->image_alt_hints;
	}

	/**
	 * @return list<array{anchor: string, target_hint: string}>
	 */
	public function internal_link_hints(): array {
		return $this->internal_link_hints;
	}

	/**
	 * @return list<string>
	 */
	public function recommendation_hints(): array {
		return $this->recommendation_hints;
	}

	/**
	 * @return list<string>
	 */
	public function seo_title_hints(): array {
		return $this->seo_title_hints;
	}

	public function has_seo_strategy(): bool {
		return $this->primary_focus_keyword_hint !== ''
			|| $this->search_intent_hint !== ''
			|| $this->secondary_keyword_hints !== array()
			|| $this->entity_hints !== array()
			|| $this->content_gap_hints !== array()
			|| $this->recommended_title_hint !== ''
			|| $this->meta_description_hint !== ''
			|| $this->heading_hints !== array()
			|| $this->faq_hints !== array()
			|| $this->recommendation_hints !== array()
			|| is_array( $this->seo_seed );
	}

	/**
	 * Prompt-safe payload: RECIPE_FACTS vs SEO_STRATEGY vs GENERATION_INSTRUCTIONS.
	 * No post_id, meta keys, owners, tickets, or Rank Math scores.
	 *
	 * @return array<string, mixed>
	 */
	public function to_prompt_array(): array {
		$facts_out   = array();
		$known       = array();
		$unavailable = array();
		foreach ( $this->recipe_facts as $key => $row ) {
			$status = (string) ( $row['status'] ?? RecipeFactAvailability::UNAVAILABLE );
			$value  = $row['value'] ?? ( $key === 'ingredients' ? array() : '' );
			if ( $key === 'ingredients' ) {
				$value = is_array( $value ) ? $value : ( $value !== '' && $value !== null ? array( (string) $value ) : array() );
			} else {
				$value = is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
			}
			$is_known = RecipeFactAvailability::is_known( $status );
			$facts_out[ $key ] = array(
				'status' => $is_known ? RecipeFactAvailability::KNOWN : RecipeFactAvailability::UNAVAILABLE,
				'value'  => $is_known ? $value : ( $key === 'ingredients' ? array() : null ),
			);
			if ( $is_known ) {
				$known[ $key ] = $value;
			} else {
				$unavailable[] = (string) $key;
			}
		}

		$band_min = max( self::WORD_COUNT_MIN, $this->requested_word_count - 150 );
		$band_max = min( self::WORD_COUNT_MAX, $this->requested_word_count + 250 );

		return array(
			'RECIPE_FACTS'            => array(
				'canonical_recipe_entity' => $this->canonical_recipe_entity(),
				'recipe_schema_status'    => $this->recipe_schema_status,
				'recipe_schema_source'    => $this->recipe_schema_source,
				'recipe_fact_source'      => $this->recipe_fact_source,
				'has_rsaip_card'          => $this->has_rsaip_card,
				'recipe_facts'            => $facts_out,
				'KNOWN_RECIPE_FACTS'      => $known,
				'UNAVAILABLE_RECIPE_FACTS'=> $unavailable,
				'note'                    => 'Authoritative factual data only. Never invent unavailable facts.',
			),
			'SEO_STRATEGY'            => array(
				'note'                       => 'Recommendations only — not factual recipe data. Do not invent facts to satisfy gaps.',
				'search_intent'              => $this->search_intent_hint,
				'primary_focus_keyword'      => $this->primary_focus_keyword_hint,
				'secondary_keywords'         => $this->secondary_keyword_hints,
				'entities'                   => $this->entity_hints,
				'content_gaps'               => $this->content_gap_hints,
				'recommended_title'          => $this->recommended_title_hint,
				'seo_title_suggestions'      => $this->seo_title_hints,
				'meta_description'           => $this->meta_description_hint,
				'heading_suggestions'        => $this->heading_hints,
				'faq_suggestions'            => $this->faq_hints,
				'image_alt_suggestions'      => $this->image_alt_hints,
				'internal_link_suggestions'  => $this->internal_link_hints,
				'recommendations'            => $this->recommendation_hints,
				'has_m5_seo_seed'            => is_array( $this->seo_seed ),
			),
			'GENERATION_INSTRUCTIONS' => array(
				'topic_title'           => $this->title,
				'template'              => $this->template,
				'requested_word_count'  => $this->requested_word_count,
				'word_count_band'       => array( 'min' => $band_min, 'max' => $band_max ),
				'supplied_keywords'     => $this->supplied_keywords,
				'image_placeholder_count' => count( $this->image_urls ),
				'rules'                 => array(
					'Center the article on the canonical recipe entity.',
					'Use the primary focus keyword naturally (title, intro, relevant headings/body) — no stuffing.',
					'Secondary keywords are optional and only when relevant.',
					'Search intent guides structure; do not hardcode intent.',
					'Content gaps and SEO suggestions are recommendations, never recipe facts.',
					'Internal-link suggestions are descriptive only — never invent URLs or post IDs.',
					'No H1 in content_html (post title is H1).',
					'Do not invent Rank Math scores or checklist values.',
					'Do not pad length with fabricated recipe facts.',
				),
			),
		);
	}
}
