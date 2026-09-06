<?php
declare(strict_types=1);

/**
 * Strict machine validator for SeoOptimizationProposal.
 *
 * Fail closed: no generic heuristic proposal on invalid AI data.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\SeoOptimization;

use RecipeSeoAiPro\Modules\Ai\TitleGeneration\TitleTopic;
use RecipeSeoAiPro\Modules\Ai\TitleGeneration\TitleTopicSelector;
use RecipeSeoAiPro\Modules\Schema\RecipeSchemaStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SeoOptimizationProposalValidator
 */
final class SeoOptimizationProposalValidator {

	private const SEARCH_INTENTS = array( 'informational', 'transactional', 'navigational', 'commercial' );
	private const ISSUE_CATEGORIES = array( 'title', 'meta', 'keyword', 'content', 'heading', 'image', 'link', 'other' );
	private const ISSUE_SEVERITIES = array( 'low', 'medium', 'high' );
	private const APPLIES_VIA = array( 'none', 'title', 'meta_description', 'keywords' );
	private const HEADING_LEVELS = array( 'h2', 'h3' );
	private const IMAGE_TARGETS = array( 'featured', 'inline' );

	private const TITLE_MAX = 60;
	private const META_MAX_PMS = 170;
	private const META_MIN_PREFERRED = 120;
	private const META_MAX_PREFERRED = 160;
	private const SECONDARY_MAX = 8;
	private const KEYWORD_MAX_WORDS = 6;
	private const KEYWORD_MAX_CHARS = 80;
	private const HEADING_TEXT_MAX = 120;
	private const HEADING_RATIONALE_MAX = 300;
	private const FAQ_Q_MAX = 200;
	private const FAQ_A_MAX = 600;
	private const ALT_MAX = 125;
	private const LINK_ANCHOR_MAX = 80;
	private const LINK_HINT_MAX = 200;
	private const LINK_REASON_MAX = 300;
	private const GAP_MAX = 200;

	private TitleTopicSelector $topics;

	public function __construct( ?TitleTopicSelector $topics = null ) {
		$this->topics = $topics instanceof TitleTopicSelector ? $topics : new TitleTopicSelector();
	}

	/**
	 * @param array<string, mixed> $data Decoded AI JSON object.
	 */
	public function validate( array $data, SeoOptimizationContext $context ): SeoOptimizationValidationResult {
		$topic = $this->require_string( $data, 'topic' );
		if ( $topic === null ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_missing_topic', 'Missing or empty topic.' );
		}

		$primary = $this->require_string( $data, 'primary_focus_keyword' );
		if ( $primary === null ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_missing_primary_keyword', 'Missing or empty primary_focus_keyword.' );
		}

		$recommended = $this->require_string( $data, 'recommended_title' );
		if ( $recommended === null ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_missing_recommended_title', 'Missing or empty recommended_title.' );
		}

		$meta_raw = $this->require_string( $data, 'meta_description' );
		if ( $meta_raw === null ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_missing_meta', 'Missing or empty meta_description.' );
		}

		if ( ! isset( $data['seo_title_suggestions'] ) || ! is_array( $data['seo_title_suggestions'] ) ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_missing_titles', 'Missing seo_title_suggestions array.' );
		}
		if ( $data['seo_title_suggestions'] === array() ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_empty_titles', 'seo_title_suggestions must contain at least one title.' );
		}

		$specific = $this->resolve_specific_entity( $context );
		if ( $specific !== '' && $this->topics->is_weaker_than_entity( $topic, $specific ) ) {
			return SeoOptimizationValidationResult::failure(
				'rsaip_seo_opt_generic_topic',
				'Topic is too generic or incomplete relative to the available recipe/title entity.'
			);
		}
		if ( $specific !== '' && $this->topics->is_weaker_than_entity( $primary, $specific ) ) {
			return SeoOptimizationValidationResult::failure(
				'rsaip_seo_opt_generic_primary_keyword',
				'Primary focus keyword is too generic or incomplete relative to the available recipe/title entity.'
			);
		}

		$intent = isset( $data['search_intent'] ) ? $this->normalize_spaces( (string) $data['search_intent'] ) : 'informational';
		$intent = strtolower( $intent );
		if ( ! in_array( $intent, self::SEARCH_INTENTS, true ) ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_invalid_intent', 'Invalid search_intent.' );
		}

		// Title gate uses the stronger of AI topic vs server entity (4B hard gate preserved).
		$entity_for_titles = $specific !== '' ? $specific : $topic;
		if ( $this->topics->covers_specific_entity( $topic, $entity_for_titles ) ) {
			$entity_for_titles = $this->topics->normalize( $topic );
		}
		$title_gate = new TitleTopic( $entity_for_titles, $entity_for_titles, $entity_for_titles );
		$titles_in         = $data['seo_title_suggestions'];
		$titles_out        = array();
		foreach ( $titles_in as $title ) {
			if ( ! is_string( $title ) && ! is_numeric( $title ) ) {
				return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_invalid_title', 'Title suggestions must be strings.' );
			}
			$title = $this->normalize_spaces( (string) $title );
			if ( $title === '' ) {
				return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_invalid_title', 'Empty title suggestion.' );
			}
			if ( $this->str_len( $title ) > self::TITLE_MAX ) {
				return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_title_too_long', 'Title exceeds 60 characters.' );
			}
			$kept = $this->topics->keep_titles_with_topic( array( $title ), $title_gate );
			if ( $kept === array() ) {
				return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_title_missing_topic', 'Title does not preserve the topic/recipe entity.' );
			}
			$titles_out[] = $title;
		}
		$titles_out = array_values( array_unique( $titles_out ) );
		if ( $titles_out === array() ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_empty_titles', 'No valid title suggestions.' );
		}

		$recommended = $this->normalize_spaces( $recommended );
		if ( $this->str_len( $recommended ) > self::TITLE_MAX ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_recommended_too_long', 'recommended_title exceeds 60 characters.' );
		}
		if ( $this->topics->keep_titles_with_topic( array( $recommended ), $title_gate ) === array() ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_recommended_missing_topic', 'recommended_title does not preserve the topic/recipe entity.' );
		}

		$meta = $this->normalize_meta_compatible( $meta_raw );
		if ( $meta === null ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_invalid_meta', 'meta_description is empty after normalization.' );
		}
		$meta_len = $this->str_len( $meta );
		if ( $meta_len > self::META_MAX_PMS ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_meta_too_long', 'meta_description exceeds PMS max length (170).' );
		}
		if ( $meta_len < self::META_MIN_PREFERRED || $meta_len > self::META_MAX_PREFERRED ) {
			// Prefer 140–160; allow 120–160 as generation target band compatible with PMS.
			if ( $meta_len < self::META_MIN_PREFERRED ) {
				return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_meta_too_short', 'meta_description is shorter than the preferred SEO band.' );
			}
			if ( $meta_len > self::META_MAX_PREFERRED && $meta_len <= self::META_MAX_PMS ) {
				// Still PMS-compatible; accept but do not invent content. Allowed for Apply compatibility.
			}
		}
		if ( $this->text_invents_unsupported_facts( $meta, $context ) ) {
			return SeoOptimizationValidationResult::failure(
				'rsaip_seo_opt_meta_unsupported_claim',
				'meta_description contains unsupported invented nutrition/allergen claims.'
			);
		}

		$secondary = $this->sanitize_keywords( $data['secondary_keywords'] ?? array(), $specific, $primary, $context );
		if ( $secondary === null ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_invalid_secondary', 'secondary_keywords must be related, non-generic phrases (not entities dump).' );
		}

		$entities = $this->sanitize_entities( $data['entities'] ?? array(), $context );
		if ( $entities === null ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_invalid_entities', 'entities must be supported display-only strings.' );
		}

		$headings = $this->parse_headings( $data['heading_suggestions'] ?? array() );
		if ( $headings === null ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_invalid_headings', 'Invalid heading_suggestions structure or enums.' );
		}

		$faqs = $this->parse_faqs( $data['faq_suggestions'] ?? array(), $context );
		if ( $faqs === null ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_invalid_faqs', 'Invalid faq_suggestions structure or unsupported invented facts.' );
		}

		$alts = $this->parse_image_alts( $data['image_alt_suggestions'] ?? array() );
		if ( $alts === null ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_invalid_alts', 'Invalid image_alt_suggestions structure or enums.' );
		}

		$links = $this->parse_internal_links( $data['internal_link_suggestions'] ?? array() );
		if ( $links === null ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_invalid_links', 'Invalid internal_link_suggestions structure (URLs not allowed).' );
		}

		$gaps = $this->sanitize_string_list( $data['content_gaps'] ?? array(), 20, self::GAP_MAX );
		if ( $gaps === null ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_invalid_gaps', 'content_gaps must be an array of strings.' );
		}

		$issues = $this->parse_issues( $data['seo_issues'] ?? array() );
		if ( $issues === null ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_invalid_issues', 'Invalid seo_issues structure or enums.' );
		}
		$issues = $this->filter_schema_issues( $issues, $context );

		$recs = $this->parse_recommendations( $data['recommendations'] ?? array() );
		if ( $recs === null ) {
			return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_invalid_recommendations', 'Invalid recommendations structure or applies_via.' );
		}
		$recs = $this->filter_schema_recommendations( $recs, $context );
		$gaps = $this->filter_schema_gaps( $gaps, $context );

		$confidence = null;
		if ( array_key_exists( 'confidence', $data ) && $data['confidence'] !== null && $data['confidence'] !== '' ) {
			if ( ! is_numeric( $data['confidence'] ) ) {
				return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_invalid_confidence', 'confidence must be numeric 0.0–1.0.' );
			}
			$confidence = (float) $data['confidence'];
			if ( $confidence < 0.0 || $confidence > 1.0 ) {
				return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_invalid_confidence', 'confidence must be between 0.0 and 1.0.' );
			}
		}

		$recipe_name = null;
		if ( array_key_exists( 'recipe_name', $data ) && $data['recipe_name'] !== null && $data['recipe_name'] !== '' ) {
			if ( ! is_string( $data['recipe_name'] ) && ! is_numeric( $data['recipe_name'] ) ) {
				return SeoOptimizationValidationResult::failure( 'rsaip_seo_opt_invalid_recipe_name', 'recipe_name must be a string or null.' );
			}
			$recipe_name = $this->normalize_spaces( (string) $data['recipe_name'] );
			if ( $recipe_name === '' ) {
				$recipe_name = null;
			} elseif ( $specific !== '' && $this->topics->is_weaker_than_entity( $recipe_name, $specific ) ) {
				return SeoOptimizationValidationResult::failure(
					'rsaip_seo_opt_inconsistent_recipe_name',
					'recipe_name is inconsistent with the server recipe entity.'
				);
			}
		}

		$reasoning = '';
		if ( isset( $data['reasoning_summary'] ) && ( is_string( $data['reasoning_summary'] ) || is_numeric( $data['reasoning_summary'] ) ) ) {
			$reasoning = $this->normalize_spaces( (string) $data['reasoning_summary'] );
			if ( $this->str_len( $reasoning ) > 1000 ) {
				$reasoning = $this->str_sub( $reasoning, 0, 1000 );
			}
		}

		$proposal = new SeoOptimizationProposal(
			$topic,
			$recipe_name,
			$intent,
			$primary,
			$secondary,
			$entities,
			$titles_out,
			$recommended,
			$meta,
			$headings,
			$faqs,
			$alts,
			$links,
			$gaps,
			$issues,
			$recs,
			$confidence,
			$reasoning
		);

		return SeoOptimizationValidationResult::success( $proposal );
	}

	private function resolve_specific_entity( SeoOptimizationContext $context ): string {
		if ( $context->detected_recipe_name() !== '' ) {
			return $this->topics->normalize( $context->detected_recipe_name() );
		}
		$anchor = $this->topics->extract_anchor( $context->current_title() );
		return $anchor !== '' ? $anchor : $this->topics->normalize( $context->current_title() );
	}

	/**
	 * Drop AI schema-existence claims that contradict server detector state.
	 *
	 * @param list<array{code: string, category: string, severity: string}> $issues Issues.
	 * @return list<array{code: string, category: string, severity: string}>
	 */
	private function filter_schema_issues( array $issues, SeoOptimizationContext $context ): array {
		$status = $context->recipe_schema_status();
		$out    = array();
		foreach ( $issues as $issue ) {
			if ( $this->text_claims_missing_schema( (string) ( $issue['code'] ?? '' ) ) ) {
				if ( $status !== RecipeSchemaStatus::MISSING ) {
					continue;
				}
			}
			$out[] = $issue;
		}
		return $out;
	}

	/**
	 * @param list<array{action: string, applies_via: string, note: string}> $recs Recs.
	 * @return list<array{action: string, applies_via: string, note: string}>
	 */
	private function filter_schema_recommendations( array $recs, SeoOptimizationContext $context ): array {
		$status = $context->recipe_schema_status();
		$out    = array();
		foreach ( $recs as $rec ) {
			$text = strtolower( (string) ( $rec['action'] ?? '' ) . ' ' . (string) ( $rec['note'] ?? '' ) );
			$creates = $this->text_claims_create_schema( $text );
			$missing = $this->text_claims_missing_schema( $text );
			if ( $creates || $missing ) {
				if ( $status === RecipeSchemaStatus::VALID_RECIPE
					|| $status === RecipeSchemaStatus::EXTERNAL_OR_UNKNOWN
					|| $status === RecipeSchemaStatus::MULTIPLE
					|| $status === RecipeSchemaStatus::INCOMPLETE ) {
					continue;
				}
			}
			$out[] = $rec;
		}
		return $out;
	}

	/**
	 * @param list<string> $gaps Gaps.
	 * @return list<string>
	 */
	private function filter_schema_gaps( array $gaps, SeoOptimizationContext $context ): array {
		$status = $context->recipe_schema_status();
		if ( $status === RecipeSchemaStatus::MISSING ) {
			return $gaps;
		}
		$out = array();
		foreach ( $gaps as $gap ) {
			if ( $this->text_claims_missing_schema( $gap ) || $this->text_claims_create_schema( $gap ) ) {
				continue;
			}
			$out[] = $gap;
		}
		return $out;
	}

	private function text_claims_missing_schema( string $text ): bool {
		$t = strtolower( $text );
		if ( $t === '' ) {
			return false;
		}
		if ( strpos( $t, 'schema' ) === false && strpos( $t, 'json-ld' ) === false && strpos( $t, 'jsonld' ) === false ) {
			return false;
		}
		return (bool) preg_match( '/missing|absent|no\s+recipe\s+schema|lack(s|ing)?\s+.*schema|without\s+.*schema/i', $t );
	}

	private function text_claims_create_schema( string $text ): bool {
		$t = strtolower( $text );
		if ( $t === '' || strpos( $t, 'schema' ) === false ) {
			return false;
		}
		return (bool) preg_match( '/(create|add|generate|insert|build|repair|fix).{0,40}schema|schema.{0,40}(create|add|generate|insert|build|repair|fix)/i', $t );
	}

	/**
	 * @param array<string, mixed> $data Data.
	 */
	private function require_string( array $data, string $key ): ?string {
		if ( ! array_key_exists( $key, $data ) ) {
			return null;
		}
		if ( ! is_string( $data[ $key ] ) && ! is_numeric( $data[ $key ] ) ) {
			return null;
		}
		$value = $this->normalize_spaces( (string) $data[ $key ] );
		return $value === '' ? null : $value;
	}

	private function normalize_spaces( string $text ): string {
		$text = preg_replace( '/\s+/u', ' ', $text );
		return trim( (string) $text );
	}

	/**
	 * Mirror MetaDescriptionWriter / fit_length max-170 space normalize (no write).
	 */
	private function normalize_meta_compatible( string $text ): ?string {
		$text = $this->normalize_spaces( $text );
		if ( $text === '' ) {
			return null;
		}
		if ( $this->str_len( $text ) > self::META_MAX_PMS ) {
			return $text; // caller rejects >170 without silent truncate of proposal truth
		}
		return $text;
	}

	/**
	 * @param mixed                   $value   Raw keywords.
	 * @param string                  $specific Server entity.
	 * @param string                  $primary Primary keyword.
	 * @return list<string>|null
	 */
	private function sanitize_keywords( $value, string $specific, string $primary, SeoOptimizationContext $context ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$out     = array();
		$primary = strtolower( $this->normalize_spaces( $primary ) );
		foreach ( $value as $keyword ) {
			if ( ! is_string( $keyword ) && ! is_numeric( $keyword ) ) {
				return null;
			}
			$keyword = $this->normalize_spaces( (string) $keyword );
			$keyword = trim( (string) preg_replace( '/^[\-\*\x{2022}\d\.\)\(,\s]+/u', '', $keyword ) );
			if ( $keyword === '' ) {
				continue;
			}
			$words = preg_split( '/\s+/u', $keyword );
			if ( ! is_array( $words ) ) {
				return null;
			}
			$words = array_values( array_filter( $words ) );
			if ( count( $words ) > self::KEYWORD_MAX_WORDS ) {
				$keyword = implode( ' ', array_slice( $words, 0, self::KEYWORD_MAX_WORDS ) );
			}
			if ( $this->str_len( $keyword ) > self::KEYWORD_MAX_CHARS ) {
				$keyword = $this->str_sub( $keyword, 0, self::KEYWORD_MAX_CHARS );
				$keyword = rtrim( $keyword );
			}
			if ( $keyword === '' ) {
				continue;
			}
			if ( strtolower( $keyword ) === $primary ) {
				continue; // Not a secondary.
			}
			if ( $specific !== '' && $this->topics->is_generic_relative_to( $keyword, $specific ) ) {
				return null; // Deterministic: generic filler like "Salad" as secondary rejected.
			}
			if ( ! $this->secondary_is_related( $keyword, $specific, $context ) ) {
				return null;
			}
			$out[] = $keyword;
			if ( count( $out ) >= self::SECONDARY_MAX ) {
				break;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Secondary must share distinctive tokens with entity/ingredients/tags or be a supported dietary variant.
	 */
	private function secondary_is_related( string $keyword, string $specific, SeoOptimizationContext $context ): bool {
		$hay_tokens = array_merge(
			$this->topics->distinctive_tokens( $specific ),
			$this->topics->distinctive_tokens( $context->current_title() ),
			$this->topics->distinctive_tokens( $context->recipe_summary() )
		);
		foreach ( $context->recipe_ingredients() as $ing ) {
			$hay_tokens = array_merge( $hay_tokens, $this->topics->distinctive_tokens( (string) $ing ) );
		}
		foreach ( array_merge( $context->categories(), $context->tags() ) as $term ) {
			$hay_tokens = array_merge( $hay_tokens, $this->topics->distinctive_tokens( (string) $term ) );
		}
		$hay_tokens = array_fill_keys( array_values( array_unique( $hay_tokens ) ), true );

		$kw_tokens = $this->topics->distinctive_tokens( $keyword );
		if ( $kw_tokens === array() ) {
			return false;
		}
		foreach ( $kw_tokens as $t ) {
			if ( isset( $hay_tokens[ $t ] ) ) {
				return true;
			}
		}

		// Dietary variants only when supported by context facts/summary/tags.
		$dietary = array( 'vegan', 'vegetarian', 'gluten-free', 'glutenfree', 'dairy-free', 'dairyfree', 'keto', 'paleo' );
		$kw_l    = strtolower( $keyword );
		$ctx_l   = strtolower(
			$context->recipe_summary() . ' ' . implode( ' ', $context->tags() ) . ' ' .
			implode( ' ', $context->categories() ) . ' ' . implode( ' ', $context->known_recipe_facts() )
		);
		foreach ( $dietary as $d ) {
			if ( strpos( $kw_l, $d ) !== false && strpos( $ctx_l, $d ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Entities are display-only; drop unsupported invented claims; never merge into keywords.
	 *
	 * @param mixed $value Raw.
	 * @return list<string>|null
	 */
	private function sanitize_entities( $value, SeoOptimizationContext $context ): ?array {
		$list = $this->sanitize_string_list( $value, 20, 80 );
		if ( $list === null ) {
			return null;
		}
		$out = array();
		foreach ( $list as $item ) {
			if ( $this->text_invents_unsupported_facts( $item, $context ) ) {
				return null;
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * @param mixed $value Raw list.
	 * @return list<string>|null
	 */
	private function sanitize_string_list( $value, int $max_items, int $max_len ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$out = array();
		foreach ( $value as $item ) {
			if ( ! is_string( $item ) && ! is_numeric( $item ) ) {
				return null;
			}
			$item = $this->normalize_spaces( (string) $item );
			if ( $item === '' ) {
				continue;
			}
			if ( $this->str_len( $item ) > $max_len ) {
				$item = $this->str_sub( $item, 0, $max_len );
			}
			$out[] = $item;
			if ( count( $out ) >= $max_items ) {
				break;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param mixed $value Raw.
	 * @return list<array{level: string, text: string, rationale: string}>|null
	 */
	private function parse_headings( $value ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$out = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				return null;
			}
			// Unknown nested keys (url, html, apply, etc.) are discarded — only level/text/rationale trusted.
			$level     = isset( $row['level'] ) ? strtolower( $this->plain_text( (string) $row['level'] ) ) : '';
			$text      = isset( $row['text'] ) ? $this->plain_text( (string) $row['text'] ) : '';
			$rationale = isset( $row['rationale'] ) ? $this->plain_text( (string) $row['rationale'] ) : '';
			if ( ! in_array( $level, self::HEADING_LEVELS, true ) || $text === '' || $rationale === '' ) {
				return null;
			}
			if ( $this->str_len( $text ) > self::HEADING_TEXT_MAX || $this->str_len( $rationale ) > self::HEADING_RATIONALE_MAX ) {
				return null;
			}
			if ( $this->contains_html_markup( (string) ( $row['text'] ?? '' ) ) || $this->looks_like_url( $text ) ) {
				return null;
			}
			$out[] = array(
				'level'     => $level,
				'text'      => $text,
				'rationale' => $rationale,
			);
			if ( count( $out ) >= 20 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $value Raw.
	 * @return list<array{q: string, a: string}>|null
	 */
	private function parse_faqs( $value, SeoOptimizationContext $context ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$out = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				return null;
			}
			$q = isset( $row['q'] ) ? $this->plain_text( (string) $row['q'] ) : '';
			$a = isset( $row['a'] ) ? $this->plain_text( (string) $row['a'] ) : '';
			if ( $q === '' || $a === '' ) {
				return null;
			}
			if ( $this->str_len( $q ) > self::FAQ_Q_MAX || $this->str_len( $a ) > self::FAQ_A_MAX ) {
				return null;
			}
			if ( $this->contains_html_markup( (string) ( $row['q'] ?? '' ) ) || $this->contains_html_markup( (string) ( $row['a'] ?? '' ) ) ) {
				return null;
			}
			if ( $this->text_invents_unsupported_facts( $q . ' ' . $a, $context ) ) {
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
	 * @param mixed $value Raw.
	 * @return list<array{target: string, alt: string}>|null
	 */
	private function parse_image_alts( $value ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$out = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				return null;
			}
			$target = isset( $row['target'] ) ? strtolower( $this->plain_text( (string) $row['target'] ) ) : '';
			$alt    = isset( $row['alt'] ) ? $this->plain_text( (string) $row['alt'] ) : '';
			if ( ! in_array( $target, self::IMAGE_TARGETS, true ) || $alt === '' ) {
				return null;
			}
			if ( $this->str_len( $alt ) > self::ALT_MAX ) {
				return null;
			}
			if ( $this->contains_html_markup( (string) ( $row['alt'] ?? '' ) ) ) {
				return null;
			}
			$out[] = array(
				'target' => $target,
				'alt'    => $alt,
			);
			if ( count( $out ) >= 12 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $value Raw.
	 * @return list<array{anchor: string, target_hint: string, reason: string}>|null
	 */
	private function parse_internal_links( $value ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$out = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				return null;
			}
			// Discard unknown keys (url, href, permalink, post_id, etc.) — never trust as write targets.
			$anchor = isset( $row['anchor'] ) ? $this->plain_text( (string) $row['anchor'] ) : '';
			$hint   = isset( $row['target_hint'] ) ? $this->plain_text( (string) $row['target_hint'] ) : '';
			$reason = isset( $row['reason'] ) ? $this->plain_text( (string) $row['reason'] ) : '';
			if ( $anchor === '' || $hint === '' || $reason === '' ) {
				return null;
			}
			if ( $this->str_len( $anchor ) > self::LINK_ANCHOR_MAX
				|| $this->str_len( $hint ) > self::LINK_HINT_MAX
				|| $this->str_len( $reason ) > self::LINK_REASON_MAX ) {
				return null;
			}
			if ( $this->looks_like_url( $hint ) || $this->looks_like_url( $anchor ) ) {
				return null;
			}
			if ( isset( $row['url'] ) || isset( $row['href'] ) || isset( $row['permalink'] ) || isset( $row['post_id'] ) ) {
				// Explicit URL / post identity fields from AI are not trusted; reject the set.
				return null;
			}
			$out[] = array(
				'anchor'      => $anchor,
				'target_hint' => $hint,
				'reason'      => $reason,
			);
			if ( count( $out ) >= 12 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Deterministic hallucination gate: invented calorie/nutrition/allergen figures not present in context.
	 */
	private function text_invents_unsupported_facts( string $text, SeoOptimizationContext $context ): bool {
		$hay = $context->content_excerpt() . ' ' . $context->recipe_summary() . ' ' . $context->current_title()
			. ' ' . implode( ' ', $context->recipe_ingredients() ) . ' ' . implode( ' ', $context->known_recipe_facts() );
		if ( preg_match_all( '/\b(\d{2,4})\s*(?:kcal|calories|cal)\b/iu', $text, $m ) ) {
			foreach ( $m[0] as $claim ) {
				if ( ! preg_match( '/' . preg_quote( $claim, '/' ) . '/iu', $hay ) ) {
					return true;
				}
			}
		}
		if ( preg_match( '/\b(?:allergen|allergens|allergy|allergic)\b/iu', $text )
			&& ! preg_match( '/\b(?:allergen|allergens|allergy|allergic|peanut|gluten|dairy|nut-free)\b/iu', $hay ) ) {
			return true;
		}
		// Invented absolute cooking times like "cook for 90 minutes" when no times known.
		$facts = $context->known_recipe_facts();
		$has_time = ! empty( $facts['prep_time'] ) || ! empty( $facts['cook_time'] ) || ! empty( $facts['total_time'] )
			|| preg_match( '/\b(?:prep_time|cook_time|total_time|minutes?|hours?)\b/iu', $context->recipe_summary() );
		if ( ! $has_time && preg_match( '/\b(?:cook|bake|roast|prep)\b.{0,20}\b\d{1,3}\s*(?:minutes?|mins?|hours?|hrs?)\b/iu', $text ) ) {
			return true;
		}
		return false;
	}

	/** @deprecated Use text_invents_unsupported_facts */
	private function faq_invents_unsupported_facts( string $text, SeoOptimizationContext $context ): bool {
		return $this->text_invents_unsupported_facts( $text, $context );
	}

	private function plain_text( string $text ): string {
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/<[^>]+>/u', ' ', $text ) ?? $text;
		return $this->normalize_spaces( $text );
	}

	private function contains_html_markup( string $raw ): bool {
		return (bool) preg_match( '/<\/?[a-z][^>]*>/i', $raw );
	}

	private function looks_like_url( string $text ): bool {
		return (bool) preg_match( '#https?://|www\.#i', $text );
	}

	/**
	 * @param mixed $value Raw.
	 * @return list<array{code: string, category: string, severity: string}>|null
	 */
	private function parse_issues( $value ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$out = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				return null;
			}
			$code     = isset( $row['code'] ) ? $this->normalize_spaces( (string) $row['code'] ) : '';
			$category = isset( $row['category'] ) ? strtolower( $this->normalize_spaces( (string) $row['category'] ) ) : '';
			$severity = isset( $row['severity'] ) ? strtolower( $this->normalize_spaces( (string) $row['severity'] ) ) : '';
			if ( $code === '' || ! in_array( $category, self::ISSUE_CATEGORIES, true ) || ! in_array( $severity, self::ISSUE_SEVERITIES, true ) ) {
				return null;
			}
			$out[] = array(
				'code'     => $code,
				'category' => $category,
				'severity' => $severity,
			);
			if ( count( $out ) >= 30 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $value Raw.
	 * @return list<array{action: string, applies_via: string, note: string}>|null
	 */
	private function parse_recommendations( $value ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$out = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				return null;
			}
			$action = isset( $row['action'] ) ? $this->normalize_spaces( (string) $row['action'] ) : '';
			$via    = isset( $row['applies_via'] ) ? strtolower( $this->normalize_spaces( (string) $row['applies_via'] ) ) : 'none';
			$note   = isset( $row['note'] ) ? $this->normalize_spaces( (string) $row['note'] ) : '';
			if ( $action === '' || ! in_array( $via, self::APPLIES_VIA, true ) ) {
				return null;
			}
			$out[] = array(
				'action'      => $action,
				'applies_via' => $via,
				'note'        => $note,
			);
			if ( count( $out ) >= 30 ) {
				break;
			}
		}
		return $out;
	}

	private function str_len( string $text ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	private function str_sub( string $text, int $start, int $length ): string {
		return function_exists( 'mb_substr' ) ? (string) mb_substr( $text, $start, $length, 'UTF-8' ) : substr( $text, $start, $length );
	}
}
