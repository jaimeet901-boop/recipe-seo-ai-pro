<?php
declare(strict_types=1);

/**
 * Builds SERP Intelligence prompts (AI-only; no scraping).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Seo\SerpIntelligence;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PromptBuilder
 */
final class PromptBuilder {

	/**
	 * @param string               $query   Target query / keyword.
	 * @param array<string, mixed> $options language, country, audience, notes.
	 */
	public function build( string $query, array $options = array() ): string {
		$query    = trim( $query );
		$language = isset( $options['language'] ) ? trim( (string) $options['language'] ) : 'en';
		$country  = isset( $options['country'] ) ? trim( (string) $options['country'] ) : '';
		$audience = isset( $options['audience'] ) ? trim( (string) $options['audience'] ) : '';
		$notes    = isset( $options['notes'] ) ? trim( (string) $options['notes'] ) : '';

		$extra = '';
		if ( $language !== '' ) {
			$extra .= "Language: {$language}\n";
		}
		if ( $country !== '' ) {
			$extra .= "Target country: {$country}\n";
		}
		if ( $audience !== '' ) {
			$extra .= "Audience: {$audience}\n";
		}
		if ( $notes !== '' ) {
			$extra .= "Additional notes: {$notes}\n";
		}

		return <<<PROMPT
You are an expert SEO SERP analyst. Produce a SERP intelligence brief for the query below.
Use only your knowledge of typical search result patterns. Do NOT pretend to scrape Google or live SERPs.
Do NOT invent live ranking URLs or real-time feature screenshots.

Query: {$query}
{$extra}
Return ONLY valid JSON (no markdown fences, no commentary) with exactly these keys:
{
  "search_intent": "string — primary intent with short explanation (informational|navigational|commercial|transactional)",
  "expected_serp_features": ["string — e.g. featured snippet, PAA, video, shopping, local pack, images, AI overview"],
  "recommended_article_type": "string — e.g. how-to guide, listicle, recipe, comparison, product review, pillar page",
  "recommended_heading_structure": ["string — ordered H1/H2/H3 outline suggestions"],
  "missing_topics": ["string — topics competitors/typical pages often miss that would differentiate"],
  "related_entities": ["string — people, places, brands, concepts"],
  "recommended_word_count": 1800,
  "recommended_media": ["string — images, video, charts, recipe cards, etc."],
  "suggested_faq": ["string — FAQ questions in natural language"],
  "eeat_recommendations": ["string — Experience, Expertise, Authoritativeness, Trust tips"],
  "common_mistakes": ["string — content/SEO mistakes for this query type"],
  "opportunities": ["string — ranking / content / SERP feature opportunities"]
}

Rules:
- expected_serp_features: 4–10 items
- recommended_heading_structure: 6–12 items
- missing_topics: 4–8 items
- related_entities: 5–12 items
- recommended_media: 3–8 items
- suggested_faq: 5–8 items
- eeat_recommendations: 4–8 items
- common_mistakes: 4–8 items
- opportunities: 4–8 items
- recommended_word_count: integer between 800 and 4000
- Be specific to the query; avoid generic filler
- Write for the given language when possible
PROMPT;
	}
}
