<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSAIP_Image_Optimizer {
	private RSAIP_AI $ai;

	public function __construct( RSAIP_AI $ai ) {
		$this->ai = $ai;
	}

	public function scan_images( int $limit ): array {
		$settings = rsaip_get_settings();
		$threshold_kb = (int) $settings['image_large_threshold_kb'];

		$q = new WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'posts_per_page'         => $limit,
				'post_mime_type'         => 'image',
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		$out = array();
		foreach ( $q->posts as $att ) {
			$att_id = (int) $att->ID;
			$alt = get_post_meta( $att_id, '_wp_attachment_image_alt', true );
			$alt = is_string( $alt ) ? trim( $alt ) : '';

			$title = is_string( $att->post_title ) ? trim( $att->post_title ) : '';

			$file = get_attached_file( $att_id );
			$size_kb = 0;
			if ( is_string( $file ) && $file !== '' && file_exists( $file ) ) {
				$bytes = filesize( $file );
				if ( is_int( $bytes ) ) {
					$size_kb = (int) ceil( $bytes / 1024 );
				}
			}

			$problems = array();
			if ( $alt === '' ) {
				$problems[] = 'missing_alt';
			}
			if ( $title === '' ) {
				$problems[] = 'missing_title';
			}
			if ( $size_kb > 0 && $size_kb >= $threshold_kb ) {
				$problems[] = 'large_image';
			}

			if ( empty( $problems ) ) {
				continue;
			}

			$out[] = array(
				'attachment_id' => $att_id,
				'thumb'         => wp_get_attachment_image_url( $att_id, 'thumbnail' ),
				'edit_url'      => get_edit_post_link( $att_id ),
				'title'         => $title,
				'alt'           => $alt,
				'size_kb'       => $size_kb,
				'problems'      => $problems,
				'post_id_hint'  => (int) $att->post_parent,
			);
		}

		return $out;
	}

	public function generate_alt_text_for_attachment( int $attachment_id, int $post_id ): array {
		$att = get_post( $attachment_id );
		if ( ! $att instanceof WP_Post ) {
			return array( 'updated' => false, 'message' => 'attachment_not_found' );
		}

		$post = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post instanceof WP_Post && $att->post_parent ) {
			$post = get_post( (int) $att->post_parent );
		}

		$recipe_name = '';
		$ingredient  = '';

		if ( $post instanceof WP_Post ) {
			$recipe_name = get_the_title( $post->ID );
			$ingredient  = $this->extract_main_ingredient_from_post( $post );
		} else {
			$recipe_name = $att->post_title ? (string) $att->post_title : (string) get_the_title( $attachment_id );
		}

		$recipe_name = sanitize_text_field( (string) $recipe_name );
		$ingredient  = sanitize_text_field( (string) $ingredient );

		$alt = $this->ai->generate_alt_text( $recipe_name, $ingredient );
		$alt = sanitize_text_field( $alt );
		if ( $alt === '' ) {
			$alt = $this->fallback_alt_text( $recipe_name, $ingredient );
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );

		if ( trim( (string) $att->post_title ) === '' && $recipe_name !== '' ) {
			wp_update_post(
				array(
					'ID'         => $attachment_id,
					'post_title' => $recipe_name,
				)
			);
		}

		return array(
			'updated'        => true,
			'attachment_id'  => $attachment_id,
			'alt'            => $alt,
			'recipe_name'    => $recipe_name,
			'main_ingredient'=> $ingredient,
		);
	}

	private function fallback_alt_text( string $recipe_name, string $ingredient ): string {
		$recipe_name = trim( $recipe_name );
		$ingredient  = trim( $ingredient );
		if ( $recipe_name === '' ) {
			return '';
		}
		if ( $ingredient === '' ) {
			return $recipe_name;
		}
		$rn = rsaip_text_normalize( $recipe_name );
		$in = rsaip_text_normalize( $ingredient );
		if ( $rn !== '' && $in !== '' && mb_stripos( $rn, $in ) !== false ) {
			return $recipe_name;
		}
		return $recipe_name . ' ' . $ingredient;
	}

	private function extract_main_ingredient_from_post( WP_Post $post ): string {
		$content = (string) $post->post_content;
		$content = wp_strip_all_tags( $content );
		$content = preg_replace( '/\r\n?/', "\n", $content );

		$patterns = array(
			'/\bIngredients\b\s*:?\s*\n(.{0,500})/i',
			'/\bالمكونات\b\s*:?\s*\n(.{0,500})/u',
		);

		foreach ( $patterns as $p ) {
			if ( preg_match( $p, $content, $m ) ) {
				$chunk = (string) ( $m[1] ?? '' );
				$lines = preg_split( '/\n+/', $chunk );
				if ( is_array( $lines ) ) {
					foreach ( $lines as $line ) {
						$line = trim( (string) $line );
						if ( $line === '' ) {
							continue;
						}
						$line = preg_replace( '/^[\-\*\•\d\.\)\(]+\s*/u', '', $line );
						$line = trim( (string) $line );
						$line = preg_replace( '/\s+/u', ' ', (string) $line );
						$parts = preg_split( '/\s+/u', $line );
						if ( is_array( $parts ) ) {
							$parts = array_values( array_filter( $parts ) );
							$parts = array_slice( $parts, 0, 2 );
							$ingredient = trim( implode( ' ', $parts ) );
							if ( $ingredient !== '' ) {
								return $ingredient;
							}
						}
					}
				}
			}
		}

		$title = get_the_title( $post->ID );
		$tokens = preg_split( '/\s+/u', rsaip_text_normalize( (string) $title ) );
		if ( is_array( $tokens ) ) {
			$tokens = array_values( array_filter( $tokens ) );
			if ( count( $tokens ) >= 2 ) {
				return sanitize_text_field( (string) $tokens[0] );
			}
		}

		return '';
	}

	public function render_image_rows_html( array $rows ): string {
		$out = '';
		foreach ( (array) $rows as $r ) {
			$aid = absint( $r['attachment_id'] ?? 0 );
			if ( $aid <= 0 ) {
				continue;
			}
			$thumb = (string) ( $r['thumb'] ?? '' );
			$edit  = (string) ( $r['edit_url'] ?? '' );
			$size  = absint( $r['size_kb'] ?? 0 );
			$problems = (array) ( $r['problems'] ?? array() );
			$post_id_hint = absint( $r['post_id_hint'] ?? 0 );

			$labels = array();
			foreach ( $problems as $p ) {
				$labels[] = '<span class="rsaip-pill">' . esc_html( (string) $p ) . '</span>';
			}

			$out .= '<tr>';
			$out .= '<td>';
			if ( $thumb ) {
				$out .= '<img class="rsaip-thumb" src="' . esc_url( $thumb ) . '" alt="" />';
			}
			$out .= '<div><a href="' . esc_url( $edit ) . '">' . esc_html( get_the_title( $aid ) ) . '</a><div class="rsaip-sub">#' . esc_html( (string) $aid ) . ( $size ? ' • ' . esc_html( (string) $size ) . 'KB' : '' ) . '</div></div>';
			$out .= '</td>';
			$out .= '<td>' . ( $labels ? implode( ' ', $labels ) : '' ) . '</td>';
			$out .= '<td><button class="button rsaip-btn" data-action="rsaip_generate_alt_text" data-attachment-id="' . esc_attr( (string) $aid ) . '" data-post-id="' . esc_attr( (string) $post_id_hint ) . '">' . esc_html__( 'Generate Alt Text', 'recipe-seo-ai-pro' ) . '</button></td>';
			$out .= '</tr>';
		}

		if ( $out === '' ) {
			$out = '<tr><td colspan="3">' . esc_html__( 'No image issues found in the current sample.', 'recipe-seo-ai-pro' ) . '</td></tr>';
		}
		return $out;
	}
}

