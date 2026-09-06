<?php
declare(strict_types=1);

/**
 * Data access for {prefix}rsaip_content_briefs (Phase 3.2).
 *
 * SQL only — no AI generation logic.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentBrief;

use RecipeSeoAiPro\Database\Repositories\AbstractRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BriefRepository
 */
final class BriefRepository extends AbstractRepository {

	/**
	 * @inheritDoc
	 */
	public function table(): string {
		return \RSAIP_DB::table_content_briefs();
	}

	/**
	 * @param array<string, mixed> $row Column => value.
	 * @return int Insert id (0 on failure).
	 */
	public function insert( array $row ): int {
		$ok = $this->db()->insert( $this->table(), $row );
		if ( false === $ok ) {
			return 0;
		}
		return (int) $this->db()->insert_id;
	}

	/**
	 * @param array<string, mixed> $row Column => value.
	 * @return bool
	 */
	public function update( int $id, array $row ): bool {
		$result = $this->db()->update(
			$this->table(),
			$row,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);
		return false !== $result;
	}

	public function delete( int $id ): bool {
		$result = $this->db()->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
		return false !== $result && $result > 0;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		$table = $this->table();
		$row   = $this->db()->get_row(
			$this->db()->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param array<string, mixed> $args q, status, sort, order, page, per_page, user_id.
	 * @return array{items: list<array<string, mixed>>, total: int}
	 */
	public function search( array $args ): array {
		$table    = $this->table();
		$q        = isset( $args['q'] ) ? trim( (string) $args['q'] ) : '';
		$status   = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
		$sort     = isset( $args['sort'] ) ? (string) $args['sort'] : 'updated_at';
		$order    = isset( $args['order'] ) && strtoupper( (string) $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
		$user_id  = isset( $args['user_id'] ) ? (int) $args['user_id'] : 0;

		$allowed_sort = array( 'updated_at', 'created_at', 'title', 'primary_keyword', 'status', 'word_count' );
		if ( ! in_array( $sort, $allowed_sort, true ) ) {
			$sort = 'updated_at';
		}

		$where  = array( '1=1' );
		$params = array();

		if ( $status !== '' && $status !== 'all' ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}

		if ( $user_id > 0 ) {
			$where[]  = 'user_id = %d';
			$params[] = $user_id;
		}

		if ( $q !== '' ) {
			$like     = '%' . $this->db()->esc_like( $q ) . '%';
			$where[]  = '(title LIKE %s OR topic LIKE %s OR primary_keyword LIKE %s OR slug LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = ( $page - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( $params ) {
			$total = (int) $this->db()->get_var( $this->db()->prepare( $count_sql, $params ) );
		} else {
			$total = (int) $this->db()->get_var( $count_sql );
		}

		$list_sql = "SELECT id, title, topic, primary_keyword, search_intent, status, word_count, user_id, created_at, updated_at, slug
			FROM {$table}
			WHERE {$where_sql}
			ORDER BY {$sort} {$order}
			LIMIT %d OFFSET %d";

		$list_params   = $params;
		$list_params[] = $per_page;
		$list_params[] = $offset;

		$rows = $this->db()->get_results( $this->db()->prepare( $list_sql, $list_params ), ARRAY_A );

		return array(
			'items' => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}
}
