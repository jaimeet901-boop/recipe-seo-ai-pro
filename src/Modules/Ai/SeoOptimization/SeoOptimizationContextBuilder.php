<?php
declare(strict_types=1);

/**
 * Builds bounded, server-owned SEO analysis context.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\SeoOptimization;

use RecipeSeoAiPro\Modules\Schema\RecipeSchemaDetector;
use RecipeSeoAiPro\Modules\Schema\RecipeSchemaStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SeoOptimizationContextBuilder
 */
final class SeoOptimizationContextBuilder {

	public const EXCERPT_MAX_CHARS = 4000;
	public const RECIPE_SUMMARY_MAX_CHARS = 1200;
	public const TITLE_MAX_CHARS = 300;

	private RecipeSchemaDetector $schema_detector;

	public function __construct( ?RecipeSchemaDetector $schema_detector = null ) {
		$this->schema_detector = $schema_detector instanceof RecipeSchemaDetector
			? $schema_detector
			: new RecipeSchemaDetector();
	}

	/**
	 * Build context from a live WordPress post (read-only).
	 *
	 * @return SeoOptimizationContext|null Null when post missing.
	 */
	public function from_post_id( int $post_id ): ?SeoOptimizationContext {
		$post_id = max( 0, $post_id );
		if ( $post_id <= 0 || ! function_exists( 'get_post' ) ) {
			return null;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		$title = function_exists( 'get_the_title' ) ? (string) get_the_title( $post_id ) : (string) $post->post_title;
		$content_raw = (string) $post->post_content;
		$content_text = function_exists( 'wp_strip_all_tags' )
			? (string) wp_strip_all_tags( $content_raw )
			: strip_tags( $content_raw );

		$focus = '';
		if ( function_exists( 'rsaip_get_focus_keywords' ) ) {
			$keywords = rsaip_get_focus_keywords( $post_id );
			if ( is_array( $keywords ) && isset( $keywords[0] ) ) {
				$focus = (string) $keywords[0];
			}
		}

		$categories = $this->term_names( $post_id, 'category' );
		$tags       = $this->term_names( $post_id, 'post_tag' );
		$schema     = $this->schema_detector->detect_for_post( $post_id );
		$recipe     = $this->extract_recipe_snapshot( $post_id, $title, $content_raw, $schema->recipe_name(), $schema->status() );
		$owner      = $this->resolve_owner_label();

		$recipe['has_card'] = $recipe['has_card'] || $schema->has_rsaip_card();

		return $this->from_server_array(
			array(
				'post_id'                      => $post_id,
				'current_title'                => $title,
				'content_excerpt'              => $content_text,
				'existing_focus_keyword'       => $focus,
				'categories'                   => $categories,
				'tags'                         => $tags,
				'detected_recipe_name'         => $recipe['name'],
				'recipe_summary'               => $recipe['summary'],
				'recipe_ingredients'           => $recipe['ingredients'],
				'known_recipe_facts'           => $recipe['facts'],
				'seo_owner_label'              => $owner,
				'has_recipe_card'              => $recipe['has_card'],
				'recipe_schema_status'         => $schema->status(),
				'recipe_schema_source'         => $schema->source(),
				'recipe_schema_authoritative'  => $schema->authoritative(),
				'recipe_schema_details'        => $schema->details(),
				'recipe_schema_repair_allowed' => $schema->repair_allowed(),
			)
		);
	}

	/**
	 * @return list<string>
	 */
	private function term_names( int $post_id, string $taxonomy ): array {
		if ( ! function_exists( 'get_the_terms' ) ) {
			return array();
		}
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! is_array( $terms ) ) {
			return array();
		}
		$out = array();
		foreach ( $terms as $term ) {
			if ( is_object( $term ) && ! empty( $term->name ) ) {
				$out[] = (string) $term->name;
			}
		}
		return $out;
	}

	/**
	 * Recipe name/summary/ingredients/facts — schema existence still from RecipeSchemaDetector.
	 *
	 * Entity priority (Milestone 5E):
	 * 1) Valid Recipe Schema name
	 * 2) Recipe card / structured PostRecipeSource title
	 * 3) TitleTopicSelector anchor from post title
	 * 4) Post title
	 * 5) Existing focus keyword only when not generic vs a stronger entity
	 *
	 * @return array{name: string, summary: string, has_card: bool, ingredients: list<string>, facts: array<string, string>}
	 */
	private function extract_recipe_snapshot( int $post_id, string $title, string $content_raw, string $schema_name, string $schema_status ): array {
		$summary     = '';
		$has_card    = $this->schema_detector->content_has_rsaip_card( $content_raw );
		$ingredients = array();
		$facts       = array();
		$card_name   = '';
		$source_name = '';

		if ( class_exists( \RecipeSeoAiPro\Modules\RecipeAI\PostRecipeSource::class ) ) {
			$source = new \RecipeSeoAiPro\Modules\RecipeAI\PostRecipeSource();
			$parsed = null;
			if ( method_exists( $source, 'load' ) ) {
				$loaded = $source->load( $post_id );
				if ( is_array( $loaded ) ) {
					$parsed = $loaded;
				}
			}
			if ( ! is_array( $parsed ) && method_exists( $source, 'parse_content' ) ) {
				$parsed = $source->parse_content( $content_raw, $title );
			}
			if ( is_array( $parsed ) ) {
				$source_name = trim( (string) ( $parsed['title'] ?? '' ) );
				$mode        = (string) ( $parsed['parse_mode'] ?? '' );
				$has_card    = $has_card || ! empty( $parsed['has_recipe_card'] ) || in_array( $mode, array( 'card', 'schema', 'lists', 'meta' ), true );
				if ( $mode === 'card' || ! empty( $parsed['has_recipe_card'] ) ) {
					$card_name = $source_name;
				}

				$parts = array();
				$desc  = trim( (string) ( $parsed['description'] ?? '' ) );
				if ( $desc !== '' ) {
					$parts[] = $desc;
				}
				$raw_ings = isset( $parsed['ingredients'] ) && is_array( $parsed['ingredients'] ) ? $parsed['ingredients'] : array();
				foreach ( array_slice( $raw_ings, 0, 20 ) as $row ) {
					if ( is_string( $row ) ) {
						$n = trim( $row );
					} elseif ( is_array( $row ) ) {
						$n = trim( (string) ( $row['name'] ?? $row['item'] ?? '' ) );
					} else {
						$n = '';
					}
					if ( $n !== '' ) {
						$ingredients[] = $n;
					}
				}
				$ingredients = array_values( array_unique( $ingredients ) );
				if ( $ingredients !== array() ) {
					$parts[] = 'Ingredients: ' . implode( ', ', array_slice( $ingredients, 0, 12 ) );
				}
				foreach ( array( 'prep_time', 'cook_time', 'total_time', 'servings', 'cuisine' ) as $key ) {
					if ( ! empty( $parsed[ $key ] ) ) {
						$val           = trim( (string) $parsed[ $key ] );
						$facts[ $key ] = $val;
						$parts[]       = $key . ': ' . $val;
					}
				}
				if ( ! empty( $parsed['dietary'] ) ) {
					$dietary = is_array( $parsed['dietary'] )
						? implode( ', ', array_map( 'strval', $parsed['dietary'] ) )
						: (string) $parsed['dietary'];
					$dietary = trim( $dietary );
					if ( $dietary !== '' ) {
						$facts['dietary'] = $dietary;
						$parts[]          = 'dietary: ' . $dietary;
					}
				}
				// Bounded instruction hints (no full dump).
				$steps = isset( $parsed['steps'] ) && is_array( $parsed['steps'] ) ? $parsed['steps'] : array();
				if ( $steps !== array() ) {
					$step_bits = array();
					foreach ( array_slice( $steps, 0, 4 ) as $step ) {
						if ( is_string( $step ) ) {
							$step_bits[] = trim( $step );
						} elseif ( is_array( $step ) ) {
							$step_bits[] = trim( (string) ( $step['text'] ?? $step['instruction'] ?? '' ) );
						}
					}
					$step_bits = array_values( array_filter( $step_bits ) );
					if ( $step_bits !== array() ) {
						$joined = implode( ' ', $step_bits );
						if ( function_exists( 'mb_substr' ) ) {
							$joined = (string) mb_substr( $joined, 0, 400, 'UTF-8' );
						} else {
							$joined = substr( $joined, 0, 400 );
						}
						$parts[] = 'Instructions summary: ' . $joined;
					}
				}
				$summary = implode( ' | ', $parts );
			}
		}

		$focus = '';
		if ( function_exists( 'rsaip_get_focus_keywords' ) ) {
			$kw = rsaip_get_focus_keywords( $post_id );
			if ( is_array( $kw ) && isset( $kw[0] ) ) {
				$focus = (string) $kw[0];
			}
		}

		$name = $this->resolve_canonical_entity(
			$title,
			$schema_name,
			$schema_status,
			$card_name,
			$source_name,
			$focus
		);

		return array(
			'name'        => $name,
			'summary'     => $summary,
			'has_card'    => $has_card,
			'ingredients' => $ingredients,
			'facts'       => $facts,
		);
	}

	/**
	 * Server-owned recipe entity selection (analysis only).
	 */
	private function resolve_canonical_entity(
		string $title,
		string $schema_name,
		string $schema_status,
		string $card_name,
		string $source_name,
		string $focus
	): string {
		$selector   = class_exists( \RecipeSeoAiPro\Modules\Ai\TitleGeneration\TitleTopicSelector::class )
			? new \RecipeSeoAiPro\Modules\Ai\TitleGeneration\TitleTopicSelector()
			: null;
		$candidates = array();

		if ( $schema_status === RecipeSchemaStatus::VALID_RECIPE && $schema_name !== '' ) {
			$candidates[] = $schema_name;
		}
		if ( $card_name !== '' ) {
			$candidates[] = $card_name;
		}
		if ( $source_name !== '' ) {
			$candidates[] = $source_name;
		}
		$candidates[] = $title;

		if ( $selector ) {
			$entity = $selector->pick_canonical_entity( $candidates, $title );
			// Focus keyword only if it covers / matches the entity — never replace with "Salad".
			if ( $focus !== '' && $entity !== '' && $selector->covers_specific_entity( $focus, $entity ) ) {
				// Keep entity; focus is informational in context.
				return $entity;
			}
			if ( $entity === '' && $focus !== '' && ! $selector->is_generic_relative_to( $focus, $title ) ) {
				return $selector->normalize( $focus );
			}
			return $entity;
		}

		foreach ( array( $schema_name, $card_name, $source_name, $title, $focus ) as $c ) {
			$c = trim( $c );
			if ( $c !== '' ) {
				return $c;
			}
		}
		return '';
	}

	private function resolve_owner_label(): string {
		if ( class_exists( \RecipeSeoAiPro\Modules\PostMutation\WpPostMutationEnvironment::class )
			&& class_exists( \RecipeSeoAiPro\Modules\PostMutation\SeoOwnershipResolver::class ) ) {
			$env      = new \RecipeSeoAiPro\Modules\PostMutation\WpPostMutationEnvironment();
			$resolver = new \RecipeSeoAiPro\Modules\PostMutation\SeoOwnershipResolver( $env );
			return $resolver->resolve_server_owner();
		}
		return 'rsaip';
	}

	/**
	 * @param array<string, mixed> $input Server context fields.
	 */
	public function from_server_array( array $input ): SeoOptimizationContext {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		if ( $post_id < 0 ) {
			$post_id = 0;
		}

		$title = $this->bound_text( (string) ( $input['current_title'] ?? '' ), self::TITLE_MAX_CHARS );
		$excerpt = $this->bound_text( (string) ( $input['content_excerpt'] ?? $input['content'] ?? '' ), self::EXCERPT_MAX_CHARS );
		$focus = $this->bound_text( (string) ( $input['existing_focus_keyword'] ?? '' ), 120 );
		$recipe_name = $this->bound_text( (string) ( $input['detected_recipe_name'] ?? '' ), 200 );
		$recipe_summary = $this->bound_text( (string) ( $input['recipe_summary'] ?? '' ), self::RECIPE_SUMMARY_MAX_CHARS );
		$ingredients = $this->string_list( $input['recipe_ingredients'] ?? array(), 20, 120 );
		$facts_in    = isset( $input['known_recipe_facts'] ) && is_array( $input['known_recipe_facts'] )
			? $input['known_recipe_facts']
			: array();
		$facts = array();
		foreach ( array( 'prep_time', 'cook_time', 'total_time', 'servings', 'cuisine', 'dietary' ) as $fkey ) {
			if ( ! empty( $facts_in[ $fkey ] ) && is_scalar( $facts_in[ $fkey ] ) ) {
				$facts[ $fkey ] = $this->bound_text( (string) $facts_in[ $fkey ], 80 );
			}
		}

		// When tests/server arrays omit a cleaned entity, refine from title.
		if ( $recipe_name === '' && class_exists( \RecipeSeoAiPro\Modules\Ai\TitleGeneration\TitleTopicSelector::class ) ) {
			$selector   = new \RecipeSeoAiPro\Modules\Ai\TitleGeneration\TitleTopicSelector();
			$recipe_name = $selector->pick_canonical_entity( array( $title ), $title );
		} elseif ( $recipe_name !== '' && class_exists( \RecipeSeoAiPro\Modules\Ai\TitleGeneration\TitleTopicSelector::class ) ) {
			$selector    = new \RecipeSeoAiPro\Modules\Ai\TitleGeneration\TitleTopicSelector();
			$refined     = $selector->pick_canonical_entity( array( $recipe_name, $title ), $title );
			$recipe_name = $refined !== '' ? $refined : $recipe_name;
		}

		$owner = strtolower( trim( (string) ( $input['seo_owner_label'] ?? 'rsaip' ) ) );
		if ( ! in_array( $owner, array( 'rankmath', 'yoast', 'rsaip' ), true ) ) {
			$owner = 'rsaip';
		}

		$schema = $this->resolve_schema_fields( $input );

		return new SeoOptimizationContext(
			$post_id,
			$title,
			$excerpt,
			$focus,
			$this->string_list( $input['categories'] ?? array(), 20, 80 ),
			$this->string_list( $input['tags'] ?? array(), 20, 80 ),
			$recipe_name,
			$recipe_summary,
			$owner,
			! empty( $input['has_recipe_card'] ) || $schema['has_card'],
			$schema['status'],
			$schema['source'],
			$schema['authoritative'],
			$schema['details'],
			$schema['repair_allowed'],
			$ingredients,
			$facts
		);
	}

	/**
	 * Prefer explicit schema fields; optionally detect from provided content.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array{status: string, source: string, authoritative: bool, details: string, repair_allowed: bool, has_card: bool}
	 */
	private function resolve_schema_fields( array $input ): array {
		if ( isset( $input['recipe_schema_status'] ) && is_string( $input['recipe_schema_status'] )
			&& RecipeSchemaStatus::is_known( $input['recipe_schema_status'] ) ) {
			return array(
				'status'         => $input['recipe_schema_status'],
				'source'         => (string) ( $input['recipe_schema_source'] ?? 'unknown' ),
				'authoritative'  => array_key_exists( 'recipe_schema_authoritative', $input )
					? ! empty( $input['recipe_schema_authoritative'] )
					: true,
				'details'        => (string) ( $input['recipe_schema_details'] ?? '' ),
				'repair_allowed' => ! empty( $input['recipe_schema_repair_allowed'] ),
				'has_card'       => ! empty( $input['has_recipe_card'] ),
			);
		}

		$content = (string) ( $input['content_raw'] ?? $input['post_content'] ?? '' );
		if ( $content !== '' ) {
			$result = $this->schema_detector->detect_from_content(
				$content,
				array(
					'title'            => (string) ( $input['current_title'] ?? '' ),
					'rank_math_active' => ( (string) ( $input['seo_owner_label'] ?? '' ) ) === 'rankmath'
						|| ! empty( $input['rank_math_active'] ),
					'yoast_active'     => ( (string) ( $input['seo_owner_label'] ?? '' ) ) === 'yoast'
						|| ! empty( $input['yoast_active'] ),
					'external_recipe_plugin' => ! empty( $input['external_recipe_plugin'] ),
					'external_recipe_plugin_id' => (string) ( $input['external_recipe_plugin_id'] ?? '' ),
				)
			);
			return array(
				'status'         => $result->status(),
				'source'         => $result->source(),
				'authoritative'  => $result->authoritative(),
				'details'        => $result->details(),
				'repair_allowed' => $result->repair_allowed(),
				'has_card'       => $result->has_rsaip_card(),
			);
		}

		// Legacy bool only — treat true as valid; false without SEO plugin signals as missing.
		if ( ! empty( $input['has_recipe_schema'] ) ) {
			return array(
				'status'         => RecipeSchemaStatus::VALID_RECIPE,
				'source'         => 'legacy',
				'authoritative'  => false,
				'details'        => 'Legacy has_recipe_schema=true without structured detector input.',
				'repair_allowed' => false,
				'has_card'       => ! empty( $input['has_recipe_card'] ),
			);
		}

		$owner = (string) ( $input['seo_owner_label'] ?? '' );
		if ( $owner === 'rankmath' || $owner === 'yoast' || ! empty( $input['rank_math_active'] ) || ! empty( $input['yoast_active'] ) ) {
			return array(
				'status'         => RecipeSchemaStatus::EXTERNAL_OR_UNKNOWN,
				'source'         => $owner === 'yoast' ? 'yoast' : 'rank_math',
				'authoritative'  => false,
				'details'        => 'SEO plugin ownership present; schema may be rendered outside post_content.',
				'repair_allowed' => false,
				'has_card'       => ! empty( $input['has_recipe_card'] ),
			);
		}

		return array(
			'status'         => RecipeSchemaStatus::MISSING,
			'source'         => 'none',
			'authoritative'  => true,
			'details'        => 'No Recipe Schema signals provided.',
			'repair_allowed' => true,
			'has_card'       => ! empty( $input['has_recipe_card'] ),
		);
	}

	private function bound_text( string $text, int $max_chars ): string {
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = trim( (string) $text );
		if ( $text === '' ) {
			return '';
		}
		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
		if ( $len <= $max_chars ) {
			return $text;
		}
		$cut = function_exists( 'mb_substr' ) ? (string) mb_substr( $text, 0, $max_chars, 'UTF-8' ) : substr( $text, 0, $max_chars );
		$cut = preg_replace( '/\s+\S*$/u', '', $cut );
		return rtrim( (string) $cut );
	}

	/**
	 * @param mixed $value Raw list.
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
}
