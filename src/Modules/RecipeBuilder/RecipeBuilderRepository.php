<?php
declare(strict_types=1);

/**
 * SQL for Recipe Builder 2.0 tables (Phase 5.1).
 *
 * Isolated from legacy recipe card HTML storage.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\RecipeBuilder;

use RecipeSeoAiPro\Database\Repositories\AbstractRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeBuilderRepository
 */
final class RecipeBuilderRepository extends AbstractRepository {

	/**
	 * @inheritDoc
	 */
	public function table(): string {
		return \RSAIP_DB::table_rb_recipes();
	}

	public function table_sections(): string {
		return \RSAIP_DB::table_rb_sections();
	}

	public function table_ingredients(): string {
		return \RSAIP_DB::table_rb_ingredients();
	}

	public function table_steps(): string {
		return \RSAIP_DB::table_rb_steps();
	}

	/**
	 * @param array<string, mixed> $row Row.
	 */
	public function insert_recipe( array $row ): int {
		$ok = $this->db()->insert( $this->table(), $row );
		return false === $ok ? 0 : (int) $this->db()->insert_id;
	}

	/**
	 * @param array<string, mixed> $row Row.
	 */
	public function update_recipe( int $id, array $row ): bool {
		$result = $this->db()->update( $this->table(), $row, array( 'id' => $id ), null, array( '%d' ) );
		return false !== $result;
	}

	public function delete_recipe( int $id ): bool {
		$this->db()->delete( $this->table_ingredients(), array( 'recipe_id' => $id ), array( '%d' ) );
		$this->db()->delete( $this->table_steps(), array( 'recipe_id' => $id ), array( '%d' ) );
		$this->db()->delete( $this->table_sections(), array( 'recipe_id' => $id ), array( '%d' ) );
		$result = $this->db()->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
		return false !== $result && $result > 0;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_recipe( int $id ): ?array {
		$table = $this->table();
		$row   = $this->db()->get_row(
			$this->db()->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_recipes( int $limit = 50 ): array {
		$table = $this->table();
		$limit = max( 1, min( 100, $limit ) );
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT id, post_id, title, servings, prep_time, cook_time, total_time, status, updated_at
				FROM {$table} ORDER BY updated_at DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array<string, mixed> $row Row.
	 */
	public function insert_section( array $row ): int {
		$ok = $this->db()->insert( $this->table_sections(), $row );
		return false === $ok ? 0 : (int) $this->db()->insert_id;
	}

	/**
	 * @param array<string, mixed> $row Row.
	 */
	public function update_section( int $id, array $row ): bool {
		$result = $this->db()->update( $this->table_sections(), $row, array( 'id' => $id ), null, array( '%d' ) );
		return false !== $result;
	}

	public function delete_section( int $id ): bool {
		$result = $this->db()->delete( $this->table_sections(), array( 'id' => $id ), array( '%d' ) );
		return false !== $result && $result > 0;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_sections( int $recipe_id ): array {
		$table = $this->table_sections();
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT * FROM {$table} WHERE recipe_id = %d ORDER BY sort_order ASC, id ASC",
				$recipe_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	public function delete_sections_for_recipe( int $recipe_id ): void {
		$this->db()->delete( $this->table_sections(), array( 'recipe_id' => $recipe_id ), array( '%d' ) );
	}

	/**
	 * @param array<string, mixed> $row Row.
	 */
	public function insert_ingredient( array $row ): int {
		$ok = $this->db()->insert( $this->table_ingredients(), $row );
		return false === $ok ? 0 : (int) $this->db()->insert_id;
	}

	/**
	 * @param array<string, mixed> $row Row.
	 */
	public function update_ingredient( int $id, array $row ): bool {
		$result = $this->db()->update( $this->table_ingredients(), $row, array( 'id' => $id ), null, array( '%d' ) );
		return false !== $result;
	}

	public function delete_ingredient( int $id ): bool {
		$result = $this->db()->delete( $this->table_ingredients(), array( 'id' => $id ), array( '%d' ) );
		return false !== $result && $result > 0;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_ingredients( int $recipe_id ): array {
		$table = $this->table_ingredients();
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT * FROM {$table} WHERE recipe_id = %d ORDER BY sort_order ASC, id ASC",
				$recipe_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	public function delete_ingredients_for_recipe( int $recipe_id ): void {
		$this->db()->delete( $this->table_ingredients(), array( 'recipe_id' => $recipe_id ), array( '%d' ) );
	}

	/**
	 * @param array<string, mixed> $row Row.
	 */
	public function insert_step( array $row ): int {
		$ok = $this->db()->insert( $this->table_steps(), $row );
		return false === $ok ? 0 : (int) $this->db()->insert_id;
	}

	/**
	 * @param array<string, mixed> $row Row.
	 */
	public function update_step( int $id, array $row ): bool {
		$result = $this->db()->update( $this->table_steps(), $row, array( 'id' => $id ), null, array( '%d' ) );
		return false !== $result;
	}

	public function delete_step( int $id ): bool {
		$result = $this->db()->delete( $this->table_steps(), array( 'id' => $id ), array( '%d' ) );
		return false !== $result && $result > 0;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_steps( int $recipe_id ): array {
		$table = $this->table_steps();
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT * FROM {$table} WHERE recipe_id = %d ORDER BY sort_order ASC, id ASC",
				$recipe_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	public function delete_steps_for_recipe( int $recipe_id ): void {
		$this->db()->delete( $this->table_steps(), array( 'recipe_id' => $recipe_id ), array( '%d' ) );
	}
}
