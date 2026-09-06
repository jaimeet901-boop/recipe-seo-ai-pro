<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSAIP_Internal_Link_Suggester {
	private RSAIP_Link_Graph $link_graph;

	public function __construct( RSAIP_Link_Graph $link_graph ) {
		$this->link_graph = $link_graph;
	}

	public function generate_for_post( int $post_id, int $limit_per_post ): array {
		$settings = rsaip_get_settings();

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array(
				'ok'        => false,
				'generated' => 0,
				'message'   => 'post_not_found',
			);
		}

		$title = get_the_title( $post_id );
		$cats  = wp_get_post_terms( $post_id, 'category', array( 'fields' => 'ids' ) );
		$tags  = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) );
		$keys  = rsaip_get_focus_keywords( $post_id );

		$cats = is_array( $cats ) ? array_values( array_filter( array_map( 'absint', $cats ) ) ) : array();
		$tags = is_array( $tags ) ? array_values( array_filter( array_map( 'absint', $tags ) ) ) : array();

		$keys_n = array();
		foreach ( $keys as $k ) {
			$kn = rsaip_text_normalize( (string) $k );
			if ( $kn !== '' ) {
				$keys_n[] = $kn;
			}
		}
		$keys_n = array_values( array_unique( $keys_n ) );

		$a = array(
			'id'    => $post_id,
			'title' => $title,
			'cats'  => $cats,
			'tags'  => $tags,
			'keys'  => $keys_n,
		);

		$post_types = $settings['auto_insert_post_types'] ?? array( 'post' );
		$statuses   = $settings['auto_insert_statuses'] ?? array( 'publish' );

		$tax_query = array();
		if ( $cats ) {
			$tax_query[] = array(
				'taxonomy' => 'category',
				'field'    => 'term_id',
				'terms'    => $cats,
				'operator' => 'IN',
			);
		}
		if ( $tags ) {
			$tax_query[] = array(
				'taxonomy' => 'post_tag',
				'field'    => 'term_id',
				'terms'    => $tags,
				'operator' => 'IN',
			);
		}
		if ( count( $tax_query ) > 1 ) {
			$tax_query = array_merge( array( 'relation' => 'OR' ), $tax_query );
		}

		$args = array(
			'post_type'              => $post_types,
			'post_status'            => $statuses,
			'posts_per_page'         => 250,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'post__not_in'           => array( $post_id ),
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		if ( $tax_query ) {
			$args['tax_query'] = $tax_query;
		}

		$q = new WP_Query( $args );
		$min_score = (int) $settings['min_relevance_score'];
		$scored = array();

		foreach ( (array) $q->posts as $cand ) {
			if ( ! $cand instanceof WP_Post ) {
				continue;
			}
			$bid = (int) $cand->ID;

			$b_cats = wp_get_post_terms( $bid, 'category', array( 'fields' => 'ids' ) );
			$b_tags = wp_get_post_terms( $bid, 'post_tag', array( 'fields' => 'ids' ) );
			$b_keys = rsaip_get_focus_keywords( $bid );

			$b_cats = is_array( $b_cats ) ? array_values( array_filter( array_map( 'absint', $b_cats ) ) ) : array();
			$b_tags = is_array( $b_tags ) ? array_values( array_filter( array_map( 'absint', $b_tags ) ) ) : array();

			$b_keys_n = array();
			foreach ( $b_keys as $k ) {
				$kn = rsaip_text_normalize( (string) $k );
				if ( $kn !== '' ) {
					$b_keys_n[] = $kn;
				}
			}
			$b_keys_n = array_values( array_unique( $b_keys_n ) );

			$b = array(
				'id'    => $bid,
				'title' => get_the_title( $bid ),
				'cats'  => $b_cats,
				'tags'  => $b_tags,
				'keys'  => $b_keys_n,
			);

			$score_data = $this->score_pair( $a, $b );
			if ( $score_data['score'] < $min_score ) {
				continue;
			}
			$scored[ $bid ] = $score_data;
		}

		if ( empty( $scored ) ) {
			return array(
				'ok'        => true,
				'generated' => 0,
				'message'   => 'no_candidates',
			);
		}

		uasort(
			$scored,
			static function ( array $x, array $y ): int {
				return ( $y['score'] <=> $x['score'] );
			}
		);

		$top = array_slice( $scored, 0, $limit_per_post, true );

		$repo      = rsaip_repo( \RecipeSeoAiPro\Database\Repositories\LinkSuggestionRepository::class );
		$now       = RSAIP_DB::now_gmt_sql();
		$generated = 0;
		foreach ( $top as $bid => $sd ) {
			$repo->upsert(
				(int) $post_id,
				(int) $bid,
				(int) $sd['score'],
				(string) wp_json_encode( $sd['reasons'] ),
				$now
			);
			$generated++;
		}

		return array(
			'ok'        => true,
			'generated' => $generated,
			'min_score' => $min_score,
		);
	}

	public function generate_for_all_posts( int $limit_per_post ): array {
		$settings = rsaip_get_settings();

		$post_types = $settings['auto_insert_post_types'] ?? array( 'post' );
		$statuses   = $settings['auto_insert_statuses'] ?? array( 'publish' );

		$posts_info = array();
		$cat_index  = array();
		$tag_index  = array();
		$key_index  = array();

		$page = 1;
		while ( true ) {
			$batch = get_posts(
				array(
					'post_type'              => $post_types,
					'post_status'            => $statuses,
					'fields'                 => 'ids',
					'posts_per_page'         => 250,
					'paged'                  => $page,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'suppress_filters'       => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			$batch = array_map( 'absint', (array) $batch );
			$batch = array_values( array_filter( $batch ) );
			if ( empty( $batch ) ) {
				break;
			}

			foreach ( $batch as $pid ) {
				$title = get_the_title( $pid );
				$cats  = wp_get_post_terms( $pid, 'category', array( 'fields' => 'ids' ) );
				$tags  = wp_get_post_terms( $pid, 'post_tag', array( 'fields' => 'ids' ) );
				$keys  = rsaip_get_focus_keywords( $pid );

				$cats = is_array( $cats ) ? array_values( array_filter( array_map( 'absint', $cats ) ) ) : array();
				$tags = is_array( $tags ) ? array_values( array_filter( array_map( 'absint', $tags ) ) ) : array();
				$keys_n = array();
				foreach ( $keys as $k ) {
					$kn = rsaip_text_normalize( (string) $k );
					if ( $kn !== '' ) {
						$keys_n[] = $kn;
					}
				}
				$keys_n = array_values( array_unique( $keys_n ) );

				$posts_info[ $pid ] = array(
					'id'    => $pid,
					'title' => $title,
					'cats'  => $cats,
					'tags'  => $tags,
					'keys'  => $keys_n,
				);

				foreach ( $cats as $c ) {
					if ( ! isset( $cat_index[ $c ] ) ) {
						$cat_index[ $c ] = array();
					}
					$cat_index[ $c ][ $pid ] = true;
				}
				foreach ( $tags as $t ) {
					if ( ! isset( $tag_index[ $t ] ) ) {
						$tag_index[ $t ] = array();
					}
					$tag_index[ $t ][ $pid ] = true;
				}
				foreach ( $keys_n as $k ) {
					if ( ! isset( $key_index[ $k ] ) ) {
						$key_index[ $k ] = array();
					}
					$key_index[ $k ][ $pid ] = true;
				}
			}

			$page++;
		}

		$repo = rsaip_repo( \RecipeSeoAiPro\Database\Repositories\LinkSuggestionRepository::class );
		$repo->truncate();

		$now       = RSAIP_DB::now_gmt_sql();
		$generated = 0;
		$min_score = (int) $settings['min_relevance_score'];

		foreach ( $posts_info as $pid => $a ) {
			$candidates = array();

			foreach ( $a['cats'] as $c ) {
				foreach ( array_keys( $cat_index[ $c ] ?? array() ) as $cand_id ) {
					$candidates[ $cand_id ] = true;
				}
			}
			foreach ( $a['tags'] as $t ) {
				foreach ( array_keys( $tag_index[ $t ] ?? array() ) as $cand_id ) {
					$candidates[ $cand_id ] = true;
				}
			}
			foreach ( $a['keys'] as $k ) {
				foreach ( array_keys( $key_index[ $k ] ?? array() ) as $cand_id ) {
					$candidates[ $cand_id ] = true;
				}
			}

			unset( $candidates[ $pid ] );
			if ( empty( $candidates ) ) {
				continue;
			}

			$scored = array();
			foreach ( array_keys( $candidates ) as $bid ) {
				if ( ! isset( $posts_info[ $bid ] ) ) {
					continue;
				}
				$b = $posts_info[ $bid ];
				$score_data = $this->score_pair( $a, $b );
				if ( $score_data['score'] < $min_score ) {
					continue;
				}
				$scored[ $bid ] = $score_data;
			}

			if ( empty( $scored ) ) {
				continue;
			}

			uasort(
				$scored,
				static function ( array $x, array $y ): int {
					return ( $y['score'] <=> $x['score'] );
				}
			);

			$top = array_slice( $scored, 0, $limit_per_post, true );
			foreach ( $top as $bid => $sd ) {
				$repo->upsert(
					(int) $pid,
					(int) $bid,
					(int) $sd['score'],
					(string) wp_json_encode( $sd['reasons'] ),
					$now
				);
				$generated++;
			}
		}

		return array(
			'posts'     => count( $posts_info ),
			'generated' => $generated,
			'min_score' => $min_score,
		);
	}

	private function score_pair( array $a, array $b ): array {
		$shared_cats = array_values( array_intersect( $a['cats'], $b['cats'] ) );
		$shared_tags = array_values( array_intersect( $a['tags'], $b['tags'] ) );
		$shared_keys = array_values( array_intersect( $a['keys'], $b['keys'] ) );

		$cat_score = min( 35, count( $shared_cats ) * 20 );
		$tag_score = min( 25, count( $shared_tags ) * 10 );
		$key_score = min( 25, count( $shared_keys ) * 25 );

		$title_a = rsaip_text_normalize( (string) $a['title'] );
		$title_b = rsaip_text_normalize( (string) $b['title'] );
		$title_sim = $this->token_overlap_percent( $title_a, $title_b );
		$title_score = (int) round( min( 15, ( $title_sim / 100 ) * 15 ) );

		$score = (int) min( 100, $cat_score + $tag_score + $key_score + $title_score );

		$reasons = array();
		if ( $shared_cats ) {
			$reasons[] = 'categories:' . count( $shared_cats );
		}
		if ( $shared_tags ) {
			$reasons[] = 'tags:' . count( $shared_tags );
		}
		if ( $shared_keys ) {
			$reasons[] = 'keywords:' . count( $shared_keys );
		}
		if ( $title_sim > 0 ) {
			$reasons[] = 'title:' . (string) (int) $title_sim . '%';
		}

		return array(
			'score'   => $score,
			'reasons' => $reasons,
		);
	}

	private function token_overlap_percent( string $a, string $b ): float {
		$ta = $this->tokenize( $a );
		$tb = $this->tokenize( $b );
		if ( empty( $ta ) || empty( $tb ) ) {
			return 0;
		}
		$shared = array_intersect( $ta, $tb );
		$base = max( count( $ta ), count( $tb ) );
		return ( count( $shared ) / $base ) * 100.0;
	}

	private function tokenize( string $s ): array {
		$s = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $s );
		$s = preg_replace( '/\s+/u', ' ', (string) $s );
		$s = trim( (string) $s );
		if ( $s === '' ) {
			return array();
		}
		$parts = preg_split( '/\s+/u', $s );
		if ( ! is_array( $parts ) ) {
			return array();
		}
		$stop = array(
			'the' => true,
			'and' => true,
			'of'  => true,
			'for' => true,
			'to'  => true,
			'a'   => true,
			'an'  => true,
			'in'  => true,
			'on'  => true,
			'with'=> true,
		);
		$out = array();
		foreach ( $parts as $p ) {
			$p = trim( (string) $p );
			if ( $p === '' ) {
				continue;
			}
			if ( isset( $stop[ $p ] ) ) {
				continue;
			}
			if ( mb_strlen( $p ) < 3 ) {
				continue;
			}
			$out[] = $p;
		}
		return array_values( array_unique( $out ) );
	}

	public function get_latest_suggestions_grouped( int $posts_limit ): array {
		$rows = rsaip_repo( \RecipeSeoAiPro\Database\Repositories\LinkSuggestionRepository::class )->select_latest(
			max( 10, $posts_limit * 10 )
		);

		$grouped = array();
		foreach ( $rows as $r ) {
			$pid = absint( $r['post_id'] ?? 0 );
			$sid = absint( $r['suggested_post_id'] ?? 0 );
			if ( $pid <= 0 || $sid <= 0 ) {
				continue;
			}
			if ( ! isset( $grouped[ $pid ] ) ) {
				$grouped[ $pid ] = array();
			}
			$grouped[ $pid ][] = array(
				'suggested_post_id' => $sid,
				'score'             => absint( $r['score'] ?? 0 ),
				'reasons'           => $this->decode_reasons( (string) ( $r['reasons'] ?? '' ) ),
			);
		}

		$final = array();
		foreach ( $grouped as $pid => $items ) {
			$final[] = array(
				'post_id'    => $pid,
				'post_title' => get_the_title( $pid ),
				'post_url'   => get_permalink( $pid ),
				'suggested'  => array_slice( $items, 0, 10 ),
			);
			if ( count( $final ) >= $posts_limit ) {
				break;
			}
		}

		return $final;
	}

	private function decode_reasons( string $json ): array {
		$d = json_decode( $json, true );
		if ( ! is_array( $d ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'sanitize_text_field', $d ) ) );
	}

	public function render_suggestions_rows_html( array $rows ): string {
		$out = '';
		foreach ( $rows as $row ) {
			$pid   = absint( $row['post_id'] ?? 0 );
			$title = (string) ( $row['post_title'] ?? '' );
			$url   = (string) ( $row['post_url'] ?? '' );
			$sugg  = $row['suggested'] ?? array();

			if ( $pid <= 0 ) {
				continue;
			}

			$sugg_links = array();
			$avg = 0;
			$c   = 0;
			foreach ( (array) $sugg as $s ) {
				$sid = absint( $s['suggested_post_id'] ?? 0 );
				if ( $sid <= 0 ) {
					continue;
				}
				$c++;
				$avg += absint( $s['score'] ?? 0 );
				$sugg_links[] = '<a href="' . esc_url( get_permalink( $sid ) ) . '" target="_blank" rel="noopener">' . esc_html( get_the_title( $sid ) ) . '</a>';
			}
			$avg_score = $c > 0 ? (int) round( $avg / $c ) : 0;

			$out .= '<tr>';
			$out .= '<td><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $title ) . '</a><div class="rsaip-sub">' . esc_html( '#' . $pid ) . '</div></td>';
			$out .= '<td>' . ( $sugg_links ? implode( '<br/>', $sugg_links ) : esc_html__( 'No suggestions yet', 'recipe-seo-ai-pro' ) ) . '</td>';
			$out .= '<td><span class="rsaip-badge">' . esc_html( (string) $avg_score ) . '</span></td>';
			$out .= '<td><button class="button rsaip-btn" data-action="rsaip_insert_links_for_post" data-post-id="' . esc_attr( (string) $pid ) . '">' . esc_html__( 'Insert Links', 'recipe-seo-ai-pro' ) . '</button></td>';
			$out .= '</tr>';
		}

		if ( $out === '' ) {
			$out = '<tr><td colspan="4">' . esc_html__( 'No data. Click “Generate Suggestions”.', 'recipe-seo-ai-pro' ) . '</td></tr>';
		}

		return $out;
	}
}
