<?php
declare(strict_types=1);

/**
 * SQL access for Content Calendar tables (Phase 3.7).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Calendar;

use RecipeSeoAiPro\Database\Repositories\AbstractRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CalendarRepository
 */
final class CalendarRepository extends AbstractRepository {

	/**
	 * @inheritDoc
	 */
	public function table(): string {
		return \RSAIP_DB::table_calendar_events();
	}

	public function table_calendars(): string {
		return \RSAIP_DB::table_content_calendars();
	}

	public function table_queue(): string {
		return \RSAIP_DB::table_publishing_queue();
	}

	/**
	 * @param array<string, mixed> $row Row.
	 */
	public function insert_calendar( array $row ): int {
		$ok = $this->db()->insert( $this->table_calendars(), $row );
		return false === $ok ? 0 : (int) $this->db()->insert_id;
	}

	/**
	 * @param array<string, mixed> $row Row.
	 */
	public function update_calendar( int $id, array $row ): bool {
		$result = $this->db()->update( $this->table_calendars(), $row, array( 'id' => $id ), null, array( '%d' ) );
		return false !== $result;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_calendar( int $id ): ?array {
		$table = $this->table_calendars();
		$row   = $this->db()->get_row(
			$this->db()->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_calendars( int $project_id = 0 ): array {
		$table = $this->table_calendars();
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
	 * @param array<string, mixed> $row Row.
	 */
	public function insert_event( array $row ): int {
		$ok = $this->db()->insert( $this->table(), $row );
		return false === $ok ? 0 : (int) $this->db()->insert_id;
	}

	/**
	 * @param array<string, mixed> $row Row.
	 */
	public function update_event( int $id, array $row ): bool {
		$result = $this->db()->update( $this->table(), $row, array( 'id' => $id ), null, array( '%d' ) );
		return false !== $result;
	}

	public function delete_event( int $id ): bool {
		$this->db()->delete( $this->table_queue(), array( 'event_id' => $id ), array( '%d' ) );
		$result = $this->db()->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
		return false !== $result && $result > 0;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_event( int $id ): ?array {
		$table = $this->table();
		$row   = $this->db()->get_row(
			$this->db()->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param array<string, mixed> $args Filters: project_id, calendar_id, status, from, to, q, assigned_user_id, upcoming, overdue.
	 * @return list<array<string, mixed>>
	 */
	public function list_events( array $args ): array {
		$table = $this->table();
		$where = array( '1=1' );
		$params = array();

		if ( ! empty( $args['project_id'] ) ) {
			$where[]  = 'project_id = %d';
			$params[] = absint( $args['project_id'] );
		}
		if ( ! empty( $args['calendar_id'] ) ) {
			$where[]  = 'calendar_id = %d';
			$params[] = absint( $args['calendar_id'] );
		}
		if ( ! empty( $args['status'] ) && $args['status'] !== 'all' ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( (string) $args['status'] );
		}
		if ( ! empty( $args['from'] ) ) {
			$where[]  = 'publish_at >= %s';
			$params[] = (string) $args['from'];
		}
		if ( ! empty( $args['to'] ) ) {
			$where[]  = 'publish_at <= %s';
			$params[] = (string) $args['to'];
		}
		if ( ! empty( $args['assigned_user_id'] ) ) {
			$where[]  = 'assigned_user_id = %d';
			$params[] = absint( $args['assigned_user_id'] );
		}
		if ( ! empty( $args['q'] ) ) {
			$like     = '%' . $this->db()->esc_like( (string) $args['q'] ) . '%';
			$where[]  = '(title LIKE %s OR notes LIKE %s OR publishing_channel LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( ! empty( $args['upcoming'] ) ) {
			$where[]  = 'publish_at >= %s';
			$params[] = \RSAIP_DB::now_gmt_sql();
			$where[]  = "status NOT IN ('published','cancelled')";
		}
		if ( ! empty( $args['overdue'] ) ) {
			$where[]  = 'publish_at < %s';
			$params[] = \RSAIP_DB::now_gmt_sql();
			$where[]  = "status NOT IN ('published','cancelled')";
		}

		$where_sql = implode( ' AND ', $where );
		$limit     = max( 1, min( 500, (int) ( $args['limit'] ?? 200 ) ) );
		$sql       = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY publish_at ASC LIMIT %d";
		$params[]  = $limit;

		$rows = $this->db()->get_results( $this->db()->prepare( $sql, $params ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param list<int>            $ids IDs.
	 * @param array<string, mixed> $row Fields.
	 */
	public function bulk_update_events( array $ids, array $row ): int {
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids || ! $row ) {
			return 0;
		}
		$updated = 0;
		foreach ( $ids as $id ) {
			if ( $this->update_event( $id, $row ) ) {
				++$updated;
			}
		}
		return $updated;
	}

	/**
	 * @param list<int> $ids IDs.
	 */
	public function bulk_delete_events( array $ids ): int {
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( $this->delete_event( $id ) ) {
				++$deleted;
			}
		}
		return $deleted;
	}

	/**
	 * @param array<string, mixed> $row Queue row.
	 */
	public function insert_queue( array $row ): int {
		$ok = $this->db()->insert( $this->table_queue(), $row );
		return false === $ok ? 0 : (int) $this->db()->insert_id;
	}

	/**
	 * @param array<string, mixed> $row Queue row.
	 */
	public function update_queue( int $id, array $row ): bool {
		$result = $this->db()->update( $this->table_queue(), $row, array( 'id' => $id ), null, array( '%d' ) );
		return false !== $result;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_queue( array $args = array() ): array {
		$table  = $this->table_queue();
		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['status'] ) && $args['status'] !== 'all' ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( (string) $args['status'] );
		}
		if ( ! empty( $args['project_id'] ) ) {
			$where[]  = 'project_id = %d';
			$params[] = absint( $args['project_id'] );
		}
		$where_sql = implode( ' AND ', $where );
		$limit     = max( 1, min( 200, (int) ( $args['limit'] ?? 50 ) ) );
		$sql       = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY scheduled_at ASC LIMIT %d";
		$params[]  = $limit;
		$rows      = $this->db()->get_results( $this->db()->prepare( $sql, $params ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Find queue row by event.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find_queue_by_event( int $event_id ): ?array {
		$table = $this->table_queue();
		$row   = $this->db()->get_row(
			$this->db()->prepare( "SELECT * FROM {$table} WHERE event_id = %d ORDER BY id DESC LIMIT 1", $event_id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}
}
