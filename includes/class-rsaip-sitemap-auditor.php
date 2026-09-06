<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSAIP_Sitemap_Auditor {
	public function audit( string $sitemap_url, int $limit ): array {
		$sitemap_url = trim( $sitemap_url );
		if ( $sitemap_url === '' ) {
			$sitemap_url = home_url( '/sitemap_index.xml' );
		}
		$sitemap_url = esc_url_raw( $sitemap_url );
		if ( $sitemap_url === '' || ! rsaip_is_safe_remote_url( $sitemap_url ) ) {
			return array(
				array(
					'url'   => '',
					'http'  => '',
					'index' => '',
					'note'  => 'invalid_sitemap_url',
				),
			);
		}

		$urls = $this->fetch_sitemap_urls( $sitemap_url, $limit );
		if ( is_wp_error( $urls ) ) {
			return array(
				array(
					'url'   => $sitemap_url,
					'http'  => '',
					'index' => '',
					'note'  => $urls->get_error_message(),
				),
			);
		}

		$gsc = new RSAIP_GSC();
		$indexed_ids = $gsc->get_indexed_post_ids_cached();
		$indexed_urls = array();
		if ( is_array( $indexed_ids ) ) {
			foreach ( $indexed_ids as $pid ) {
				$indexed_urls[ get_permalink( (int) $pid ) ] = true;
			}
		}

		$rows = array();
		foreach ( $urls as $u ) {
			$u = esc_url_raw( (string) $u );
			if ( $u === '' || ! rsaip_is_safe_remote_url( $u ) ) {
				$rows[] = array(
					'url'   => $u,
					'http'  => '',
					'index' => '',
					'note'  => 'unsafe_url_skipped',
				);
				continue;
			}
			$http = $this->head_status( $u );
			$indexed = 'unknown';
			if ( is_array( $indexed_ids ) ) {
				$indexed = isset( $indexed_urls[ $u ] ) ? 'yes' : 'no';
			}
			$note = '';
			if ( $http >= 400 || $http === 0 ) {
				$note = 'broken_url';
			}
			$rows[] = array(
				'url'   => $u,
				'http'  => $http,
				'index' => $indexed,
				'note'  => $note,
			);
		}

		$missing = $this->find_missing_post_urls( $urls, 50 );
		foreach ( $missing as $m ) {
			$rows[] = array(
				'url'   => $m,
				'http'  => '',
				'index' => '',
				'note'  => 'missing_from_sitemap',
			);
		}

		return $rows;
	}

	private function fetch_sitemap_urls( string $url, int $limit ) {
		$url = esc_url_raw( $url );
		if ( $url === '' || ! rsaip_is_safe_remote_url( $url ) ) {
			return new WP_Error( 'rsaip_sitemap_unsafe', 'Unsafe sitemap URL blocked.' );
		}

		$resp = $this->safe_remote_get(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 0,
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$body = (string) wp_remote_retrieve_body( $resp );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'rsaip_sitemap_http', 'Sitemap fetch failed: ' . $code );
		}
		return $this->parse_sitemap_xml( $body, $url, $limit );
	}

	private function parse_sitemap_xml( string $xml, string $base_url, int $limit ) {
		$prev = libxml_use_internal_errors( true );
		$sx = simplexml_load_string( $xml );
		$errs = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $sx ) {
			$msg = 'Invalid XML';
			if ( $errs ) {
				$msg = trim( (string) ( $errs[0]->message ?? $msg ) );
			}
			return new WP_Error( 'rsaip_sitemap_xml', $msg );
		}

		$name = $sx->getName();
		$urls = array();

		if ( $name === 'sitemapindex' ) {
			$count = 0;
			foreach ( $sx->sitemap as $sm ) {
				$loc = (string) $sm->loc;
				$loc = esc_url_raw( $loc );
				if ( $loc === '' || ! rsaip_is_safe_remote_url( $loc ) ) {
					continue;
				}
				$count++;
				$child_urls = $this->fetch_sitemap_urls( $loc, max( 1, (int) floor( $limit / 5 ) ) );
				if ( is_wp_error( $child_urls ) ) {
					continue;
				}
				foreach ( (array) $child_urls as $u ) {
					$u = esc_url_raw( (string) $u );
					if ( $u === '' || ! rsaip_is_safe_remote_url( $u ) ) {
						continue;
					}
					$urls[] = $u;
					if ( count( $urls ) >= $limit ) {
						break 2;
					}
				}
				if ( $count >= 5 && count( $urls ) >= $limit ) {
					break;
				}
			}
		} elseif ( $name === 'urlset' ) {
			foreach ( $sx->url as $u ) {
				$loc = (string) $u->loc;
				$loc = esc_url_raw( $loc );
				if ( $loc === '' || ! rsaip_is_safe_remote_url( $loc ) ) {
					continue;
				}
				$urls[] = $loc;
				if ( count( $urls ) >= $limit ) {
					break;
				}
			}
		} else {
			return new WP_Error( 'rsaip_sitemap_format', 'Unsupported sitemap format: ' . $name );
		}

		$urls = array_values( array_unique( $urls ) );
		return $urls;
	}

	private function head_status( string $url ): int {
		$url = esc_url_raw( $url );
		if ( $url === '' || ! rsaip_is_safe_remote_url( $url ) ) {
			return 0;
		}

		$resp = $this->safe_remote_head(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 0,
			)
		);
		if ( is_wp_error( $resp ) ) {
			$resp = $this->safe_remote_get(
				$url,
				array(
					'timeout'     => 10,
					'redirection' => 0,
				)
			);
		}
		if ( is_wp_error( $resp ) ) {
			return 0;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		if ( in_array( $code, array( 405, 501 ), true ) ) {
			$resp = $this->safe_remote_get(
				$url,
				array(
					'timeout'     => 10,
					'redirection' => 0,
				)
			);
			if ( is_wp_error( $resp ) ) {
				return 0;
			}
			$code = (int) wp_remote_retrieve_response_code( $resp );
		}
		return $code;
	}

	/**
	 * @param array<string, mixed> $args Request args.
	 * @return array|\WP_Error
	 */
	private function safe_remote_get( string $url, array $args ) {
		if ( function_exists( 'wp_safe_remote_get' ) ) {
			return wp_safe_remote_get( $url, $args );
		}
		return wp_remote_get( $url, $args );
	}

	/**
	 * @param array<string, mixed> $args Request args.
	 * @return array|\WP_Error
	 */
	private function safe_remote_head( string $url, array $args ) {
		if ( function_exists( 'wp_safe_remote_head' ) ) {
			return wp_safe_remote_head( $url, $args );
		}
		return wp_remote_head( $url, $args );
	}

	private function find_missing_post_urls( array $sitemap_urls, int $max_missing ): array {
		$set = array();
		foreach ( $sitemap_urls as $u ) {
			$set[ untrailingslashit( (string) $u ) ] = true;
		}

		$settings = rsaip_get_settings();
		$types    = (array) ( $settings['auto_insert_post_types'] ?? array( 'post' ) );
		$q        = new WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => 'publish',
				'posts_per_page'         => min( 200, max( 20, $max_missing * 4 ) ),
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$missing = array();
		foreach ( $q->posts as $pid ) {
			$permalink = get_permalink( (int) $pid );
			if ( ! is_string( $permalink ) || $permalink === '' ) {
				continue;
			}
			$key = untrailingslashit( $permalink );
			if ( ! isset( $set[ $key ] ) ) {
				$missing[] = $permalink;
				if ( count( $missing ) >= $max_missing ) {
					break;
				}
			}
		}

		return $missing;
	}
}
