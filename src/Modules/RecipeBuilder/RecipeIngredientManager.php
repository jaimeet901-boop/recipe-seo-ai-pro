<?php
declare(strict_types=1);

/**
 * Recipe Builder ingredients + unit conversion (Phase 5.1).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\RecipeBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeIngredientManager
 */
final class RecipeIngredientManager {

	private RecipeBuilderRepository $repository;

	/** @var array<string, float> Unit → grams (approx for conversion baseline). */
	private const TO_GRAMS = array(
		'g'     => 1.0,
		'gram'  => 1.0,
		'grams' => 1.0,
		'kg'    => 1000.0,
		'oz'    => 28.3495,
		'lb'    => 453.592,
		'ml'    => 1.0,
		'l'     => 1000.0,
		'cup'   => 240.0,
		'cups'  => 240.0,
		'tbsp'  => 15.0,
		'tsp'   => 5.0,
	);

	public function __construct( RecipeBuilderRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * @param list<array<string, mixed>> $ingredients Ingredients.
	 * @param array<string, int>         $section_map client_key => section_id.
	 * @return list<array<string, mixed>>
	 */
	public function sync( int $recipe_id, array $ingredients, array $section_map = array() ): array {
		$this->repository->delete_ingredients_for_recipe( $recipe_id );
		$now   = \RSAIP_DB::now_gmt_sql();
		$saved = array();
		$order = 0;
		foreach ( $ingredients as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
			if ( $name === '' ) {
				continue;
			}
			$section_id = absint( $row['section_id'] ?? 0 );
			$client_sec = (string) ( $row['section_key'] ?? '' );
			if ( $section_id <= 0 && $client_sec !== '' && isset( $section_map[ $client_sec ] ) ) {
				$section_id = $section_map[ $client_sec ];
			}
			$id = $this->repository->insert_ingredient(
				array(
					'recipe_id'  => $recipe_id,
					'section_id' => $section_id,
					'name'       => mb_substr( $name, 0, 255 ),
					'quantity'   => $this->to_float( $row['quantity'] ?? 0 ),
					'unit'       => sanitize_text_field( (string) ( $row['unit'] ?? '' ) ),
					'note'       => sanitize_text_field( (string) ( $row['note'] ?? '' ) ),
					'sort_order' => isset( $row['sort_order'] ) ? absint( $row['sort_order'] ) : $order,
					'created_at' => $now,
					'updated_at' => $now,
				)
			);
			if ( $id > 0 ) {
				$saved[] = array(
					'id'         => $id,
					'section_id' => $section_id,
					'name'       => $name,
					'quantity'   => $this->to_float( $row['quantity'] ?? 0 ),
					'unit'       => (string) ( $row['unit'] ?? '' ),
					'note'       => (string) ( $row['note'] ?? '' ),
					'sort_order' => $order,
				);
			}
			++$order;
		}
		return $saved;
	}

	/**
	 * Scale ingredient quantities by servings ratio.
	 *
	 * @param list<array<string, mixed>> $ingredients Ingredients.
	 * @return list<array<string, mixed>>
	 */
	public function scale_for_servings( array $ingredients, float $from_servings, float $to_servings ): array {
		if ( $from_servings <= 0 || $to_servings <= 0 ) {
			return $ingredients;
		}
		$ratio = $to_servings / $from_servings;
		$out   = array();
		foreach ( $ingredients as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$row['quantity'] = round( $this->to_float( $row['quantity'] ?? 0 ) * $ratio, 3 );
			$out[]           = $row;
		}
		return $out;
	}

	/**
	 * Convert a quantity between units when possible.
	 *
	 * @return array{quantity: float, unit: string}|null
	 */
	public function convert_unit( float $quantity, string $from_unit, string $to_unit ): ?array {
		$from = $this->normalize_unit( $from_unit );
		$to   = $this->normalize_unit( $to_unit );
		if ( ! isset( self::TO_GRAMS[ $from ], self::TO_GRAMS[ $to ] ) ) {
			return null;
		}
		$base = $quantity * self::TO_GRAMS[ $from ];
		$qty  = $base / self::TO_GRAMS[ $to ];
		return array(
			'quantity' => round( $qty, 3 ),
			'unit'     => $to,
		);
	}

	/**
	 * Convert all convertible ingredients to a unit system preference.
	 *
	 * @param list<array<string, mixed>> $ingredients Ingredients.
	 * @return list<array<string, mixed>>
	 */
	public function convert_system( array $ingredients, string $system ): array {
		$system = $system === 'imperial' ? 'imperial' : 'metric';
		$out    = array();
		foreach ( $ingredients as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$unit = $this->normalize_unit( (string) ( $row['unit'] ?? '' ) );
			$qty  = $this->to_float( $row['quantity'] ?? 0 );
			if ( $system === 'metric' && in_array( $unit, array( 'oz', 'lb', 'cup', 'cups', 'tbsp', 'tsp' ), true ) ) {
				$target = in_array( $unit, array( 'cup', 'cups', 'tbsp', 'tsp' ), true ) ? 'ml' : 'g';
				$conv   = $this->convert_unit( $qty, $unit, $target );
				if ( $conv ) {
					$row['quantity'] = $conv['quantity'];
					$row['unit']     = $conv['unit'];
				}
			}
			if ( $system === 'imperial' && in_array( $unit, array( 'g', 'kg', 'ml', 'l' ), true ) ) {
				$target = in_array( $unit, array( 'ml', 'l' ), true ) ? 'cup' : 'oz';
				$conv   = $this->convert_unit( $qty, $unit, $target );
				if ( $conv ) {
					$row['quantity'] = $conv['quantity'];
					$row['unit']     = $conv['unit'];
				}
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_for( int $recipe_id ): array {
		return $this->repository->list_ingredients( $recipe_id );
	}

	private function normalize_unit( string $unit ): string {
		$unit = strtolower( trim( $unit ) );
		$map  = array(
			'tablespoon'  => 'tbsp',
			'tablespoons' => 'tbsp',
			'teaspoon'    => 'tsp',
			'teaspoons'   => 'tsp',
			'ounce'       => 'oz',
			'ounces'      => 'oz',
			'pound'       => 'lb',
			'pounds'      => 'lb',
		);
		return $map[ $unit ] ?? $unit;
	}

	/**
	 * @param mixed $value Raw.
	 */
	private function to_float( $value ): float {
		if ( is_numeric( $value ) ) {
			return (float) $value;
		}
		return 0.0;
	}
}
