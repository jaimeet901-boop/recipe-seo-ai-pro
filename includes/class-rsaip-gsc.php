<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSAIP_GSC {
	public function test_connection(): array {
		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return array(
				'ok'      => false,
				'message' => $token->get_error_message(),
			);
		}

		$resp = $this->request(
			'GET',
			'https://searchconsole.googleapis.com/webmasters/v3/sites',
			null
		);

		if ( is_wp_error( $resp ) ) {
			return array(
				'ok'      => false,
				'message' => $resp->get_error_message(),
			);
		}

		return array(
			'ok'      => true,
			'message' => 'connected',
			'data'    => $resp,
		);
	}

	public function get_indexed_post_ids_cached(): ?array {
		$settings = rsaip_get_settings();
		if ( empty( $settings['gsc_site_url'] ) || empty( $settings['gsc_service_account_email'] ) || empty( $settings['gsc_private_key_pem'] ) ) {
			return null;
		}

		$cached = get_transient( 'rsaip_gsc_indexed_posts' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$end   = gmdate( 'Y-m-d' );
		$start = gmdate( 'Y-m-d', strtotime( '-' . absint( $settings['gsc_lookback_days'] ) . ' days' ) );

		$rows = $this->search_analytics(
			array(
				'startDate'  => $start,
				'endDate'    => $end,
				'dimensions' => array( 'page' ),
				'rowLimit'   => 500,
			)
		);

		if ( is_wp_error( $rows ) ) {
			return null;
		}

		$ids = array();
		foreach ( (array) ( $rows['rows'] ?? array() ) as $r ) {
			$page = $r['keys'][0] ?? '';
			$imp  = isset( $r['impressions'] ) ? (int) $r['impressions'] : 0;
			if ( ! is_string( $page ) || $page === '' || $imp <= 0 ) {
				continue;
			}
			$pid = url_to_postid( $page );
			if ( $pid > 0 ) {
				$ids[ $pid ] = true;
			}
		}

		$list = array_map( 'absint', array_keys( $ids ) );
		set_transient( 'rsaip_gsc_indexed_posts', $list, 12 * HOUR_IN_SECONDS );
		return $list;
	}

	public function get_top_opportunities( int $limit ): array {
		$settings = rsaip_get_settings();
		$end   = gmdate( 'Y-m-d' );
		$start = gmdate( 'Y-m-d', strtotime( '-' . absint( $settings['gsc_lookback_days'] ) . ' days' ) );

		$data = $this->search_analytics(
			array(
				'startDate'  => $start,
				'endDate'    => $end,
				'dimensions' => array( 'query' ),
				'rowLimit'   => 250,
			)
		);

		if ( is_wp_error( $data ) ) {
			return array(
				array(
					'error' => $data->get_error_message(),
				),
			);
		}

		$rows = array();
		foreach ( (array) ( $data['rows'] ?? array() ) as $r ) {
			$query = $r['keys'][0] ?? '';
			$pos   = isset( $r['position'] ) ? (float) $r['position'] : 0.0;
			$imp   = isset( $r['impressions'] ) ? (int) $r['impressions'] : 0;
			$clk   = isset( $r['clicks'] ) ? (int) $r['clicks'] : 0;
			$ctr   = isset( $r['ctr'] ) ? (float) $r['ctr'] : 0.0;

			if ( ! is_string( $query ) || $query === '' ) {
				continue;
			}
			if ( $imp < 200 ) {
				continue;
			}
			if ( $pos < 8 || $pos > 15 ) {
				continue;
			}

			$rows[] = array(
				'keyword'       => $query,
				'position'      => round( $pos, 1 ),
				'impressions'   => $imp,
				'clicks'        => $clk,
				'ctr'           => round( $ctr * 100, 2 ),
				'recommendation'=> __( 'Improve the best matching article to reach top 5 (expand content, add internal links, improve CTR).', 'recipe-seo-ai-pro' ),
			);
		}

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				return ( $b['impressions'] <=> $a['impressions'] );
			}
		);

		return array_slice( $rows, 0, $limit );
	}

	public function get_low_hanging_fruits( int $limit ): array {
		$settings = rsaip_get_settings();
		$end   = gmdate( 'Y-m-d' );
		$start = gmdate( 'Y-m-d', strtotime( '-' . absint( $settings['gsc_lookback_days'] ) . ' days' ) );

		$data = $this->search_analytics(
			array(
				'startDate'  => $start,
				'endDate'    => $end,
				'dimensions' => array( 'page', 'query' ),
				'rowLimit'   => 500,
			)
		);

		if ( is_wp_error( $data ) ) {
			return array(
				array(
					'error' => $data->get_error_message(),
				),
			);
		}

		$rows = array();
		foreach ( (array) ( $data['rows'] ?? array() ) as $r ) {
			$page  = $r['keys'][0] ?? '';
			$query = $r['keys'][1] ?? '';
			$pos   = isset( $r['position'] ) ? (float) $r['position'] : 0.0;
			$imp   = isset( $r['impressions'] ) ? (int) $r['impressions'] : 0;
			$clk   = isset( $r['clicks'] ) ? (int) $r['clicks'] : 0;
			$ctr   = isset( $r['ctr'] ) ? (float) $r['ctr'] : 0.0;

			if ( ! is_string( $page ) || $page === '' || ! is_string( $query ) || $query === '' ) {
				continue;
			}
			if ( $imp <= 500 ) {
				continue;
			}
			if ( $pos < 5 || $pos > 20 ) {
				continue;
			}
			$expected = (int) floor( $imp * 0.03 );
			if ( $clk >= max( 5, $expected ) ) {
				continue;
			}

			$rows[] = array(
				'page'       => $page,
				'query'      => $query,
				'position'   => round( $pos, 1 ),
				'impressions'=> $imp,
				'clicks'     => $clk,
				'ctr'        => round( $ctr * 100, 2 ),
			);
		}

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				return ( $b['impressions'] <=> $a['impressions'] );
			}
		);

		return array_slice( $rows, 0, $limit );
	}

	public function render_opportunities_rows_html( array $rows ): string {
		$out = '';
		foreach ( (array) $rows as $r ) {
			if ( isset( $r['error'] ) ) {
				$out .= '<tr><td colspan="6">' . esc_html( (string) $r['error'] ) . '</td></tr>';
				continue;
			}
			$out .= '<tr>';
			$out .= '<td>' . esc_html( (string) ( $r['keyword'] ?? '' ) ) . '</td>';
			$out .= '<td>' . esc_html( (string) ( $r['position'] ?? '' ) ) . '</td>';
			$out .= '<td>' . esc_html( (string) ( $r['impressions'] ?? '' ) ) . '</td>';
			$out .= '<td>' . esc_html( (string) ( $r['clicks'] ?? '' ) ) . '</td>';
			$out .= '<td>' . esc_html( (string) ( $r['ctr'] ?? '' ) ) . '%</td>';
			$out .= '<td>' . esc_html( (string) ( $r['recommendation'] ?? '' ) ) . '</td>';
			$out .= '</tr>';
		}
		if ( $out === '' ) {
			$out = '<tr><td colspan="6">' . esc_html__( 'No opportunities found in the selected date range.', 'recipe-seo-ai-pro' ) . '</td></tr>';
		}
		return $out;
	}

	public function render_low_hanging_rows_html( array $rows ): string {
		$out = '';
		foreach ( (array) $rows as $r ) {
			if ( isset( $r['error'] ) ) {
				$out .= '<tr><td colspan="6">' . esc_html( (string) $r['error'] ) . '</td></tr>';
				continue;
			}
			$page = (string) ( $r['page'] ?? '' );
			$out .= '<tr>';
			$out .= '<td><a href="' . esc_url( $page ) . '" target="_blank" rel="noopener">' . esc_html( $page ) . '</a></td>';
			$out .= '<td>' . esc_html( (string) ( $r['query'] ?? '' ) ) . '</td>';
			$out .= '<td>' . esc_html( (string) ( $r['position'] ?? '' ) ) . '</td>';
			$out .= '<td>' . esc_html( (string) ( $r['impressions'] ?? '' ) ) . '</td>';
			$out .= '<td>' . esc_html( (string) ( $r['clicks'] ?? '' ) ) . '</td>';
			$out .= '<td>' . esc_html( (string) ( $r['ctr'] ?? '' ) ) . '%</td>';
			$out .= '</tr>';
		}
		if ( $out === '' ) {
			$out = '<tr><td colspan="6">' . esc_html__( 'No low hanging fruits found.', 'recipe-seo-ai-pro' ) . '</td></tr>';
		}
		return $out;
	}

	private function search_analytics( array $body ) {
		$settings = rsaip_get_settings();
		$site = (string) ( $settings['gsc_site_url'] ?? '' );
		$site = trim( $site );
		if ( $site === '' ) {
			return new WP_Error( 'rsaip_gsc_site', 'Missing GSC site URL' );
		}

		$endpoint = 'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site ) . '/searchAnalytics/query';
		return $this->request( 'POST', $endpoint, $body );
	}

	private function request( string $method, string $url, ?array $body ) {
		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$args = array(
			'method'      => $method,
			'timeout'     => 25,
			'redirection' => 0,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $token,
			),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body'] = wp_json_encode( $body );
		}

		$resp = wp_remote_request( $url, $args );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$raw  = (string) wp_remote_retrieve_body( $resp );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = 'GSC API error';
			if ( is_array( $data ) && ! empty( $data['error']['message'] ) ) {
				$msg = (string) $data['error']['message'];
			}
			return new WP_Error( 'rsaip_gsc_api', $msg, array( 'status' => $code, 'body' => $raw ) );
		}

		return is_array( $data ) ? $data : array();
	}

	private function get_access_token() {
		$settings = rsaip_get_settings();
		$email = (string) ( $settings['gsc_service_account_email'] ?? '' );
		$key_pem = (string) ( $settings['gsc_private_key_pem'] ?? '' );
		if ( trim( $email ) === '' || trim( $key_pem ) === '' ) {
			return new WP_Error( 'rsaip_gsc_credentials', 'Missing service account credentials' );
		}

		$cached = get_transient( 'rsaip_gsc_token' );
		if ( is_string( $cached ) && $cached !== '' ) {
			return $cached;
		}

		$jwt = $this->build_jwt( $email, $key_pem );
		if ( is_wp_error( $jwt ) ) {
			return $jwt;
		}

		$resp = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout'     => 25,
				'redirection' => 0,
				'body'        => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
			)
		);

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$raw  = (string) wp_remote_retrieve_body( $resp );
		$data = json_decode( $raw, true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) || empty( $data['access_token'] ) ) {
			$msg = 'Token request failed';
			if ( is_array( $data ) && ! empty( $data['error_description'] ) ) {
				$msg = (string) $data['error_description'];
			}
			return new WP_Error( 'rsaip_gsc_token', $msg, array( 'status' => $code, 'body' => $raw ) );
		}

		$token = (string) $data['access_token'];
		$ttl   = absint( $settings['gsc_token_cache_seconds'] ?? 3500 );
		set_transient( 'rsaip_gsc_token', $token, max( 60, $ttl ) );
		return $token;
	}

	private function build_jwt( string $email, string $key_pem ) {
		$header = array(
			'alg' => 'RS256',
			'typ' => 'JWT',
		);
		$now = time();
		$claims = array(
			'iss'   => $email,
			'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
			'aud'   => 'https://oauth2.googleapis.com/token',
			'iat'   => $now,
			'exp'   => $now + 3600,
		);

		$segments = array(
			$this->b64url( wp_json_encode( $header ) ),
			$this->b64url( wp_json_encode( $claims ) ),
		);
		$signing_input = implode( '.', $segments );

		$pkey = openssl_pkey_get_private( $key_pem );
		if ( ! $pkey ) {
			return new WP_Error( 'rsaip_gsc_key', 'Invalid private key PEM' );
		}

		$signature = '';
		$ok = openssl_sign( $signing_input, $signature, $pkey, OPENSSL_ALGO_SHA256 );
		openssl_pkey_free( $pkey );
		if ( ! $ok ) {
			return new WP_Error( 'rsaip_gsc_sign', 'JWT signing failed' );
		}

		$segments[] = $this->b64url( $signature );
		return implode( '.', $segments );
	}

	private function b64url( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}

