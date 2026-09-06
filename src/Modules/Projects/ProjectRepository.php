<?php
declare(strict_types=1);

/**
 * SQL access for SEO Projects tables (Phase 3.3).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Projects;

use RecipeSeoAiPro\Database\Repositories\AbstractRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProjectRepository
 */
final class ProjectRepository extends AbstractRepository {

	/**
	 * @inheritDoc
	 */
	public function table(): string {
		return \RSAIP_DB::table_projects();
	}

	public function table_members(): string {
		return \RSAIP_DB::table_project_members();
	}

	public function table_statistics(): string {
		return \RSAIP_DB::table_project_statistics();
	}

	public function table_assets(): string {
		return \RSAIP_DB::table_project_assets();
	}

	public function table_tasks(): string {
		return \RSAIP_DB::table_project_tasks();
	}

	public function table_activity(): string {
		return \RSAIP_DB::table_project_activity();
	}

	/**
	 * @param array<string, mixed> $row Row data.
	 */
	public function insert_project( array $row ): int {
		$ok = $this->db()->insert( $this->table(), $row );
		return false === $ok ? 0 : (int) $this->db()->insert_id;
	}

	/**
	 * @param array<string, mixed> $row Row data.
	 */
	public function update_project( int $id, array $row ): bool {
		$result = $this->db()->update( $this->table(), $row, array( 'id' => $id ), null, array( '%d' ) );
		return false !== $result;
	}

	public function delete_project( int $id ): bool {
		$project_id = $id;
		$this->db()->delete( $this->table_members(), array( 'project_id' => $project_id ), array( '%d' ) );
		$this->db()->delete( $this->table_statistics(), array( 'project_id' => $project_id ), array( '%d' ) );
		$this->db()->delete( $this->table_assets(), array( 'project_id' => $project_id ), array( '%d' ) );
		$this->db()->delete( $this->table_tasks(), array( 'project_id' => $project_id ), array( '%d' ) );
		$this->db()->delete( $this->table_activity(), array( 'project_id' => $project_id ), array( '%d' ) );
		$result = $this->db()->delete( $this->table(), array( 'id' => $project_id ), array( '%d' ) );
		return false !== $result && $result > 0;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_project( int $id ): ?array {
		$table = $this->table();
		$row   = $this->db()->get_row(
			$this->db()->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param array<string, mixed> $args status, q, page, per_page, sort, order.
	 * @return array{items: list<array<string, mixed>>, total: int}
	 */
	public function search_projects( array $args ): array {
		$table    = $this->table();
		$stats    = $this->table_statistics();
		$q        = isset( $args['q'] ) ? trim( (string) $args['q'] ) : '';
		$status   = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
		$sort     = isset( $args['sort'] ) ? (string) $args['sort'] : 'updated_at';
		$order    = isset( $args['order'] ) && strtoupper( (string) $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$allowed = array( 'updated_at', 'created_at', 'name', 'status', 'niche' );
		if ( ! in_array( $sort, $allowed, true ) ) {
			$sort = 'updated_at';
		}

		$where  = array( '1=1' );
		$params = array();
		if ( $status !== '' && $status !== 'all' ) {
			$where[]  = 'p.status = %s';
			$params[] = $status;
		}
		if ( $q !== '' ) {
			$like     = '%' . $this->db()->esc_like( $q ) . '%';
			$where[]  = '(p.name LIKE %s OR p.niche LIKE %s OR p.description LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = ( $page - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$table} p WHERE {$where_sql}";
		$total     = $params
			? (int) $this->db()->get_var( $this->db()->prepare( $count_sql, $params ) )
			: (int) $this->db()->get_var( $count_sql );

		$list_sql = "SELECT p.*,
				COALESCE(s.briefs_count, 0) AS briefs_count,
				COALESCE(s.keywords_count, 0) AS keywords_count,
				COALESCE(s.articles_count, 0) AS articles_count,
				COALESCE(s.completion_pct, 0) AS completion_pct
			FROM {$table} p
			LEFT JOIN {$stats} s ON s.project_id = p.id
			WHERE {$where_sql}
			ORDER BY p.{$sort} {$order}
			LIMIT %d OFFSET %d";

		$list_params   = $params;
		$list_params[] = $per_page;
		$list_params[] = $offset;
		$rows          = $this->db()->get_results( $this->db()->prepare( $list_sql, $list_params ), ARRAY_A );

		return array(
			'items' => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * @param array<string, mixed> $row Member row.
	 */
	public function upsert_member( array $row ): bool {
		$table = $this->table_members();
		$this->db()->query(
			$this->db()->prepare(
				"INSERT INTO {$table} (project_id, user_id, role, created_at) VALUES (%d, %d, %s, %s)
				ON DUPLICATE KEY UPDATE role = VALUES(role)",
				(int) $row['project_id'],
				(int) $row['user_id'],
				(string) $row['role'],
				(string) $row['created_at']
			)
		);
		return (int) $this->db()->rows_affected >= 0;
	}

	public function remove_member( int $project_id, int $user_id ): bool {
		$result = $this->db()->delete(
			$this->table_members(),
			array(
				'project_id' => $project_id,
				'user_id'    => $user_id,
			),
			array( '%d', '%d' )
		);
		return false !== $result;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_members( int $project_id ): array {
		$table = $this->table_members();
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT * FROM {$table} WHERE project_id = %d ORDER BY created_at ASC",
				$project_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array<string, mixed> $row Stats row.
	 */
	public function upsert_statistics( array $row ): bool {
		$table = $this->table_statistics();
		$this->db()->query(
			$this->db()->prepare(
				"INSERT INTO {$table} (project_id, briefs_count, keywords_count, articles_count, completion_pct, meta, updated_at)
				VALUES (%d, %d, %d, %d, %d, %s, %s)
				ON DUPLICATE KEY UPDATE
					briefs_count = VALUES(briefs_count),
					keywords_count = VALUES(keywords_count),
					articles_count = VALUES(articles_count),
					completion_pct = VALUES(completion_pct),
					meta = VALUES(meta),
					updated_at = VALUES(updated_at)",
				(int) $row['project_id'],
				(int) $row['briefs_count'],
				(int) $row['keywords_count'],
				(int) $row['articles_count'],
				(int) $row['completion_pct'],
				(string) ( $row['meta'] ?? '' ),
				(string) $row['updated_at']
			)
		);
		return true;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_statistics( int $project_id ): ?array {
		$table = $this->table_statistics();
		$row   = $this->db()->get_row(
			$this->db()->prepare( "SELECT * FROM {$table} WHERE project_id = %d LIMIT 1", $project_id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	public function count_assets( int $project_id, string $asset_type ): int {
		$table = $this->table_assets();
		return (int) $this->db()->get_var(
			$this->db()->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE project_id = %d AND asset_type = %s",
				$project_id,
				$asset_type
			)
		);
	}

	/**
	 * @param array<string, mixed> $row Asset row.
	 */
	public function attach_asset( array $row ): int {
		$table = $this->table_assets();
		$this->db()->query(
			$this->db()->prepare(
				"INSERT INTO {$table} (project_id, asset_type, asset_id, title, meta, created_at)
				VALUES (%d, %s, %d, %s, %s, %s)
				ON DUPLICATE KEY UPDATE title = VALUES(title), meta = VALUES(meta)",
				(int) $row['project_id'],
				(string) $row['asset_type'],
				(int) $row['asset_id'],
				(string) $row['title'],
				(string) ( $row['meta'] ?? '' ),
				(string) $row['created_at']
			)
		);
		return (int) $this->db()->insert_id;
	}

	public function detach_asset( int $project_id, string $asset_type, int $asset_id ): bool {
		$result = $this->db()->delete(
			$this->table_assets(),
			array(
				'project_id' => $project_id,
				'asset_type' => $asset_type,
				'asset_id'   => $asset_id,
			),
			array( '%d', '%s', '%d' )
		);
		return false !== $result;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_assets( int $project_id, string $asset_type = '', int $limit = 50 ): array {
		$table = $this->table_assets();
		$limit = max( 1, min( 200, $limit ) );
		if ( $asset_type !== '' ) {
			$rows = $this->db()->get_results(
				$this->db()->prepare(
					"SELECT * FROM {$table} WHERE project_id = %d AND asset_type = %s ORDER BY created_at DESC LIMIT %d",
					$project_id,
					$asset_type,
					$limit
				),
				ARRAY_A
			);
		} else {
			$rows = $this->db()->get_results(
				$this->db()->prepare(
					"SELECT * FROM {$table} WHERE project_id = %d ORDER BY created_at DESC LIMIT %d",
					$project_id,
					$limit
				),
				ARRAY_A
			);
		}
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array<string, mixed> $row Task row.
	 */
	public function insert_task( array $row ): int {
		$ok = $this->db()->insert( $this->table_tasks(), $row );
		return false === $ok ? 0 : (int) $this->db()->insert_id;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function upcoming_tasks( int $project_id, int $limit = 5 ): array {
		$table = $this->table_tasks();
		$limit = max( 1, min( 50, $limit ) );
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT * FROM {$table}
				WHERE project_id = %d AND status IN ('open', 'in_progress')
				ORDER BY (due_at IS NULL) ASC, due_at ASC, created_at ASC
				LIMIT %d",
				$project_id,
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array<string, mixed> $row Activity row.
	 */
	public function insert_activity( array $row ): int {
		$ok = $this->db()->insert( $this->table_activity(), $row );
		return false === $ok ? 0 : (int) $this->db()->insert_id;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function recent_activity( int $project_id, int $limit = 10 ): array {
		$table = $this->table_activity();
		$limit = max( 1, min( 50, $limit ) );
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT * FROM {$table} WHERE project_id = %d ORDER BY created_at DESC LIMIT %d",
				$project_id,
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}
}
