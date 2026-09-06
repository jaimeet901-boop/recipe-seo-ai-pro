<?php
declare(strict_types=1);

/**
 * Read-only recipe entity + fact resolution for Article Generator B.
 *
 * Reuses RecipeSchemaDetector nodes and PostRecipeSource card parsing.
 * Never writes. Never invents missing facts (no default servings/times).
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\ArticleGeneration;

use RecipeSeoAiPro\Modules\Ai\TitleGeneration\TitleTopicSelector;
use RecipeSeoAiPro\Modules\RecipeAI\PostRecipeSource;
use RecipeSeoAiPro\Modules\Schema\RecipeSchemaDetector;
use RecipeSeoAiPro\Modules\Schema\RecipeSchemaStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ArticleRecipeContextResolver
 */
final class ArticleRecipeContextResolver {

	public const FACT_KEYS = array(
		'ingredients',
		'prep_time',
		'cook_time',
		'total_time',
		'servings',
		'nutrition',
		'dietary',
		'allergens',
	);

	private RecipeSchemaDetector $detector;
	private TitleTopicSelector $topics;

	public function __construct( ?RecipeSchemaDetector $detector = null, ?TitleTopicSelector $topics = null ) {
		$this->detector = $detector instanceof RecipeSchemaDetector ? $detector : new RecipeSchemaDetector();
		$this->topics   = $topics instanceof TitleTopicSelector ? $topics : new TitleTopicSelector();
	}

	/**
	 * Resolve recipe entity + facts from HTML/content (no WP writes).
	 *
	 * @param array{
	 *   rank_math_active?: bool,
	 *   yoast_active?: bool,
	 *   external_recipe_plugin?: bool,
	 *   external_recipe_plugin_id?: string,
	 *   focus_keyword?: string,
	 *   structured_recipe?: array<string, mixed>|null
	 * } $options
	 * @return array{
	 *   canonical_recipe_entity: string,
	 *   recipe_schema_status: string,
	 *   recipe_schema_source: string,
	 *   recipe_schema_details: string,
	 *   has_rsaip_card: bool,
	 *   recipe_fact_source: string,
	 *   recipe_facts: array<string, array{status: string, value: mixed}>
	 * }
	 */
	public function resolve_from_content( string $content, string $title = '', array $options = array() ): array {
		$title   = $this->bound_text( $title, 300 );
		$content = (string) $content;

		$detection = $this->detector->detect_from_content(
			$content,
			array(
				'title'                     => $title,
				'rank_math_active'          => ! empty( $options['rank_math_active'] ),
				'yoast_active'              => ! empty( $options['yoast_active'] ),
				'external_recipe_plugin'    => ! empty( $options['external_recipe_plugin'] ),
				'external_recipe_plugin_id' => (string) ( $options['external_recipe_plugin_id'] ?? '' ),
			)
		);

		$facts      = $this->empty_facts();
		$fact_source = 'none';
		$schema_name = '';
		$card_name   = '';
		$source_name = '';

		$nodes = $this->detector->extract_recipe_nodes( $content );
		$schema_node = $this->pick_best_schema_node( $nodes );
		if ( is_array( $schema_node ) ) {
			$schema_name = $this->bound_text( (string) ( $schema_node['name'] ?? '' ), 200 );
			$schema_facts = $this->facts_from_schema_node( $schema_node );
			$facts       = $this->merge_facts( $facts, $schema_facts );
			if ( $this->has_any_known( $schema_facts ) ) {
				$fact_source = 'schema';
			}
		}

		// Structured meta / card — never trust PostRecipeSource default servings=4.
		$structured = isset( $options['structured_recipe'] ) && is_array( $options['structured_recipe'] )
			? $options['structured_recipe']
			: null;
		if ( is_array( $structured ) ) {
			$source_name = $this->bound_text( (string) ( $structured['title'] ?? '' ), 200 );
			$struct_facts = $this->facts_from_structured( $structured, true );
			$before       = $facts;
			$facts        = $this->merge_facts( $facts, $struct_facts );
			if ( $fact_source === 'none' && $this->has_any_known( $struct_facts ) ) {
				$fact_source = 'structured';
			} elseif ( $this->facts_gained( $before, $facts ) && $fact_source === 'none' ) {
				$fact_source = 'structured';
			}
		}

		if ( class_exists( PostRecipeSource::class ) && $content !== '' ) {
			$source = new PostRecipeSource();
			$parsed = $source->parse_content( $content, $title );
			$mode   = (string) ( $parsed['parse_mode'] ?? '' );
			$source_name = $source_name !== '' ? $source_name : $this->bound_text( (string) ( $parsed['title'] ?? '' ), 200 );
			if ( in_array( $mode, array( 'card', 'meta' ), true ) || ! empty( $parsed['has_recipe_card'] ) ) {
				$card_name = $source_name !== '' ? $source_name : $card_name;
			}

			// Ingredients from authoritative card/schema/meta/lists only — never fabricate.
			$trust_lists = in_array( $mode, array( 'card', 'schema', 'meta', 'lists' ), true );
			if ( $trust_lists ) {
				$card_facts = $this->facts_from_parsed_card( $parsed, $content, $mode );
				$before     = $facts;
				$facts      = $this->merge_facts( $facts, $card_facts );
				if ( $fact_source === 'none' && $this->has_any_known( $card_facts ) ) {
					$fact_source = ( $mode === 'schema' ) ? 'schema' : ( in_array( $mode, array( 'card', 'meta' ), true ) ? 'card' : 'card' );
				} elseif ( $fact_source === 'none' && $this->facts_gained( $before, $facts ) ) {
					$fact_source = 'card';
				}
			}
		}

		// External/unknown systems: do not guess internal fields; keep only schema-extracted facts.
		if ( $detection->status() === RecipeSchemaStatus::EXTERNAL_OR_UNKNOWN && $fact_source === 'none' ) {
			$facts = $this->empty_facts();
		}

		$entity = $this->resolve_canonical_entity(
			$title,
			$schema_name,
			$detection->status(),
			$card_name,
			$source_name,
			(string) ( $options['focus_keyword'] ?? '' )
		);

		return array(
			'canonical_recipe_entity' => $entity,
			'recipe_schema_status'    => $detection->status(),
			'recipe_schema_source'    => $detection->source(),
			'recipe_schema_details'   => $detection->details(),
			'has_rsaip_card'          => $detection->has_rsaip_card() || $this->detector->content_has_rsaip_card( $content ),
			'recipe_fact_source'      => $fact_source,
			'recipe_facts'            => $facts,
		);
	}

	/**
	 * @return array<string, array{status: string, value: mixed}>
	 */
	public function empty_facts(): array {
		$out = array();
		foreach ( self::FACT_KEYS as $key ) {
			$out[ $key ] = array(
				'status' => RecipeFactAvailability::UNAVAILABLE,
				'value'  => $key === 'ingredients' ? array() : '',
			);
		}
		return $out;
	}

	/**
	 * Entity priority aligned with M5:
	 * 1) Valid Recipe Schema name
	 * 2) Card / structured title
	 * 3) Parsed source title
	 * 4) Title anchor / title
	 * Generic keywords never override a stronger entity.
	 */
	public function resolve_canonical_entity(
		string $title,
		string $schema_name,
		string $schema_status,
		string $card_name,
		string $source_name,
		string $focus = ''
	): string {
		$candidates = array();
		if ( $schema_status === RecipeSchemaStatus::VALID_RECIPE && $schema_name !== '' ) {
			$candidates[] = $schema_name;
		} elseif ( $schema_name !== '' && in_array( $schema_status, array( RecipeSchemaStatus::INCOMPLETE, RecipeSchemaStatus::MULTIPLE ), true ) ) {
			$candidates[] = $schema_name;
		}
		if ( $card_name !== '' ) {
			$candidates[] = $card_name;
		}
		if ( $source_name !== '' ) {
			$candidates[] = $source_name;
		}
		$candidates[] = $title;

		$entity = $this->topics->pick_canonical_entity( $candidates, $title );
		if ( $focus !== '' && $entity !== '' && $this->topics->is_weaker_than_entity( $focus, $entity ) ) {
			return $entity;
		}
		if ( $entity === '' && $focus !== '' && ! $this->topics->is_generic_relative_to( $focus, $title ) ) {
			return $this->topics->normalize( $focus );
		}
		return $entity;
	}

	/**
	 * @param list<array<string, mixed>> $nodes Schema nodes.
	 * @return array<string, mixed>|null
	 */
	private function pick_best_schema_node( array $nodes ): ?array {
		if ( $nodes === array() ) {
			return null;
		}
		$best     = null;
		$best_score = -1;
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$score = 0;
			if ( $this->has_nonempty( $node['recipeIngredient'] ?? null ) ) {
				$score += 2;
			}
			if ( $this->has_nonempty( $node['recipeInstructions'] ?? null ) ) {
				$score += 2;
			}
			foreach ( array( 'prepTime', 'cookTime', 'totalTime', 'recipeYield', 'nutrition' ) as $k ) {
				if ( $this->has_nonempty( $node[ $k ] ?? null ) ) {
					$score++;
				}
			}
			if ( $score > $best_score ) {
				$best_score = $score;
				$best       = $node;
			}
		}
		return $best;
	}

	/**
	 * @param array<string, mixed> $node Recipe node.
	 * @return array<string, array{status: string, value: mixed}>
	 */
	private function facts_from_schema_node( array $node ): array {
		$facts = $this->empty_facts();

		$ingredients = $this->normalize_ingredient_list( $node['recipeIngredient'] ?? null );
		if ( $ingredients !== array() ) {
			$facts['ingredients'] = array(
				'status' => RecipeFactAvailability::KNOWN,
				'value'  => $ingredients,
			);
		}

		foreach ( array(
			'prep_time'  => 'prepTime',
			'cook_time'  => 'cookTime',
			'total_time' => 'totalTime',
		) as $fact_key => $schema_key ) {
			$formatted = $this->format_duration( $node[ $schema_key ] ?? null );
			if ( $formatted !== '' ) {
				$facts[ $fact_key ] = array(
					'status' => RecipeFactAvailability::KNOWN,
					'value'  => $formatted,
				);
			}
		}

		$servings = $this->format_servings( $node['recipeYield'] ?? null );
		if ( $servings !== '' ) {
			$facts['servings'] = array(
				'status' => RecipeFactAvailability::KNOWN,
				'value'  => $servings,
			);
		}

		$nutrition = $this->format_nutrition( $node['nutrition'] ?? null );
		if ( $nutrition !== '' ) {
			$facts['nutrition'] = array(
				'status' => RecipeFactAvailability::KNOWN,
				'value'  => $nutrition,
			);
		}

		$dietary = $this->format_dietary( $node['suitableForDiet'] ?? ( $node['diet'] ?? null ) );
		if ( $dietary !== '' ) {
			$facts['dietary'] = array(
				'status' => RecipeFactAvailability::KNOWN,
				'value'  => $dietary,
			);
		}

		$allergens = $this->format_allergens( $node );
		if ( $allergens !== '' ) {
			$facts['allergens'] = array(
				'status' => RecipeFactAvailability::KNOWN,
				'value'  => $allergens,
			);
		}

		return $facts;
	}

	/**
	 * @param array<string, mixed> $parsed Structured or parsed recipe.
	 * @return array<string, array{status: string, value: mixed}>
	 */
	private function facts_from_structured( array $parsed, bool $trust_times_servings ): array {
		$facts = $this->empty_facts();

		$ingredients = array();
		$raw_ings    = isset( $parsed['ingredients'] ) && is_array( $parsed['ingredients'] ) ? $parsed['ingredients'] : array();
		foreach ( $raw_ings as $row ) {
			$line = $this->ingredient_row_to_line( $row );
			if ( $line !== '' ) {
				$ingredients[] = $line;
			}
		}
		$ingredients = array_values( array_unique( $ingredients ) );
		if ( $ingredients !== array() ) {
			$facts['ingredients'] = array(
				'status' => RecipeFactAvailability::KNOWN,
				'value'  => array_slice( $ingredients, 0, 40 ),
			);
		}

		if ( $trust_times_servings ) {
			foreach ( array( 'prep_time', 'cook_time', 'total_time' ) as $key ) {
				$val = $this->format_duration( $parsed[ $key ] ?? null );
				if ( $val !== '' && $val !== '0' && $val !== '0 minutes' ) {
					$facts[ $key ] = array(
						'status' => RecipeFactAvailability::KNOWN,
						'value'  => $val,
					);
				}
			}
			$serv = $this->format_servings( $parsed['servings'] ?? ( $parsed['recipeYield'] ?? null ) );
			if ( $serv !== '' ) {
				$facts['servings'] = array(
					'status' => RecipeFactAvailability::KNOWN,
					'value'  => $serv,
				);
			}
		}

		$nutrition = $this->format_nutrition( $parsed['nutrition'] ?? null );
		if ( $nutrition !== '' ) {
			$facts['nutrition'] = array(
				'status' => RecipeFactAvailability::KNOWN,
				'value'  => $nutrition,
			);
		}
		$dietary = $this->format_dietary( $parsed['dietary'] ?? null );
		if ( $dietary !== '' ) {
			$facts['dietary'] = array(
				'status' => RecipeFactAvailability::KNOWN,
				'value'  => $dietary,
			);
		}
		$allergens = '';
		if ( isset( $parsed['allergens'] ) ) {
			$allergens = is_array( $parsed['allergens'] )
				? $this->bound_text( implode( ', ', array_map( 'strval', $parsed['allergens'] ) ), 300 )
				: $this->bound_text( (string) $parsed['allergens'], 300 );
		}
		if ( $allergens !== '' ) {
			$facts['allergens'] = array(
				'status' => RecipeFactAvailability::KNOWN,
				'value'  => $allergens,
			);
		}

		return $facts;
	}

	/**
	 * Card/list parse: trust ingredients; times/servings only when explicitly present (not defaults).
	 *
	 * @param array<string, mixed> $parsed Parsed recipe.
	 * @return array<string, array{status: string, value: mixed}>
	 */
	private function facts_from_parsed_card( array $parsed, string $content, string $mode ): array {
		// Never trust PostRecipeSource default servings (4.0) — only when yield is explicit in content/meta.
		$trust_servings = ( $mode === 'meta' )
			|| (bool) preg_match( '/itemprop=["\']recipeYield["\']/i', $content )
			|| (bool) preg_match( '/"recipeYield"\s*:/i', $content );
		$trust_times = in_array( $mode, array( 'schema', 'meta', 'card' ), true )
			&& (
				(bool) preg_match( '/itemprop=["\'](?:prep|cook|total)Time["\']/i', $content )
				|| (bool) preg_match( '/"(?:prep|cook|total)Time"\s*:/i', $content )
				|| (bool) preg_match( '/\b(?:prep|cook|total)\s*(?:time)?\s*[:\-]\s*\d+/i', wp_strip_all_tags( $content ) )
			);

		$facts = $this->facts_from_structured( $parsed, false );

		if ( $trust_times ) {
			foreach ( array( 'prep_time', 'cook_time', 'total_time' ) as $key ) {
				$raw = $parsed[ $key ] ?? null;
				// PostRecipeSource uses 0 for missing — treat 0 as unavailable.
				if ( is_numeric( $raw ) && (float) $raw <= 0 ) {
					continue;
				}
				$val = $this->format_duration( $raw );
				if ( $val !== '' ) {
					$facts[ $key ] = array(
						'status' => RecipeFactAvailability::KNOWN,
						'value'  => $val,
					);
				}
			}
		}

		if ( $trust_servings ) {
			$raw = $parsed['servings'] ?? null;
			// Default heuristic 4.0 without explicit yield marker is discarded by trust_servings gate.
			$serv = $this->format_servings( $raw );
			if ( $serv !== '' ) {
				$facts['servings'] = array(
					'status' => RecipeFactAvailability::KNOWN,
					'value'  => $serv,
				);
			}
		}

		return $facts;
	}

	/**
	 * Known facts in $overlay fill only unavailable slots in $base (schema wins over card).
	 *
	 * @param array<string, array{status: string, value: mixed}> $base Base.
	 * @param array<string, array{status: string, value: mixed}> $overlay Overlay.
	 * @return array<string, array{status: string, value: mixed}>
	 */
	private function merge_facts( array $base, array $overlay ): array {
		foreach ( self::FACT_KEYS as $key ) {
			$base_status = (string) ( $base[ $key ]['status'] ?? RecipeFactAvailability::UNAVAILABLE );
			if ( $base_status === RecipeFactAvailability::KNOWN ) {
				continue;
			}
			$over_status = (string) ( $overlay[ $key ]['status'] ?? RecipeFactAvailability::UNAVAILABLE );
			if ( $over_status !== RecipeFactAvailability::KNOWN ) {
				continue;
			}
			$base[ $key ] = $overlay[ $key ];
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
	 * @param array<string, array{status: string, value: mixed}> $before Before.
	 * @param array<string, array{status: string, value: mixed}> $after After.
	 */
	private function facts_gained( array $before, array $after ): bool {
		foreach ( self::FACT_KEYS as $key ) {
			$b = (string) ( $before[ $key ]['status'] ?? '' );
			$a = (string) ( $after[ $key ]['status'] ?? '' );
			if ( $b !== RecipeFactAvailability::KNOWN && $a === RecipeFactAvailability::KNOWN ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param mixed $raw Ingredients field.
	 * @return list<string>
	 */
	private function normalize_ingredient_list( $raw ): array {
		if ( is_string( $raw ) && trim( $raw ) !== '' ) {
			return array( $this->bound_text( $raw, 200 ) );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $row ) {
			$line = $this->ingredient_row_to_line( $row );
			if ( $line !== '' ) {
				$out[] = $line;
			}
			if ( count( $out ) >= 40 ) {
				break;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param mixed $row Ingredient row.
	 */
	private function ingredient_row_to_line( $row ): string {
		if ( is_string( $row ) || is_numeric( $row ) ) {
			return $this->bound_text( (string) $row, 200 );
		}
		if ( ! is_array( $row ) ) {
			return '';
		}
		if ( isset( $row['name'] ) || isset( $row['item'] ) ) {
			$line = trim(
				trim( (string) ( $row['quantity'] ?? '' ) ) . ' '
				. trim( (string) ( $row['unit'] ?? '' ) ) . ' '
				. trim( (string) ( $row['name'] ?? $row['item'] ?? '' ) )
			);
			$note = trim( (string) ( $row['note'] ?? '' ) );
			if ( $note !== '' ) {
				$line .= ' — ' . $note;
			}
			return $this->bound_text( $line, 200 );
		}
		return '';
	}

	/**
	 * @param mixed $raw Duration.
	 */
	private function format_duration( $raw ): string {
		if ( $raw === null || $raw === '' || $raw === false ) {
			return '';
		}
		if ( is_array( $raw ) ) {
			// Schema.org QuantitativeValue-ish.
			if ( isset( $raw['value'] ) ) {
				return $this->format_duration( $raw['value'] );
			}
			return '';
		}
		if ( is_numeric( $raw ) ) {
			$n = (float) $raw;
			if ( $n <= 0 ) {
				return '';
			}
			$mins = (int) round( $n );
			return $mins === 1 ? '1 minute' : $mins . ' minutes';
		}
		$text = trim( (string) $raw );
		if ( $text === '' ) {
			return '';
		}
		if ( preg_match( '/^P(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)$/i', $text, $m ) ) {
			$hours = isset( $m[1] ) && $m[1] !== '' ? (int) $m[1] : 0;
			$mins  = isset( $m[2] ) && $m[2] !== '' ? (int) $m[2] : 0;
			$secs  = isset( $m[3] ) && $m[3] !== '' ? (int) $m[3] : 0;
			$parts = array();
			if ( $hours > 0 ) {
				$parts[] = $hours === 1 ? '1 hour' : $hours . ' hours';
			}
			if ( $mins > 0 ) {
				$parts[] = $mins === 1 ? '1 minute' : $mins . ' minutes';
			}
			if ( $parts === array() && $secs > 0 ) {
				$parts[] = $secs === 1 ? '1 second' : $secs . ' seconds';
			}
			return implode( ' ', $parts );
		}
		return $this->bound_text( $text, 80 );
	}

	/**
	 * @param mixed $raw Yield.
	 */
	private function format_servings( $raw ): string {
		if ( $raw === null || $raw === '' || $raw === false ) {
			return '';
		}
		if ( is_array( $raw ) ) {
			$parts = array();
			foreach ( $raw as $item ) {
				$s = $this->format_servings( $item );
				if ( $s !== '' ) {
					$parts[] = $s;
				}
			}
			return $parts !== array() ? $this->bound_text( implode( ', ', $parts ), 80 ) : '';
		}
		$text = trim( (string) $raw );
		if ( $text === '' || $text === '0' ) {
			return '';
		}
		return $this->bound_text( $text, 80 );
	}

	/**
	 * @param mixed $raw Nutrition.
	 */
	private function format_nutrition( $raw ): string {
		if ( $raw === null || $raw === '' || $raw === false ) {
			return '';
		}
		if ( is_string( $raw ) || is_numeric( $raw ) ) {
			return $this->bound_text( (string) $raw, 300 );
		}
		if ( ! is_array( $raw ) ) {
			return '';
		}
		$parts = array();
		$map   = array(
			'calories'      => 'calories',
			'calorie'       => 'calories',
			'fatContent'    => 'fat',
			'carbohydrateContent' => 'carbs',
			'proteinContent'=> 'protein',
			'sodiumContent' => 'sodium',
			'sugarContent'  => 'sugar',
			'fiberContent'  => 'fiber',
			'servingSize'   => 'serving size',
		);
		foreach ( $map as $key => $label ) {
			if ( ! isset( $raw[ $key ] ) ) {
				continue;
			}
			$val = is_array( $raw[ $key ] ) ? (string) ( $raw[ $key ]['value'] ?? '' ) : (string) $raw[ $key ];
			$val = trim( $val );
			if ( $val !== '' ) {
				$parts[] = $label . ': ' . $val;
			}
		}
		if ( $parts === array() && isset( $raw['name'] ) ) {
			return $this->bound_text( (string) $raw['name'], 300 );
		}
		return $this->bound_text( implode( '; ', $parts ), 300 );
	}

	/**
	 * @param mixed $raw Dietary.
	 */
	private function format_dietary( $raw ): string {
		if ( $raw === null || $raw === '' ) {
			return '';
		}
		if ( is_string( $raw ) || is_numeric( $raw ) ) {
			$text = preg_replace( '#https?://schema\.org/#i', '', (string) $raw );
			return $this->bound_text( (string) $text, 200 );
		}
		if ( ! is_array( $raw ) ) {
			return '';
		}
		$parts = array();
		foreach ( $raw as $item ) {
			$s = $this->format_dietary( $item );
			if ( $s !== '' ) {
				$parts[] = $s;
			}
		}
		return $this->bound_text( implode( ', ', $parts ), 200 );
	}

	/**
	 * @param array<string, mixed> $node Schema node.
	 */
	private function format_allergens( array $node ): string {
		foreach ( array( 'allergens', 'allergy', 'allergen', 'containsAllergen' ) as $key ) {
			if ( ! isset( $node[ $key ] ) ) {
				continue;
			}
			$raw = $node[ $key ];
			if ( is_array( $raw ) ) {
				$parts = array();
				foreach ( $raw as $item ) {
					if ( is_string( $item ) || is_numeric( $item ) ) {
						$parts[] = (string) $item;
					} elseif ( is_array( $item ) ) {
						$parts[] = (string) ( $item['name'] ?? $item['value'] ?? '' );
					}
				}
				$text = $this->bound_text( implode( ', ', array_filter( array_map( 'trim', $parts ) ) ), 300 );
			} else {
				$text = $this->bound_text( (string) $raw, 300 );
			}
			if ( $text !== '' ) {
				return $text;
			}
		}
		return '';
	}

	/**
	 * @param mixed $value Field.
	 */
	private function has_nonempty( $value ): bool {
		if ( is_string( $value ) ) {
			return trim( $value ) !== '';
		}
		if ( is_array( $value ) ) {
			return $value !== array();
		}
		if ( is_numeric( $value ) ) {
			return true;
		}
		return false;
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
