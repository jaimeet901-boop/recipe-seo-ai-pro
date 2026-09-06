<?php
declare(strict_types=1);

/**
 * SQL for Content Optimizer tables (Phase 4.1).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentOptimizer;

use RecipeSeoAiPro\Database\Repositories\AbstractRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ContentOptimizerRepository
 */
final class ContentOptimizerRepository extends AbstractRepository {

	/**
	 * @inheritDoc
	 */
	public function table(): string {
		return \RSAIP_DB::table_content_optimizations();
	}

	public function table_versions(): string {
		return \RSAIP_DB::table_content_versions();
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
	public function list_runs( int $post_id, int $limit = 20 ): array {
		$table = $this->table();
		$limit = max( 1, min( 100, $limit ) );
		if ( $post_id > 0 ) {
			$rows = $this->db()->get_results(
				$this->db()->prepare(
					"SELECT * FROM {$table} WHERE post_id = %d ORDER BY updated_at DESC LIMIT %d",
					$post_id,
					$limit
				),
				ARRAY_A
			);
		} else {
			$rows = $this->db()->get_results(
				$this->db()->prepare(
					"SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT %d",
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
	 * @return list<array<string, mixed>>
	 */
	public function list_versions( int $optimization_id ): array {
		$table = $this->table_versions();
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT * FROM {$table} WHERE optimization_id = %d ORDER BY version_no ASC, id ASC",
				$optimization_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_post_versions( int $post_id, int $limit = 50 ): array {
		$table = $this->table_versions();
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d ORDER BY created_at DESC, id DESC LIMIT %d",
				$post_id,
				max( 1, min( 100, $limit ) )
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
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
	 * Latest version with a given label for an optimization or post.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find_latest_labeled( int $post_id, string $label, int $optimization_id = 0 ): ?array {
		$table = $this->table_versions();
		$label = sanitize_key( $label );
		if ( $optimization_id > 0 ) {
			$row = $this->db()->get_row(
				$this->db()->prepare(
					"SELECT * FROM {$table} WHERE optimization_id = %d AND label = %s ORDER BY id DESC LIMIT 1",
					$optimization_id,
					$label
				),
				ARRAY_A
			);
		} else {
			$row = $this->db()->get_row(
				$this->db()->prepare(
					"SELECT * FROM {$table} WHERE post_id = %d AND label = %s ORDER BY id DESC LIMIT 1",
					$post_id,
					$label
				),
				ARRAY_A
			);
		}
		return is_array( $row ) ? $row : null;
	}

	public function next_version_no( int $optimization_id ): int {
		$table = $this->table_versions();
		$max   = (int) $this->db()->get_var(
			$this->db()->prepare(
				"SELECT MAX(version_no) FROM {$table} WHERE optimization_id = %d",
				$optimization_id
			)
		);
		return $max + 1;
	}
}
