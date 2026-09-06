<?php
declare(strict_types=1);

/**
 * Data access for {prefix}rsaip_post_metrics.
 *
 * SQL copied from RSAIP_Link_Graph, RSAIP_Audit, RSAIP_Reports. No business logic.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Database\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PostMetricsRepository
 */
final class PostMetricsRepository extends AbstractRepository {

	/**
	 * @inheritDoc
	 */
	public function table(): string {
		return \RSAIP_DB::table_post_metrics();
	}

	/**
	 * Basic upsert used during link-graph indexing.
	 *
	 * @return int|false
	 */
	public function upsert_basic(
		int $post_id,
		string $post_type,
		string $post_status,
		int $word_count,
		int $internal_out,
		string $updated_at
	) {
		$table = $this->table();
		return $this->db()->query(
			$this->db()->prepare(
				"INSERT INTO {$table} (post_id, post_type, post_status, word_count, internal_out, internal_in, has_featured, missing_meta_desc, missing_featured, missing_h2, missing_h3, missing_alt_images, low_internal_links, missing_external_links, thin_content, orphan, seo_score, last_scanned, updated_at)
				VALUES (%d, %s, %s, %d, %d, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, %s)
				ON DUPLICATE KEY UPDATE post_type = VALUES(post_type), post_status = VALUES(post_status), internal_out = VALUES(internal_out), updated_at = VALUES(updated_at)",
				$post_id,
				$post_type,
				$post_status,
				$word_count,
				$internal_out,
				$updated_at
			)
		);
	}

	/**
	 * UPDATE … SET internal_in = 0, orphan = 0
	 *
	 * @return int|false
	 */
	public function reset_internal_in_and_orphan() {
		$table = $this->table();
		return $this->db()->query( "UPDATE {$table} SET internal_in = 0, orphan = 0" );
	}

	/**
	 * UPDATE internal_in for one post.
	 *
	 * @return int|false
	 */
	public function update_internal_in( int $post_id, int $internal_in ) {
		return $this->db()->update(
			$this->table(),
			array( 'internal_in' => $internal_in ),
			array( 'post_id' => $post_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * UPDATE orphan = 1 WHERE internal_in = 0 AND post_status = 'publish'
	 *
	 * @return int|false
	 */
	public function mark_publish_orphans() {
		$table = $this->table();
		return $this->db()->query( "UPDATE {$table} SET orphan = 1 WHERE internal_in = 0 AND post_status = 'publish'" );
	}

	/**
	 * SELECT COUNT(*) FROM … WHERE {column} = 1 (or missing_alt_images > 0).
	 *
	 * Column names are whitelisted to preserve hardcoded legacy SQL safely.
	 */
	public function count_flag( string $column, bool $greater_than_zero = false ): int {
		$allowed = array(
			'missing_meta_desc',
			'missing_featured',
			'missing_h2',
			'missing_h3',
			'missing_alt_images',
			'low_internal_links',
			'missing_external_links',
			'thin_content',
			'orphan',
		);
		if ( ! in_array( $column, $allowed, true ) ) {
			return 0;
		}

		$table = $this->table();
		if ( $greater_than_zero ) {
			$sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} > 0";
		} else {
			$sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = 1";
		}
		return (int) $this->db()->get_var( $sql );
	}

	/**
	 * Full audit metrics upsert.
	 *
	 * @param array<string, int> $fields Metric fields.
	 * @return int|false
	 */
	public function upsert_audit_metrics(
		int $post_id,
		string $post_type,
		string $post_status,
		array $fields,
		string $now
	) {
		$table = $this->table();
		return $this->db()->query(
			$this->db()->prepare(
				"INSERT INTO {$table} (post_id, post_type, post_status, word_count, internal_out, internal_in, has_featured, missing_meta_desc, missing_featured, missing_h2, missing_h3, missing_alt_images, low_internal_links, missing_external_links, thin_content, orphan, seo_score, last_scanned, updated_at)
				VALUES (%d, %s, %s, %d, 0, 0, %d, %d, %d, %d, %d, %d, %d, %d, %d, 0, %d, %s, %s)
				ON DUPLICATE KEY UPDATE post_type = VALUES(post_type), post_status = VALUES(post_status), word_count = VALUES(word_count), has_featured = VALUES(has_featured), missing_meta_desc = VALUES(missing_meta_desc), missing_featured = VALUES(missing_featured), missing_h2 = VALUES(missing_h2), missing_h3 = VALUES(missing_h3), missing_alt_images = VALUES(missing_alt_images), low_internal_links = VALUES(low_internal_links), missing_external_links = VALUES(missing_external_links), thin_content = VALUES(thin_content), seo_score = VALUES(seo_score), last_scanned = VALUES(last_scanned), updated_at = VALUES(updated_at)",
				$post_id,
				$post_type,
				$post_status,
				(int) $fields['word_count'],
				(int) $fields['has_featured'],
				(int) $fields['missing_meta_desc'],
				(int) $fields['missing_featured'],
				(int) $fields['missing_h2'],
				(int) $fields['missing_h3'],
				(int) $fields['missing_alt_images'],
				(int) $fields['low_internal_links'],
				(int) $fields['missing_external_links'],
				(int) $fields['thin_content'],
				(int) $fields['seo_score'],
				$now,
				$now
			)
		);
	}

	/**
	 * SELECT internal_out FROM … WHERE post_id = %d
	 */
	public function get_internal_out( int $post_id ): int {
		$table = $this->table();
		$v     = $this->db()->get_var(
			$this->db()->prepare(
				"SELECT internal_out FROM {$table} WHERE post_id = %d",
				$post_id
			)
		);
		return absint( $v );
	}

	/**
	 * SELECT post_id, word_count … orphan = 1
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function select_orphans( int $limit ): array {
		$table = $this->table();
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT post_id, word_count FROM {$table} WHERE orphan = 1 ORDER BY updated_at DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * SELECT post_id, seo_score … ORDER BY seo_score ASC|DESC
	 *
	 * @param string $direction Must be ASC or DESC (caller whitelist).
	 * @return array<int, array<string, mixed>>
	 */
	public function select_by_seo_score( string $direction, int $limit ): array {
		$table = $this->table();
		$dir   = ( 'ASC' === $direction ) ? 'ASC' : 'DESC';
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT post_id, seo_score FROM {$table} WHERE post_status = 'publish' ORDER BY seo_score {$dir} LIMIT %d",
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}
}
