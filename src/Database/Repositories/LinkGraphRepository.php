<?php
declare(strict_types=1);

/**
 * Data access for {prefix}rsaip_link_graph.
 *
 * SQL copied from RSAIP_Link_Graph / RSAIP_Audit. No business logic.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Database\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LinkGraphRepository
 */
final class LinkGraphRepository extends AbstractRepository {

	/**
	 * @inheritDoc
	 */
	public function table(): string {
		return \RSAIP_DB::table_link_graph();
	}

	/**
	 * DELETE FROM … WHERE from_post_id = %d
	 *
	 * @return int|false
	 */
	public function delete_by_from_post_id( int $from_post_id ) {
		return $this->db()->delete(
			$this->table(),
			array( 'from_post_id' => $from_post_id ),
			array( '%d' )
		);
	}

	/**
	 * INSERT … ON DUPLICATE KEY UPDATE anchor_text, link_type
	 *
	 * @return int|false
	 */
	public function insert_or_update_link(
		int $from_post_id,
		int $to_post_id,
		string $url,
		string $anchor_text,
		string $link_type,
		string $created_at
	) {
		$table = $this->table();
		return $this->db()->query(
			$this->db()->prepare(
				"INSERT INTO {$table} (from_post_id, to_post_id, url, anchor_text, link_type, created_at) VALUES (%d, %d, %s, %s, %s, %s)
				ON DUPLICATE KEY UPDATE anchor_text = VALUES(anchor_text), link_type = VALUES(link_type)",
				$from_post_id,
				$to_post_id,
				$url,
				$anchor_text,
				$link_type,
				$created_at
			)
		);
	}

	/**
	 * SELECT to_post_id, COUNT(*) AS c … GROUP BY to_post_id
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function select_internal_inbound_counts(): array {
		$table = $this->table();
		$rows  = $this->db()->get_results(
			"SELECT to_post_id, COUNT(*) AS c FROM {$table} WHERE link_type = 'internal' AND to_post_id > 0 GROUP BY to_post_id",
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * SELECT id, url … stale / unchecked links.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function select_stale_urls_for_check( int $limit ): array {
		$table = $this->table();
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT id, url FROM {$table} WHERE url <> '' AND (last_checked IS NULL OR last_checked < (UTC_TIMESTAMP() - INTERVAL 3 DAY)) ORDER BY last_checked ASC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * UPDATE http_status, redirect_hops, final_url, last_checked
	 *
	 * @return int|false
	 */
	public function update_check_result( int $id, int $http_status, int $redirect_hops, ?string $final_url, string $last_checked ) {
		return $this->db()->update(
			$this->table(),
			array(
				'http_status'   => $http_status,
				'redirect_hops' => $redirect_hops,
				'final_url'     => $final_url,
				'last_checked'  => $last_checked,
			),
			array( 'id' => $id ),
			array( '%d', '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * SELECT COUNT(*) … external outlinks for a post.
	 */
	public function count_external_out( int $from_post_id ): int {
		$table = $this->table();
		$v     = $this->db()->get_var(
			$this->db()->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE from_post_id = %d AND link_type = 'external'",
				$from_post_id
			)
		);
		return absint( $v );
	}

	/**
	 * SELECT link_type, http_status, redirect_hops WHERE from_post_id = %d
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function select_status_rows_for_post( int $from_post_id ): array {
		$table = $this->table();
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT link_type, http_status, redirect_hops FROM {$table} WHERE from_post_id = %d",
				$from_post_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}
}
