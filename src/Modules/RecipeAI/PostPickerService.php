<?php
declare(strict_types=1);

/**
 * Professional WordPress post picker data layer (UI only).
 *
 * Does not alter Recipe AI optimization, apply, or undo behavior.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\RecipeAI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PostPickerService
 */
final class PostPickerService {

	public const USER_META_RECENT    = 'rsaip_rai_post_picker_recent';
	public const USER_META_FAVORITES = 'rsaip_rai_post_picker_favorites';
	public const CACHE_PREFIX        = 'rsaip_rai_picker_';
	public const CACHE_TTL           = 120;

	/**
	 * Search posts with rich card metadata + filters + pagination.
	 *
	 * @param array<string, mixed> $args Args.
	 * @return array{items: list<array<string,mixed>>, page: int, per_page: int, total: int, total_pages: int, categories: list<array{id:int,name:string}>}
	 */
	public function search( array $args = array() ): array {
		$query      = trim( (string) ( $args['q'] ?? '' ) );
		$page       = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page   = max( 5, min( 30, absint( $args['per_page'] ?? 10 ) ) );
		$status     = sanitize_key( (string) ( $args['status'] ?? '' ) );
		$category   = absint( $args['category'] ?? 0 );
		$date       = sanitize_key( (string) ( $args['date'] ?? '' ) );
		$recipe_only = ! empty( $args['recipe_only'] );

		$statuses = array( 'publish', 'draft', 'pending', 'future', 'private' );
		if ( in_array( $status, array( 'publish', 'draft', 'private' ), true ) ) {
			$statuses = array( $status );
		}

		$cache_key = self::CACHE_PREFIX . md5( wp_json_encode( array( $query, $page, $per_page, $status, $category, $date, $recipe_only ) ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['items'] ) ) {
			$cached['cached'] = true;
			return $cached;
		}

		$wp_args = array(
			'post_type'              => array( 'post', 'page' ),
			'post_status'            => $statuses,
			'posts_per_page'         => $recipe_only ? min( 50, $per_page * 3 ) : $per_page,
			'paged'                  => $recipe_only ? 1 : $page,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		);

		if ( $category > 0 ) {
			$wp_args['cat'] = $category;
		}

		if ( $date !== '' ) {
			$after = $this->date_after( $date );
			if ( $after !== '' ) {
				$wp_args['date_query'] = array(
					array(
						'after'     => $after,
						'inclusive' => true,
					),
				);
			}
		}

		// ID exact match.
		if ( $query !== '' && ctype_digit( $query ) ) {
			$wp_args['p'] = absint( $query );
			unset( $wp_args['s'] );
		} elseif ( $query !== '' ) {
			$wp_args['s'] = $query;
			$wp_args['orderby'] = 'relevance';
		}

		$q = new \WP_Query( $wp_args );
		$items = array();
		foreach ( $q->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			// Slug / title boost filter when searching text.
			if ( $query !== '' && ! ctype_digit( $query ) ) {
				$slug = (string) $post->post_name;
				$title = (string) $post->post_title;
				if ( stripos( $title, $query ) === false
					&& stripos( $slug, $query ) === false
					&& stripos( (string) $post->ID, $query ) === false
					&& stripos( wp_strip_all_tags( (string) $post->post_content ), $query ) === false ) {
					continue;
				}
			}

			$card = $this->card_for_post( $post );
			if ( $recipe_only && empty( $card['recipe_detected'] ) ) {
				continue;
			}
			$items[] = $card;
		}

		// When recipe_only over-fetched, paginate in PHP.
		$total = (int) $q->found_posts;
		$total_pages = max( 1, (int) $q->max_num_pages );
		if ( $recipe_only ) {
			$total       = count( $items );
			$total_pages = max( 1, (int) ceil( $total / $per_page ) );
			$offset      = ( $page - 1 ) * $per_page;
			$items       = array_slice( $items, $offset, $per_page );
		}

		// Title/slug-first sort for text queries.
		if ( $query !== '' && ! ctype_digit( $query ) ) {
			usort(
				$items,
				static function ( $a, $b ) use ( $query ) {
					$score = static function ( $row ) use ( $query ) {
						$title = (string) ( $row['title'] ?? '' );
						$slug  = (string) ( $row['slug'] ?? '' );
						if ( strcasecmp( $title, $query ) === 0 || strcasecmp( $slug, $query ) === 0 ) {
							return 0;
						}
						if ( stripos( $title, $query ) === 0 || stripos( $slug, $query ) === 0 ) {
							return 1;
						}
						if ( stripos( $title, $query ) !== false ) {
							return 2;
						}
						if ( stripos( $slug, $query ) !== false ) {
							return 3;
						}
						return 4;
					};
					$sa = $score( $a );
					$sb = $score( $b );
					if ( $sa !== $sb ) {
						return $sa - $sb;
					}
					return strcasecmp( (string) $a['title'], (string) $b['title'] );
				}
			);
		}

		$payload = array(
			'items'       => $items,
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => $total,
			'total_pages' => $total_pages,
			'categories'  => $this->categories(),
			'cached'      => false,
		);

		set_transient( $cache_key, $payload, self::CACHE_TTL );
		return $payload;
	}

	/**
	 * Preview payload for a selected post.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function preview( int $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error( 'rsaip_recipe_ai_post', 'Post not found.', array( 'status' => 404 ) );
		}
		$card = $this->card_for_post( $post, true );
		$this->remember_recent( (int) $post->ID );
		return $card;
	}

	/**
	 * @return array{recent: list<array<string,mixed>>, optimized: list<array<string,mixed>>, favorites: list<array<string,mixed>>}
	 */
	public function shelves( int $user_id = 0 ): array {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		return array(
			'recent'    => $this->hydrate_ids( $this->get_recent_ids( $user_id ), 8 ),
			'optimized' => $this->recently_optimized( 8 ),
			'favorites' => $this->hydrate_ids( $this->get_favorite_ids( $user_id ), 12 ),
		);
	}

	/**
	 * @return list<int>
	 */
	public function toggle_favorite( int $post_id, int $user_id = 0 ): array {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		$post_id = absint( $post_id );
		$favs    = $this->get_favorite_ids( $user_id );
		if ( in_array( $post_id, $favs, true ) ) {
			$favs = array_values(
				array_filter(
					$favs,
					static function ( $id ) use ( $post_id ) {
						return (int) $id !== $post_id;
					}
				)
			);
		} else {
			array_unshift( $favs, $post_id );
			$favs = array_values( array_unique( array_map( 'absint', $favs ) ) );
			$favs = array_slice( $favs, 0, 40 );
		}
		update_user_meta( $user_id, self::USER_META_FAVORITES, $favs );
		return $favs;
	}

	public function remember_recent( int $post_id, int $user_id = 0 ): void {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		$post_id = absint( $post_id );
		if ( $user_id <= 0 || $post_id <= 0 ) {
			return;
		}
		$ids = $this->get_recent_ids( $user_id );
		array_unshift( $ids, $post_id );
		$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
		$ids = array_slice( $ids, 0, 20 );
		update_user_meta( $user_id, self::USER_META_RECENT, $ids );
	}

	/**
	 * @return list<array{id:int,name:string}>
	 */
	public function categories(): array {
		$terms = get_categories(
			array(
				'hide_empty' => true,
				'number'     => 80,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		$out = array();
		if ( ! is_array( $terms ) ) {
			return $out;
		}
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$out[] = array(
				'id'   => (int) $term->term_id,
				'name' => (string) $term->name,
			);
		}
		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function card_for_post( \WP_Post $post, bool $with_excerpt = false ): array {
		$content = (string) $post->post_content;
		$recipe  = $this->detect_recipe( $content );
		$schema  = $this->detect_schema( $content );
		$words   = str_word_count( wp_strip_all_tags( $content ) );
		$author  = get_the_author_meta( 'display_name', (int) $post->post_author );
		$cats    = get_the_category( $post->ID );
		$cat_name = '';
		if ( is_array( $cats ) && isset( $cats[0] ) && $cats[0] instanceof \WP_Term ) {
			$cat_name = (string) $cats[0]->name;
		}

		$thumb = get_the_post_thumbnail_url( $post, 'thumbnail' );
		if ( ! is_string( $thumb ) ) {
			$thumb = '';
		}

		$opt = $this->optimizer_snapshot( (int) $post->ID );
		$favs = $this->get_favorite_ids( get_current_user_id() );

		$card = array(
			'id'               => (int) $post->ID,
			'title'            => (string) $post->post_title,
			'slug'             => (string) $post->post_name,
			'type'             => (string) $post->post_type,
			'status'           => (string) $post->post_status,
			'status_label'     => $this->status_label( (string) $post->post_status ),
			'date'             => get_the_date( 'Y-m-d', $post ),
			'date_display'     => get_the_date( get_option( 'date_format' ), $post ),
			'author'           => is_string( $author ) ? $author : '',
			'category'         => $cat_name,
			'word_count'       => $words,
			'recipe_detected'  => $recipe,
			'schema_detected'  => $schema,
			'seo_score'        => $opt['seo_score'],
			'last_optimized'   => $opt['last_optimized'],
			'thumbnail'        => $thumb,
			'edit_url'         => (string) get_edit_post_link( $post->ID, 'raw' ),
			'view_url'         => (string) get_permalink( $post ),
			'is_favorite'      => in_array( (int) $post->ID, $favs, true ),
		);

		if ( $with_excerpt ) {
			$excerpt = $post->post_excerpt !== '' ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $content ), 40 );
			$card['excerpt'] = (string) $excerpt;
			$medium = get_the_post_thumbnail_url( $post, 'medium' );
			$card['image'] = is_string( $medium ) && $medium !== '' ? $medium : $thumb;
		}

		return $card;
	}

	private function detect_recipe( string $content ): bool {
		return (bool) preg_match(
			'/rsaip-recipe|wp-block-recipe|itemprop=["\']recipe|itemtype=["\'][^"\']*Recipe|\[rsaip_recipe|recipeIngredient|application\/ld\+json[\s\S]{0,200}Recipe/i',
			$content
		);
	}

	private function detect_schema( string $content ): bool {
		return (bool) preg_match(
			'/itemtype=["\'][^"\']*Recipe|application\/ld\+json[\s\S]{0,400}"@type"\s*:\s*"Recipe"/i',
			$content
		);
	}

	/**
	 * @return array{seo_score: int|null, last_optimized: string}
	 */
	private function optimizer_snapshot( int $post_id ): array {
		$out = array(
			'seo_score'      => null,
			'last_optimized' => '',
		);

		global $wpdb;
		if ( class_exists( 'RSAIP_DB' ) && method_exists( 'RSAIP_DB', 'table_content_optimizations' ) ) {
			$table = \RSAIP_DB::table_content_optimizations();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT seo_score, updated_at FROM {$table} WHERE post_id = %d ORDER BY updated_at DESC LIMIT 1",
					$post_id
				),
				ARRAY_A
			);
			if ( is_array( $row ) ) {
				$out['seo_score'] = isset( $row['seo_score'] ) ? (int) $row['seo_score'] : null;
				$out['last_optimized'] = (string) ( $row['updated_at'] ?? '' );
			}
		}

		// Recipe AI structured meta implies a prior save-back.
		if ( $out['last_optimized'] === '' && metadata_exists( 'post', $post_id, PostRecipeSource::META_STRUCTURED ) ) {
			$out['last_optimized'] = (string) get_post_modified_time( 'Y-m-d H:i:s', true, $post_id );
		}

		return $out;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function recently_optimized( int $limit ): array {
		global $wpdb;
		$ids = array();
		if ( class_exists( 'RSAIP_DB' ) && method_exists( 'RSAIP_DB', 'table_content_optimizations' ) ) {
			$table = \RSAIP_DB::table_content_optimizations();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT post_id FROM {$table} WHERE post_id > 0 ORDER BY updated_at DESC LIMIT %d",
					max( 1, min( 20, $limit ) )
				)
			);
			if ( is_array( $rows ) ) {
				$ids = array_map( 'absint', $rows );
			}
		}

		// Also surface posts with Recipe AI structured data.
		if ( count( $ids ) < $limit ) {
			$q = new \WP_Query(
				array(
					'post_type'              => array( 'post', 'page' ),
					'post_status'            => array( 'publish', 'draft', 'private', 'pending', 'future' ),
					'posts_per_page'         => $limit,
					'meta_key'               => PostRecipeSource::META_STRUCTURED,
					'orderby'                => 'modified',
					'order'                  => 'DESC',
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			foreach ( $q->posts as $pid ) {
				$ids[] = absint( $pid );
			}
		}

		$ids = array_values( array_unique( array_filter( $ids ) ) );
		return $this->hydrate_ids( array_slice( $ids, 0, $limit ), $limit );
	}

	/**
	 * @param list<int> $ids IDs.
	 * @return list<array<string,mixed>>
	 */
	private function hydrate_ids( array $ids, int $limit ): array {
		$out = array();
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id <= 0 ) {
				continue;
			}
			$post = get_post( $id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$out[] = $this->card_for_post( $post );
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * @return list<int>
	 */
	private function get_recent_ids( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::USER_META_RECENT, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		return array_values( array_map( 'absint', $raw ) );
	}

	/**
	 * @return list<int>
	 */
	private function get_favorite_ids( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::USER_META_FAVORITES, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		return array_values( array_map( 'absint', $raw ) );
	}

	private function status_label( string $status ): string {
		$map = array(
			'publish' => 'Published',
			'draft'   => 'Draft',
			'private' => 'Private',
			'pending' => 'Pending',
			'future'  => 'Scheduled',
		);
		return $map[ $status ] ?? ucfirst( $status );
	}

	private function date_after( string $key ): string {
		switch ( $key ) {
			case '7d':
				return gmdate( 'Y-m-d', strtotime( '-7 days' ) );
			case '30d':
				return gmdate( 'Y-m-d', strtotime( '-30 days' ) );
			case '90d':
				return gmdate( 'Y-m-d', strtotime( '-90 days' ) );
			case 'year':
				return gmdate( 'Y-01-01' );
			default:
				return '';
		}
	}
}
