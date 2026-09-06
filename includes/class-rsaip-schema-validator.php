<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSAIP_Schema_Validator {
	public function scan( int $limit = 100 ): array {
		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'posts_per_page'  => max( 1, min( 200, $limit ) ),
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);

		$rows = array();
		foreach ( $posts as $post_id ) {
			$schema = $this->collect_schema_issues( $post_id );
			if ( ! empty( $schema['issues'] ) ) {
				$rows[] = array(
					'post_id' => (int) $post_id,
					'title'   => get_the_title( $post_id ),
					'issues'  => $schema['issues'],
					'status'  => $schema['status'],
				);
			}
		}

		return $rows;
	}

	public function render_rows_html( array $rows ): string {
		if ( empty( $rows ) ) {
			return '<div class="rsaip-empty">No schema issues detected.</div>';
		}

		$html = '<table class="widefat striped"><thead><tr><th>Post</th><th>Issues</th><th>Status</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$issues = is_array( $row['issues'] ?? null ) ? array_map( 'esc_html', $row['issues'] ) : array();
			$html .= '<tr><td><a href="' . esc_url( get_edit_post_link( (int) ( $row['post_id'] ?? 0 ), 'raw' ) ) . '">' . esc_html( (string) ( $row['title'] ?? '' ) ) . '</a></td><td>' . esc_html( implode( ', ', $issues ) ) . '</td><td>' . esc_html( (string) ( $row['status'] ?? 'review' ) ) . '</td></tr>';
		}
		$html .= '</tbody></table>';
		return $html;
	}

	public function fix_post_schema( int $post_id ): array {
		if ( ! function_exists( 'rsaip_never_modify_posts' ) || rsaip_never_modify_posts() ) {
			return array(
				'ok'      => false,
				'message' => function_exists( 'rsaip_existing_post_mutation_frozen_message' )
					? rsaip_existing_post_mutation_frozen_message()
					: 'This existing-post mutation is temporarily disabled while Safe Article Mode is enabled.',
			);
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array( 'ok' => false, 'message' => 'post_not_found' );
		}

		$updated = array();
		$content = (string) $post->post_content;
		if ( strpos( $content, '<script type="application/ld+json">' ) === false ) {
			$schema = array(
				'@context' => 'https://schema.org',
				'@type'    => 'Article',
				'headline' => get_the_title( $post_id ),
			);
			$content .= "\n<script type=\"application/ld+json\">" . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
			$updated[] = 'schema_script';
		}

		if ( $updated !== array() ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => wp_slash( $content ),
				)
			);
		}

		return array(
			'ok'      => true,
			'post_id' => $post_id,
			'updated' => $updated,
		);
	}

	public function fix_batch( int $limit = 50 ): array {
		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'posts_per_page'  => max( 1, min( 200, $limit ) ),
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);

		$processed = 0;
		foreach ( $posts as $post_id ) {
			$result = $this->fix_post_schema( (int) $post_id );
			if ( ! empty( $result['ok'] ) ) {
				$processed++;
			}
		}

		return array(
			'ok'       => true,
			'processed' => $processed,
			'limit'    => $limit,
		);
	}

	private function collect_schema_issues( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array( 'issues' => array(), 'status' => 'missing_post' );
		}

		$issues = array();
		$content = (string) $post->post_content;
		if ( strpos( $content, '<script type="application/ld+json">' ) === false ) {
			$issues[] = 'Missing JSON-LD schema markup';
		}

		return array(
			'issues' => $issues,
			'status' => empty( $issues ) ? 'ok' : 'needs_review',
		);
	}
}
