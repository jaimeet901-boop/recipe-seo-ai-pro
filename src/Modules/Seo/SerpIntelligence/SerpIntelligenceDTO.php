<?php
declare(strict_types=1);

/**
 * SERP Intelligence analysis DTO (Phase 3.6).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Seo\SerpIntelligence;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SerpIntelligenceDTO
 */
final class SerpIntelligenceDTO {

	public int $id = 0;

	public string $query = '';

	public string $language = '';

	public string $country = '';

	public string $search_intent = '';

	/** @var list<string> */
	public array $expected_serp_features = array();

	public string $recommended_article_type = '';

	/** @var list<string> */
	public array $recommended_heading_structure = array();

	/** @var list<string> */
	public array $missing_topics = array();

	/** @var list<string> */
	public array $related_entities = array();

	public int $recommended_word_count = 0;

	/** @var list<string> */
	public array $recommended_media = array();

	/** @var list<string> */
	public array $suggested_faq = array();

	/** @var list<string> */
	public array $eeat_recommendations = array();

	/** @var list<string> */
	public array $common_mistakes = array();

	/** @var list<string> */
	public array $opportunities = array();

	public int $project_id = 0;

	public int $keyword_id = 0;

	public int $brief_id = 0;

	public int $user_id = 0;

	public string $created_at = '';

	public string $updated_at = '';

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'                            => $this->id,
			'query'                         => $this->query,
			'language'                      => $this->language,
			'country'                       => $this->country,
			'search_intent'                 => $this->search_intent,
			'expected_serp_features'        => $this->expected_serp_features,
			'recommended_article_type'      => $this->recommended_article_type,
			'recommended_heading_structure' => $this->recommended_heading_structure,
			'missing_topics'                => $this->missing_topics,
			'related_entities'              => $this->related_entities,
			'recommended_word_count'        => $this->recommended_word_count,
			'recommended_media'             => $this->recommended_media,
			'suggested_faq'                 => $this->suggested_faq,
			'eeat_recommendations'          => $this->eeat_recommendations,
			'common_mistakes'               => $this->common_mistakes,
			'opportunities'                 => $this->opportunities,
			'project_id'                    => $this->project_id,
			'keyword_id'                    => $this->keyword_id,
			'brief_id'                      => $this->brief_id,
			'user_id'                       => $this->user_id,
			'created_at'                    => $this->created_at,
			'updated_at'                    => $this->updated_at,
		);
	}

	/**
	 * JSON payload stored in DB (analysis fields only).
	 *
	 * @return array<string, mixed>
	 */
	public function payload_array(): array {
		return array(
			'search_intent'                 => $this->search_intent,
			'expected_serp_features'        => $this->expected_serp_features,
			'recommended_article_type'      => $this->recommended_article_type,
			'recommended_heading_structure' => $this->recommended_heading_structure,
			'missing_topics'                => $this->missing_topics,
			'related_entities'              => $this->related_entities,
			'recommended_word_count'        => $this->recommended_word_count,
			'recommended_media'             => $this->recommended_media,
			'suggested_faq'                 => $this->suggested_faq,
			'eeat_recommendations'          => $this->eeat_recommendations,
			'common_mistakes'               => $this->common_mistakes,
			'opportunities'                 => $this->opportunities,
		);
	}
}
