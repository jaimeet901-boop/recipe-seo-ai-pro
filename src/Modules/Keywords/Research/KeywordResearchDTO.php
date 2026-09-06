<?php
declare(strict_types=1);

/**
 * AI Keyword Research result DTO (Phase 3.5).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Keywords\Research;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KeywordResearchDTO
 */
final class KeywordResearchDTO {

	public string $seed = '';

	public string $language = '';

	public string $country = '';

	public string $audience = '';

	/** @var list<string> */
	public array $related_entities = array();

	/**
	 * Flattened selectable keyword rows.
	 *
	 * @var list<array{
	 *   id: string,
	 *   keyword: string,
	 *   category: string,
	 *   intent: string,
	 *   difficulty: int,
	 *   priority: int,
	 *   suggested_cluster: string
	 * }>
	 */
	public array $keywords = array();

	/**
	 * Category buckets for display.
	 *
	 * @var array<string, list<string>>
	 */
	public array $buckets = array();

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'seed'             => $this->seed,
			'language'         => $this->language,
			'country'          => $this->country,
			'audience'         => $this->audience,
			'related_entities' => $this->related_entities,
			'keywords'         => $this->keywords,
			'buckets'          => $this->buckets,
			'count'            => count( $this->keywords ),
		);
	}
}
