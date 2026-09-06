<?php
declare(strict_types=1);

/**
 * Content Brief data transfer object (Phase 3.1).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentBrief;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BriefDTO
 */
final class BriefDTO {

	public string $topic = '';

	public string $search_intent = '';

	public string $primary_keyword = '';

	/** @var list<string> */
	public array $secondary_keywords = array();

	/** @var list<string> */
	public array $long_tail_keywords = array();

	/** @var list<string> */
	public array $semantic_keywords = array();

	/** @var list<string> */
	public array $entities = array();

	/** @var list<string> */
	public array $faq_ideas = array();

	public string $h1 = '';

	/** @var list<string> */
	public array $h2_structure = array();

	/** @var list<string> */
	public array $h3_suggestions = array();

	public string $meta_description = '';

	public string $suggested_slug = '';

	/** @var list<string> */
	public array $internal_linking_opportunities = array();

	/** @var list<string> */
	public array $external_authority_suggestions = array();

	public string $schema_recommendation = '';

	/** @var list<string> */
	public array $eeat_recommendations = array();

	public int $recommended_word_count = 0;

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'topic'                           => $this->topic,
			'search_intent'                   => $this->search_intent,
			'primary_keyword'                 => $this->primary_keyword,
			'secondary_keywords'              => $this->secondary_keywords,
			'long_tail_keywords'              => $this->long_tail_keywords,
			'semantic_keywords'               => $this->semantic_keywords,
			'entities'                        => $this->entities,
			'faq_ideas'                       => $this->faq_ideas,
			'h1'                              => $this->h1,
			'h2_structure'                    => $this->h2_structure,
			'h3_suggestions'                  => $this->h3_suggestions,
			'meta_description'                => $this->meta_description,
			'suggested_slug'                  => $this->suggested_slug,
			'internal_linking_opportunities'  => $this->internal_linking_opportunities,
			'external_authority_suggestions'  => $this->external_authority_suggestions,
			'schema_recommendation'           => $this->schema_recommendation,
			'eeat_recommendations'            => $this->eeat_recommendations,
			'recommended_word_count'          => $this->recommended_word_count,
		);
	}
}
