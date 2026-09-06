<?php
declare(strict_types=1);

/**
 * Builds ArticleGenerationContext from a server-owned brief (Article Generator A/B).
 *
 * Milestone B: recipe-aware, read-only resolution from schema/card/content.
 * Never writes WordPress data. Never invents recipe facts.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\ArticleGeneration;

use RecipeSeoAiPro\Modules\Ai\TitleGeneration\TitleTopicSelector;
use RecipeSeoAiPro\Modules\Schema\RecipeSchemaDetector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ArticleGenerationContextBuilder
 */
final class ArticleGenerationContextBuilder {

	private TitleTopicSelector $topics;
	private ArticleRecipeContextResolver $resolver;

	public function __construct(
		?TitleTopicSelector $topics = null,
		?ArticleRecipeContextResolver $resolver = null
	) {
		$this->topics   = $topics instanceof TitleTopicSelector ? $topics : new TitleTopicSelector();
		$this->resolver = $resolver instanceof ArticleRecipeContextResolver
			? $resolver
			: new ArticleRecipeContextResolver( null, $this->topics );
	}

	public function resolver(): ArticleRecipeContextResolver {
		return $this->resolver;
	}

	/**
	 * @param array<string, mixed> $brief Server brief fields.
	 */
	public function from_brief( array $brief ): ArticleGenerationContext {
		$title = $this->bound_text( (string) ( $brief['title'] ?? $brief['topic'] ?? '' ), 300 );
		$template = $this->normalize_template( (string) ( $brief['template'] ?? ArticleGenerationContext::TEMPLATE_GENERAL ) );
		$words = $this->normalize_word_count( isset( $brief['word_count'] ) ? (int) $brief['word_count'] : (int) ( $brief['requested_word_count'] ?? 1000 ) );

		$keywords = $this->string_list( $brief['keywords'] ?? $brief['supplied_keywords'] ?? array(), 12, 80 );
		$images   = $this->sanitize_image_urls( $brief['images'] ?? $brief['image_urls'] ?? array() );

		$content = (string) ( $brief['content'] ?? $brief['post_content'] ?? $brief['recipe_content'] ?? '' );

		$schema_status  = 'none';
		$schema_source  = 'none';
		$schema_details = '';
		$fact_source    = 'none';
		$has_card       = false;
		$resolved_facts = $this->resolver->empty_facts();
		$resolved_entity = '';

		if ( $content !== '' || ! empty( $brief['structured_recipe'] ) ) {
			$resolved = $this->resolver->resolve_from_content(
				$content,
				$title,
				array(
					'rank_math_active'          => ! empty( $brief['rank_math_active'] ),
					'yoast_active'              => ! empty( $brief['yoast_active'] ),
					'external_recipe_plugin'    => ! empty( $brief['external_recipe_plugin'] ),
					'external_recipe_plugin_id' => (string) ( $brief['external_recipe_plugin_id'] ?? '' ),
					'focus_keyword'             => (string) ( $brief['primary_focus_keyword'] ?? $brief['primary_focus_keyword_hint'] ?? ( $keywords[0] ?? '' ) ),
					'structured_recipe'         => isset( $brief['structured_recipe'] ) && is_array( $brief['structured_recipe'] )
						? $brief['structured_recipe']
						: null,
				)
			);
			$resolved_entity = (string) $resolved['canonical_recipe_entity'];
			$schema_status   = (string) $resolved['recipe_schema_status'];
			$schema_source   = (string) $resolved['recipe_schema_source'];
			$schema_details  = (string) $resolved['recipe_schema_details'];
			$fact_source     = (string) $resolved['recipe_fact_source'];
			$has_card        = ! empty( $resolved['has_rsaip_card'] );
			$resolved_facts  = is_array( $resolved['recipe_facts'] ) ? $resolved['recipe_facts'] : $resolved_facts;
		}

		// Explicit brief facts overlay (server-owned); fill only when provided as known.
		$brief_facts = $this->normalize_recipe_facts(
			isset( $brief['recipe_facts'] ) && is_array( $brief['recipe_facts'] ) ? $brief['recipe_facts'] : array()
		);
		$facts = $this->overlay_known_facts( $resolved_facts, $brief_facts );
		if ( $this->has_any_known( $brief_facts ) && $fact_source === 'none' ) {
			$fact_source = 'brief';
		}

		if ( isset( $brief['recipe_schema_status'] ) && is_string( $brief['recipe_schema_status'] ) && $brief['recipe_schema_status'] !== '' ) {
			$schema_status = (string) $brief['recipe_schema_status'];
		}
		if ( isset( $brief['recipe_schema_source'] ) && is_string( $brief['recipe_schema_source'] ) && $brief['recipe_schema_source'] !== '' ) {
			$schema_source = (string) $brief['recipe_schema_source'];
		}
		if ( isset( $brief['recipe_fact_source'] ) && is_string( $brief['recipe_fact_source'] ) && $brief['recipe_fact_source'] !== '' ) {
			$fact_source = (string) $brief['recipe_fact_source'];
		}
		if ( ! empty( $brief['has_rsaip_card'] ) ) {
			$has_card = true;
		}

		$entity = $this->bound_text( (string) ( $brief['canonical_recipe_entity'] ?? $brief['recipe_name'] ?? '' ), 200 );
		if ( $entity === '' ) {
			$entity = $resolved_entity;
		}
		if ( $entity === '' && $title !== '' ) {
			$entity = $this->topics->extract_anchor( $title );
			if ( $entity === '' ) {
				$entity = $this->topics->normalize( $title );
			}
		} elseif ( $entity !== '' ) {
			$entity = $this->topics->pick_canonical_entity( array( $entity, $resolved_entity, $title ), $title );
		}

		// Generic focus keyword must not replace a specific entity.
		$primary = $this->bound_text( (string) ( $brief['primary_focus_keyword'] ?? $brief['primary_focus_keyword_hint'] ?? '' ), 120 );
		if ( $primary === '' && isset( $keywords[0] ) ) {
			$primary = $keywords[0];
		}
		if ( $entity !== '' && $primary !== '' && $this->topics->is_weaker_than_entity( $primary, $entity ) ) {
			// Keep entity; primary remains a hint only.
		} elseif ( $entity === '' && $primary !== '' && ! $this->topics->is_generic_relative_to( $primary, $title ) ) {
			$entity = $this->topics->normalize( $primary );
		}

		$seo_seed = null;
		if ( isset( $brief['seo_seed'] ) && is_array( $brief['seo_seed'] ) ) {
			$seo_seed = $this->sanitize_seo_seed( $brief['seo_seed'] );
		}

		$intent = strtolower( trim( (string) ( $brief['search_intent'] ?? $brief['search_intent_hint'] ?? '' ) ) );
		if ( ! in_array( $intent, array( 'informational', 'transactional', 'navigational', 'commercial' ), true ) ) {
			$intent = '';
		}

		$heading_hints = $this->normalize_heading_hints(
			$brief['heading_hints'] ?? $brief['heading_suggestions'] ?? ( $seo_seed['heading_suggestions'] ?? array() )
		);
		$faq_hints = $this->normalize_faq_hints(
			$brief['faq_hints'] ?? $brief['faq_suggestions'] ?? ( $seo_seed['faq_suggestions'] ?? array() )
		);
		$image_alt_hints = $this->string_list(
			$brief['image_alt_hints'] ?? $brief['image_alt_suggestions'] ?? ( $seo_seed['image_alt_suggestions'] ?? array() ),
			12,
			120
		);
		$internal_link_hints = $this->normalize_internal_link_hints(
			$brief['internal_link_hints'] ?? $brief['internal_link_suggestions'] ?? ( $seo_seed['internal_link_suggestions'] ?? array() )
		);
		$recommendation_hints = $this->string_list(
			$brief['recommendation_hints'] ?? $brief['recommendations'] ?? ( $seo_seed['recommendations'] ?? array() ),
			20,
			200
		);
		$seo_title_hints = $this->string_list(
			$brief['seo_title_hints'] ?? $brief['seo_title_suggestions'] ?? ( $seo_seed['seo_title_suggestions'] ?? array() ),
			8,
			80
		);

		$content_gaps = $this->string_list( $brief['content_gaps'] ?? $brief['content_gap_hints'] ?? array(), 20, 200 );
		$secondary    = $this->string_list( $brief['secondary_keywords'] ?? $brief['secondary_keyword_hints'] ?? array(), 8, 80 );
		$entities     = $this->string_list( $brief['entities'] ?? $brief['entity_hints'] ?? array(), 20, 80 );
		$rec_title    = $this->bound_text( (string) ( $brief['recommended_title'] ?? $brief['recommended_title_hint'] ?? '' ), 60 );
		$rec_meta     = $this->bound_text( (string) ( $brief['meta_description'] ?? $brief['meta_description_hint'] ?? '' ), 170 );

		// Fill SEO strategy from sanitized M5 seed when brief fields are empty.
		if ( is_array( $seo_seed ) ) {
			if ( $intent === '' && ! empty( $seo_seed['search_intent'] ) ) {
				$seed_intent = strtolower( trim( (string) $seo_seed['search_intent'] ) );
				if ( in_array( $seed_intent, array( 'informational', 'transactional', 'navigational', 'commercial' ), true ) ) {
					$intent = $seed_intent;
				}
			}
			if ( $primary === '' && ! empty( $seo_seed['primary_focus_keyword'] ) ) {
				$primary = $this->bound_text( (string) $seo_seed['primary_focus_keyword'], 120 );
			}
			if ( $secondary === array() && ! empty( $seo_seed['secondary_keywords'] ) ) {
				$secondary = $this->string_list( $seo_seed['secondary_keywords'], 8, 80 );
			}
			if ( $entities === array() && ! empty( $seo_seed['entities'] ) ) {
				$entities = $this->string_list( $seo_seed['entities'], 20, 80 );
			}
			if ( $content_gaps === array() && ! empty( $seo_seed['content_gaps'] ) ) {
				$content_gaps = $this->string_list( $seo_seed['content_gaps'], 20, 200 );
			}
			if ( $rec_title === '' && ! empty( $seo_seed['recommended_title'] ) ) {
				$rec_title = $this->bound_text( (string) $seo_seed['recommended_title'], 60 );
			}
			if ( $rec_meta === '' && ! empty( $seo_seed['meta_description'] ) ) {
				$rec_meta = $this->bound_text( (string) $seo_seed['meta_description'], 170 );
			}
			if ( $entity === '' && ! empty( $seo_seed['recipe_name'] ) ) {
				$entity = $this->topics->pick_canonical_entity(
					array( (string) $seo_seed['recipe_name'], (string) ( $seo_seed['topic'] ?? '' ), $title ),
					$title
				);
			}
		}

		// Primary must stay entity-relevant when both exist.
		if ( $entity !== '' && $primary !== '' && $this->topics->is_weaker_than_entity( $primary, $entity ) ) {
			// Keep primary as supplied SEO hint but do not let it become the entity.
		}

		return new ArticleGenerationContext(
			$title,
			$template,
			$words,
			$keywords,
			$images,
			$entity,
			$intent,
			$primary,
			$secondary,
			$entities,
			$facts,
			$content_gaps,
			$rec_title,
			$rec_meta,
			$seo_seed,
			$schema_status,
			$schema_source,
			$schema_details,
			$fact_source,
			$has_card,
			$heading_hints,
			$faq_hints,
			$image_alt_hints,
			$internal_link_hints,
			$recommendation_hints,
			$seo_title_hints
		);
	}

	/**
	 * Build context from title + HTML content (read-only).
	 *
	 * @param array<string, mixed> $brief Extra brief fields.
	 */
	public function from_content( string $title, string $content, array $brief = array() ): ArticleGenerationContext {
		$brief['title']   = $title !== '' ? $title : (string) ( $brief['title'] ?? '' );
		$brief['content'] = $content;
		return $this->from_brief( $brief );
	}

	/**
	 * Read-only load from an existing WordPress post. Never writes.
	 *
	 * @param array<string, mixed> $brief Extra brief overrides.
	 */
	public function from_post_id( int $post_id, array $brief = array() ): ?ArticleGenerationContext {
		$post_id = max( 0, $post_id );
		if ( $post_id <= 0 || ! function_exists( 'get_post' ) ) {
			return null;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		$title   = function_exists( 'get_the_title' ) ? (string) get_the_title( $post_id ) : (string) $post->post_title;
		$content = (string) $post->post_content;

		$detector = new RecipeSchemaDetector();
		$detection = $detector->detect_for_post( $post_id );

		$structured = null;
		if ( class_exists( \RecipeSeoAiPro\Modules\RecipeAI\PostRecipeSource::class ) ) {
			$meta = get_post_meta( $post_id, \RecipeSeoAiPro\Modules\RecipeAI\PostRecipeSource::META_STRUCTURED, true );
			if ( is_string( $meta ) && $meta !== '' ) {
				$decoded = json_decode( $meta, true );
				if ( is_array( $decoded ) ) {
					$structured = $decoded;
				}
			}
		}

		$brief = array_merge(
			$brief,
			array(
				'title'                   => $title !== '' ? $title : (string) ( $brief['title'] ?? '' ),
				'content'                 => $content,
				'structured_recipe'       => $structured,
				'recipe_schema_status'    => $detection->status(),
				'recipe_schema_source'    => $detection->source(),
				'has_rsaip_card'          => $detection->has_rsaip_card(),
			)
		);

		// Never pass post_id into AI-facing context seed.
		unset( $brief['post_id'] );

		return $this->from_brief( $brief );
	}

	/**
	 * Attach a future M5 SeoOptimizationProposal seed without merging DTOs.
	 * Accepts proposal->to_array()-shaped data only as informational hints.
	 *
	 * @param array<string, mixed> $proposal_array SeoOptimizationProposal::to_array() shape.
	 */
	public function with_seo_proposal_seed( ArticleGenerationContext $context, array $proposal_array ): ArticleGenerationContext {
		$seed = $this->sanitize_seo_seed( $proposal_array );
		$merged = array(
			'title'                      => $context->title() !== '' ? $context->title() : (string) ( $seed['recommended_title'] ?? '' ),
			'template'                   => $context->template(),
			'requested_word_count'       => $context->requested_word_count(),
			'supplied_keywords'          => $context->supplied_keywords() !== array()
				? $context->supplied_keywords()
				: array_values(
					array_filter(
						array_merge(
							array( (string) ( $seed['primary_focus_keyword'] ?? '' ) ),
							is_array( $seed['secondary_keywords'] ?? null ) ? $seed['secondary_keywords'] : array()
						)
					)
				),
			'image_urls'                 => $context->image_urls(),
			'canonical_recipe_entity'    => $context->canonical_recipe_entity() !== ''
				? $context->canonical_recipe_entity()
				: (string) ( $seed['recipe_name'] ?? $seed['topic'] ?? '' ),
			'search_intent_hint'         => $context->search_intent_hint() !== ''
				? $context->search_intent_hint()
				: (string) ( $seed['search_intent'] ?? '' ),
			'primary_focus_keyword_hint' => $context->primary_focus_keyword_hint() !== ''
				? $context->primary_focus_keyword_hint()
				: (string) ( $seed['primary_focus_keyword'] ?? '' ),
			'secondary_keyword_hints'    => $context->secondary_keyword_hints() !== array()
				? $context->secondary_keyword_hints()
				: ( is_array( $seed['secondary_keywords'] ?? null ) ? $seed['secondary_keywords'] : array() ),
			'entity_hints'               => $context->entity_hints() !== array()
				? $context->entity_hints()
				: ( is_array( $seed['entities'] ?? null ) ? $seed['entities'] : array() ),
			'recipe_facts'               => $context->recipe_facts(),
			'content_gap_hints'          => $context->content_gap_hints() !== array()
				? $context->content_gap_hints()
				: ( is_array( $seed['content_gaps'] ?? null ) ? $seed['content_gaps'] : array() ),
			'recommended_title_hint'     => $context->recommended_title_hint() !== ''
				? $context->recommended_title_hint()
				: (string) ( $seed['recommended_title'] ?? '' ),
			'meta_description_hint'      => $context->meta_description_hint() !== ''
				? $context->meta_description_hint()
				: (string) ( $seed['meta_description'] ?? '' ),
			'seo_seed'                   => $seed,
			'recipe_schema_status'       => $context->recipe_schema_status(),
			'recipe_schema_source'       => $context->recipe_schema_source(),
			'recipe_fact_source'         => $context->recipe_fact_source(),
			'has_rsaip_card'             => $context->has_rsaip_card(),
			'heading_hints'              => $context->heading_hints() !== array()
				? $context->heading_hints()
				: ( $seed['heading_suggestions'] ?? array() ),
			'faq_hints'                  => $context->faq_hints() !== array()
				? $context->faq_hints()
				: ( $seed['faq_suggestions'] ?? array() ),
			'image_alt_hints'            => $context->image_alt_hints() !== array()
				? $context->image_alt_hints()
				: ( $seed['image_alt_suggestions'] ?? array() ),
			'internal_link_hints'        => $context->internal_link_hints() !== array()
				? $context->internal_link_hints()
				: ( $seed['internal_link_suggestions'] ?? array() ),
			'recommendation_hints'       => $context->recommendation_hints() !== array()
				? $context->recommendation_hints()
				: ( $seed['recommendations'] ?? array() ),
			'seo_title_hints'            => $context->seo_title_hints() !== array()
				? $context->seo_title_hints()
				: ( $seed['seo_title_suggestions'] ?? array() ),
		);

		return $this->from_brief( $merged );
	}

	private function normalize_template( string $template ): string {
		$template = strtolower( trim( $template ) );
		$allowed  = array(
			ArticleGenerationContext::TEMPLATE_GENERAL,
			ArticleGenerationContext::TEMPLATE_RECIPE_MIDJOURNEY,
		);
		return in_array( $template, $allowed, true ) ? $template : ArticleGenerationContext::TEMPLATE_GENERAL;
	}

	private function normalize_word_count( int $words ): int {
		if ( $words < ArticleGenerationContext::WORD_COUNT_MIN ) {
			return ArticleGenerationContext::WORD_COUNT_MIN;
		}
		if ( $words > ArticleGenerationContext::WORD_COUNT_MAX ) {
			return ArticleGenerationContext::WORD_COUNT_MAX;
		}
		return $words;
	}

	/**
	 * @param array<string, mixed> $raw Raw facts.
	 * @return array<string, array{status: string, value: mixed}>
	 */
	private function normalize_recipe_facts( array $raw ): array {
		$out = $this->resolver->empty_facts();
		foreach ( ArticleRecipeContextResolver::FACT_KEYS as $key ) {
			if ( ! isset( $raw[ $key ] ) ) {
				continue;
			}
			$row = $raw[ $key ];
			$status = RecipeFactAvailability::UNAVAILABLE;
			$value  = $key === 'ingredients' ? array() : '';

			if ( is_array( $row ) && ( isset( $row['status'] ) || array_key_exists( 'value', $row ) ) ) {
				$st = strtolower( (string) ( $row['status'] ?? '' ) );
				if ( in_array( $st, RecipeFactAvailability::all(), true ) ) {
					$status = $st;
				}
				$value = $this->normalize_fact_value( $key, $row['value'] ?? null );
			} elseif ( is_array( $row ) && $key === 'ingredients' ) {
				$status = RecipeFactAvailability::KNOWN;
				$value  = $this->normalize_fact_value( $key, $row );
			} elseif ( is_scalar( $row ) && (string) $row !== '' ) {
				$status = RecipeFactAvailability::KNOWN;
				$value  = $this->normalize_fact_value( $key, $row );
			}

			if ( $status === RecipeFactAvailability::KNOWN ) {
				if ( $key === 'ingredients' && ( ! is_array( $value ) || $value === array() ) ) {
					$status = RecipeFactAvailability::UNAVAILABLE;
					$value  = array();
				} elseif ( $key !== 'ingredients' && ( ! is_string( $value ) || $value === '' ) ) {
					$status = RecipeFactAvailability::UNAVAILABLE;
					$value  = '';
				}
			} else {
				$value = $key === 'ingredients' ? array() : '';
			}

			$out[ $key ] = array(
				'status' => $status,
				'value'  => $value,
			);
		}
		return $out;
	}

	/**
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	private function normalize_fact_value( string $key, $value ) {
		if ( $key === 'ingredients' ) {
			if ( is_string( $value ) && trim( $value ) !== '' ) {
				$parts = preg_split( '/[\r\n]+|;\s*/u', $value ) ?: array( $value );
				$out   = array();
				foreach ( $parts as $part ) {
					$part = $this->bound_text( (string) $part, 200 );
					if ( $part !== '' ) {
						$out[] = $part;
					}
				}
				return array_values( array_unique( $out ) );
			}
			if ( ! is_array( $value ) ) {
				return array();
			}
			$out = array();
			foreach ( $value as $row ) {
				if ( is_string( $row ) || is_numeric( $row ) ) {
					$line = $this->bound_text( (string) $row, 200 );
				} elseif ( is_array( $row ) ) {
					$line = $this->bound_text(
						trim(
							trim( (string) ( $row['quantity'] ?? '' ) ) . ' '
							. trim( (string) ( $row['unit'] ?? '' ) ) . ' '
							. trim( (string) ( $row['name'] ?? $row['item'] ?? '' ) )
						),
						200
					);
				} else {
					$line = '';
				}
				if ( $line !== '' ) {
					$out[] = $line;
				}
				if ( count( $out ) >= 40 ) {
					break;
				}
			}
			return array_values( array_unique( $out ) );
		}
		if ( is_array( $value ) ) {
			return $this->bound_text( implode( ', ', array_map( 'strval', $value ) ), 500 );
		}
		if ( $value === null ) {
			return '';
		}
		return $this->bound_text( (string) $value, 500 );
	}

	/**
	 * @param array<string, array{status: string, value: mixed}> $base Base (resolved).
	 * @param array<string, array{status: string, value: mixed}> $overlay Brief overlay.
	 * @return array<string, array{status: string, value: mixed}>
	 */
	private function overlay_known_facts( array $base, array $overlay ): array {
		foreach ( ArticleRecipeContextResolver::FACT_KEYS as $key ) {
			if ( ( $overlay[ $key ]['status'] ?? '' ) === RecipeFactAvailability::KNOWN ) {
				$base[ $key ] = $overlay[ $key ];
			}
		}
		return $base;
	}

	/**
	 * @param array<string, array{status: string, value: mixed}> $facts Facts.
	 */
	private function has_any_known( array $facts ): bool {
		foreach ( $facts as $row ) {
			if ( ( $row['status'] ?? '' ) === RecipeFactAvailability::KNOWN ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Strip write-oriented keys from an M5 proposal seed.
	 *
	 * @param array<string, mixed> $seed Seed.
	 * @return array<string, mixed>
	 */
	private function sanitize_seo_seed( array $seed ): array {
		$forbidden = array(
			'post_id',
			'meta_key',
			'meta_keys',
			'owner',
			'seo_owner',
			'mutation_type',
			'proposal_ticket',
			'ticket',
			'rank_math_score',
			'seo_score',
			'score',
		);
		foreach ( $forbidden as $key ) {
			unset( $seed[ $key ] );
		}
		$allowed = array(
			'topic',
			'recipe_name',
			'search_intent',
			'primary_focus_keyword',
			'secondary_keywords',
			'entities',
			'recommended_title',
			'seo_title_suggestions',
			'meta_description',
			'heading_suggestions',
			'faq_suggestions',
			'content_gaps',
			'image_alt_suggestions',
			'internal_link_suggestions',
			'recommendations',
			'reasoning_summary',
		);
		$out = array();
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $seed ) ) {
				$out[ $key ] = $seed[ $key ];
			}
		}
		return $out;
	}

	/**
	 * @param mixed $value Raw headings.
	 * @return list<array{level: string, text: string}>
	 */
	private function normalize_heading_hints( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $row ) {
			if ( is_string( $row ) ) {
				$text = $this->bound_text( $row, 120 );
				if ( $text !== '' ) {
					$out[] = array( 'level' => 'h2', 'text' => $text );
				}
				continue;
			}
			if ( ! is_array( $row ) ) {
				continue;
			}
			$level = strtolower( trim( (string) ( $row['level'] ?? 'h2' ) ) );
			$text  = $this->bound_text( (string) ( $row['text'] ?? '' ), 120 );
			if ( ! in_array( $level, array( 'h2', 'h3' ), true ) || $text === '' ) {
				continue;
			}
			$out[] = array(
				'level' => $level,
				'text'  => $text,
			);
			if ( count( $out ) >= 20 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $value Raw FAQ.
	 * @return list<array{q: string, a: string}>
	 */
	private function normalize_faq_hints( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$q = $this->bound_text( (string) ( $row['q'] ?? $row['question'] ?? '' ), 200 );
			$a = $this->bound_text( (string) ( $row['a'] ?? $row['answer'] ?? '' ), 400 );
			if ( $q === '' ) {
				continue;
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
	 * Descriptive hints only — URLs / post IDs stripped.
	 *
	 * @param mixed $value Raw links.
	 * @return list<array{anchor: string, target_hint: string}>
	 */
	private function normalize_internal_link_hints( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( isset( $row['url'] ) || isset( $row['href'] ) || isset( $row['post_id'] ) || isset( $row['permalink'] ) ) {
				continue;
			}
			$anchor = $this->bound_text( (string) ( $row['anchor'] ?? '' ), 80 );
			$hint   = $this->bound_text( (string) ( $row['target_hint'] ?? $row['hint'] ?? '' ), 120 );
			if ( $anchor === '' && $hint === '' ) {
				continue;
			}
			if ( preg_match( '#https?://|javascript:#i', $anchor . ' ' . $hint ) ) {
				continue;
			}
			$out[] = array(
				'anchor'      => $anchor,
				'target_hint' => $hint,
			);
			if ( count( $out ) >= 12 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $value Raw.
	 * @return list<string>
	 */
	private function string_list( $value, int $max_items, int $max_len ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[,|\n]+/u', $value ) ?: array();
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $item ) {
			if ( ! is_string( $item ) && ! is_numeric( $item ) ) {
				continue;
			}
			$item = $this->bound_text( (string) $item, $max_len );
			if ( $item === '' ) {
				continue;
			}
			$out[] = $item;
			if ( count( $out ) >= $max_items ) {
				break;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param mixed $value Raw URLs.
	 * @return list<string>
	 */
	private function sanitize_image_urls( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\r\n]+/u', $value ) ?: array();
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $url ) {
			if ( ! is_string( $url ) && ! is_numeric( $url ) ) {
				continue;
			}
			$url = trim( (string) $url );
			if ( $url === '' ) {
				continue;
			}
			if ( function_exists( 'esc_url_raw' ) ) {
				$url = (string) esc_url_raw( $url );
			}
			if ( $url === '' || ! preg_match( '#^https?://#i', $url ) ) {
				continue;
			}
			if ( preg_match( '#javascript:#i', $url ) ) {
				continue;
			}
			$out[] = $url;
			if ( count( $out ) >= 8 ) {
				break;
			}
		}
		return array_values( array_unique( $out ) );
	}

	private function bound_text( string $text, int $max ): string {
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = trim( (string) $text );
		if ( $text === '' ) {
			return '';
		}
		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
		if ( $len > $max ) {
			return function_exists( 'mb_substr' )
				? (string) mb_substr( $text, 0, $max, 'UTF-8' )
				: substr( $text, 0, $max );
		}
		return $text;
	}
}
