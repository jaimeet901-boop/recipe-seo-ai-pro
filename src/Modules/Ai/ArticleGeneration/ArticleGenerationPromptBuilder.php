<?php
declare(strict_types=1);

/**
 * Prompt builder for SEO-aware article generation (Article Generator A/B/C).
 *
 * Consumes RECIPE_FACTS + SEO_STRATEGY + GENERATION_INSTRUCTIONS from context.
 * Does not invent Rank Math scores or recipe facts.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\ArticleGeneration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ArticleGenerationPromptBuilder
 */
final class ArticleGenerationPromptBuilder {

	public function system_prompt(): string {
		return 'You are a professional SEO article writer for recipe and food blogs. '
			. 'Return ONE JSON object only. '
			. 'CONTEXT_JSON has three sections: RECIPE_FACTS (authoritative), SEO_STRATEGY (recommendations only), GENERATION_INSTRUCTIONS. '
			. 'Never treat SEO recommendations, content gaps, keyword suggestions, or heading suggestions as factual recipe data. '
			. 'Use KNOWN_RECIPE_FACTS exactly when provided. '
			. 'Never invent ingredients, nutrition values, allergens, dietary claims, preparation/cooking/total times, or servings. '
			. 'If a fact appears in UNAVAILABLE_RECIPE_FACTS, mark it unavailable and write around it. '
			. 'Keep the canonical recipe entity as the central subject; never drift into a generic hypernym such as Salad, Chicken, Pasta, or Recipe. '
			. 'Use the primary focus keyword naturally in title, introduction, relevant headings, and body — never keyword-stuff or invent a different primary keyword. '
			. 'Secondary keywords are optional and only when semantically relevant. '
			. 'Honor search_intent from SEO_STRATEGY for structure; do not hardcode informational intent. '
			. 'Do not invent Rank Math scores, green scores, keyword-density targets, or checklist pass values. '
			. 'Do not invent URLs, post IDs, meta keys, SEO owners, mutation types, or tickets. '
			. 'content_html must be safe HTML only (no H1, script, iframe, javascript: URLs, or event handlers). '
			. 'The requested word count applies to content_html only: write approximately that many words inside content_html. '
			. 'Separate headings[] and faq[] JSON fields do not count toward the content_html word total. '
			. 'Complete the full JSON response without truncating content_html. '
			. 'Do not pad word count by inventing recipe facts or meaningless filler.';
	}

	public function build( ArticleGenerationContext $context ): string {
		$payload_arr = $context->to_prompt_array();
		$encoded     = function_exists( 'wp_json_encode' )
			? wp_json_encode( $payload_arr )
			: json_encode( $payload_arr );
		$payload = is_string( $encoded ) && $encoded !== '' ? $encoded : '{}';

		$min = max( ArticleGenerationContext::WORD_COUNT_MIN, $context->requested_word_count() - 150 );
		$max = min( ArticleGenerationContext::WORD_COUNT_MAX, $context->requested_word_count() + 250 );

		$known_keys = array_keys( $context->known_recipe_facts() );
		$unavailable = $context->unavailable_recipe_fact_keys();
		$known_list = $known_keys !== array() ? implode( ', ', $known_keys ) : '(none)';
		$unavail_list = $unavailable !== array() ? implode( ', ', $unavailable ) : '(none)';

		$primary = $context->primary_focus_keyword_hint() !== ''
			? $context->primary_focus_keyword_hint()
			: '(derive from entity — do not invent unrelated keywords)';
		$intent = $context->search_intent_hint() !== ''
			? $context->search_intent_hint()
			: '(choose the best supported intent; do not invent unsupported facts)';
		$entity = $context->canonical_recipe_entity();
		$secondaries = $context->secondary_keyword_hints() !== array()
			? implode( ', ', $context->secondary_keyword_hints() )
			: '(optional — only supplied/recommended secondaries)';

		return <<<PROMPT
Generate a WordPress article candidate as STRICT JSON only (no markdown fences) with this shape:
{
  "title": "string",
  "excerpt": "string",
  "meta_description": "string",
  "primary_focus_keyword": "string — must match server primary when supplied",
  "secondary_keywords": ["string — optional subset of supplied secondaries"],
  "entities": ["string — supporting concepts only"],
  "search_intent": "informational|transactional|navigational|commercial",
  "recipe_name": "string|null",
  "recipe_facts": {
    "ingredients":{"status":"known|unavailable","value":[]},
    "prep_time":{"status":"known|unavailable","value":"string|null"},
    "cook_time":{"status":"known|unavailable","value":"string|null"},
    "total_time":{"status":"known|unavailable","value":"string|null"},
    "servings":{"status":"known|unavailable","value":"string|null"},
    "nutrition":{"status":"known|unavailable","value":"string|null"},
    "dietary":{"status":"known|unavailable","value":"string|null"},
    "allergens":{"status":"known|unavailable","value":"string|null"}
  },
  "headings": [{"level":"h2|h3","text":"string"}],
  "faq": [{"q":"string","a":"string"}],
  "image_plans": [{"placeholder":"[[IMAGE_1]]","alt":"string","note":"string"}],
  "content_html": "string — safe HTML body with H2/H3 only (no H1)",
  "template": "{$context->template()}"
}

SEO-aware hard rules:
- Canonical entity (central subject): {$entity}
- Primary focus keyword (server-supplied; do not invent a different one): {$primary}
- Secondary keywords (optional, relevant only): {$secondaries}
- Search intent (authoritative when supplied): {$intent}
- WORD COUNT (content_html only): Target about {$context->requested_word_count()} words inside content_html. Required band {$min}-{$max} (for 1000: 850-1250). Server counts words in content_html after stripping HTML tags.
- headings[] and faq[] are separate JSON fields and do NOT count toward the content_html word total. Put the full article body words in content_html.
- Complete the article naturally within the required range. Finish the entire JSON response completely (do not truncate content_html).
- Do not compensate for length by inventing recipe facts. Do not pad with meaningless or repetitive filler text. Do not keyword-stuff.
- Server template is authoritative: {$context->template()}
- KNOWN recipe facts (use these): {$known_list}
- UNAVAILABLE recipe facts (must NOT invent): {$unavail_list}
- Echo unavailable facts as status=unavailable with empty/null value.
- Structure for the search intent (overview, ingredients/instructions only when known, tips, variations, storage/serving when supported, FAQ, conclusion).
- Content gaps and SEO suggestions are recommendations — never turn them into invented recipe facts.
- Internal-link suggestions: descriptive only; never invent http(s) URLs or post IDs in content_html or image_plans.
- image_plans use placeholders only.
- Never output post_id, meta_key, owner, mutation_type, tickets, rank_math_score, or seo_score.
- Do not invent Rank Math scores or keyword-density targets.
- Title/meta: entity-relevant, useful, no clickbait, no invented facts, no stuffing.
- FAQ: recipe-specific; answers must not invent times/servings/nutrition/allergens.

CONTEXT_JSON:
{$payload}
PROMPT;
	}
}
