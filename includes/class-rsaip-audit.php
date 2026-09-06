<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSAIP_Audit {
	private RSAIP_Link_Graph $link_graph;
	private string $state_option = 'rsaip_audit_state';

	public function __construct( RSAIP_Link_Graph $link_graph ) {
		$this->link_graph = $link_graph;
	}

	public function get_dashboard_stats(): array {
		$cached = get_transient( 'rsaip_dashboard_stats' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$settings = rsaip_get_settings();
		$post_types = (array) ( $settings['auto_insert_post_types'] ?? array( 'post' ) );
		$statuses   = (array) ( $settings['auto_insert_statuses'] ?? array( 'publish' ) );

		$total = (int) ( new WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => $statuses,
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
			)
		) )->found_posts;

		$metrics = rsaip_repo( \RecipeSeoAiPro\Database\Repositories\PostMetricsRepository::class );

		$missing_meta     = $metrics->count_flag( 'missing_meta_desc' );
		$missing_feat     = $metrics->count_flag( 'missing_featured' );
		$missing_h2       = $metrics->count_flag( 'missing_h2' );
		$missing_h3       = $metrics->count_flag( 'missing_h3' );
		$missing_alt      = $metrics->count_flag( 'missing_alt_images', true );
		$low_links        = $metrics->count_flag( 'low_internal_links' );
		$missing_external = $metrics->count_flag( 'missing_external_links' );
		$thin             = $metrics->count_flag( 'thin_content' );
		$orphans          = $metrics->count_flag( 'orphan' );

		$indexed = 0;
		$non_indexed = 0;
		$gsc = new RSAIP_GSC();
		$indexed_ids = $gsc->get_indexed_post_ids_cached();
		if ( is_array( $indexed_ids ) ) {
			$indexed = count( $indexed_ids );
			$non_indexed = max( 0, $total - $indexed );
		}

		$data = array(
			'total_posts'              => $total,
			'indexed_posts'            => $indexed,
			'non_indexed_posts'        => $non_indexed,
			'orphan_posts'             => $orphans,
			'missing_meta_description' => $missing_meta,
			'missing_featured_images'  => $missing_feat,
			'missing_h2'               => $missing_h2,
			'missing_h3'               => $missing_h3,
			'missing_alt_text'         => $missing_alt,
			'low_internal_links'       => $low_links,
			'missing_external_links'   => $missing_external,
			'thin_content_pages'       => $thin,
		);

		set_transient( 'rsaip_dashboard_stats', $data, (int) $settings['cache_ttl_seconds'] );
		return $data;
	}

	public function run_full_audit( bool $interactive ): array {
		$settings = rsaip_get_settings();
		$batch    = (int) $settings['link_graph_rebuild_batch'];

		$state = get_option( $this->state_option, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}

		$offset = isset( $state['offset'] ) ? absint( $state['offset'] ) : 0;
		$total  = isset( $state['total'] ) ? absint( $state['total'] ) : 0;
		$done   = isset( $state['done'] ) ? (bool) $state['done'] : false;
		$started_at = isset( $state['started_at'] ) ? sanitize_text_field( (string) $state['started_at'] ) : '';

		if ( $done || $total === 0 || $offset === 0 ) {
			$total      = (int) $this->count_posts_for_audit();
			$offset     = 0;
			$done       = false;
			$started_at = RSAIP_DB::now_gmt_sql();
			$state      = array(
				'offset'     => 0,
				'total'      => $total,
				'done'       => false,
				'started_at' => $started_at,
			);
			update_option( $this->state_option, $state, false );
		}

		$start = microtime( true );
		$processed = 0;

		while ( $processed < $batch ) {
			$posts = $this->get_posts_for_audit( $batch, $offset );
			if ( empty( $posts ) ) {
				$state['done']        = true;
				$state['offset']      = $total;
				$state['finished_at'] = RSAIP_DB::now_gmt_sql();
				update_option( $this->state_option, $state, false );
				return array(
					'done'        => true,
					'processed'   => $total,
					'total'       => $total,
					'started_at'  => $started_at,
					'finished_at' => $state['finished_at'],
				);
			}

			foreach ( $posts as $post ) {
				$this->audit_post( $post );
				$offset++;
				$processed++;

				if ( ( microtime( true ) - $start ) > ( $interactive ? 14.0 : 7.0 ) ) {
					break 2;
				}
			}
		}

		$state['offset'] = $offset;
		update_option( $this->state_option, $state, false );

		return array(
			'done'       => false,
			'processed'  => min( $offset, $total ),
			'total'      => $total,
			'started_at' => $started_at,
		);
	}

	private function count_posts_for_audit(): int {
		$settings = rsaip_get_settings();
		$post_types = (array) ( $settings['auto_insert_post_types'] ?? array( 'post' ) );
		$statuses   = (array) ( $settings['auto_insert_statuses'] ?? array( 'publish' ) );
		$q = new WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => $statuses,
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
			)
		);
		return (int) $q->found_posts;
	}

	private function get_posts_for_audit( int $limit, int $offset ): array {
		$settings = rsaip_get_settings();
		$post_types = (array) ( $settings['auto_insert_post_types'] ?? array( 'post' ) );
		$statuses   = (array) ( $settings['auto_insert_statuses'] ?? array( 'publish' ) );

		$q = new WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => $statuses,
				'posts_per_page'         => $limit,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return $q->posts;
	}

	private function audit_post( WP_Post $post ): void {
		$settings = rsaip_get_settings();
		$min_words = (int) $settings['thin_content_min_words'];

		$issues = array();

		$meta_desc = rsaip_get_meta_description( (int) $post->ID );
		$missing_meta = $meta_desc === '';
		if ( $missing_meta ) {
			$issues[] = 'missing_meta_description';
		}

		$has_featured = has_post_thumbnail( $post->ID );
		$missing_featured = ! $has_featured;
		if ( $missing_featured ) {
			$issues[] = 'missing_featured_image';
		}

		$h1 = preg_match( '/<h1\b[^>]*>/i', (string) $post->post_content ) === 1;
		$h2 = preg_match( '/<h2\b[^>]*>/i', (string) $post->post_content ) === 1;
		$h3 = preg_match( '/<h3\b[^>]*>/i', (string) $post->post_content ) === 1;
		if ( ! $h1 ) {
			$issues[] = 'missing_h1';
		}
		if ( ! $h2 ) {
			$issues[] = 'missing_h2';
		}
		if ( ! $h3 ) {
			$issues[] = 'missing_h3';
		}

		$word_count = rsaip_get_word_count_from_post( $post );
		$thin = $word_count > 0 && $word_count < $min_words;
		if ( $thin ) {
			$issues[] = 'thin_content';
		}

		$missing_alt_count = $this->count_missing_alt_in_post_content( (string) $post->post_content );
		$featured_alt_missing = 0;
		if ( $has_featured ) {
			$thumb_id = get_post_thumbnail_id( $post->ID );
			if ( $thumb_id ) {
				$alt = get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
				if ( ! is_string( $alt ) || trim( $alt ) === '' ) {
					$featured_alt_missing = 1;
				}
			}
		}
		$missing_alt_total = $missing_alt_count + $featured_alt_missing;
		if ( $missing_alt_total > 0 ) {
			$issues[] = 'missing_alt_text';
		}

		$internal_out = $this->get_internal_out( (int) $post->ID );
		$low_internal = $internal_out < 3;
		if ( $low_internal ) {
			$issues[] = 'low_internal_links';
		}
		$external_out = $this->get_external_out( (int) $post->ID );
		$missing_external = $external_out < 1;
		if ( $missing_external ) {
			$issues[] = 'missing_external_links';
		}

		$broken = $this->get_broken_links_counts_for_post( (int) $post->ID );
		if ( $broken['internal'] > 0 ) {
			$issues[] = 'broken_internal_links';
		}
		if ( $broken['external'] > 0 ) {
			$issues[] = 'broken_external_links';
		}
		if ( $broken['redirect_chains'] > 0 ) {
			$issues[] = 'redirect_chains';
		}

		$score = 100;
		if ( $missing_meta ) {
			$score -= 10;
		}
		if ( $missing_featured ) {
			$score -= 8;
		}
		if ( $missing_alt_total > 0 ) {
			$score -= min( 12, $missing_alt_total * 3 );
		}
		if ( ! $h2 ) {
			$score -= 6;
		}
		if ( ! $h3 ) {
			$score -= 4;
		}
		if ( $thin ) {
			$score -= 15;
		}
		if ( $low_internal ) {
			$score -= 10;
		}
		if ( $missing_external ) {
			$score -= 5;
		}
		if ( $broken['internal'] > 0 ) {
			$score -= min( 10, $broken['internal'] * 5 );
		}
		if ( $broken['external'] > 0 ) {
			$score -= min( 10, $broken['external'] * 4 );
		}
		if ( $broken['redirect_chains'] > 0 ) {
			$score -= min( 6, $broken['redirect_chains'] * 3 );
		}

		$score = max( 0, min( 100, $score ) );

		update_post_meta( $post->ID, 'rsaip_audit_issues', wp_json_encode( $issues ) );
		update_post_meta( $post->ID, 'rsaip_seo_score', (int) $score );

		$this->upsert_metrics(
			$post,
			array(
				'word_count'         => $word_count,
				'has_featured'       => $has_featured ? 1 : 0,
				'missing_meta_desc'  => $missing_meta ? 1 : 0,
				'missing_featured'   => $missing_featured ? 1 : 0,
				'missing_h2'         => ! $h2 ? 1 : 0,
				'missing_h3'         => ! $h3 ? 1 : 0,
				'missing_alt_images' => $missing_alt_total,
				'low_internal_links' => $low_internal ? 1 : 0,
				'missing_external_links' => $missing_external ? 1 : 0,
				'thin_content'       => $thin ? 1 : 0,
				'seo_score'          => (int) $score,
			)
		);
	}

	private function upsert_metrics( WP_Post $post, array $fields ): void {
		$now = RSAIP_DB::now_gmt_sql();
		rsaip_repo( \RecipeSeoAiPro\Database\Repositories\PostMetricsRepository::class )->upsert_audit_metrics(
			(int) $post->ID,
			(string) $post->post_type,
			(string) $post->post_status,
			array(
				'word_count'             => (int) ( $fields['word_count'] ?? 0 ),
				'has_featured'           => (int) ( $fields['has_featured'] ?? 0 ),
				'missing_meta_desc'      => (int) ( $fields['missing_meta_desc'] ?? 0 ),
				'missing_featured'       => (int) ( $fields['missing_featured'] ?? 0 ),
				'missing_h2'             => (int) ( $fields['missing_h2'] ?? 0 ),
				'missing_h3'             => (int) ( $fields['missing_h3'] ?? 0 ),
				'missing_alt_images'     => (int) ( $fields['missing_alt_images'] ?? 0 ),
				'low_internal_links'     => (int) ( $fields['low_internal_links'] ?? 0 ),
				'missing_external_links' => (int) ( $fields['missing_external_links'] ?? 0 ),
				'thin_content'           => (int) ( $fields['thin_content'] ?? 0 ),
				'seo_score'              => (int) ( $fields['seo_score'] ?? 0 ),
			),
			$now
		);
	}

	private function count_missing_alt_in_post_content( string $html ): int {
		if ( trim( $html ) === '' ) {
			return 0;
		}
		$count = 0;
		if ( preg_match_all( '/<img\b[^>]*>/i', $html, $m ) ) {
			foreach ( $m[0] as $img ) {
				$has_alt = preg_match( '/\salt=["\']([^"\']*)["\']/i', $img, $am );
				if ( ! $has_alt ) {
					$count++;
					continue;
				}
				$alt = (string) ( $am[1] ?? '' );
				if ( trim( html_entity_decode( $alt, ENT_QUOTES, 'UTF-8' ) ) === '' ) {
					$count++;
				}
			}
		}
		return $count;
	}

	private function get_internal_out( int $post_id ): int {
		return rsaip_repo( \RecipeSeoAiPro\Database\Repositories\PostMetricsRepository::class )->get_internal_out( $post_id );
	}

	private function get_external_out( int $post_id ): int {
		return rsaip_repo( \RecipeSeoAiPro\Database\Repositories\LinkGraphRepository::class )->count_external_out( $post_id );
	}

	private function get_broken_links_counts_for_post( int $post_id ): array {
		$rows = rsaip_repo( \RecipeSeoAiPro\Database\Repositories\LinkGraphRepository::class )->select_status_rows_for_post( $post_id );

		$broken_internal = 0;
		$broken_external = 0;
		$redirect_chains = 0;

		foreach ( $rows as $r ) {
			$type = (string) ( $r['link_type'] ?? '' );
			$code = isset( $r['http_status'] ) ? (int) $r['http_status'] : 0;
			$hops = isset( $r['redirect_hops'] ) ? (int) $r['redirect_hops'] : 0;
			if ( $hops >= 2 ) {
				$redirect_chains++;
			}
			if ( $code === 0 ) {
				continue;
			}
			if ( $code >= 400 ) {
				if ( $type === 'internal' ) {
					$broken_internal++;
				} else {
					$broken_external++;
				}
			}
		}

		return array(
			'internal'       => $broken_internal,
			'external'       => $broken_external,
			'redirect_chains'=> $redirect_chains,
		);
	}

	public function get_orphan_posts( int $limit ): array {
		return rsaip_repo( \RecipeSeoAiPro\Database\Repositories\PostMetricsRepository::class )->select_orphans( $limit );
	}

	public function render_orphans_rows_html( array $rows ): string {
		$out = '';
		foreach ( (array) $rows as $r ) {
			$pid = absint( $r['post_id'] ?? 0 );
			if ( $pid <= 0 ) {
				continue;
			}
			$post = get_post( $pid );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$cats = get_the_category( $pid );
			$cat_label = '';
			if ( is_array( $cats ) && ! empty( $cats ) ) {
				$cat_label = (string) $cats[0]->name;
			}
			$out .= '<tr>';
			$out .= '<td><a href="' . esc_url( get_permalink( $pid ) ) . '" target="_blank" rel="noopener">' . esc_html( get_the_title( $pid ) ) . '</a><div class="rsaip-sub">#' . esc_html( (string) $pid ) . '</div></td>';
			$out .= '<td>' . esc_html( $cat_label ) . '</td>';
			$out .= '<td>' . esc_html( mysql2date( get_option( 'date_format' ), (string) $post->post_date ) ) . '</td>';
			$out .= '<td>' . esc_html( (string) absint( $r['word_count'] ?? 0 ) ) . '</td>';
			$out .= '<td><button class="button rsaip-btn" data-action="rsaip_insert_links_for_post" data-post-id="' . esc_attr( (string) $pid ) . '">' . esc_html__( 'Fix Internal Links', 'recipe-seo-ai-pro' ) . '</button> <a class="button" href="' . esc_url( get_edit_post_link( $pid ) ) . '">' . esc_html__( 'Edit', 'recipe-seo-ai-pro' ) . '</a></td>';
			$out .= '</tr>';
		}
		if ( $out === '' ) {
			$out = '<tr><td colspan="5">' . esc_html__( 'No orphan posts found (or link graph not built yet).', 'recipe-seo-ai-pro' ) . '</td></tr>';
		}
		return $out;
	}

	public function get_audit_rows( int $limit ): array {
		$settings = rsaip_get_settings();
		$post_types = (array) ( $settings['auto_insert_post_types'] ?? array( 'post' ) );
		$statuses   = (array) ( $settings['auto_insert_statuses'] ?? array( 'publish' ) );

		$q = new WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => $statuses,
				'posts_per_page'         => $limit,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$out = array();
		foreach ( $q->posts as $p ) {
			$issues = get_post_meta( $p->ID, 'rsaip_audit_issues', true );
			$issues = is_string( $issues ) ? $issues : '';
			$d = json_decode( $issues, true );
			$d = is_array( $d ) ? array_values( array_filter( array_map( 'sanitize_text_field', $d ) ) ) : array();

			$score = get_post_meta( $p->ID, 'rsaip_seo_score', true );
			$out[] = array(
				'post_id'    => (int) $p->ID,
				'title'      => get_the_title( $p->ID ),
				'url'        => get_permalink( $p->ID ),
				'issues'     => $d,
				'seo_score'  => absint( $score ),
			);
		}

		return $out;
	}

	public function render_audit_rows_html( array $rows ): string {
		$out = '';
		foreach ( (array) $rows as $r ) {
			$pid = absint( $r['post_id'] ?? 0 );
			if ( $pid <= 0 ) {
				continue;
			}
			$title  = (string) ( $r['title'] ?? '' );
			$url    = (string) ( $r['url'] ?? '' );
			$issues = (array) ( $r['issues'] ?? array() );
			$score  = absint( $r['seo_score'] ?? 0 );

			$labels = array();
			foreach ( $issues as $k ) {
				$labels[] = '<span class="rsaip-pill">' . esc_html( $k ) . '</span>';
			}

			$out .= '<tr>';
			$out .= '<td><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $title ) . '</a><div class="rsaip-sub">#' . esc_html( (string) $pid ) . '</div></td>';
			$out .= '<td>' . ( $labels ? implode( ' ', $labels ) : esc_html__( 'No issues detected', 'recipe-seo-ai-pro' ) ) . '</td>';
			$out .= '<td><span class="rsaip-badge rsaip-score">' . esc_html( (string) $score ) . '</span></td>';
			$out .= '<td><a class="button" href="' . esc_url( get_edit_post_link( $pid ) ) . '">' . esc_html__( 'Edit', 'recipe-seo-ai-pro' ) . '</a></td>';
			$out .= '</tr>';
		}

		if ( $out === '' ) {
			$out = '<tr><td colspan="4">' . esc_html__( 'No audit data yet. Click “Run Audit”.', 'recipe-seo-ai-pro' ) . '</td></tr>';
		}
		return $out;
	}
}
