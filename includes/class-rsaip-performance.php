<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSAIP_Performance {
	public function analyze( string $url, string $strategy ): array {
		$settings = rsaip_get_settings();
		$key = (string) ( $settings['psi_api_key'] ?? '' );
		$key = trim( $key );
		if ( $key === '' ) {
			return array(
				'ok'      => false,
				'message' => __( 'Missing PageSpeed Insights API key.', 'recipe-seo-ai-pro' ),
			);
		}

		$url = trim( $url );
		if ( $url === '' ) {
			$url = home_url( '/' );
		}
		$url = esc_url_raw( $url );
		if ( $url === '' || ! rsaip_is_safe_remote_url( $url ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Invalid URL.', 'recipe-seo-ai-pro' ),
			);
		}

		$strategy = $strategy === 'desktop' ? 'desktop' : 'mobile';

		$api = add_query_arg(
			array(
				'url'      => rawurlencode( $url ),
				'key'      => $key,
				'strategy' => $strategy,
				'category' => array( 'performance', 'seo' ),
			),
			'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'
		);

		$resp = wp_remote_get(
			$api,
			array(
				'timeout'     => 30,
				'redirection' => 0,
			)
		);
		if ( is_wp_error( $resp ) ) {
			return array(
				'ok'      => false,
				'message' => $resp->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$raw  = (string) wp_remote_retrieve_body( $resp );
		$data = json_decode( $raw, true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'PageSpeed API error.', 'recipe-seo-ai-pro' ),
			);
		}

		$lighthouse = $data['lighthouseResult'] ?? array();
		$audits     = is_array( $lighthouse ) ? ( $lighthouse['audits'] ?? array() ) : array();
		$cats       = is_array( $lighthouse ) ? ( $lighthouse['categories'] ?? array() ) : array();
		$perf_score = isset( $cats['performance']['score'] ) ? (float) $cats['performance']['score'] : null;
		$seo_score  = isset( $cats['seo']['score'] ) ? (float) $cats['seo']['score'] : null;

		$metrics = array(
			'LCP' => $this->audit_display_value( $audits, 'largest-contentful-paint' ),
			'CLS' => $this->audit_display_value( $audits, 'cumulative-layout-shift' ),
			'INP' => $this->audit_display_value( $audits, 'interaction-to-next-paint' ),
		);

		$suggestions = $this->top_audit_opportunities( $audits, 8 );

		return array(
			'ok'          => true,
			'url'         => $url,
			'strategy'    => $strategy,
			'perf_score'  => is_null( $perf_score ) ? null : (int) round( $perf_score * 100 ),
			'seo_score'   => is_null( $seo_score ) ? null : (int) round( $seo_score * 100 ),
			'metrics'     => $metrics,
			'suggestions' => $suggestions,
			'html'        => $this->render_html( $url, $strategy, $perf_score, $seo_score, $metrics, $suggestions ),
		);
	}

	private function audit_display_value( array $audits, string $key ): string {
		if ( ! isset( $audits[ $key ] ) || ! is_array( $audits[ $key ] ) ) {
			return '';
		}
		$v = $audits[ $key ]['displayValue'] ?? '';
		return is_string( $v ) ? $v : '';
	}

	private function top_audit_opportunities( array $audits, int $limit ): array {
		$items = array();
		foreach ( $audits as $k => $a ) {
			if ( ! is_array( $a ) ) {
				continue;
			}
			$score = $a['score'] ?? null;
			$title = $a['title'] ?? '';
			$desc  = $a['description'] ?? '';
			$det   = $a['details'] ?? null;

			if ( ! is_string( $title ) || $title === '' ) {
				continue;
			}
			if ( is_null( $score ) ) {
				continue;
			}
			if ( is_numeric( $score ) && (float) $score >= 0.9 ) {
				continue;
			}

			$savings_ms = 0;
			if ( is_array( $det ) && isset( $det['overallSavingsMs'] ) ) {
				$savings_ms = (int) $det['overallSavingsMs'];
			}

			$items[] = array(
				'key'        => (string) $k,
				'title'      => $title,
				'description'=> is_string( $desc ) ? wp_strip_all_tags( $desc ) : '',
				'score'      => is_numeric( $score ) ? (float) $score : null,
				'savings_ms' => $savings_ms,
			);
		}

		usort(
			$items,
			static function ( array $a, array $b ): int {
				return ( $b['savings_ms'] <=> $a['savings_ms'] );
			}
		);

		return array_slice( $items, 0, $limit );
	}

	private function render_html( string $url, string $strategy, $perf_score, $seo_score, array $metrics, array $suggestions ): string {
		$out = '<h2>' . esc_html__( 'Results', 'recipe-seo-ai-pro' ) . '</h2>';
		$out .= '<div class="rsaip-grid rsaip-perf-grid">';
		$out .= '<div><div class="rsaip-k">' . esc_html__( 'URL', 'recipe-seo-ai-pro' ) . '</div><div class="rsaip-v"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $url ) . '</a></div></div>';
		$out .= '<div><div class="rsaip-k">' . esc_html__( 'Strategy', 'recipe-seo-ai-pro' ) . '</div><div class="rsaip-v">' . esc_html( $strategy ) . '</div></div>';
		$out .= '</div>';

		$out .= '<div class="rsaip-grid rsaip-perf-grid">';
		$out .= '<div><div class="rsaip-k">' . esc_html__( 'Performance Score', 'recipe-seo-ai-pro' ) . '</div><div class="rsaip-v">' . esc_html( is_null( $perf_score ) ? '—' : (string) (int) round( (float) $perf_score * 100 ) ) . '</div></div>';
		$out .= '<div><div class="rsaip-k">' . esc_html__( 'SEO Score', 'recipe-seo-ai-pro' ) . '</div><div class="rsaip-v">' . esc_html( is_null( $seo_score ) ? '—' : (string) (int) round( (float) $seo_score * 100 ) ) . '</div></div>';
		$out .= '</div>';

		$out .= '<h3>' . esc_html__( 'Core Web Vitals (Lab)', 'recipe-seo-ai-pro' ) . '</h3>';
		$out .= '<div class="rsaip-grid rsaip-perf-grid">';
		foreach ( $metrics as $k => $v ) {
			$out .= '<div><div class="rsaip-k">' . esc_html( $k ) . '</div><div class="rsaip-v">' . esc_html( $v !== '' ? $v : '—' ) . '</div></div>';
		}
		$out .= '</div>';

		$out .= '<h3>' . esc_html__( 'Top Suggestions', 'recipe-seo-ai-pro' ) . '</h3>';
		if ( empty( $suggestions ) ) {
			$out .= '<div>' . esc_html__( 'No major issues found.', 'recipe-seo-ai-pro' ) . '</div>';
		} else {
			$out .= '<ol class="rsaip-list">';
			foreach ( $suggestions as $s ) {
				$out .= '<li><div class="rsaip-li-title">' . esc_html( (string) ( $s['title'] ?? '' ) ) . '</div><div class="rsaip-sub">' . esc_html( (string) ( $s['description'] ?? '' ) ) . '</div></li>';
			}
			$out .= '</ol>';
		}

		return $out;
	}
}

