<?php
declare(strict_types=1);

/**
 * Data access for {prefix}rsaip_bulk_queue.
 *
 * SQL copied from RSAIP_Bulk_Optimizer. No queue orchestration logic.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Database\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BulkQueueRepository
 */
final class BulkQueueRepository extends AbstractRepository {

	/**
	 * @inheritDoc
	 */
	public function table(): string {
		return \RSAIP_DB::table_bulk_queue();
	}

	/**
	 * INSERT … ON DUPLICATE KEY UPDATE status = IF(status = 'done', status, 'pending')
	 *
	 * @return int Rows affected from last query (via $wpdb->rows_affected).
	 */
	public function enqueue( int $post_id, string $task_type, string $created_at ): int {
		$table = $this->table();
		$this->db()->query(
			$this->db()->prepare(
				"INSERT INTO {$table} (post_id, task_type, status, attempts, created_at) VALUES (%d, %s, 'pending', 0, %s)
				ON DUPLICATE KEY UPDATE status = IF(status = 'done', status, 'pending')",
				$post_id,
				$task_type,
				$created_at
			)
		);
		return (int) $this->db()->rows_affected;
	}

	/**
	 * SELECT claimable queue rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function select_claimable( int $limit ): array {
		$table = $this->table();
		$rows  = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT id, post_id, task_type, attempts FROM {$table}
				WHERE status IN ('pending', 'failed') AND (locked_at IS NULL OR locked_at < (UTC_TIMESTAMP() - INTERVAL 20 MINUTE))
				ORDER BY created_at ASC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Mark row processing.
	 *
	 * @return int|false
	 */
	public function mark_processing( int $id, string $locked_at, int $attempts ) {
		return $this->db()->update(
			$this->table(),
			array(
				'status'    => 'processing',
				'locked_at' => $locked_at,
				'attempts'  => $attempts,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Mark row done.
	 *
	 * @return int|false
	 */
	public function mark_done( int $id, string $processed_at, string $payload_json ) {
		// Format list intentionally matches legacy RSAIP_Bulk_Optimizer (4 formats / 5 fields).
		return $this->db()->update(
			$this->table(),
			array(
				'status'       => 'done',
				'processed_at' => $processed_at,
				'last_error'   => '',
				'payload'      => $payload_json,
				'locked_at'    => null,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark row failed.
	 *
	 * @return int|false
	 */
	public function mark_failed( int $id, string $processed_at, string $last_error, string $payload_json ) {
		// Format list intentionally matches legacy RSAIP_Bulk_Optimizer (4 formats / 5 fields).
		return $this->db()->update(
			$this->table(),
			array(
				'status'       => 'failed',
				'processed_at' => $processed_at,
				'last_error'   => $last_error,
				'payload'      => $payload_json,
				'locked_at'    => null,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Terminal-block existing-post mutation jobs without deleting rows.
	 *
	 * Sets status=done so select_claimable() will not pick them up after Safe Mode is disabled.
	 *
	 * @param string[] $task_types
	 * @return int Rows affected.
	 */
	public function retire_existing_post_mutation_jobs( array $task_types, string $processed_at, string $payload_json ): int {
		$task_types = array_values( array_unique( array_filter( array_map( 'sanitize_key', $task_types ) ) ) );
		if ( $task_types === array() ) {
			return 0;
		}

		$table = $this->table();
		$in    = implode( ',', array_fill( 0, count( $task_types ), '%s' ) );
		$sql   = $this->db()->prepare(
			"UPDATE {$table}
			SET status = 'done', processed_at = %s, last_error = '', payload = %s, locked_at = NULL
			WHERE task_type IN ({$in})
			AND status IN ('pending', 'failed', 'processing')",
			...array_merge( array( $processed_at, $payload_json ), $task_types )
		);

		$this->db()->query( $sql );
		return (int) $this->db()->rows_affected;
	}

	/**
	 * SELECT COUNT(*) WHERE status = %s (status embedded like legacy).
	 */
	public function count_by_status( string $status ): int {
		$table = $this->table();
		$map   = array(
			'pending'    => "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'",
			'processing' => "SELECT COUNT(*) FROM {$table} WHERE status = 'processing'",
			'done'       => "SELECT COUNT(*) FROM {$table} WHERE status = 'done'",
			'failed'     => "SELECT COUNT(*) FROM {$table} WHERE status = 'failed'",
		);
		if ( ! isset( $map[ $status ] ) ) {
			return 0;
		}
		return (int) $this->db()->get_var( $map[ $status ] );
	}
}
