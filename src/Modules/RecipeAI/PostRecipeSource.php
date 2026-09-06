<?php
declare(strict_types=1);

/**
 * WordPress post source for AI Recipe Assistant.
 *
 * Additive to Recipe Builder — search, parse, and save-back for WP posts.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\RecipeAI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PostRecipeSource
 */
final class PostRecipeSource {

	public const META_STRUCTURED = '_rsaip_recipe_ai_structured';

	/**
	 * Search posts by title.
	 *
	 * @return list<array{id:int,title:string,type:string,status:string,url:string}>
	 */
	public function search( string $query, int $limit = 20 ): array {
		$query = trim( $query );
		$limit = max( 1, min( 50, $limit ) );

		$args = array(
			'post_type'              => array( 'post', 'page' ),
			'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page'         => $limit,
			'orderby'                => 'relevance',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		if ( $query !== '' ) {
			$args['s'] = $query;
			// Prefer title matches via WP search; refine after.
		} else {
			$args['orderby'] = 'date';
		}

		$q = new \WP_Query( $args );
		$out = array();
		foreach ( $q->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			if ( $query !== '' && stripos( $post->post_title, $query ) === false && stripos( $post->post_content, $query ) === false ) {
				// Keep WP search hits even if title miss (content hit).
			}
			$out[] = array(
				'id'     => (int) $post->ID,
				'title'  => (string) $post->post_title,
				'type'   => (string) $post->post_type,
				'status' => (string) $post->post_status,
				'url'    => (string) get_edit_post_link( $post->ID, 'raw' ),
			);
		}

		// Title-first sort when query present.
		if ( $query !== '' ) {
			usort(
				$out,
				static function ( $a, $b ) use ( $query ) {
					$at = stripos( (string) $a['title'], $query ) !== false ? 0 : 1;
					$bt = stripos( (string) $b['title'], $query ) !== false ? 0 : 1;
					if ( $at !== $bt ) {
						return $at - $bt;
					}
					return strcasecmp( (string) $a['title'], (string) $b['title'] );
				}
			);
		}

		return $out;
	}

	/**
	 * Load and parse a post into the Recipe AI recipe shape.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function load( int $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'rsaip_recipe_ai_post', 'Provide a WordPress post ID.' );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error( 'rsaip_recipe_ai_post', 'Post not found.', array( 'status' => 404 ) );
		}

		$structured = get_post_meta( $post_id, self::META_STRUCTURED, true );
		if ( is_string( $structured ) && $structured !== '' ) {
			$decoded = json_decode( $structured, true );
			if ( is_array( $decoded ) && ( ! empty( $decoded['ingredients'] ) || ! empty( $decoded['steps'] ) ) ) {
				$decoded['source_type'] = 'post';
				$decoded['post_id']     = $post_id;
				$decoded['id']          = 0;
				$decoded['title']       = (string) ( $decoded['title'] ?? $post->post_title );
				$decoded['parse_mode']  = (string) ( $decoded['parse_mode'] ?? 'meta' );
				return $decoded;
			}
		}

		$content = (string) $post->post_content;
		$parsed  = $this->parse_content( $content, (string) $post->post_title );
		$parsed['source_type'] = 'post';
		$parsed['post_id']     = $post_id;
		$parsed['id']          = 0;
		$parsed['status']      = (string) $post->post_status;

		return $parsed;
	}

	/**
	 * Apply optimized recipe back to the original post (caller confirms first).
	 *
	 * @param array<string, mixed> $recipe Optimized recipe.
	 * @return array{post_id:int,backup_content:string,backup_title:string}|\WP_Error
	 */
	public function apply( array $recipe, int $user_id = 0 ) {
		if ( ! function_exists( 'rsaip_never_modify_posts' ) || rsaip_never_modify_posts() ) {
			return new \WP_Error(
				'rsaip_recipe_ai_frozen',
				function_exists( 'rsaip_existing_post_mutation_frozen_message' )
					? rsaip_existing_post_mutation_frozen_message()
					: 'This existing-post mutation is temporarily disabled while Safe Article Mode is enabled.',
				array( 'status' => 403 )
			);
		}

		$post_id = absint( $recipe['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'rsaip_recipe_ai_post', 'Optimized recipe is missing post_id.' );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error( 'rsaip_recipe_ai_post', 'Post not found.', array( 'status' => 404 ) );
		}

		$backup_content = (string) $post->post_content;
		$backup_title   = (string) $post->post_title;
		$card_html      = $this->render_card( $recipe );
		$new_content    = $this->replace_or_append_card( $backup_content, $card_html );
		$new_title      = trim( (string) ( $recipe['title'] ?? '' ) );
		if ( $new_title === '' ) {
			$new_title = $backup_title;
		}

		$update = array(
			'ID'           => $post_id,
			'post_content' => $new_content,
			'post_title'   => $new_title,
		);
		$result = wp_update_post( wp_slash( $update ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$meta = $recipe;
		$meta['source_type'] = 'post';
		$meta['post_id']     = $post_id;
		$meta['parse_mode']  = 'meta';
		update_post_meta( $post_id, self::META_STRUCTURED, wp_json_encode( $meta ) );

		unset( $user_id );

		return array(
			'post_id'        => $post_id,
			'backup_content' => $backup_content,
			'backup_title'   => $backup_title,
		);
	}

	/**
	 * Restore a previous post content/title backup.
	 *
	 * @return true|\WP_Error
	 */
	public function restore_post( int $post_id, string $content, string $title = '' ) {
		if ( ! function_exists( 'rsaip_never_modify_posts' ) || rsaip_never_modify_posts() ) {
			return new \WP_Error(
				'rsaip_recipe_ai_frozen',
				function_exists( 'rsaip_existing_post_mutation_frozen_message' )
					? rsaip_existing_post_mutation_frozen_message()
					: 'This existing-post mutation is temporarily disabled while Safe Article Mode is enabled.',
				array( 'status' => 403 )
			);
		}

		$post_id = absint( $post_id );
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error( 'rsaip_recipe_ai_post', 'Post not found.', array( 'status' => 404 ) );
		}

		$update = array(
			'ID'           => $post_id,
			'post_content' => $content,
		);
		if ( $title !== '' ) {
			$update['post_title'] = $title;
		}
		$result = wp_update_post( wp_slash( $update ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return true;
	}

	/**
	 * Parse post HTML into recipe fields.
	 *
	 * @return array<string, mixed>
	 */
	public function parse_content( string $content, string $title = '' ): array {
		$ingredients = array();
		$steps       = array();
		$description = '';
		$parse_mode  = 'article';

		// 1) Prefer RSAIP / Gutenberg recipe blocks.
		$card = $this->extract_card_html( $content );
		if ( $card !== '' ) {
			$parse_mode = 'card';
			$pair       = $this->parse_lists_from_html( $card );
			$ingredients = $pair['ingredients'];
			$steps       = $pair['steps'];
			$description = $this->first_paragraph( $card );
		}

		// 2) Schema.org Recipe microdata / JSON-LD.
		if ( empty( $ingredients ) && empty( $steps ) ) {
			$schema = $this->parse_schema_recipe( $content );
			if ( ! empty( $schema['ingredients'] ) || ! empty( $schema['steps'] ) ) {
				$parse_mode  = 'schema';
				$ingredients = $schema['ingredients'];
				$steps       = $schema['steps'];
				if ( ! empty( $schema['description'] ) ) {
					$description = $schema['description'];
				}
				if ( ! empty( $schema['title'] ) && $title === '' ) {
					$title = $schema['title'];
				}
			}
		}

		// 3) Heuristic lists from full content.
		if ( empty( $ingredients ) && empty( $steps ) ) {
			$pair = $this->parse_lists_from_html( $content );
			if ( ! empty( $pair['ingredients'] ) || ! empty( $pair['steps'] ) ) {
				$parse_mode  = 'lists';
				$ingredients = $pair['ingredients'];
				$steps       = $pair['steps'];
			}
		}

		// 4) Fallback: article body as description for AI to work with.
		if ( $description === '' ) {
			$description = $this->article_excerpt( $content );
		}
		if ( empty( $ingredients ) && empty( $steps ) ) {
			$parse_mode = 'article';
		}

		$servings = $this->detect_servings( $content );
		$times    = $this->detect_times( $content );

		return array(
			'id'          => 0,
			'post_id'     => 0,
			'source_type' => 'post',
			'parse_mode'  => $parse_mode,
			'title'       => $title,
			'description' => $description,
			'servings'    => $servings,
			'prep_time'   => $times['prep'],
			'cook_time'   => $times['cook'],
			'total_time'  => $times['total'],
			'notes'       => '',
			'tips'        => '',
			'equipment'   => array(),
			'unit_system' => 'metric',
			'status'      => 'draft',
			'sections'    => array(),
			'ingredients' => $ingredients,
			'steps'       => $steps,
			'has_recipe_card' => ( $parse_mode === 'card' || $parse_mode === 'schema' || $parse_mode === 'lists' ),
		);
	}

	/**
	 * Build a portable HTML recipe card for save-back.
	 *
	 * @param array<string, mixed> $recipe Recipe.
	 */
	public function render_card( array $recipe ): string {
		$title = esc_html( (string) ( $recipe['title'] ?? 'Recipe' ) );
		$desc  = esc_html( (string) ( $recipe['description'] ?? '' ) );
		$serv  = esc_html( (string) ( $recipe['servings'] ?? '' ) );
		$prep  = absint( $recipe['prep_time'] ?? 0 );
		$cook  = absint( $recipe['cook_time'] ?? 0 );
		$total = absint( $recipe['total_time'] ?? 0 );

		$ing_html = '';
		$ingredients = isset( $recipe['ingredients'] ) && is_array( $recipe['ingredients'] ) ? $recipe['ingredients'] : array();
		foreach ( $ingredients as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$line = trim(
				trim( (string) ( $row['quantity'] ?? '' ) ) . ' '
				. trim( (string) ( $row['unit'] ?? '' ) ) . ' '
				. trim( (string) ( $row['name'] ?? '' ) )
			);
			$note = trim( (string) ( $row['note'] ?? '' ) );
			if ( $note !== '' ) {
				$line .= ' — ' . $note;
			}
			if ( $line !== '' ) {
				$ing_html .= '<li itemprop="recipeIngredient">' . esc_html( $line ) . '</li>';
			}
		}

		$step_html = '';
		$steps = isset( $recipe['steps'] ) && is_array( $recipe['steps'] ) ? $recipe['steps'] : array();
		$n = 1;
		foreach ( $steps as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$instruction = trim( (string) ( $row['instruction'] ?? '' ) );
			if ( $instruction === '' ) {
				continue;
			}
			$step_html .= '<li itemprop="recipeInstructions">' . esc_html( $instruction ) . '</li>';
			$n++;
		}

		$meta = '';
		if ( $serv !== '' && $serv !== '0' ) {
			$meta .= '<p><strong>Servings:</strong> <span itemprop="recipeYield">' . $serv . '</span></p>';
		}
		if ( $prep > 0 ) {
			$meta .= '<p><strong>Prep:</strong> ' . $prep . ' min</p>';
		}
		if ( $cook > 0 ) {
			$meta .= '<p><strong>Cook:</strong> ' . $cook . ' min</p>';
		}
		if ( $total > 0 ) {
			$meta .= '<p><strong>Total:</strong> ' . $total . ' min</p>';
		}

		$html  = '<div class="rsaip-recipe rsaip-recipe-card" itemscope itemtype="https://schema.org/Recipe">';
		$html .= '<h2 class="rsaip-recipe-title" itemprop="name">' . $title . '</h2>';
		if ( $desc !== '' ) {
			$html .= '<p class="rsaip-recipe-description" itemprop="description">' . $desc . '</p>';
		}
		$html .= $meta;
		if ( $ing_html !== '' ) {
			$html .= '<h3>Ingredients</h3><ul class="rsaip-recipe-ingredients">' . $ing_html . '</ul>';
		}
		if ( $step_html !== '' ) {
			$html .= '<h3>Instructions</h3><ol class="rsaip-recipe-instructions">' . $step_html . '</ol>';
		}
		$notes = trim( (string) ( $recipe['notes'] ?? '' ) );
		if ( $notes !== '' ) {
			$html .= '<h3>Notes</h3><p>' . esc_html( $notes ) . '</p>';
		}
		$html .= '</div>';

		return $html;
	}

	private function extract_card_html( string $content ): string {
		if ( preg_match( '/(<div[^>]+class=["\'][^"\']*rsaip-recipe[^"\']*["\'][^>]*>.*?<\/div>)/is', $content, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '/(\[rsaip_recipe[^\]]*\].*?\[\/rsaip_recipe\])/is', $content, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '/(<!--\s*wp:recipe\b.*?<!--\s*\/wp:recipe\s*-->)/is', $content, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '/(<div[^>]+class=["\'][^"\']*wp-block-recipe[^"\']*["\'][^>]*>.*?<\/div>)/is', $content, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * @return array{ingredients: list<array<string,mixed>>, steps: list<array<string,mixed>>}
	 */
	private function parse_lists_from_html( string $html ): array {
		$ingredients = array();
		$steps       = array();

		// Ingredients section → following list.
		if ( preg_match( '/ingredients?\s*<\/h[2-4]>\s*(<ul[\s\S]*?<\/ul>|<ol[\s\S]*?<\/ol>)/i', $html, $m )
			|| preg_match( '/<h[2-4][^>]*>\s*ingredients?\s*<\/h[2-4]>\s*(<ul[\s\S]*?<\/ul>|<ol[\s\S]*?<\/ol>)/i', $html, $m )
			|| preg_match( '/itemprop=["\']recipeIngredient["\'][^>]*>(.*?)<\/li>/is', $html ) ) {
			if ( ! empty( $m[1] ) ) {
				$ingredients = $this->list_items_to_ingredients( $m[1] );
			}
		}

		if ( empty( $ingredients ) && preg_match_all( '/itemprop=["\']recipeIngredient["\'][^>]*>(.*?)<\/[^>]+>/is', $html, $mm ) ) {
			foreach ( $mm[1] as $raw ) {
				$line = trim( wp_strip_all_tags( (string) $raw ) );
				if ( $line !== '' ) {
					$ingredients[] = $this->line_to_ingredient( $line );
				}
			}
		}

		// Instructions / Directions / Method.
		if ( preg_match( '/(?:instructions?|directions?|method|steps?)\s*<\/h[2-4]>\s*(<ol[\s\S]*?<\/ol>|<ul[\s\S]*?<\/ul>)/i', $html, $m )
			|| preg_match( '/<h[2-4][^>]*>\s*(?:instructions?|directions?|method|steps?)\s*<\/h[2-4]>\s*(<ol[\s\S]*?<\/ol>|<ul[\s\S]*?<\/ul>)/i', $html, $m ) ) {
			$steps = $this->list_items_to_steps( $m[1] );
		}

		if ( empty( $steps ) && preg_match_all( '/itemprop=["\']recipeInstructions["\'][^>]*>(.*?)<\/[^>]+>/is', $html, $mm ) ) {
			foreach ( $mm[1] as $raw ) {
				$line = trim( wp_strip_all_tags( (string) $raw ) );
				if ( $line !== '' ) {
					$steps[] = array( 'instruction' => $line, 'image_url' => '', 'section_id' => 0 );
				}
			}
		}

		// Plain-text fallback after headings.
		if ( empty( $ingredients ) || empty( $steps ) ) {
			$text = wp_strip_all_tags( $html );
			$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
			if ( empty( $ingredients ) && preg_match( '/ingredients?\s*[:\n]+([\s\S]{0,4000}?)(?:instructions?|directions?|method|steps?|$)/i', $text, $m ) ) {
				$ingredients = $this->lines_to_ingredients( $m[1] );
			}
			if ( empty( $steps ) && preg_match( '/(?:instructions?|directions?|method|steps?)\s*[:\n]+([\s\S]{0,8000})$/i', $text, $m ) ) {
				$steps = $this->lines_to_steps( $m[1] );
			}
		}

		return array(
			'ingredients' => $ingredients,
			'steps'       => $steps,
		);
	}

	/**
	 * @return array{ingredients: list<array<string,mixed>>, steps: list<array<string,mixed>>, description: string, title: string}
	 */
	private function parse_schema_recipe( string $content ): array {
		$empty = array(
			'ingredients' => array(),
			'steps'       => array(),
			'description' => '',
			'title'       => '',
		);

		if ( preg_match_all( '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $content, $matches ) ) {
			foreach ( $matches[1] as $json ) {
				$data = json_decode( html_entity_decode( (string) $json, ENT_QUOTES, 'UTF-8' ), true );
				$recipe = $this->find_schema_recipe_node( $data );
				if ( ! is_array( $recipe ) ) {
					continue;
				}
				$ingredients = array();
				$raw_ings = $recipe['recipeIngredient'] ?? array();
				if ( is_string( $raw_ings ) ) {
					$raw_ings = array( $raw_ings );
				}
				if ( is_array( $raw_ings ) ) {
					foreach ( $raw_ings as $line ) {
						if ( is_string( $line ) && trim( $line ) !== '' ) {
							$ingredients[] = $this->line_to_ingredient( trim( $line ) );
						}
					}
				}
				$steps = array();
				$raw_steps = $recipe['recipeInstructions'] ?? array();
				if ( is_string( $raw_steps ) ) {
					$raw_steps = array( $raw_steps );
				}
				if ( is_array( $raw_steps ) ) {
					foreach ( $raw_steps as $step ) {
						$text = '';
						if ( is_string( $step ) ) {
							$text = $step;
						} elseif ( is_array( $step ) ) {
							$text = (string) ( $step['text'] ?? $step['name'] ?? '' );
						}
						$text = trim( wp_strip_all_tags( $text ) );
						if ( $text !== '' ) {
							$steps[] = array( 'instruction' => $text, 'image_url' => '', 'section_id' => 0 );
						}
					}
				}
				return array(
					'ingredients' => $ingredients,
					'steps'       => $steps,
					'description' => is_string( $recipe['description'] ?? null ) ? (string) $recipe['description'] : '',
					'title'       => is_string( $recipe['name'] ?? null ) ? (string) $recipe['name'] : '',
				);
			}
		}

		return $empty;
	}

	/**
	 * @param mixed $data JSON-LD.
	 * @return array<string, mixed>|null
	 */
	private function find_schema_recipe_node( $data ) {
		if ( ! is_array( $data ) ) {
			return null;
		}
		if ( isset( $data['@type'] ) ) {
			$type = $data['@type'];
			if ( ( is_string( $type ) && strcasecmp( $type, 'Recipe' ) === 0 )
				|| ( is_array( $type ) && in_array( 'Recipe', $type, true ) ) ) {
				return $data;
			}
		}
		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			foreach ( $data['@graph'] as $node ) {
				$found = $this->find_schema_recipe_node( $node );
				if ( $found ) {
					return $found;
				}
			}
		}
		foreach ( $data as $value ) {
			if ( is_array( $value ) ) {
				$found = $this->find_schema_recipe_node( $value );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function list_items_to_ingredients( string $list_html ): array {
		$out = array();
		if ( preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $list_html, $m ) ) {
			foreach ( $m[1] as $raw ) {
				$line = trim( wp_strip_all_tags( (string) $raw ) );
				if ( $line !== '' ) {
					$out[] = $this->line_to_ingredient( $line );
				}
			}
		}
		return $out;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function list_items_to_steps( string $list_html ): array {
		$out = array();
		if ( preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $list_html, $m ) ) {
			foreach ( $m[1] as $raw ) {
				$line = trim( wp_strip_all_tags( (string) $raw ) );
				if ( $line !== '' ) {
					$out[] = array( 'instruction' => $line, 'image_url' => '', 'section_id' => 0 );
				}
			}
		}
		return $out;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function lines_to_ingredients( string $block ): array {
		$out = array();
		foreach ( preg_split( '/\r\n|\n|\r/', $block ) ?: array() as $line ) {
			$line = trim( (string) $line );
			$line = preg_replace( '/^[\-\*\u2022\d\.\)\s]+/u', '', $line );
			$line = trim( (string) $line );
			if ( $line !== '' && strlen( $line ) < 200 ) {
				$out[] = $this->line_to_ingredient( $line );
			}
		}
		return $out;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function lines_to_steps( string $block ): array {
		$out = array();
		foreach ( preg_split( '/\r\n|\n|\r/', $block ) ?: array() as $line ) {
			$line = trim( (string) $line );
			$line = preg_replace( '/^[\-\*\u2022\d\.\)\s]+/u', '', $line );
			$line = trim( (string) $line );
			if ( $line !== '' && strlen( $line ) > 3 ) {
				$out[] = array( 'instruction' => $line, 'image_url' => '', 'section_id' => 0 );
			}
		}
		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function line_to_ingredient( string $line ): array {
		$quantity = '';
		$unit     = '';
		$name     = $line;
		if ( preg_match( '/^(\d+[\/\d\.\s]*)\s*([a-zA-Z]+)?\s+(.+)$/u', $line, $m ) ) {
			$quantity = trim( $m[1] );
			$unit     = trim( (string) ( $m[2] ?? '' ) );
			$name     = trim( $m[3] );
		}
		return array(
			'name'       => $name,
			'quantity'   => is_numeric( $quantity ) ? (float) $quantity : $quantity,
			'unit'       => $unit,
			'note'       => '',
			'section_id' => 0,
		);
	}

	private function first_paragraph( string $html ): string {
		if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $html, $m ) ) {
			return trim( wp_strip_all_tags( $m[1] ) );
		}
		$text = trim( wp_strip_all_tags( $html ) );
		return mb_substr( $text, 0, 400 );
	}

	private function article_excerpt( string $content ): string {
		$text = trim( wp_strip_all_tags( $content ) );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return mb_substr( (string) $text, 0, 2000 );
	}

	private function detect_servings( string $content ): float {
		$text = wp_strip_all_tags( $content );
		if ( preg_match( '/servings?\s*[:\-]?\s*(\d+(?:\.\d+)?)/i', $text, $m ) ) {
			return (float) $m[1];
		}
		if ( preg_match( '/serves?\s*[:\-]?\s*(\d+(?:\.\d+)?)/i', $text, $m ) ) {
			return (float) $m[1];
		}
		return 4.0;
	}

	/**
	 * @return array{prep:int,cook:int,total:int}
	 */
	private function detect_times( string $content ): array {
		$text = wp_strip_all_tags( $content );
		$prep = 0;
		$cook = 0;
		$total = 0;
		if ( preg_match( '/prep(?:\s*time)?\s*[:\-]?\s*(\d+)\s*(min|minute)/i', $text, $m ) ) {
			$prep = (int) $m[1];
		}
		if ( preg_match( '/cook(?:\s*time)?\s*[:\-]?\s*(\d+)\s*(min|minute)/i', $text, $m ) ) {
			$cook = (int) $m[1];
		}
		if ( preg_match( '/total(?:\s*time)?\s*[:\-]?\s*(\d+)\s*(min|minute)/i', $text, $m ) ) {
			$total = (int) $m[1];
		}
		if ( $total <= 0 && ( $prep > 0 || $cook > 0 ) ) {
			$total = $prep + $cook;
		}
		return array(
			'prep'  => $prep,
			'cook'  => $cook,
			'total' => $total,
		);
	}

	private function replace_or_append_card( string $content, string $card_html ): string {
		$patterns = array(
			'/(<div[^>]+class=["\'][^"\']*rsaip-recipe[^"\']*["\'][^>]*>.*?<\/div>)/is',
			'/(\[rsaip_recipe[^\]]*\].*?\[\/rsaip_recipe\])/is',
			'/(<div[^>]+class=["\'][^"\']*wp-block-recipe[^"\']*["\'][^>]*>.*?<\/div>)/is',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				return (string) preg_replace( $pattern, $card_html, $content, 1 );
			}
		}
		// No existing card — append after content.
		$content = rtrim( $content );
		return $content . "\n\n" . $card_html . "\n";
	}
}
