<?php
declare(strict_types=1);

/**
 * SQL for Keyword Workspace tables (Phase 3.4).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Keywords;

use RecipeSeoAiPro\Database\Repositories\AbstractRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KeywordRepository
 */
final class KeywordRepository extends AbstractRepository {

	/**
	 * @inheritDoc
	 */
	public function table(): string {
		return \RSAIP_DB::table_keywords();
	}

	public function table_clusters(): string {
		return \RSAIP_DB::table_keyword_clusters();
	}

	public function table_notes(): string {
		return \RSAIP_DB::table_keyword_notes();
	}

	public function table_history(): string {
		return \RSAIP_DB::table_keyword_history();
	}

	/**
	 * @param array<string, mixed> $row Row.
	 */
	public function insert( array $row ): int {
		$ok = $this->db()->insert( $this->table(), $row );
		return false === $ok ? 0 : (int) $this->db()->insert_id;
	}

	/**
	 * @param array<string, mixed> $row Row.
	 */
	public function update( int $id, array $row ): bool {
		$result = $this->db()->update( $this->table(), $row, array( 'id' => $id ), null, array( '%d' ) );
		return false !== $result;
	}

	public function delete( int $id ): bool {
		$this->db()->delete( $this->table_notes(), array( 'keyword_id' => $id ), array( '%d' ) );
		$this->db()->delete( $this->table_history(), array( 'keyword_id' => $id ), array( '%d' ) );
		$result = $this->db()->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
		return false !== $result && $result > 0;
	}

	/**
	 * @param list<int> $ids IDs.
	 */
	public function delete_many( array $ids ): int {
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids ) {
			return 0;
		}
		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( $this->delete( $id ) ) {
				++$deleted;
			}
		}
		return $deleted;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		$kw = $this->table();
		$cl = $this->table_clusters();
		$pr = \RSAIP_DB::table_projects();
		$row = $this->db()->get_row(
			$this->db()->prepare(
				"SELECT k.*, c.name AS cluster_name, p.name AS project_name
				FROM {$kw} k
				LEFT JOIN {$cl} c ON c.id = k.cluster_id
				LEFT JOIN {$pr} p ON p.id = k.project_id
				WHERE k.id = %d LIMIT 1",
				$id
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param array<string, mixed> $args Filters.
	 * @return array{items: list<array<string, mixed>>, total: int}
	 */
	public function search( array $args ): array {
		$kw       = $this->table();
		$cl       = $this->table_clusters();
		$q        = isset( $args['q'] ) ? trim( (string) $args['q'] ) : '';
		$status   = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
		$intent   = isset( $args['intent'] ) ? sanitize_key( (string) $args['intent'] ) : '';
		$project  = isset( $args['project_id'] ) ? (int) $args['project_id'] : 0;
		$cluster  = isset( $args['cluster_id'] ) ? (int) $args['cluster_id'] : 0;
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 25 ) ) );
		$sort     = isset( $args['sort'] ) ? (string) $args['sort'] : 'updated_at';
		$order    = isset( $args['order'] ) && strtoupper( (string) $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$allowed = array( 'updated_at', 'created_at', 'primary_keyword', 'priority', 'difficulty', 'status', 'roadmap_order' );
		if ( ! in_array( $sort, $allowed, true ) ) {
			$sort = 'updated_at';
		}

		$where  = array( '1=1' );
		$params = array();
		if ( $status !== '' && $status !== 'all' ) {
			$where[]  = 'k.status = %s';
			$params[] = $status;
		}
		if ( $intent !== '' && $intent !== 'all' ) {
			$where[]  = 'k.intent = %s';
			$params[] = $intent;
		}
		if ( $project > 0 ) {
			$where[]  = 'k.project_id = %d';
			$params[] = $project;
		}
		if ( $cluster > 0 ) {
			$where[]  = 'k.cluster_id = %d';
			$params[] = $cluster;
		}
		if ( $q !== '' ) {
			$like     = '%' . $this->db()->esc_like( $q ) . '%';
			$where[]  = '(k.primary_keyword LIKE %s OR k.target_url LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = ( $page - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$kw} k WHERE {$where_sql}";
		$total     = $params
			? (int) $this->db()->get_var( $this->db()->prepare( $count_sql, $params ) )
			: (int) $this->db()->get_var( $count_sql );

		$list_sql = "SELECT k.*, c.name AS cluster_name
			FROM {$kw} k
			LEFT JOIN {$cl} c ON c.id = k.cluster_id
			WHERE {$where_sql}
			ORDER BY k.{$sort} {$order}
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
	 * @param list<int>            $ids  IDs.
	 * @param array<string, mixed> $row  Columns to set.
	 */
	public function bulk_update( array $ids, array $row ): int {
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids || ! $row ) {
			return 0;
		}
		$updated = 0;
		foreach ( $ids as $id ) {
			if ( $this->update( $id, $row ) ) {
				++$updated;
			}
		}
		return $updated;
	}

	/**
	 * @param array<string, mixed> $row Cluster row.
	 */
	public function insert_cluster( array $row ): int {
		$ok = $this->db()->insert( $this->table_clusters(), $row );
		return false === $ok ? 0 : (int) $this->db()->insert_id;
	}

	/**
	 * @param array<string, mixed> $row Cluster row.
	 */
	public function update_cluster( int $id, array $row ): bool {
		$result = $this->db()->update( $this->table_clusters(), $row, array( 'id' => $id ), null, array( '%d' ) );
		return false !== $result;
	}

	public function delete_cluster( int $id ): bool {
		$this->db()->update(
			$this->table(),
			array( 'cluster_id' => 0 ),
			array( 'cluster_id' => $id ),
			array( '%d' ),
			array( '%d' )
		);
		$result = $this->db()->delete( $this->table_clusters(), array( 'id' => $id ), array( '%d' ) );
		return false !== $result && $result > 0;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_clusters( int $project_id = 0 ): array {
		$table = $this->table_clusters();
		if ( $project_id > 0 ) {
			$rows = $this->db()->get_results(
				$this->db()->prepare(
					"SELECT * FROM {$table} WHERE project_id = %d OR project_id = 0 ORDER BY name ASC",
					$project_id
				),
				ARRAY_A
			);
		} else {
			$rows = $this->db()->get_results( "SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A );
		}
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Cluster view: keywords grouped by cluster for a project.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function cluster_view( int $project_id ): array {
		$kw = $this->table();
		$cl = $this->table_clusters();
		$rows = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT k.id, k.primary_keyword, k.status, k.priority, k.intent, k.cluster_id,
					COALESCE(c.name, 'Unclustered') AS cluster_name
				FROM {$kw} k
				LEFT JOIN {$cl} c ON c.id = k.cluster_id
				WHERE k.project_id = %d
				ORDER BY cluster_name ASC, k.priority DESC, k.primary_keyword ASC",
				$project_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Roadmap view ordered by phase + order.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function roadmap_view( int $project_id ): array {
		$kw = $this->table();
		$rows = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT id, primary_keyword, status, priority, roadmap_phase, roadmap_order, brief_id, article_id, target_url
				FROM {$kw}
				WHERE project_id = %d
				ORDER BY
					FIELD(roadmap_phase, 'now', 'next', 'later', 'backlog', '') ASC,
					roadmap_order ASC,
					priority DESC",
				$project_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array<string, mixed> $row Note row.
	 */
	public function insert_note( array $row ): int {
		$ok = $this->db()->insert( $this->table_notes(), $row );
		return false === $ok ? 0 : (int) $this->db()->insert_id;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_notes( int $keyword_id, int $limit = 50 ): array {
		$table = $this->table_notes();
		$limit = max( 1, min( 100, $limit ) );
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT * FROM {$table} WHERE keyword_id = %d ORDER BY created_at DESC LIMIT %d",
				$keyword_id,
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array<string, mixed> $row History row.
	 */
	public function insert_history( array $row ): int {
		$ok = $this->db()->insert( $this->table_history(), $row );
		return false === $ok ? 0 : (int) $this->db()->insert_id;
	}

	/**
	 * Timeline = history for keyword or project keywords.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function timeline( int $project_id = 0, int $keyword_id = 0, int $limit = 40 ): array {
		$h     = $this->table_history();
		$k     = $this->table();
		$limit = max( 1, min( 100, $limit ) );

		if ( $keyword_id > 0 ) {
			$rows = $this->db()->get_results(
				$this->db()->prepare(
					"SELECT h.*, kw.primary_keyword
					FROM {$h} h
					INNER JOIN {$k} kw ON kw.id = h.keyword_id
					WHERE h.keyword_id = %d
					ORDER BY h.created_at DESC LIMIT %d",
					$keyword_id,
					$limit
				),
				ARRAY_A
			);
		} elseif ( $project_id > 0 ) {
			$rows = $this->db()->get_results(
				$this->db()->prepare(
					"SELECT h.*, kw.primary_keyword
					FROM {$h} h
					INNER JOIN {$k} kw ON kw.id = h.keyword_id
					WHERE kw.project_id = %d
					ORDER BY h.created_at DESC LIMIT %d",
					$project_id,
					$limit
				),
				ARRAY_A
			);
		} else {
			$rows = $this->db()->get_results(
				$this->db()->prepare(
					"SELECT h.*, kw.primary_keyword
					FROM {$h} h
					INNER JOIN {$k} kw ON kw.id = h.keyword_id
					ORDER BY h.created_at DESC LIMIT %d",
					$limit
				),
				ARRAY_A
			);
		}

		return is_array( $rows ) ? $rows : array();
	}
}
