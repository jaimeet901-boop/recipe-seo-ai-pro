<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSAIP_Auto_Linker {
	private RSAIP_Internal_Link_Suggester $suggester;

	public function __construct( RSAIP_Internal_Link_Suggester $suggester ) {
		$this->suggester = $suggester;
	}

	public function on_save_post( int $post_id, WP_Post $post, bool $update ): void {
		if ( ! function_exists( 'rsaip_never_modify_posts' ) || rsaip_never_modify_posts() ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$settings = rsaip_get_settings();
		if ( empty( $settings['auto_insert_internal_links'] ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, (array) $settings['auto_insert_post_types'], true ) ) {
			return;
		}
		if ( ! in_array( $post->post_status, (array) $settings['auto_insert_statuses'], true ) ) {
			return;
		}

		$this->insert_for_post_id( $post_id );
	}

	public function run_batch(): array {
		$settings = rsaip_get_settings();
		$post_types = (array) $settings['auto_insert_post_types'];
		$statuses   = (array) $settings['auto_insert_statuses'];

		$q = new WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => $statuses,
				'posts_per_page'         => 10,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'meta_query'             => array(
					array(
						'key'     => 'rsaip_auto_links_last',
						'compare' => 'NOT EXISTS',
					),
				),
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$done = 0;
		foreach ( $q->posts as $p ) {
			$r = $this->insert_for_post_id( (int) $p->ID );
			if ( ! empty( $r['inserted'] ) ) {
				$done++;
			}
		}

		return array(
			'processed' => count( $q->posts ),
			'updated'   => $done,
		);
	}

	public function insert_for_post_id( int $post_id ): array {
		if ( ! function_exists( 'rsaip_never_modify_posts' ) || rsaip_never_modify_posts() ) {
			return array(
				'inserted' => 0,
				'message'  => function_exists( 'rsaip_existing_post_mutation_frozen_message' )
					? rsaip_existing_post_mutation_frozen_message()
					: 'This existing-post mutation is temporarily disabled while Safe Article Mode is enabled.',
			);
		}

		$settings = rsaip_get_settings();
		$post     = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array( 'inserted' => 0, 'message' => 'not_found' );
		}
		if ( $post->post_status !== 'publish' ) {
			return array( 'inserted' => 0, 'message' => 'not_published' );
		}

		$max_links = (int) $settings['max_links_per_post'];
		$min_score = (int) $settings['min_relevance_score'];

		$content = (string) $post->post_content;
		$existing_internal = $this->count_internal_links_in_html( $content );
		if ( $existing_internal >= $max_links ) {
			update_post_meta( $post_id, 'rsaip_auto_links_last', RSAIP_DB::now_gmt_sql() );
			return array(
				'post_id'  => $post_id,
				'inserted' => 0,
				'message'  => 'already_max_links',
			);
		}

		$remaining = max( 1, $max_links - $existing_internal );

		$suggestions = $this->get_suggestions_for_post( $post_id, $remaining, $min_score );
		if ( empty( $suggestions ) ) {
			$this->suggester->generate_for_post( $post_id, $remaining );
			$suggestions = $this->get_suggestions_for_post( $post_id, $remaining, $min_score );
			if ( empty( $suggestions ) ) {
				update_post_meta( $post_id, 'rsaip_auto_links_last', RSAIP_DB::now_gmt_sql() );
				return array( 'inserted' => 0, 'message' => 'no_suggestions' );
			}
		}

		$inserted = $this->insert_links_into_html( $content, $suggestions, $remaining );
		$new_content = $inserted['html'] ?? $content;

		if ( (int) ( $inserted['count'] ?? 0 ) === 0 ) {
			$append = $this->append_related_section( $content, $suggestions, $remaining );
			if ( (int) $append['count'] > 0 && (string) $append['html'] !== $content ) {
				$inserted['count'] = (int) $append['count'];
				$new_content       = (string) $append['html'];
			}
		}

		if ( (int) ( $inserted['count'] ?? 0 ) > 0 && $new_content !== $content ) {
			remove_action( 'save_post', array( $this, 'on_save_post' ), 20 );
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => wp_slash( $new_content ),
				)
			);
			add_action( 'save_post', array( $this, 'on_save_post' ), 20, 3 );
		}

		update_post_meta( $post_id, 'rsaip_auto_links_last', RSAIP_DB::now_gmt_sql() );
		return array(
			'post_id'  => $post_id,
			'inserted' => (int) ( $inserted['count'] ?? 0 ),
		);
	}

	private function get_suggestions_for_post( int $post_id, int $limit, int $min_score ): array {
		$rows = rsaip_repo( \RecipeSeoAiPro\Database\Repositories\LinkSuggestionRepository::class )->select_for_post_min_score(
			$post_id,
			$min_score,
			$limit * 2
		);

		$out = array();
		foreach ( $rows as $r ) {
			$sid = absint( $r['suggested_post_id'] ?? 0 );
			if ( $sid <= 0 ) {
				continue;
			}
			$out[] = array(
				'post_id' => $sid,
				'url'     => get_permalink( $sid ),
				'title'   => get_the_title( $sid ),
				'score'   => absint( $r['score'] ?? 0 ),
				'anchors' => $this->build_anchor_candidates( $sid ),
			);
		}
		return $out;
	}

	private function append_related_section( string $html, array $suggestions, int $max_links ): array {
		$existing = $this->extract_existing_hrefs( $html );
		$existing_set = array();
		foreach ( $existing as $e ) {
			$existing_set[ $e ] = true;
		}

		$links = array();
		foreach ( $suggestions as $s ) {
			if ( count( $links ) >= $max_links ) {
				break;
			}
			$url = esc_url_raw( (string) ( $s['url'] ?? '' ) );
			if ( $url === '' ) {
				continue;
			}
			if ( isset( $existing_set[ $url ] ) ) {
				continue;
			}
			$title = sanitize_text_field( (string) ( $s['title'] ?? '' ) );
			if ( $title === '' ) {
				$title = $url;
			}
			$links[] = '<a href="' . esc_url( $url ) . '" rel="noopener">' . esc_html( $title ) . '</a>';
			$existing_set[ $url ] = true;
		}

		if ( empty( $links ) ) {
			return array(
				'count' => 0,
				'html'  => $html,
			);
		}

		$section = '<p><strong>' . esc_html__( 'Related:', 'recipe-seo-ai-pro' ) . '</strong> ' . implode( ' • ', $links ) . '</p>';
		$new_html = rtrim( $html ) . "\n" . $section;

		return array(
			'count' => count( $links ),
			'html'  => $new_html,
		);
	}

	private function count_internal_links_in_html( string $html ): int {
		$home = home_url( '/' );
		$home_parts = wp_parse_url( $home );
		$home_host  = is_array( $home_parts ) ? (string) ( $home_parts['host'] ?? '' ) : '';
		$home_host  = strtolower( $home_host );

		$prev = libxml_use_internal_errors( true );
		$doc  = new DOMDocument();
		$ok   = $doc->loadHTML( '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $ok ) {
			return 0;
		}

		$count = 0;
		$anchors = $doc->getElementsByTagName( 'a' );
		foreach ( $anchors as $a ) {
			if ( ! $a instanceof DOMElement ) {
				continue;
			}
			$href = trim( (string) $a->getAttribute( 'href' ) );
			if ( $href === '' ) {
				continue;
			}
			if ( rsaip_string_starts_with( $href, '/' ) ) {
				$count++;
				continue;
			}
			if ( ! preg_match( '/^https?:\/\//i', $href ) ) {
				continue;
			}
			$parts = wp_parse_url( $href );
			$host  = is_array( $parts ) ? (string) ( $parts['host'] ?? '' ) : '';
			if ( $host !== '' && strtolower( $host ) === $home_host ) {
				$count++;
			}
		}

		return $count;
	}

	private function build_anchor_candidates( int $suggested_post_id ): array {
		$anchors = array();
		$title = get_the_title( $suggested_post_id );
		if ( is_string( $title ) && trim( $title ) !== '' ) {
			$anchors[] = trim( $title );
		}

		$keys = rsaip_get_focus_keywords( $suggested_post_id );
		foreach ( $keys as $k ) {
			if ( is_string( $k ) && trim( $k ) !== '' ) {
				$anchors[] = trim( $k );
			}
		}

		$anchors = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $anchors ) ) ) );

		usort(
			$anchors,
			static function ( string $a, string $b ): int {
				return mb_strlen( $b ) <=> mb_strlen( $a );
			}
		);

		return array_slice( $anchors, 0, 6 );
	}

	private function insert_links_into_html( string $html, array $suggestions, int $max_links ): array {
		$existing = $this->extract_existing_hrefs( $html );
		$inserted_urls = array();
		foreach ( $existing as $e ) {
			$inserted_urls[ $e ] = true;
		}

		$prev = libxml_use_internal_errors( true );
		$doc  = new DOMDocument();
		$ok   = $doc->loadHTML( '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $ok ) {
			return array( 'count' => 0, 'html' => $html );
		}

		$xpath = new DOMXPath( $doc );
		$text_nodes = $xpath->query( '//text()[not(ancestor::a) and not(ancestor::script) and not(ancestor::style)]' );
		if ( ! $text_nodes ) {
			return array( 'count' => 0, 'html' => $html );
		}

		$count = 0;
		foreach ( $suggestions as $s ) {
			if ( $count >= $max_links ) {
				break;
			}
			$url = esc_url_raw( (string) ( $s['url'] ?? '' ) );
			if ( $url === '' ) {
				continue;
			}
			if ( isset( $inserted_urls[ $url ] ) ) {
				continue;
			}

			$anchors = (array) ( $s['anchors'] ?? array() );
			$did = false;

			foreach ( $anchors as $anchor ) {
				$anchor = (string) $anchor;
				if ( mb_strlen( $anchor ) < 4 ) {
					continue;
				}

				for ( $i = 0; $i < $text_nodes->length; $i++ ) {
					$node = $text_nodes->item( $i );
					if ( ! $node instanceof DOMText ) {
						continue;
					}

					$node_text = $node->nodeValue ?? '';
					if ( ! is_string( $node_text ) || $node_text === '' ) {
						continue;
					}

					$pos = mb_stripos( $node_text, $anchor );
					if ( $pos === false ) {
						continue;
					}

					$before = mb_substr( $node_text, 0, (int) $pos );
					$match  = mb_substr( $node_text, (int) $pos, mb_strlen( $anchor ) );
					$after  = mb_substr( $node_text, (int) $pos + mb_strlen( $anchor ) );

					$link_el = $doc->createElement( 'a' );
					$link_el->setAttribute( 'href', $url );
					$link_el->setAttribute( 'rel', 'noopener' );
					$link_el->appendChild( $doc->createTextNode( $match ) );

					$frag = $doc->createDocumentFragment();
					if ( $before !== '' ) {
						$frag->appendChild( $doc->createTextNode( $before ) );
					}
					$frag->appendChild( $link_el );
					if ( $after !== '' ) {
						$frag->appendChild( $doc->createTextNode( $after ) );
					}

					if ( $node->parentNode ) {
						$node->parentNode->replaceChild( $frag, $node );
					}
					$did = true;
					break 2;
				}
			}

			if ( $did ) {
				$inserted_urls[ $url ] = true;
				$count++;
			}
		}

		$new_html = $doc->saveHTML();
		if ( is_string( $new_html ) && $new_html !== '' ) {
			$new_html = preg_replace( '/^<meta[^>]+>\s*/i', '', $new_html );
			$new_html = (string) $new_html;
		} else {
			$new_html = $html;
		}

		return array(
			'count' => $count,
			'html'  => $new_html,
		);
	}

	private function extract_existing_hrefs( string $html ): array {
		$hrefs = array();
		$prev = libxml_use_internal_errors( true );
		$doc  = new DOMDocument();
		$ok   = $doc->loadHTML( '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( $ok ) {
			$anchors = $doc->getElementsByTagName( 'a' );
			foreach ( $anchors as $a ) {
				if ( ! $a instanceof DOMElement ) {
					continue;
				}
				$href = trim( (string) $a->getAttribute( 'href' ) );
				$href = esc_url_raw( $href );
				if ( $href !== '' ) {
					$hrefs[] = $href;
				}
			}
		}
		return array_values( array_unique( $hrefs ) );
	}
}
