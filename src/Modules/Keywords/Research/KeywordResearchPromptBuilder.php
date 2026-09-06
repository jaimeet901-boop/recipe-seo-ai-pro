<?php
declare(strict_types=1);

/**
 * Builds Keyword Research prompts (AI-only; no SERP scraping).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Keywords\Research;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KeywordResearchPromptBuilder
 */
final class KeywordResearchPromptBuilder {

	/**
	 * @param string               $seed    Seed topic / niche / keyword.
	 * @param array<string, mixed> $options language, country, audience, notes, niche.
	 */
	public function build( string $seed, array $options = array() ): string {
		$seed     = trim( $seed );
		$language = isset( $options['language'] ) ? trim( (string) $options['language'] ) : 'en';
		$country  = isset( $options['country'] ) ? trim( (string) $options['country'] ) : '';
		$audience = isset( $options['audience'] ) ? trim( (string) $options['audience'] ) : '';
		$niche    = isset( $options['niche'] ) ? trim( (string) $options['niche'] ) : '';
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
		if ( $niche !== '' ) {
			$extra .= "Niche: {$niche}\n";
		}
		if ( $notes !== '' ) {
			$extra .= "Additional notes: {$notes}\n";
		}

		return <<<PROMPT
You are an expert SEO keyword strategist. Generate a high-quality keyword research set for the seed below.
Use only your knowledge. Do NOT pretend to scrape Google, SERPs, or keyword tools. Do NOT invent live volume numbers.

Seed: {$seed}
{$extra}
Return ONLY valid JSON (no markdown fences, no commentary) with this shape:
{
  "related_entities": ["string — people, places, brands, concepts"],
  "keywords": [
    {
      "keyword": "string",
      "category": "primary|secondary|long_tail|question|comparison|commercial|informational|transactional|local|seasonal",
      "intent": "informational|navigational|commercial|transactional",
      "difficulty": 0,
      "priority": 0,
      "suggested_cluster": "string — short topical cluster name"
    }
  ]
}

Rules:
- Produce 35–55 unique keywords total across categories
- Include at least 2 primary, 5 secondary, 6 long_tail, 5 question, 3 comparison
- Include commercial, informational, transactional, local, and seasonal examples when relevant to the seed
- difficulty: integer 0–100 (AI estimation of ranking difficulty)
- priority: integer 0–100 (recommended focus score for a content program)
- suggested_cluster: concise cluster label (2–5 words); group related keywords under the same cluster name
- Keywords must be specific to the seed; avoid generic filler
- Prefer natural search phrasing in the given language
- related_entities: 5–12 items
PROMPT;
	}
}
