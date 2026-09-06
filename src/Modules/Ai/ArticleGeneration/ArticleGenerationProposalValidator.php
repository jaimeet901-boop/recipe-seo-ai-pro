<?php
declare(strict_types=1);

/**
 * Strict validator for ArticleGenerationProposal (Article Generator A/B/C).
 *
 * Fail closed. No heuristic article. No WordPress writes.
 * SEO-aware: preserves M5 primary/intent, rejects fabricated facts and scores.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\ArticleGeneration;

use RecipeSeoAiPro\Modules\Ai\TitleGeneration\TitleTopicSelector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ArticleGenerationProposalValidator
 */
final class ArticleGenerationProposalValidator {

	private const SEARCH_INTENTS = array( 'informational', 'transactional', 'navigational', 'commercial' );
	private const FORBIDDEN_AI_KEYS = array(
		'post_id',
		'meta_key',
		'meta_keys',
		'owner',
		'seo_owner',
		'mutation_type',
		'proposal_ticket',
		'ticket',
		'apply',
		'rank_math_score',
		'seo_score',
		'score',
		'draft_id',
		'draft_allowed',
	);

	private TitleTopicSelector $topics;

	public function __construct( ?TitleTopicSelector $topics = null ) {
		$this->topics = $topics instanceof TitleTopicSelector ? $topics : new TitleTopicSelector();
	}

	/**
	 * @param array<string, mixed> $data Decoded AI JSON.
	 */
	public function validate( array $data, ArticleGenerationContext $context ): ArticleGenerationValidationResult {
		foreach ( self::FORBIDDEN_AI_KEYS as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				return ArticleGenerationValidationResult::failure(
					'rsaip_article_forbidden_field',
					'AI output must not include ' . $key . '.'
				);
			}
		}

		$title = $this->require_non_empty_string( $data, 'title' );
		if ( $title === null ) {
			return ArticleGenerationValidationResult::failure( 'rsaip_article_missing_title', 'Missing or empty title.' );
		}

		$content = isset( $data['content_html'] ) ? (string) $data['content_html'] : '';
		$content = trim( $content );
		if ( $content === '' ) {
			return ArticleGenerationValidationResult::failure( 'rsaip_article_missing_content', 'Missing or empty content_html.' );
		}

		$html_check = $this->assert_safe_html( $content );
		if ( $html_check !== null ) {
			return $html_check;
		}
		if ( preg_match( '/<\s*h1\b/i', $content ) ) {
			return ArticleGenerationValidationResult::failure(
				'rsaip_article_h1_not_allowed',
				'content_html must not include H1 (post title is the H1).'
			);
		}
		$content = $this->sanitize_html( $content );
		$plain   = function_exists( 'wp_strip_all_tags' ) ? (string) wp_strip_all_tags( $content ) : strip_tags( $content );
		if ( trim( $plain ) === '' ) {
			return ArticleGenerationValidationResult::failure( 'rsaip_article_empty_after_sanitize', 'content_html empty after sanitization.' );
		}

		$template = $context->template();
		if ( isset( $data['template'] ) ) {
			$ai_template = strtolower( trim( (string) $data['template'] ) );
			$allowed_templates = array(
				ArticleGenerationContext::TEMPLATE_GENERAL,
				ArticleGenerationContext::TEMPLATE_RECIPE_MIDJOURNEY,
			);
			// AI may only echo an allowed template; server context remains authoritative.
			if ( $ai_template !== '' && ! in_array( $ai_template, $allowed_templates, true ) ) {
				return ArticleGenerationValidationResult::failure( 'rsaip_article_invalid_template', 'Invalid template.' );
			}
		}

		$requested = $context->requested_word_count();
		if ( $requested < ArticleGenerationContext::WORD_COUNT_MIN || $requested > ArticleGenerationContext::WORD_COUNT_MAX ) {
			return ArticleGenerationValidationResult::failure( 'rsaip_article_invalid_word_count', 'Requested word count out of bounds.' );
		}

		$actual = $this->count_words( $content );
		$band_min = max( ArticleGenerationContext::WORD_COUNT_MIN, $requested - 150 );
		$band_max = min( ArticleGenerationContext::WORD_COUNT_MAX, $requested + 250 );
		$in_band  = ( $actual >= $band_min && $actual <= $band_max );
		if ( ! $in_band ) {
			return ArticleGenerationValidationResult::failure(
				'rsaip_article_word_count_out_of_band',
				'Actual word count is outside the required band; do not pad with invented content.',
				array(
					'requested_word_count' => $requested,
					'actual_word_count'    => $actual,
					'band_min'             => $band_min,
					'band_max'             => $band_max,
				)
			);
		}

		$entity = $context->canonical_recipe_entity();
		if ( $entity !== '' && $this->topics->is_weaker_than_entity( $title, $entity ) ) {
			if ( ! $this->topics->covers_specific_entity( $title, $entity ) ) {
				return ArticleGenerationValidationResult::failure(
					'rsaip_article_generic_entity',
					'Title does not preserve the recipe entity.'
				);
			}
		}

		// Server primary is authoritative when supplied — AI must not invent a different primary.
		$server_primary = $context->primary_focus_keyword_hint();
		$primary        = $this->optional_string( $data, 'primary_focus_keyword' );
		if ( $server_primary !== '' ) {
			$primary = $server_primary;
		} elseif ( $primary === '' && $context->supplied_keywords() !== array() ) {
			$primary = (string) $context->supplied_keywords()[0];
		} elseif ( $primary === '' ) {
			$primary = $entity !== '' ? $entity : $title;
		}
		if ( $entity !== '' && $this->topics->is_weaker_than_entity( $primary, $entity ) ) {
			return ArticleGenerationValidationResult::failure(
				'rsaip_article_generic_primary',
				'Primary focus keyword is too generic relative to the recipe entity.'
			);
		}

		$recipe_name = null;
		if ( array_key_exists( 'recipe_name', $data ) && $data['recipe_name'] !== null && $data['recipe_name'] !== '' ) {
			$recipe_name = $this->normalize_spaces( (string) $data['recipe_name'] );
			if ( $entity !== '' && $this->topics->is_weaker_than_entity( $recipe_name, $entity ) ) {
				return ArticleGenerationValidationResult::failure(
					'rsaip_article_inconsistent_recipe_name',
					'recipe_name is inconsistent with the canonical recipe entity.'
				);
			}
		} elseif ( $entity !== '' ) {
			$recipe_name = $entity;
		}

		// Search intent: M5/context hint is authoritative when present.
		$server_intent = strtolower( $this->normalize_spaces( $context->search_intent_hint() ) );
		$intent        = strtolower( $this->normalize_spaces( (string) ( $data['search_intent'] ?? '' ) ) );
		if ( $server_intent !== '' && in_array( $server_intent, self::SEARCH_INTENTS, true ) ) {
			$intent = $server_intent;
		} elseif ( $intent === '' ) {
			$intent = 'informational';
		}
		if ( ! in_array( $intent, self::SEARCH_INTENTS, true ) ) {
			return ArticleGenerationValidationResult::failure( 'rsaip_article_invalid_intent', 'Invalid search_intent.' );
		}

		$secondary = $this->filter_secondary_keywords(
			$this->string_list( $data['secondary_keywords'] ?? array(), 8, 80 ),
			$context,
			$entity
		);
		$entities = $this->filter_supporting_entities(
			$this->string_list( $data['entities'] ?? array(), 20, 80 ),
			$context,
			$entity
		);
		$excerpt = $this->normalize_spaces( (string) ( $data['excerpt'] ?? '' ) );
		$meta    = $this->normalize_spaces( (string) ( $data['meta_description'] ?? '' ) );

		if ( $entity !== '' && $meta !== '' && $this->topics->is_weaker_than_entity( $meta, $entity )
			&& ! $this->topics->covers_specific_entity( $meta, $entity ) ) {
			return ArticleGenerationValidationResult::failure(
				'rsaip_article_generic_meta',
				'Meta description does not preserve the recipe entity.'
			);
		}
		if ( $entity !== '' && $excerpt !== '' && $this->topics->is_weaker_than_entity( $excerpt, $entity )
			&& ! $this->topics->covers_specific_entity( $excerpt, $entity ) ) {
			return ArticleGenerationValidationResult::failure(
				'rsaip_article_generic_excerpt',
				'Excerpt does not preserve the recipe entity.'
			);
		}

		$facts = $this->validate_recipe_facts( $data['recipe_facts'] ?? array(), $context );
		if ( $facts === null ) {
			return ArticleGenerationValidationResult::failure(
				'rsaip_article_fabricated_recipe_facts',
				'Proposal fabricates unavailable recipe facts.'
			);
		}

		$headings = $this->parse_headings( $data['headings'] ?? $data['heading_suggestions'] ?? array() );
		if ( $headings === null ) {
			return ArticleGenerationValidationResult::failure( 'rsaip_article_invalid_headings', 'Invalid headings structure.' );
		}
		$faq = $this->parse_faq( $data['faq'] ?? $data['faq_suggestions'] ?? array() );
		if ( $faq === null ) {
			return ArticleGenerationValidationResult::failure( 'rsaip_article_invalid_faq', 'Invalid FAQ structure.' );
		}

		$faq_gate = $this->assert_faq_entity_relevance( $faq, $entity );
		if ( $faq_gate !== null ) {
			return $faq_gate;
		}

		$faq_text = '';
		foreach ( $faq as $row ) {
			$faq_text .= ' ' . $row['q'] . ' ' . $row['a'];
		}
		$body_gate = $this->assert_no_fabricated_body_facts( $plain . ' ' . $excerpt . ' ' . $meta . ' ' . $faq_text, $context, $content );
		if ( $body_gate !== null ) {
			return $body_gate;
		}

		$url_gate = $this->assert_no_invented_urls( $content . ' ' . $excerpt . ' ' . $meta . ' ' . $faq_text, $context );
		if ( $url_gate !== null ) {
			return $url_gate;
		}

		$plans = $this->parse_image_plans( $data['image_plans'] ?? array() );
		if ( $plans === null ) {
			return ArticleGenerationValidationResult::failure( 'rsaip_article_invalid_image_plans', 'Invalid image_plans (URLs must not be invented).' );
		}

		$proposal = new ArticleGenerationProposal(
			$title,
			$excerpt,
			$meta,
			$primary,
			$secondary,
			$entities,
			$intent,
			$recipe_name,
			$facts,
			$headings,
			$faq,
			$plans,
			$content,
			$template,
			$requested,
			$actual,
			$in_band
		);

		return ArticleGenerationValidationResult::success( $proposal, $context );
	}

	/**
	 * @param mixed $raw Raw facts from AI.
	 * @return array<string, array{status: string, value: mixed}>|null
	 */
	private function validate_recipe_facts( $raw, ArticleGenerationContext $context ): ?array {
		$server = $context->recipe_facts();
		$out    = $server;
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( $raw as $key => $row ) {
			$key = (string) $key;
			if ( ! isset( $server[ $key ] ) ) {
				continue; // Unknown fact keys discarded.
			}
			$server_status = (string) ( $server[ $key ]['status'] ?? RecipeFactAvailability::UNAVAILABLE );
			$server_value  = $server[ $key ]['value'] ?? ( $key === 'ingredients' ? array() : '' );

			if ( is_array( $row ) && ( isset( $row['status'] ) || array_key_exists( 'value', $row ) ) ) {
				$ai_status = strtolower( (string) ( $row['status'] ?? '' ) );
				$ai_value  = $row['value'] ?? null;
			} elseif ( is_array( $row ) && $key === 'ingredients' ) {
				$ai_status = RecipeFactAvailability::KNOWN;
				$ai_value  = $row;
			} elseif ( is_scalar( $row ) ) {
				$ai_status = RecipeFactAvailability::KNOWN;
				$ai_value  = $row;
			} else {
				continue;
			}

			$ai_has_value = $this->fact_value_nonempty( $key, $ai_value );

			if ( $server_status === RecipeFactAvailability::UNAVAILABLE ) {
				// AI must not invent a known value for unavailable facts.
				if ( $ai_status === RecipeFactAvailability::KNOWN && $ai_has_value ) {
					return null;
				}
				$out[ $key ] = array(
					'status' => RecipeFactAvailability::UNAVAILABLE,
					'value'  => $key === 'ingredients' ? array() : '',
				);
				continue;
			}

			// Known server facts remain authoritative (do not silently repair with AI guesses).
			$out[ $key ] = array(
				'status' => RecipeFactAvailability::KNOWN,
				'value'  => $server_value,
			);
		}
		return $out;
	}

	/**
	 * @param mixed $value Fact value.
	 */
	private function fact_value_nonempty( string $key, $value ): bool {
		if ( $key === 'ingredients' ) {
			if ( is_string( $value ) ) {
				return trim( $value ) !== '';
			}
			return is_array( $value ) && $value !== array();
		}
		if ( $value === null ) {
			return false;
		}
		if ( is_array( $value ) ) {
			return $value !== array();
		}
		return trim( (string) $value ) !== '';
	}

	/**
	 * Reject body/FAQ text that asserts unavailable nutrition/allergen/time/servings facts.
	 */
	private function assert_no_fabricated_body_facts( string $text, ArticleGenerationContext $context, string $html = '' ): ?ArticleGenerationValidationResult {
		$facts = $context->recipe_facts();
		$hay   = $this->known_facts_haystack( $context );

		if ( ( $facts['nutrition']['status'] ?? '' ) !== RecipeFactAvailability::KNOWN ) {
			if ( preg_match_all( '/\b(\d{2,4})\s*(?:kcal|calories|cal)\b/iu', $text, $m ) ) {
				foreach ( $m[0] as $claim ) {
					if ( ! preg_match( '/' . preg_quote( (string) $claim, '/' ) . '/iu', $hay ) ) {
						return ArticleGenerationValidationResult::failure(
							'rsaip_article_fabricated_nutrition',
							'Content invents nutrition values not present in known recipe facts.'
						);
					}
				}
			}
		}

		if ( ( $facts['allergens']['status'] ?? '' ) !== RecipeFactAvailability::KNOWN ) {
			if ( preg_match( '/\b(?:contains\s+)?(?:allergen|allergens|allergy|allergic|peanut-free|nut-free)\b/iu', $text )
				&& ! preg_match( '/\b(?:allergen|allergens|allergy|allergic|peanut|gluten|dairy|nut-free)\b/iu', $hay ) ) {
				return ArticleGenerationValidationResult::failure(
					'rsaip_article_fabricated_allergens',
					'Content invents allergen claims not present in known recipe facts.'
				);
			}
		}

		$has_time = ( $facts['prep_time']['status'] ?? '' ) === RecipeFactAvailability::KNOWN
			|| ( $facts['cook_time']['status'] ?? '' ) === RecipeFactAvailability::KNOWN
			|| ( $facts['total_time']['status'] ?? '' ) === RecipeFactAvailability::KNOWN;
		if ( ! $has_time && preg_match( '/\b(?:cook|bake|roast|prep|total)\b.{0,24}\b\d{1,3}\s*(?:minutes?|mins?|hours?|hrs?)\b/iu', $text ) ) {
			return ArticleGenerationValidationResult::failure(
				'rsaip_article_fabricated_times',
				'Content invents cooking times not present in known recipe facts.'
			);
		}

		if ( ( $facts['servings']['status'] ?? '' ) !== RecipeFactAvailability::KNOWN ) {
			if ( preg_match( '/\b(?:serves?|servings?|yield)\b.{0,12}\b\d{1,3}\b/iu', $text ) ) {
				return ArticleGenerationValidationResult::failure(
					'rsaip_article_fabricated_servings',
					'Content invents servings not present in known recipe facts.'
				);
			}
		}

		if ( ( $facts['ingredients']['status'] ?? '' ) !== RecipeFactAvailability::KNOWN ) {
			$scan = $html !== '' ? $html : $text;
			if ( preg_match( '/<h[2-4][^>]*>\s*ingredients?\s*<\/h[2-4]>\s*<ul[\s\S]{0,2000}?<li/i', $scan )
				|| preg_match( '/\bingredients?\s*:\s*(?:\d+\s+\w+){2,}/iu', $text ) ) {
				return ArticleGenerationValidationResult::failure(
					'rsaip_article_fabricated_ingredients',
					'Content invents an ingredient list when ingredients are unavailable.'
				);
			}
		}

		return null;
	}

	private function known_facts_haystack( ArticleGenerationContext $context ): string {
		$parts = array( $context->title(), $context->canonical_recipe_entity() );
		foreach ( $context->known_recipe_facts() as $value ) {
			if ( is_array( $value ) ) {
				$parts[] = implode( ' ', array_map( 'strval', $value ) );
			} else {
				$parts[] = (string) $value;
			}
		}
		return implode( ' ', $parts );
	}

	/**
	 * Keep secondary keywords optional; drop unrelated inventions.
	 *
	 * @param list<string> $secondary AI secondaries.
	 * @return list<string>
	 */
	private function filter_secondary_keywords( array $secondary, ArticleGenerationContext $context, string $entity ): array {
		$allowed = $context->secondary_keyword_hints();
		$out     = array();
		foreach ( $secondary as $kw ) {
			$kw = $this->normalize_spaces( $kw );
			if ( $kw === '' ) {
				continue;
			}
			if ( $entity !== '' && $this->topics->is_weaker_than_entity( $kw, $entity )
				&& ! $this->topics->covers_specific_entity( $kw, $entity )
				&& ! $this->shares_distinctive_token( $kw, $entity ) ) {
				continue;
			}
			if ( $allowed !== array() ) {
				$matched = false;
				foreach ( $allowed as $hint ) {
					if ( strcasecmp( $kw, $hint ) === 0 || $this->shares_distinctive_token( $kw, $hint ) ) {
						$matched = true;
						break;
					}
				}
				if ( ! $matched && $entity !== '' && ! $this->shares_distinctive_token( $kw, $entity ) ) {
					continue;
				}
			}
			$out[] = $kw;
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param list<string> $entities AI entities.
	 * @return list<string>
	 */
	private function filter_supporting_entities( array $entities, ArticleGenerationContext $context, string $entity ): array {
		$hints = $context->entity_hints();
		$out   = array();
		foreach ( $entities as $item ) {
			$item = $this->normalize_spaces( $item );
			if ( $item === '' ) {
				continue;
			}
			// Drop generic hypernyms that weaken the recipe entity.
			if ( $entity !== '' && $this->topics->is_weaker_than_entity( $item, $entity )
				&& ! $this->shares_distinctive_token( $item, $entity )
				&& ( $hints === array() || ! in_array( $item, $hints, true ) ) ) {
				continue;
			}
			$out[] = $item;
		}
		return array_values( array_unique( $out ) );
	}

	private function shares_distinctive_token( string $a, string $b ): bool {
		$ta = $this->topics->distinctive_tokens( $a );
		$tb = $this->topics->distinctive_tokens( $b );
		if ( $ta === array() || $tb === array() ) {
			return false;
		}
		foreach ( $ta as $tok ) {
			if ( in_array( $tok, $tb, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param list<array{q: string, a: string}> $faq FAQ rows.
	 */
	private function assert_faq_entity_relevance( array $faq, string $entity ): ?ArticleGenerationValidationResult {
		if ( $faq === array() || $entity === '' ) {
			return null;
		}
		foreach ( $faq as $row ) {
			$text = $row['q'] . ' ' . $row['a'];
			if ( ! $this->topics->covers_specific_entity( $text, $entity )
				&& ! $this->shares_distinctive_token( $text, $entity ) ) {
				return ArticleGenerationValidationResult::failure(
					'rsaip_article_faq_off_topic',
					'FAQ must remain specific to the canonical recipe entity.'
				);
			}
		}
		return null;
	}

	private function assert_no_invented_urls( string $text, ArticleGenerationContext $context ): ?ArticleGenerationValidationResult {
		if ( ! preg_match_all( '#https?://[^\s"\'<>]+#i', $text, $m ) ) {
			return null;
		}
		$allowed = $context->image_urls();
		foreach ( $m[0] as $url ) {
			$ok = false;
			foreach ( $allowed as $known ) {
				if ( strcasecmp( (string) $url, (string) $known ) === 0 ) {
					$ok = true;
					break;
				}
			}
			if ( ! $ok ) {
				return ArticleGenerationValidationResult::failure(
					'rsaip_article_invented_url',
					'Invented URLs are not allowed in article output.'
				);
			}
		}
		return null;
	}

	private function assert_safe_html( string $html ): ?ArticleGenerationValidationResult {
		if ( preg_match( '/<\s*(script|iframe|object|embed|link|meta)\b/i', $html ) ) {
			return ArticleGenerationValidationResult::failure( 'rsaip_article_unsafe_html', 'Unsupported HTML tags in content_html.' );
		}
		if ( preg_match( '/\son[a-z]+\s*=/i', $html ) ) {
			return ArticleGenerationValidationResult::failure( 'rsaip_article_unsafe_html', 'Inline event handlers are not allowed.' );
		}
		if ( preg_match( '/javascript\s*:/i', $html ) ) {
			return ArticleGenerationValidationResult::failure( 'rsaip_article_unsafe_url', 'javascript: URLs are not allowed.' );
		}
		if ( preg_match( '/\bdata:\s*text\/html/i', $html ) ) {
			return ArticleGenerationValidationResult::failure( 'rsaip_article_unsafe_url', 'data:text/html URLs are not allowed.' );
		}
		return null;
	}

	private function sanitize_html( string $html ): string {
		if ( function_exists( 'wp_kses_post' ) ) {
			return (string) wp_kses_post( $html );
		}
		return strip_tags( $html, '<p><br><h1><h2><h3><h4><ul><ol><li><strong><em><a><img><figure><figcaption><div><span>' );
	}

	private function count_words( string $html ): int {
		$text = function_exists( 'wp_strip_all_tags' ) ? (string) wp_strip_all_tags( $html ) : strip_tags( $html );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) ?? '' );
		if ( $text === '' ) {
			return 0;
		}
		$parts = preg_split( '/\s+/u', $text );
		return is_array( $parts ) ? count( array_filter( $parts ) ) : 0;
	}

	/**
	 * @param mixed $value Raw.
	 * @return list<array{level: string, text: string}>|null
	 */
	private function parse_headings( $value ): ?array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				return null;
			}
			$level = strtolower( $this->normalize_spaces( (string) ( $row['level'] ?? 'h2' ) ) );
			$text  = $this->normalize_spaces( (string) ( $row['text'] ?? '' ) );
			if ( ! in_array( $level, array( 'h2', 'h3' ), true ) || $text === '' ) {
				return null;
			}
			$out[] = array(
				'level' => $level,
				'text'  => $text,
			);
			if ( count( $out ) >= 30 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $value Raw.
	 * @return list<array{q: string, a: string}>|null
	 */
	private function parse_faq( $value ): ?array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				return null;
			}
			$q = $this->normalize_spaces( (string) ( $row['q'] ?? '' ) );
			$a = $this->normalize_spaces( (string) ( $row['a'] ?? '' ) );
			if ( $q === '' || $a === '' ) {
				return null;
			}
			$out[] = array(
				'q' => $q,
				'a' => $a,
			);
			if ( count( $out ) >= 12 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Image plans are placeholders only — no invented absolute URLs.
	 *
	 * @param mixed $value Raw.
	 * @return list<array{placeholder: string, alt: string, note: string}>|null
	 */
	private function parse_image_plans( $value ): ?array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				return null;
			}
			if ( isset( $row['url'] ) || isset( $row['href'] ) || isset( $row['src'] ) ) {
				return null;
			}
			$placeholder = $this->normalize_spaces( (string) ( $row['placeholder'] ?? '' ) );
			$alt         = $this->normalize_spaces( (string) ( $row['alt'] ?? '' ) );
			$note        = $this->normalize_spaces( (string) ( $row['note'] ?? '' ) );
			if ( $placeholder === '' ) {
				return null;
			}
			if ( preg_match( '#https?://|javascript:#i', $placeholder . ' ' . $alt . ' ' . $note ) ) {
				return null;
			}
			$out[] = array(
				'placeholder' => $placeholder,
				'alt'         => $alt,
				'note'        => $note,
			);
			if ( count( $out ) >= 8 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $data Data.
	 */
	private function require_non_empty_string( array $data, string $key ): ?string {
		if ( ! isset( $data[ $key ] ) ) {
			return null;
		}
		$text = $this->normalize_spaces( (string) $data[ $key ] );
		return $text !== '' ? $text : null;
	}

	/**
	 * @param array<string, mixed> $data Data.
	 */
	private function optional_string( array $data, string $key ): string {
		if ( ! isset( $data[ $key ] ) ) {
			return '';
		}
		return $this->normalize_spaces( (string) $data[ $key ] );
	}

	/**
	 * @param mixed $value Raw.
	 * @return list<string>
	 */
	private function string_list( $value, int $max_items, int $max_len ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $item ) {
			if ( ! is_string( $item ) && ! is_numeric( $item ) ) {
				continue;
			}
			$item = $this->normalize_spaces( (string) $item );
			if ( $item === '' ) {
				continue;
			}
			$len = function_exists( 'mb_strlen' ) ? mb_strlen( $item, 'UTF-8' ) : strlen( $item );
			if ( $len > $max_len ) {
				$item = function_exists( 'mb_substr' ) ? (string) mb_substr( $item, 0, $max_len, 'UTF-8' ) : substr( $item, 0, $max_len );
			}
			$out[] = $item;
			if ( count( $out ) >= $max_items ) {
				break;
			}
		}
		return array_values( array_unique( $out ) );
	}

	private function normalize_spaces( string $text ): string {
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return trim( (string) $text );
	}
}
