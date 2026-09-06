<?php
declare(strict_types=1);

/**
 * Data access for {prefix}rsaip_serp_analyses (Phase 3.6).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Seo\SerpIntelligence;

use RecipeSeoAiPro\Database\Repositories\AbstractRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SerpIntelligenceRepository
 */
final class SerpIntelligenceRepository extends AbstractRepository {

	/**
	 * @inheritDoc
	 */
	public function table(): string {
		return \RSAIP_DB::table_serp_analyses();
	}

	/**
	 * @param array<string, mixed> $row Column => value.
	 */
	public function insert( array $row ): int {
		$ok = $this->db()->insert( $this->table(), $row );
		return false === $ok ? 0 : (int) $this->db()->insert_id;
	}

	/**
	 * @param array<string, mixed> $row Column => value.
	 */
	public function update( int $id, array $row ): bool {
		$result = $this->db()->update( $this->table(), $row, array( 'id' => $id ), null, array( '%d' ) );
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
	 * @param array<string, mixed> $args project_id, keyword_id, brief_id, q, page, per_page.
	 * @return array{items: list<array<string, mixed>>, total: int}
	 */
	public function search( array $args ): array {
		$table    = $this->table();
		$q        = isset( $args['q'] ) ? trim( (string) $args['q'] ) : '';
		$project  = absint( $args['project_id'] ?? 0 );
		$keyword  = absint( $args['keyword_id'] ?? 0 );
		$brief    = absint( $args['brief_id'] ?? 0 );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );

		$where  = array( '1=1' );
		$params = array();
		if ( $project > 0 ) {
			$where[]  = 'project_id = %d';
			$params[] = $project;
		}
		if ( $keyword > 0 ) {
			$where[]  = 'keyword_id = %d';
			$params[] = $keyword;
		}
		if ( $brief > 0 ) {
			$where[]  = 'brief_id = %d';
			$params[] = $brief;
		}
		if ( $q !== '' ) {
			$like     = '%' . $this->db()->esc_like( $q ) . '%';
			$where[]  = '(query_text LIKE %s OR search_intent LIKE %s OR recommended_article_type LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = ( $page - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = $params
			? (int) $this->db()->get_var( $this->db()->prepare( $count_sql, $params ) )
			: (int) $this->db()->get_var( $count_sql );

		$list_sql = "SELECT id, query_text, language, country, search_intent, recommended_article_type,
				recommended_word_count, project_id, keyword_id, brief_id, user_id, created_at, updated_at
			FROM {$table}
			WHERE {$where_sql}
			ORDER BY updated_at DESC
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
}
