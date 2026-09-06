<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSAIP_Link_Graph {
	private string $state_option = 'rsaip_link_graph_state';

	public function cron_rebuild(): void {
		$this->rebuild_all( false );
	}

	public function cron_check_broken_links(): void {
		$this->check_broken_links_batch( 60 );
	}

	public function rebuild_all( bool $interactive ): array {
		$settings = rsaip_get_settings();
		$batch    = (int) $settings['link_graph_rebuild_batch'];
		$state    = get_option( $this->state_option, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}

		$offset = isset( $state['offset'] ) ? absint( $state['offset'] ) : 0;
		$total  = isset( $state['total'] ) ? absint( $state['total'] ) : 0;
		$done   = isset( $state['done'] ) ? (bool) $state['done'] : false;
		$started_at = isset( $state['started_at'] ) ? sanitize_text_field( (string) $state['started_at'] ) : '';

		if ( $done || $total === 0 || $offset === 0 ) {
			$total      = (int) $this->count_posts_for_graph();
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
			$posts = $this->get_posts_for_graph( $batch, $offset );
			if ( empty( $posts ) ) {
				$this->finalize_graph_metrics();
				$state['done']       = true;
				$state['offset']     = $total;
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
				$this->index_post_links( $post );
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

	private function count_posts_for_graph(): int {
		$settings = rsaip_get_settings();
		$post_types = $settings['auto_insert_post_types'] ?? array( 'post' );
		$statuses   = $settings['auto_insert_statuses'] ?? array( 'publish' );
		$q = new WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => $statuses,
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'paged'          => 1,
				'no_found_rows'  => false,
			)
		);
		return (int) $q->found_posts;
	}

	private function get_posts_for_graph( int $limit, int $offset ): array {
		$settings = rsaip_get_settings();
		$post_types = $settings['auto_insert_post_types'] ?? array( 'post' );
		$statuses   = $settings['auto_insert_statuses'] ?? array( 'publish' );

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

	public function index_post_links( WP_Post $post ): array {
		$links_repo = rsaip_repo( \RecipeSeoAiPro\Database\Repositories\LinkGraphRepository::class );
		$links_repo->delete_by_from_post_id( (int) $post->ID );

		$links = $this->extract_links_from_html( (string) $post->post_content );

		$internal = 0;
		$now      = RSAIP_DB::now_gmt_sql();

		foreach ( $links as $link ) {
			$url = $link['url'];
			if ( $url === '' ) {
				continue;
			}
			if ( rsaip_string_starts_with( $url, '#' ) ) {
				continue;
			}
			if ( rsaip_string_starts_with( $url, 'mailto:' ) || rsaip_string_starts_with( $url, 'tel:' ) ) {
				continue;
			}

			$absolute = $this->to_absolute_url( $url );
			if ( $absolute === '' ) {
				continue;
			}

			$to_post_id = 0;
			$type       = 'external';

			$maybe_id = url_to_postid( $absolute );
			if ( $maybe_id > 0 ) {
				$to_post_id = $maybe_id;
				$type       = 'internal';
				$internal++;
				$absolute = get_permalink( $to_post_id );
			}

			$links_repo->insert_or_update_link(
				(int) $post->ID,
				(int) $to_post_id,
				$absolute,
				(string) $link['anchor'],
				$type,
				$now
			);
		}

		$this->upsert_post_metrics_basic( $post, $internal, $now );

		return array(
			'post_id'      => (int) $post->ID,
			'internal_out' => $internal,
			'links_found'  => count( $links ),
		);
	}

	private function upsert_post_metrics_basic( WP_Post $post, int $internal_out, string $now ): void {
		rsaip_repo( \RecipeSeoAiPro\Database\Repositories\PostMetricsRepository::class )->upsert_basic(
			(int) $post->ID,
			(string) $post->post_type,
			(string) $post->post_status,
			(int) rsaip_get_word_count_from_post( $post ),
			(int) $internal_out,
			$now
		);
	}

	private function finalize_graph_metrics(): void {
		$metrics = rsaip_repo( \RecipeSeoAiPro\Database\Repositories\PostMetricsRepository::class );
		$links   = rsaip_repo( \RecipeSeoAiPro\Database\Repositories\LinkGraphRepository::class );

		$metrics->reset_internal_in_and_orphan();

		$rows = $links->select_internal_inbound_counts();
		foreach ( $rows as $r ) {
			$pid = absint( $r['to_post_id'] ?? 0 );
			$c   = absint( $r['c'] ?? 0 );
			if ( $pid > 0 ) {
				$metrics->update_internal_in( $pid, $c );
			}
		}

		$metrics->mark_publish_orphans();
		update_option( 'rsaip_link_graph_last_rebuild', RSAIP_DB::now_gmt_sql(), false );
	}

	public function check_broken_links_batch( int $limit ): array {
		$settings  = rsaip_get_settings();
		$timeout   = (int) $settings['broken_link_timeout_seconds'];
		$links_repo = rsaip_repo( \RecipeSeoAiPro\Database\Repositories\LinkGraphRepository::class );

		$rows = $links_repo->select_stale_urls_for_check( $limit );

		$updated = 0;
		foreach ( $rows as $r ) {
			$id  = absint( $r['id'] ?? 0 );
			$url = esc_url_raw( (string) ( $r['url'] ?? '' ) );
			if ( $id <= 0 || $url === '' ) {
				continue;
			}

			// SSRF guard: never request unsafe / private / non-http(s) URLs.
			if ( ! rsaip_is_safe_remote_url( $url ) ) {
				$links_repo->update_check_result(
					$id,
					0,
					0,
					null,
					RSAIP_DB::now_gmt_sql()
				);
				$updated++;
				continue;
			}

			$status = $this->fetch_http_status_with_redirects( $url, $timeout, 5 );
			$code   = (int) $status['code'];
			$hops   = (int) $status['hops'];
			$final  = (string) $status['final_url'];

			$links_repo->update_check_result(
				$id,
				$code,
				$hops,
				$final !== '' ? $final : null,
				RSAIP_DB::now_gmt_sql()
			);
			$updated++;
		}

		return array(
			'checked' => count( $rows ),
			'updated' => $updated,
		);
	}

	private function fetch_http_status_with_redirects( string $url, int $timeout, int $max_hops ): array {
		$current = $url;
		$hops    = 0;
		$code    = 0;

		for ( $i = 0; $i <= $max_hops; $i++ ) {
			if ( ! rsaip_is_safe_remote_url( $current ) ) {
				return array(
					'code'      => 0,
					'hops'      => $hops,
					'final_url' => $current,
				);
			}

			$resp = $this->safe_remote_request(
				$current,
				array(
					'method'      => 'HEAD',
					'timeout'     => $timeout,
					'redirection' => 0,
				)
			);

			if ( is_wp_error( $resp ) ) {
				$resp = $this->safe_remote_request(
					$current,
					array(
						'method'      => 'GET',
						'timeout'     => $timeout,
						'redirection' => 0,
					)
				);
			}

			if ( is_wp_error( $resp ) ) {
				return array(
					'code'      => 0,
					'hops'      => $hops,
					'final_url' => $current,
				);
			}

			$code = (int) wp_remote_retrieve_response_code( $resp );
			if ( in_array( $code, array( 405, 501 ), true ) ) {
				$resp = $this->safe_remote_request(
					$current,
					array(
						'method'      => 'GET',
						'timeout'     => $timeout,
						'redirection' => 0,
					)
				);
				if ( is_wp_error( $resp ) ) {
					return array(
						'code'      => 0,
						'hops'      => $hops,
						'final_url' => $current,
					);
				}
				$code = (int) wp_remote_retrieve_response_code( $resp );
			}
			if ( $code >= 300 && $code < 400 ) {
				$loc = wp_remote_retrieve_header( $resp, 'location' );
				$loc = is_string( $loc ) ? trim( $loc ) : '';
				if ( $loc === '' ) {
					break;
				}
				$next = $this->resolve_redirect_url( $current, $loc );
				if ( $next === '' || ! rsaip_is_safe_remote_url( $next ) ) {
					break;
				}
				$hops++;
				$current = $next;
				continue;
			}

			break;
		}

		return array(
			'code'      => $code,
			'hops'      => $hops,
			'final_url' => $current,
		);
	}

	/**
	 * @param array<string, mixed> $args Request args.
	 * @return array|\WP_Error
	 */
	private function safe_remote_request( string $url, array $args ) {
		if ( function_exists( 'wp_safe_remote_request' ) ) {
			return wp_safe_remote_request( $url, $args );
		}
		return wp_remote_request( $url, $args );
	}

	private function resolve_redirect_url( string $base, string $location ): string {
		if ( preg_match( '/^https?:\/\//i', $location ) ) {
			$resolved = esc_url_raw( $location );
			return rsaip_is_safe_remote_url( $resolved ) ? $resolved : '';
		}
		if ( rsaip_string_starts_with( $location, '//' ) ) {
			$resolved = esc_url_raw( 'https:' . $location );
			return rsaip_is_safe_remote_url( $resolved ) ? $resolved : '';
		}
		$b = wp_parse_url( $base );
		if ( ! is_array( $b ) || empty( $b['scheme'] ) || empty( $b['host'] ) ) {
			return '';
		}
		$scheme = $b['scheme'];
		$host   = $b['host'];
		$port   = isset( $b['port'] ) ? ':' . (int) $b['port'] : '';

		if ( rsaip_string_starts_with( $location, '/' ) ) {
			$resolved = esc_url_raw( $scheme . '://' . $host . $port . $location );
			return rsaip_is_safe_remote_url( $resolved ) ? $resolved : '';
		}

		$path = $b['path'] ?? '/';
		$dir  = rtrim( (string) dirname( $path ), '/\\' );
		$dir  = $dir === '' ? '' : $dir;

		$resolved = esc_url_raw( $scheme . '://' . $host . $port . ( $dir ? '/' . $dir : '' ) . '/' . ltrim( $location, '/' ) );
		return rsaip_is_safe_remote_url( $resolved ) ? $resolved : '';
	}

	private function extract_links_from_html( string $html ): array {
		$results = array();
		if ( trim( $html ) === '' ) {
			return $results;
		}

		$prev = libxml_use_internal_errors( true );
		$doc  = new DOMDocument();
		$ok   = $doc->loadHTML( '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		if ( ! $ok ) {
			return $this->extract_links_regex( $html );
		}

		$anchors = $doc->getElementsByTagName( 'a' );
		foreach ( $anchors as $a ) {
			if ( ! $a instanceof DOMElement ) {
				continue;
			}
			$href = trim( (string) $a->getAttribute( 'href' ) );
			if ( $href === '' ) {
				continue;
			}
			$anchor = trim( wp_strip_all_tags( $a->textContent ?? '' ) );
			$results[] = array(
				'url'    => $href,
				'anchor' => mb_substr( $anchor, 0, 250 ),
			);
		}

		return $results;
	}

	private function extract_links_regex( string $html ): array {
		$results = array();
		if ( preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $row ) {
				$href   = trim( (string) ( $row[1] ?? '' ) );
				$anchor = trim( wp_strip_all_tags( (string) ( $row[2] ?? '' ) ) );
				if ( $href !== '' ) {
					$results[] = array(
						'url'    => $href,
						'anchor' => mb_substr( $anchor, 0, 250 ),
					);
				}
			}
		}
		return $results;
	}

	private function to_absolute_url( string $url ): string {
		$url = trim( $url );
		if ( $url === '' ) {
			return '';
		}
		if ( preg_match( '/^https?:\/\//i', $url ) ) {
			return esc_url_raw( $url );
		}
		if ( rsaip_string_starts_with( $url, '//' ) ) {
			return esc_url_raw( 'https:' . $url );
		}
		if ( rsaip_string_starts_with( $url, '/' ) ) {
			return esc_url_raw( home_url( $url ) );
		}
		return '';
	}
}
