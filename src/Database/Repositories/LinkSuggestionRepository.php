<?php
declare(strict_types=1);

/**
 * Data access for {prefix}rsaip_link_suggestions.
 *
 * SQL copied from RSAIP_Internal_Link_Suggester, RSAIP_Auto_Linker, RSAIP_AI.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Database\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LinkSuggestionRepository
 */
final class LinkSuggestionRepository extends AbstractRepository {

	/**
	 * @inheritDoc
	 */
	public function table(): string {
		return \RSAIP_DB::table_link_suggestions();
	}

	/**
	 * INSERT … ON DUPLICATE KEY UPDATE score, reasons, created_at
	 *
	 * @return int|false
	 */
	public function upsert( int $post_id, int $suggested_post_id, int $score, string $reasons_json, string $created_at ) {
		$table = $this->table();
		return $this->db()->query(
			$this->db()->prepare(
				"INSERT INTO {$table} (post_id, suggested_post_id, score, reasons, created_at) VALUES (%d, %d, %d, %s, %s)
				ON DUPLICATE KEY UPDATE score = VALUES(score), reasons = VALUES(reasons), created_at = VALUES(created_at)",
				$post_id,
				$suggested_post_id,
				$score,
				$reasons_json,
				$created_at
			)
		);
	}

	/**
	 * TRUNCATE TABLE …
	 *
	 * @return int|false
	 */
	public function truncate() {
		$table = $this->table();
		return $this->db()->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * SELECT post_id, suggested_post_id, score, reasons ORDER BY score DESC LIMIT %d
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function select_latest( int $limit ): array {
		$table = $this->table();
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT post_id, suggested_post_id, score, reasons FROM {$table} ORDER BY score DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * SELECT suggested_post_id, score WHERE post_id AND score >= … 
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function select_for_post_min_score( int $post_id, int $min_score, int $limit ): array {
		$table = $this->table();
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT suggested_post_id, score FROM {$table} WHERE post_id = %d AND score >= %d ORDER BY score DESC LIMIT %d",
				$post_id,
				$min_score,
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * SELECT suggested_post_id, score WHERE post_id ORDER BY score DESC LIMIT %d
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function select_for_post_top( int $post_id, int $limit ): array {
		$table = $this->table();
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT suggested_post_id, score FROM {$table} WHERE post_id = %d ORDER BY score DESC LIMIT %d",
				$post_id,
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}
}
