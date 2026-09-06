<?php
declare(strict_types=1);

/**
 * Builds Content Brief prompts (isolated from legacy RSAIP_AI prompts).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentBrief;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PromptBuilder
 */
final class PromptBuilder {

	/**
	 * Build the v1 AI-only content brief prompt.
	 *
	 * No scraping. Topic / seed keyword only.
	 *
	 * @param string               $topic   Topic or seed keyword.
	 * @param array<string, mixed> $options Optional: audience, locale, notes.
	 */
	public function build( string $topic, array $options = array() ): string {
		$topic    = trim( $topic );
		$audience = isset( $options['audience'] ) ? trim( (string) $options['audience'] ) : '';
		$locale   = isset( $options['locale'] ) ? trim( (string) $options['locale'] ) : 'en';
		$notes    = isset( $options['notes'] ) ? trim( (string) $options['notes'] ) : '';

		$extra = '';
		if ( $audience !== '' ) {
			$extra .= "Target audience: {$audience}\n";
		}
		if ( $locale !== '' ) {
			$extra .= "Locale / language: {$locale}\n";
		}
		if ( $notes !== '' ) {
			$extra .= "Additional notes: {$notes}\n";
		}

		return <<<PROMPT
You are an expert SEO content strategist. Create a complete content brief for the topic below.
Use only your knowledge — do not pretend to scrape SERPs or keyword tools.

Topic / seed: {$topic}
{$extra}
Return ONLY valid JSON (no markdown fences, no commentary) with exactly these keys:
{
  "search_intent": "string — informational|navigational|commercial|transactional (with short explanation)",
  "primary_keyword": "string",
  "secondary_keywords": ["string"],
  "long_tail_keywords": ["string"],
  "semantic_keywords": ["string"],
  "entities": ["string — people, places, brands, concepts"],
  "faq_ideas": ["string — question form"],
  "h1": "string",
  "h2_structure": ["string — proposed H2 headings in order"],
  "h3_suggestions": ["string — H3 ideas mapped to the outline"],
  "meta_description": "string — max ~155 characters",
  "suggested_slug": "string — lowercase-kebab-case",
  "internal_linking_opportunities": ["string — topical internal link targets to create or find on-site"],
  "external_authority_suggestions": ["string — reputable external sources/types to cite (no fabricated URLs required)"],
  "schema_recommendation": "string — recommended schema type(s) and why",
  "eeat_recommendations": ["string — Experience, Expertise, Authoritativeness, Trust tips"],
  "recommended_word_count": 1200
}

Rules:
- secondary_keywords: 5–10 items
- long_tail_keywords: 5–10 items
- semantic_keywords: 8–15 items
- entities: 5–12 items
- faq_ideas: 5–8 items
- h2_structure: 5–10 items
- h3_suggestions: 6–12 items
- internal_linking_opportunities: 4–8 items
- external_authority_suggestions: 3–6 items
- eeat_recommendations: 4–8 items
- recommended_word_count: integer between 800 and 3500
- Write for the given locale when possible
- Be specific to the topic; avoid generic filler
PROMPT;
	}
}
