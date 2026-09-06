<?php
declare(strict_types=1);

/**
 * Data access for {prefix}rsaip_recipe_votes.
 *
 * SQL copied from RSAIP_AJAX rating handlers. Phase 2B does not modify AJAX;
 * this repository is available for later wiring without changing schema/SQL.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Database\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeVoteRepository
 */
final class RecipeVoteRepository extends AbstractRepository {

	/**
	 * @inheritDoc
	 */
	public function table(): string {
		return \RSAIP_DB::table_recipe_votes();
	}

	/**
	 * SELECT id FROM … WHERE post_id = %d AND voter_key = %s LIMIT 1
	 *
	 * @return string|null Vote id or null.
	 */
	public function find_id_by_post_and_voter( int $post_id, string $voter_key ) {
		$table = $this->table();
		return $this->db()->get_var(
			$this->db()->prepare(
				"SELECT id FROM {$table} WHERE post_id = %d AND voter_key = %s LIMIT 1",
				$post_id,
				$voter_key
			)
		);
	}

	/**
	 * INSERT INTO … (post_id, voter_key, rating, ip_hash, created_at)
	 *
	 * @return int|false
	 */
	public function insert_vote( int $post_id, string $voter_key, int $rating, string $ip_hash, string $created_at ) {
		$table = $this->table();
		return $this->db()->query(
			$this->db()->prepare(
				"INSERT INTO {$table} (post_id, voter_key, rating, ip_hash, created_at) VALUES (%d, %s, %d, %s, %s)",
				$post_id,
				$voter_key,
				$rating,
				$ip_hash,
				$created_at
			)
		);
	}

	/**
	 * SELECT AVG(rating), COUNT(*) WHERE post_id = %d
	 *
	 * @return array<string, mixed>|null
	 */
	public function select_aggregate_for_post( int $post_id ): ?array {
		$table = $this->table();
		$row   = $this->db()->get_row(
			$this->db()->prepare(
				"SELECT AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM {$table} WHERE post_id = %d",
				$post_id
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}
}
