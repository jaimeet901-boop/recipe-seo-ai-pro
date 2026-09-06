<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSAIP_Reports {
	private RSAIP_Audit $audit;
	private RSAIP_GSC $gsc;

	public function __construct( RSAIP_Audit $audit, RSAIP_GSC $gsc ) {
		$this->audit = $audit;
		$this->gsc   = $gsc;
	}

	public function build_report( string $type ): array {
		$type = $type === 'monthly' ? 'monthly' : 'weekly';

		$end_ts   = time();
		$start_ts = $type === 'monthly' ? strtotime( '-30 days', $end_ts ) : strtotime( '-7 days', $end_ts );

		$stats = $this->audit->get_dashboard_stats();
		$top_low_score = $this->get_top_posts_by_score( 10, 'ASC' );
		$top_orphans   = $this->audit->get_orphan_posts( 10 );

		$gsc_low = array();
		$gsc_top = array();
		$gsc_test = $this->gsc->get_indexed_post_ids_cached();
		if ( is_array( $gsc_test ) ) {
			$gsc_low = $this->gsc->get_low_hanging_fruits( 10 );
			$gsc_top = $this->gsc->get_top_opportunities( 10 );
		}

		$report = array(
			'type'      => $type,
			'generated' => current_time( 'mysql' ),
			'period'    => array(
				'start' => gmdate( 'Y-m-d', $start_ts ),
				'end'   => gmdate( 'Y-m-d', $end_ts ),
			),
			'stats'     => $stats,
			'top_low_score_posts' => $top_low_score,
			'top_orphan_posts'    => $top_orphans,
			'gsc_top_opportunities' => $gsc_top,
			'gsc_low_hanging_fruits'=> $gsc_low,
		);

		$report['html'] = $this->render_report_html( $report );
		$report['rows'] = $this->to_export_rows( $report );
		return $report;
	}

	private function get_top_posts_by_score( int $limit, string $direction ): array {
		$dir  = strtoupper( $direction ) === 'ASC' ? 'ASC' : 'DESC';
		$rows = rsaip_repo( \RecipeSeoAiPro\Database\Repositories\PostMetricsRepository::class )->select_by_seo_score( $dir, $limit );

		$out = array();
		foreach ( $rows as $r ) {
			$pid = absint( $r['post_id'] ?? 0 );
			if ( $pid <= 0 ) {
				continue;
			}
			$out[] = array(
				'post_id'   => $pid,
				'title'     => get_the_title( $pid ),
				'url'       => get_permalink( $pid ),
				'seo_score' => absint( $r['seo_score'] ?? 0 ),
			);
		}
		return $out;
	}

	private function render_report_html( array $report ): string {
		$type = (string) ( $report['type'] ?? '' );
		$period = $report['period'] ?? array();
		$stats  = $report['stats'] ?? array();

		$out  = '<h2>' . esc_html__( 'SEO Report', 'recipe-seo-ai-pro' ) . ' (' . esc_html( $type ) . ')</h2>';
		$out .= '<div class="rsaip-sub">' . esc_html( (string) ( $period['start'] ?? '' ) ) . ' → ' . esc_html( (string) ( $period['end'] ?? '' ) ) . '</div>';

		$out .= '<h3>' . esc_html__( 'Key Stats', 'recipe-seo-ai-pro' ) . '</h3>';
		$out .= '<div class="rsaip-grid rsaip-report-grid">';
		foreach ( $stats as $k => $v ) {
			$out .= '<div><div class="rsaip-k">' . esc_html( (string) $k ) . '</div><div class="rsaip-v">' . esc_html( (string) $v ) . '</div></div>';
		}
		$out .= '</div>';

		$out .= $this->render_posts_table( __( 'Lowest SEO Score (Top 10)', 'recipe-seo-ai-pro' ), $report['top_low_score_posts'] ?? array() );
		$out .= $this->render_orphans_table( __( 'Orphan Posts (Top 10)', 'recipe-seo-ai-pro' ), $report['top_orphan_posts'] ?? array() );

		if ( ! empty( $report['gsc_top_opportunities'] ) && is_array( $report['gsc_top_opportunities'] ) ) {
			$out .= '<h3>' . esc_html__( 'GSC Top Opportunities', 'recipe-seo-ai-pro' ) . '</h3>';
			$out .= '<div class="rsaip-sub">' . esc_html__( 'Queries with high impressions and average position around 8–15.', 'recipe-seo-ai-pro' ) . '</div>';
			$out .= $this->render_gsc_opportunities_table( $report['gsc_top_opportunities'] );
		}

		if ( ! empty( $report['gsc_low_hanging_fruits'] ) && is_array( $report['gsc_low_hanging_fruits'] ) ) {
			$out .= '<h3>' . esc_html__( 'Low Hanging Fruits', 'recipe-seo-ai-pro' ) . '</h3>';
			$out .= $this->render_gsc_low_table( $report['gsc_low_hanging_fruits'] );
		}

		return $out;
	}

	private function render_posts_table( string $title, array $rows ): string {
		$out = '<h3>' . esc_html( $title ) . '</h3>';
		if ( empty( $rows ) ) {
			return $out . '<div>—</div>';
		}
		$out .= '<table class="widefat striped rsaip-table"><thead><tr><th>' . esc_html__( 'Post', 'recipe-seo-ai-pro' ) . '</th><th>' . esc_html__( 'SEO Score', 'recipe-seo-ai-pro' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$out .= '<tr><td><a href="' . esc_url( (string) ( $r['url'] ?? '' ) ) . '" target="_blank" rel="noopener">' . esc_html( (string) ( $r['title'] ?? '' ) ) . '</a></td><td>' . esc_html( (string) ( $r['seo_score'] ?? '' ) ) . '</td></tr>';
		}
		$out .= '</tbody></table>';
		return $out;
	}

	private function render_orphans_table( string $title, array $rows ): string {
		$out = '<h3>' . esc_html( $title ) . '</h3>';
		if ( empty( $rows ) ) {
			return $out . '<div>—</div>';
		}
		$out .= '<table class="widefat striped rsaip-table"><thead><tr><th>' . esc_html__( 'Post', 'recipe-seo-ai-pro' ) . '</th><th>' . esc_html__( 'Word Count', 'recipe-seo-ai-pro' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$pid = absint( $r['post_id'] ?? 0 );
			$out .= '<tr><td><a href="' . esc_url( get_permalink( $pid ) ) . '" target="_blank" rel="noopener">' . esc_html( get_the_title( $pid ) ) . '</a></td><td>' . esc_html( (string) absint( $r['word_count'] ?? 0 ) ) . '</td></tr>';
		}
		$out .= '</tbody></table>';
		return $out;
	}

	private function render_gsc_opportunities_table( array $rows ): string {
		$out = '<table class="widefat striped rsaip-table"><thead><tr><th>' . esc_html__( 'Keyword', 'recipe-seo-ai-pro' ) . '</th><th>' . esc_html__( 'Position', 'recipe-seo-ai-pro' ) . '</th><th>' . esc_html__( 'Impressions', 'recipe-seo-ai-pro' ) . '</th><th>' . esc_html__( 'Clicks', 'recipe-seo-ai-pro' ) . '</th><th>' . esc_html__( 'CTR', 'recipe-seo-ai-pro' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			if ( isset( $r['error'] ) ) {
				$out .= '<tr><td colspan="5">' . esc_html( (string) $r['error'] ) . '</td></tr>';
				continue;
			}
			$out .= '<tr><td>' . esc_html( (string) ( $r['keyword'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $r['position'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $r['impressions'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $r['clicks'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $r['ctr'] ?? '' ) ) . '%</td></tr>';
		}
		$out .= '</tbody></table>';
		return $out;
	}

	private function render_gsc_low_table( array $rows ): string {
		$out = '<table class="widefat striped rsaip-table"><thead><tr><th>' . esc_html__( 'Page', 'recipe-seo-ai-pro' ) . '</th><th>' . esc_html__( 'Query', 'recipe-seo-ai-pro' ) . '</th><th>' . esc_html__( 'Position', 'recipe-seo-ai-pro' ) . '</th><th>' . esc_html__( 'Impressions', 'recipe-seo-ai-pro' ) . '</th><th>' . esc_html__( 'Clicks', 'recipe-seo-ai-pro' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			if ( isset( $r['error'] ) ) {
				$out .= '<tr><td colspan="5">' . esc_html( (string) $r['error'] ) . '</td></tr>';
				continue;
			}
			$page = (string) ( $r['page'] ?? '' );
			$out .= '<tr><td><a href="' . esc_url( $page ) . '" target="_blank" rel="noopener">' . esc_html( $page ) . '</a></td><td>' . esc_html( (string) ( $r['query'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $r['position'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $r['impressions'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $r['clicks'] ?? '' ) ) . '</td></tr>';
		}
		$out .= '</tbody></table>';
		return $out;
	}

	private function to_export_rows( array $report ): array {
		$rows = array();
		$stats = $report['stats'] ?? array();
		foreach ( $stats as $k => $v ) {
			$rows[] = $this->pad_export_row( array( 'metric', (string) $k, (string) $v ) );
		}

		$rows[] = $this->pad_export_row( array( 'section', 'lowest_seo_score_posts', '' ) );
		foreach ( (array) ( $report['top_low_score_posts'] ?? array() ) as $r ) {
			$rows[] = $this->pad_export_row( array( 'post', (string) ( $r['title'] ?? '' ), (string) ( $r['seo_score'] ?? '' ), (string) ( $r['url'] ?? '' ) ) );
		}

		$rows[] = $this->pad_export_row( array( 'section', 'orphan_posts', '' ) );
		foreach ( (array) ( $report['top_orphan_posts'] ?? array() ) as $r ) {
			$pid = absint( $r['post_id'] ?? 0 );
			$rows[] = $this->pad_export_row( array( 'post', (string) get_the_title( $pid ), (string) absint( $r['word_count'] ?? 0 ), (string) get_permalink( $pid ) ) );
		}

		if ( ! empty( $report['gsc_top_opportunities'] ) ) {
			$rows[] = $this->pad_export_row( array( 'section', 'gsc_top_opportunities', '' ) );
			foreach ( (array) $report['gsc_top_opportunities'] as $r ) {
				if ( isset( $r['error'] ) ) {
					$rows[] = $this->pad_export_row( array( 'error', (string) $r['error'] ) );
					continue;
				}
				$rows[] = $this->pad_export_row( array( 'query', (string) ( $r['keyword'] ?? '' ), (string) ( $r['position'] ?? '' ), (string) ( $r['impressions'] ?? '' ), (string) ( $r['clicks'] ?? '' ), (string) ( $r['ctr'] ?? '' ) ) );
			}
		}

		if ( ! empty( $report['gsc_low_hanging_fruits'] ) ) {
			$rows[] = $this->pad_export_row( array( 'section', 'gsc_low_hanging_fruits', '' ) );
			foreach ( (array) $report['gsc_low_hanging_fruits'] as $r ) {
				if ( isset( $r['error'] ) ) {
					$rows[] = $this->pad_export_row( array( 'error', (string) $r['error'] ) );
					continue;
				}
				$rows[] = $this->pad_export_row( array( 'page_query', (string) ( $r['page'] ?? '' ), (string) ( $r['query'] ?? '' ), (string) ( $r['position'] ?? '' ), (string) ( $r['impressions'] ?? '' ), (string) ( $r['clicks'] ?? '' ), (string) ( $r['ctr'] ?? '' ) ) );
			}
		}

		return $rows;
	}

	private function pad_export_row( array $row ): array {
		return array_values( array_pad( array_slice( $row, 0, 7 ), 7, '' ) );
	}

	public function stream_csv( array $report, string $filename ): void {
		$rows = $report['rows'] ?? array();
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );

		$fh = fopen( 'php://output', 'w' );
		if ( ! $fh ) {
			exit;
		}
		foreach ( $rows as $r ) {
			fputcsv( $fh, array_map( 'strval', (array) $r ) );
		}
		fclose( $fh );
		exit;
	}

	public function stream_xlsx( array $report, string $filename ): void {
		$rows = $report['rows'] ?? array();
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$headers = array( 'type', 'col1', 'col2', 'col3', 'col4', 'col5', 'col6' );
		$bin = '';
		if ( class_exists( 'RSAIP_Export_XLSX' ) ) {
			$bin = RSAIP_Export_XLSX::build( $headers, $rows, 'Report' );
		}
		if ( $bin === '' ) {
			$this->stream_csv( $report, str_replace( '.xlsx', '.csv', $filename ) );
			return;
		}

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $bin ) );
		echo $bin;
		exit;
	}

	public function stream_pdf( array $report, string $filename ): void {
		if ( ! class_exists( 'RSAIP_Export_PDF' ) ) {
			$this->stream_csv( $report, str_replace( '.pdf', '.csv', $filename ) );
			return;
		}

		$lines = array();
		$lines[] = 'Type: ' . (string) ( $report['type'] ?? '' );
		$period = $report['period'] ?? array();
		$lines[] = 'Period: ' . (string) ( $period['start'] ?? '' ) . ' -> ' . (string) ( $period['end'] ?? '' );

		$stats = $report['stats'] ?? array();
		foreach ( $stats as $k => $v ) {
			$lines[] = (string) $k . ': ' . (string) $v;
		}

		$lines[] = '';
		$lines[] = 'Lowest SEO score posts:';
		foreach ( (array) ( $report['top_low_score_posts'] ?? array() ) as $r ) {
			$lines[] = '- ' . (string) ( $r['seo_score'] ?? '' ) . ' | ' . (string) ( $r['title'] ?? '' ) . ' | ' . (string) ( $r['url'] ?? '' );
		}

		$bin = RSAIP_Export_PDF::build_simple( 'Recipe SEO AI Pro Report', $lines );

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $bin ) );
		echo $bin;
		exit;
	}
}

