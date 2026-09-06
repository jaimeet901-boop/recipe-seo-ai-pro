<?php
declare(strict_types=1);

/**
 * Persist AI Hub request logs (never stores API keys).
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AiRequestLogger
 */
final class AiRequestLogger {

	/**
	 * @param array<string, mixed> $row Log fields.
	 */
	public function log( array $row ): void {
		global $wpdb;

		if ( ! class_exists( 'RSAIP_DB' ) || ! \RSAIP_DB::ai_logs_table_ready() ) {
			return;
		}

		$table = \RSAIP_DB::table_ai_logs();
		$wpdb->insert(
			$table,
			array(
				'provider'          => sanitize_key( (string) ( $row['provider'] ?? '' ) ),
				'model'             => sanitize_text_field( (string) ( $row['model'] ?? '' ) ),
				'latency_ms'        => max( 0, (int) ( $row['latency_ms'] ?? 0 ) ),
				'request_size'      => max( 0, (int) ( $row['request_size'] ?? 0 ) ),
				'response_size'     => max( 0, (int) ( $row['response_size'] ?? 0 ) ),
				'prompt_tokens'     => max( 0, (int) ( $row['prompt_tokens'] ?? 0 ) ),
				'completion_tokens' => max( 0, (int) ( $row['completion_tokens'] ?? 0 ) ),
				'total_tokens'      => max( 0, (int) ( $row['total_tokens'] ?? 0 ) ),
				'estimated_cost'    => (float) ( $row['estimated_cost'] ?? 0 ),
				'success'           => ! empty( $row['success'] ) ? 1 : 0,
				'error_message'     => sanitize_text_field( (string) ( $row['error_message'] ?? '' ) ),
				'created_at'        => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%f', '%d', '%s', '%s' )
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function recent( int $limit = 50 ): array {
		global $wpdb;

		if ( ! class_exists( 'RSAIP_DB' ) || ! \RSAIP_DB::ai_logs_table_ready() ) {
			return array();
		}

		$table = \RSAIP_DB::table_ai_logs();
		$limit = max( 1, min( 200, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Dashboard aggregates for "today" (UTC date boundary via created_at).
	 *
	 * @return array<string, mixed>
	 */
	public function today_summary(): array {
		global $wpdb;

		$empty = array(
			'requests' => 0,
			'tokens'   => 0,
			'cost'     => 0.0,
			'avg_latency' => 0,
			'failures' => 0,
		);

		if ( ! class_exists( 'RSAIP_DB' ) || ! \RSAIP_DB::ai_logs_table_ready() ) {
			return $empty;
		}

		$table = \RSAIP_DB::table_ai_logs();
		$day   = gmdate( 'Y-m-d 00:00:00' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS requests,
					COALESCE(SUM(total_tokens),0) AS tokens,
					COALESCE(SUM(estimated_cost),0) AS cost,
					COALESCE(AVG(latency_ms),0) AS avg_latency,
					COALESCE(SUM(CASE WHEN success=0 THEN 1 ELSE 0 END),0) AS failures
				 FROM {$table} WHERE created_at >= %s",
				$day
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return $empty;
		}

		return array(
			'requests'    => (int) $row['requests'],
			'tokens'      => (int) $row['tokens'],
			'cost'        => (float) $row['cost'],
			'avg_latency' => (int) round( (float) $row['avg_latency'] ),
			'failures'    => (int) $row['failures'],
		);
	}
}
