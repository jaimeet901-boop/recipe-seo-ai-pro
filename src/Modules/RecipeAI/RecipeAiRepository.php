<?php
declare(strict_types=1);

/**
 * SQL for AI Recipe Assistant (Phase 5.2).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\RecipeAI;

use RecipeSeoAiPro\Database\Repositories\AbstractRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeAiRepository
 */
final class RecipeAiRepository extends AbstractRepository {

	/**
	 * @inheritDoc
	 */
	public function table(): string {
		return \RSAIP_DB::table_recipe_ai_runs();
	}

	public function table_versions(): string {
		return \RSAIP_DB::table_recipe_ai_versions();
	}

	/**
	 * @param array<string, mixed> $row Row.
	 */
	public function insert_run( array $row ): int {
		$ok = $this->db()->insert( $this->table(), $row );
		return false === $ok ? 0 : (int) $this->db()->insert_id;
	}

	/**
	 * @param array<string, mixed> $row Row.
	 */
	public function update_run( int $id, array $row ): bool {
		$result = $this->db()->update( $this->table(), $row, array( 'id' => $id ), null, array( '%d' ) );
		return false !== $result;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_run( int $id ): ?array {
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
	public function list_runs( int $rb_recipe_id = 0, int $limit = 30 ): array {
		$table = $this->table();
		$limit = max( 1, min( 100, $limit ) );
		if ( $rb_recipe_id > 0 ) {
			$rows = $this->db()->get_results(
				$this->db()->prepare(
					"SELECT id, rb_recipe_id, action_type, mode, status, summary, created_at, updated_at
					FROM {$table} WHERE rb_recipe_id = %d ORDER BY updated_at DESC LIMIT %d",
					$rb_recipe_id,
					$limit
				),
				ARRAY_A
			);
		} else {
			$rows = $this->db()->get_results(
				$this->db()->prepare(
					"SELECT id, rb_recipe_id, action_type, mode, status, summary, created_at, updated_at
					FROM {$table} ORDER BY updated_at DESC LIMIT %d",
					$limit
				),
				ARRAY_A
			);
		}
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array<string, mixed> $row Version row.
	 */
	public function insert_version( array $row ): int {
		$ok = $this->db()->insert( $this->table_versions(), $row );
		return false === $ok ? 0 : (int) $this->db()->insert_id;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_version( int $id ): ?array {
		$table = $this->table_versions();
		$row   = $this->db()->get_row(
			$this->db()->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_versions_for_run( int $run_id ): array {
		$table = $this->table_versions();
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT * FROM {$table} WHERE run_id = %d ORDER BY version_no ASC, id ASC",
				$run_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_versions_for_recipe( int $rb_recipe_id, int $limit = 50 ): array {
		$table = $this->table_versions();
		$limit = max( 1, min( 100, $limit ) );
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT id, run_id, rb_recipe_id, label, version_no, created_at
				FROM {$table} WHERE rb_recipe_id = %d ORDER BY created_at DESC, id DESC LIMIT %d",
				$rb_recipe_id,
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_latest_labeled( int $rb_recipe_id, string $label, int $run_id = 0 ): ?array {
		$table = $this->table_versions();
		$label = sanitize_key( $label );
		if ( $run_id > 0 ) {
			$row = $this->db()->get_row(
				$this->db()->prepare(
					"SELECT * FROM {$table} WHERE rb_recipe_id = %d AND label = %s AND run_id = %d
					ORDER BY id DESC LIMIT 1",
					$rb_recipe_id,
					$label,
					$run_id
				),
				ARRAY_A
			);
		} else {
			$row = $this->db()->get_row(
				$this->db()->prepare(
					"SELECT * FROM {$table} WHERE rb_recipe_id = %d AND label = %s
					ORDER BY id DESC LIMIT 1",
					$rb_recipe_id,
					$label
				),
				ARRAY_A
			);
		}
		return is_array( $row ) ? $row : null;
	}

	public function next_version_no( int $rb_recipe_id ): int {
		$table = $this->table_versions();
		$max   = $this->db()->get_var(
			$this->db()->prepare(
				"SELECT MAX(version_no) FROM {$table} WHERE rb_recipe_id = %d",
				$rb_recipe_id
			)
		);
		return (int) $max + 1;
	}
}
