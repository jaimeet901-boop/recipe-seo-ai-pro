<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSAIP_Recipe_Optimizer {
	private const CARD_START = '<!-- RSAIP_RECIPE_CARD_START -->';
	private const CARD_END   = '<!-- RSAIP_RECIPE_CARD_END -->';

	public function scan( int $limit = 100 ): array {
		$limit = max( 1, min( 200, $limit ) );
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'fields'         => 'all',
			)
		);

		$rows = array();
		foreach ( (array) $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$data = $this->extract_recipe_data( $post );
			$checklist = array(
				__( 'Ingredients', 'recipe-seo-ai-pro' ) => ! empty( $data['ingredients'] ),
				__( 'Instructions', 'recipe-seo-ai-pro' ) => ! empty( $data['instructions'] ),
				__( 'Description', 'recipe-seo-ai-pro' ) => trim( $data['description'] ) !== '',
				__( 'Schema', 'recipe-seo-ai-pro' ) => $this->post_has_recipe_schema( (string) $post->post_content ),
			);

			$score = 0;
			foreach ( $checklist as $passed ) {
				if ( $passed ) {
					$score += 25;
				}
			}

			$rows[] = array(
				'post_id'   => $post->ID,
				'title'     => get_the_title( $post->ID ),
				'score'     => min( 100, $score ),
				'checklist' => $checklist,
				'actions'   => array(
					array(
						'label'  => __( 'Preview', 'recipe-seo-ai-pro' ),
						'action' => 'rsaip_recipe_generate_card',
					),
					array(
						'label'  => __( 'Insert', 'recipe-seo-ai-pro' ),
						'action' => 'rsaip_recipe_insert_card',
					),
				),
			);
		}

		return $rows;
	}

	public function render_rows_html( array $rows ): string {
		if ( empty( $rows ) ) {
			return '<tr><td colspan="4">' . esc_html__( 'No recipe posts found.', 'recipe-seo-ai-pro' ) . '</td></tr>';
		}

		$html = '';
		foreach ( $rows as $row ) {
			$post_id = absint( $row['post_id'] ?? 0 );
			$title = (string) ( $row['title'] ?? '' );
			$score = absint( $row['score'] ?? 0 );
			$checklist = isset( $row['checklist'] ) && is_array( $row['checklist'] ) ? $row['checklist'] : array();
			$actions = isset( $row['actions'] ) && is_array( $row['actions'] ) ? $row['actions'] : array();

			$html .= '<tr>';
			$html .= '<td>' . esc_html( $title ) . '</td>';
			$html .= '<td><ul class="rsaip-inline-list">';
			foreach ( $checklist as $label => $passed ) {
				$html .= '<li>' . esc_html( (string) $label ) . ': ' . ( $passed ? esc_html__( 'Yes', 'recipe-seo-ai-pro' ) : esc_html__( 'No', 'recipe-seo-ai-pro' ) ) . '</li>';
			}
			$html .= '</ul></td>';
			$html .= '<td>' . esc_html( (string) $score ) . '</td>';
			$html .= '<td>';
			foreach ( $actions as $action ) {
				$label = (string) ( $action['label'] ?? '' );
				$action_name = (string) ( $action['action'] ?? '' );
				if ( $label === '' || $action_name === '' ) {
					continue;
				}
				$html .= '<button type="button" class="button rsaip-btn" data-action="' . esc_attr( $action_name ) . '" data-post-id="' . esc_attr( (string) $post_id ) . '">' . esc_html( $label ) . '</button> ';
			}
			$html .= '</td>';
			$html .= '</tr>';
		}

		return $html;
	}

	public function generate_recipe_card_for_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array(
				'ok'      => false,
				'message' => __( 'Post not found.', 'recipe-seo-ai-pro' ),
			);
		}

		$data = $this->extract_recipe_data( $post );
		$html = $this->build_recipe_card_html( $post, $data );

		return array(
			'ok'      => true,
			'post_id' => $post_id,
			'html'    => $html,
		);
	}

	public function insert_recipe_card_for_post( int $post_id ): array {
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
			return array(
				'ok'      => false,
				'message' => __( 'Post not found.', 'recipe-seo-ai-pro' ),
			);
		}

		if ( strpos( (string) $post->post_content, 'RSAIP_RECIPE_CARD_START' ) !== false || strpos( (string) $post->post_content, 'rsaip-recipe-card' ) !== false ) {
			return array(
				'ok'      => false,
				'message' => __( 'Recipe card already exists in this post.', 'recipe-seo-ai-pro' ),
			);
		}

		$data = $this->extract_recipe_data( $post );
		$card = $this->build_recipe_card_html( $post, $data );
		$content = rtrim( (string) $post->post_content );
		$content .= "\n\n" . $card;

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_content' => wp_slash( $content ),
			)
		);

		return array(
			'ok'      => true,
			'post_id' => $post_id,
			'html'    => $card,
			'message' => __( 'Recipe card inserted.', 'recipe-seo-ai-pro' ),
		);
	}

	private function extract_recipe_data( WP_Post $post ): array {
		$content = (string) $post->post_content;
		$plain = $this->plain_text_from_content( $content );
		$schema_data = $this->extract_recipe_schema_data( $content );

		$ingredients = ! empty( $schema_data['ingredients'] ) ? (array) $schema_data['ingredients'] : $this->extract_html_list_section( $content, array( 'Ingredients', 'Ingredient', 'What You Need', 'المكونات' ) );
		if ( empty( $ingredients ) ) {
			$ingredients = $this->extract_list_block( $plain, array( 'Ingredients', 'Ingredient', 'المكونات' ), array( 'Instructions', 'Directions', 'Method', 'How to Make', 'طريقة التحضير', 'الطريقة', 'Prep Time', 'Cook Time', 'Nutrition', 'FAQ', 'الأسئلة الشائعة' ) );
		}

		$instructions = ! empty( $schema_data['instructions'] ) ? (array) $schema_data['instructions'] : $this->extract_html_list_section( $content, array( 'Instructions', 'Directions', 'Method', 'How to Make', 'طريقة التحضير', 'الطريقة' ) );
		if ( empty( $instructions ) ) {
			$instructions = $this->extract_list_block( $plain, array( 'Instructions', 'Directions', 'Method', 'طريقة التحضير', 'الطريقة' ), array( 'Nutrition', 'Prep Time', 'Cook Time', 'Ingredients', 'FAQ', 'الأسئلة الشائعة' ) );
		}

		$prep_time = ! empty( $schema_data['prep_time'] ) ? (string) $schema_data['prep_time'] : $this->extract_inline_value( $plain, array( 'Prep Time', 'وقت التحضير' ) );
		$cook_time = ! empty( $schema_data['cook_time'] ) ? (string) $schema_data['cook_time'] : $this->extract_inline_value( $plain, array( 'Cook Time', 'وقت الطهي', 'وقت الطبخ' ) );
		$nutrition = ! empty( $schema_data['nutrition'] ) ? (string) $schema_data['nutrition'] : $this->extract_inline_value( $plain, array( 'Nutrition', 'القيم الغذائية' ) );
		$servings = ! empty( $schema_data['servings'] ) ? (string) $schema_data['servings'] : $this->extract_inline_value( $plain, array( 'Servings', 'Yield', 'الكمية', 'عدد الحصص' ) );
		$notes = $this->extract_html_notes_section( $content, array( 'Notes', 'Tips', 'Chef Tips', 'Storage', 'ملاحظات', 'نصائح', 'التخزين' ) );
		$category = $this->get_primary_category_label( $post->ID );
		$description = $this->build_recipe_description( $post, $schema_data );

		return array(
			'title'        => get_the_title( $post->ID ),
			'description'  => $description,
			'ingredients'  => $ingredients,
			'instructions' => $instructions,
			'prep_time'    => $prep_time,
			'cook_time'    => $cook_time,
			'total_time'   => $this->build_total_time_label( $prep_time, $cook_time ),
			'servings'     => $servings,
			'nutrition'    => $nutrition,
			'category'     => $category,
			'notes'        => $notes,
			'image'        => get_the_post_thumbnail_url( $post->ID, 'full' ) ?: '',
			'url'          => get_permalink( $post->ID ),
		);
	}

	private function build_recipe_card_html( WP_Post $post, array $data ): string {
		$schema = array(
			'@context'          => 'https://schema.org',
			'@type'             => 'Recipe',
			'name'              => (string) $data['title'],
			'description'       => (string) $data['description'],
			'keywords'          => implode( ', ', $this->build_recipe_keywords( $post, $data ) ),
			'mainEntityOfPage'  => (string) $data['url'],
			'recipeIngredient'  => array_values( (array) $data['ingredients'] ),
			'recipeInstructions' => array(),
		);

		if ( ! empty( $data['image'] ) ) {
			$schema['image'] = array( (string) $data['image'] );
		}
		if ( ! empty( $data['prep_time'] ) ) {
			$schema['prepTime'] = $this->label_to_iso_duration( (string) $data['prep_time'] );
		}
		if ( ! empty( $data['cook_time'] ) ) {
			$schema['cookTime'] = $this->label_to_iso_duration( (string) $data['cook_time'] );
		}
		if ( ! empty( $data['total_time'] ) ) {
			$schema['totalTime'] = $this->label_to_iso_duration( (string) $data['total_time'] );
		}
		if ( ! empty( $data['servings'] ) ) {
			$schema['recipeYield'] = (string) $data['servings'];
		}
		if ( ! empty( $data['nutrition'] ) ) {
			$schema['nutrition'] = array(
				'@type'    => 'NutritionInformation',
				'calories' => (string) $data['nutrition'],
			);
		}

		$aggregate_rating = $this->build_recipe_aggregate_rating( $post );
		if ( ! empty( $aggregate_rating ) ) {
			$schema['aggregateRating'] = $aggregate_rating;
		}

		$video = $this->build_recipe_video_object( $post, $data );
		if ( ! empty( $video ) ) {
			$schema['video'] = $video;
		}

		foreach ( (array) $data['instructions'] as $step ) {
			$schema['recipeInstructions'][] = array(
				'@type' => 'HowToStep',
				'text'  => (string) $step,
			);
		}

		$html  = self::CARD_START . "\n";
		$html .= '<div class="rsaip-recipe-card" data-post-id="' . esc_attr( (string) $post->ID ) . '" data-recipe-url="' . esc_attr( (string) $data['url'] ) . '" data-recipe-title="' . esc_attr( (string) $data['title'] ) . '">';
		$html .= '<div class="rsaip-recipe-card__hero' . ( empty( $data['image'] ) ? ' rsaip-recipe-card__hero--no-image' : '' ) . '">';
		if ( ! empty( $data['image'] ) ) {
			$html .= '<div class="rsaip-recipe-card__media"><img src="' . esc_url( (string) $data['image'] ) . '" alt="' . esc_attr( (string) $data['title'] ) . '" /></div>';
		}
		$html .= '<div class="rsaip-recipe-card__content">';
		$html .= '<div class="rsaip-recipe-card__eyebrow">' . esc_html__( 'Recipe Card', 'recipe-seo-ai-pro' ) . '</div>';
		$html .= '<h2 class="rsaip-recipe-card__title">' . esc_html( (string) $data['title'] ) . '</h2>';
		if ( ! empty( $data['description'] ) ) {
			$html .= '<p class="rsaip-recipe-card__desc">' . esc_html( (string) $data['description'] ) . '</p>';
		}

		$rating_value = '';
		$review_count = 0;
		if ( ! empty( $schema['aggregateRating'] ) && is_array( $schema['aggregateRating'] ) ) {
			$rating_value = isset( $schema['aggregateRating']['ratingValue'] ) ? (string) $schema['aggregateRating']['ratingValue'] : '';
			$review_count = isset( $schema['aggregateRating']['reviewCount'] ) ? (int) $schema['aggregateRating']['reviewCount'] : 0;
		}
		$html .= '<div class="rsaip-recipe-card__header-row">';
		$html .= '<div class="rsaip-recipe-meta">';
		$html .= '<div class="rsaip-rating">';
		if ( $rating_value !== '' ) {
			$round = round( (float) $rating_value, 1 );
			$html .= '<div class="rsaip-stars" aria-hidden="true">' . $this->render_stars( $round ) . '</div>';
			$html .= '<div class="rsaip-rating-value">' . esc_html( (string) $round ) . '</div>';
			if ( $review_count > 0 ) {
				$html .= '<div class="rsaip-review-count">' . esc_html( (string) $review_count ) . ' ' . esc_html__( 'Reviews', 'recipe-seo-ai-pro' ) . '</div>';
			}
		}
		$html .= '</div>';

		$author = get_the_author_meta( 'display_name', $post->post_author );
		$updated = get_post_modified_time( 'F j, Y', false, $post );
		$html .= '<div class="rsaip-byline">';
		if ( is_string( $author ) && $author !== '' ) {
			$html .= '<span class="rsaip-author">' . esc_html( $author ) . '</span>';
		}
		if ( is_string( $updated ) && $updated !== '' ) {
			$html .= '<span class="rsaip-updated">' . esc_html( $updated ) . '</span>';
		}
		$html .= '<span class="rsaip-tested-by">' . esc_html__( 'Tested by', 'recipe-seo-ai-pro' ) . ' ' . esc_html( get_post_meta( $post->ID, 'rsaip_tested_by', true ) ?: '' ) . '</span>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<div class="rsaip-recipe-actions">';
		$html .= '<button type="button" class="button rsaip-btn rsaip-recipe-action" data-action="save-recipe">' . esc_html__( 'Save', 'recipe-seo-ai-pro' ) . '</button>';
		$html .= '<button type="button" class="button rsaip-btn rsaip-recipe-action" data-action="rate-recipe">' . esc_html__( 'Rate', 'recipe-seo-ai-pro' ) . '</button>';
		$html .= '<button type="button" class="button rsaip-btn rsaip-recipe-action" data-action="print-recipe">' . esc_html__( 'Print', 'recipe-seo-ai-pro' ) . '</button>';
		$html .= '<button type="button" class="button rsaip-btn rsaip-recipe-action" data-action="share-recipe">' . esc_html__( 'Share', 'recipe-seo-ai-pro' ) . '</button>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<div class="rsaip-recipe-card__stats">';
		$html .= $this->metric_card_html( __( 'Prep', 'recipe-seo-ai-pro' ), (string) $data['prep_time'] );
		$html .= $this->metric_card_html( __( 'Cook', 'recipe-seo-ai-pro' ), (string) $data['cook_time'] );
		$html .= $this->metric_card_html( __( 'Total', 'recipe-seo-ai-pro' ), (string) $data['total_time'] );
		$html .= $this->metric_card_html( __( 'Servings', 'recipe-seo-ai-pro' ), (string) $data['servings'] );
		$html .= $this->metric_card_html( __( 'Category', 'recipe-seo-ai-pro' ), (string) $data['category'] );
		$html .= $this->metric_card_html( __( 'Nutrition', 'recipe-seo-ai-pro' ), (string) $data['nutrition'] );
		$html .= '</div>';

		$html .= '<div class="rsaip-recipe-card__grid">';
		if ( ! empty( $data['ingredients'] ) ) {
			$html .= '<div class="rsaip-recipe-card__section">';
			$html .= '<h3>' . esc_html__( 'Ingredients', 'recipe-seo-ai-pro' ) . '</h3><ul class="rsaip-recipe-card__list">';
			foreach ( (array) $data['ingredients'] as $item ) {
				$html .= '<li>' . esc_html( (string) $item ) . '</li>';
			}
			$html .= '</ul></div>';
		}

		if ( ! empty( $data['instructions'] ) ) {
			$html .= '<div class="rsaip-recipe-card__section">';
			$html .= '<h3>' . esc_html__( 'Instructions', 'recipe-seo-ai-pro' ) . '</h3><ol class="rsaip-recipe-card__steps">';
			foreach ( (array) $data['instructions'] as $step ) {
				$html .= '<li>' . esc_html( (string) $step ) . '</li>';
			}
			$html .= '</ol></div>';
		}
		$html .= '</div>';

		if ( ! empty( $data['notes'] ) ) {
			$html .= '<div class="rsaip-recipe-card__notes"><strong>' . esc_html__( 'Notes', 'recipe-seo-ai-pro' ) . ':</strong> ' . esc_html( (string) $data['notes'] ) . '</div>';
		}

		if ( ! $this->post_has_recipe_schema( (string) $post->post_content ) ) {
			$html .= '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
		}

		$html .= '</div>' . "\n";
		$html .= self::CARD_END;

		return $html;
	}

	private function build_recipe_keywords( WP_Post $post, array $data ): array {
		$keywords = array();
		foreach ( rsaip_get_focus_keywords( $post->ID ) as $keyword ) {
			$keyword = sanitize_text_field( (string) $keyword );
			$keyword = trim( $keyword );
			if ( $keyword !== '' ) {
				$keywords[] = $keyword;
			}
		}
		if ( empty( $keywords ) ) {
			$keywords[] = (string) $data['title'];
			$category = trim( (string) $data['category'] );
			if ( $category !== '' ) {
				$keywords[] = $category;
			}
		}

		$keywords = array_values( array_unique( array_filter( $keywords, static function ( $value ): bool {
			return is_string( $value ) && trim( $value ) !== '';
		} ) ) );
		return array_slice( $keywords, 0, 10 );
	}

	private function build_recipe_aggregate_rating( WP_Post $post ): array {
		$existing = $this->extract_existing_recipe_rating( (string) $post->post_content );
		if ( ! empty( $existing ) ) {
			return $existing;
		}

		$rating_candidates = array( 'rsaip_recipe_rating_value', 'aggregate_rating', 'rating', 'recipe_rating' );
		$review_candidates = array( 'rsaip_recipe_review_count', 'review_count', 'aggregate_rating_count', 'recipe_review_count' );

		$rating_value = '';
		foreach ( $rating_candidates as $meta_key ) {
			$meta_value = get_post_meta( $post->ID, $meta_key, true );
			if ( is_string( $meta_value ) && trim( $meta_value ) !== '' ) {
				$rating_value = trim( $meta_value );
				break;
			}
			if ( is_numeric( $meta_value ) ) {
				$rating_value = (string) $meta_value;
				break;
			}
		}

		$review_count = 0;
		foreach ( $review_candidates as $meta_key ) {
			$meta_value = get_post_meta( $post->ID, $meta_key, true );
			if ( is_numeric( $meta_value ) ) {
				$review_count = (int) $meta_value;
				break;
			}
		}

		if ( $rating_value === '' ) {
			return array();
		}

		$rating_value = (float) $rating_value;
		if ( $rating_value <= 0 ) {
			return array();
		}

		$rating = array(
			'@type'       => 'AggregateRating',
			'ratingValue' => (string) $rating_value,
			'bestRating'  => '5',
			'worstRating' => '1',
		);
		if ( $review_count > 0 ) {
			$rating['reviewCount'] = $review_count;
		}

		return $rating;
	}

	private function build_recipe_video_object( WP_Post $post, array $data ): array {
		$video_url = $this->extract_video_url_from_content( (string) $post->post_content );
		if ( $video_url === '' ) {
			return array();
		}

		$video = array(
			'@type'       => 'VideoObject',
			'name'        => (string) $data['title'],
			'description' => (string) $data['description'],
			'contentUrl'  => $video_url,
			'embedUrl'    => $video_url,
		);
		$thumbnail = get_the_post_thumbnail_url( $post->ID, 'large' );
		if ( is_string( $thumbnail ) && $thumbnail !== '' ) {
			$video['thumbnailUrl'] = $thumbnail;
		}

		return $video;
	}

	private function extract_existing_recipe_rating( string $content ): array {
		if ( ! preg_match_all( '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/isu', $content, $matches ) ) {
			return array();
		}

		foreach ( (array) $matches[1] as $raw ) {
			$decoded = json_decode( trim( (string) $raw ), true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}
			$nodes = $this->flatten_schema_nodes( $decoded );
			foreach ( $nodes as $node ) {
				$type = $node['@type'] ?? '';
				if ( $type !== 'Recipe' && ( ! is_array( $type ) || ! in_array( 'Recipe', $type, true ) ) ) {
					continue;
				}
				if ( ! empty( $node['aggregateRating'] ) && is_array( $node['aggregateRating'] ) ) {
					return $node['aggregateRating'];
				}
			}
		}

		return array();
	}

	private function extract_video_url_from_content( string $content ): string {
		$patterns = array(
			'/https?:\/\/[^\s"\'<>]+(?:youtube\.com|youtu\.be|vimeo\.com|videopress\.com|\.mp4|\.webm)[^\s"\'<>]*/iu',
			'/\b(?:youtube\.com|youtu\.be|vimeo\.com|videopress\.com)\b[^\s"\'<>]*/iu',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $content, $match ) ) {
				return trim( (string) $match[0] );
			}
		}

		return '';
	}

	private function post_has_recipe_schema( string $content ): bool {
		if ( ! preg_match_all( '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/isu', $content, $matches ) ) {
			return false;
		}

		foreach ( (array) $matches[1] as $raw ) {
			$decoded = json_decode( trim( (string) $raw ), true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}
			$nodes = $this->flatten_schema_nodes( $decoded );
			foreach ( $nodes as $node ) {
				$type = $node['@type'] ?? '';
				if ( $type === 'Recipe' || ( is_array( $type ) && in_array( 'Recipe', $type, true ) ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private function render_stars( float $rating ): string {
		$out = '';
		$full = (int) floor( $rating );
		$half = ( $rating - $full ) >= 0.5 ? 1 : 0;
		for ( $i = 0; $i < 5; $i++ ) {
			if ( $i < $full ) {
				$out .= '<span class="rsaip-star rsaip-star-full">★</span>';
			} elseif ( $i === $full && $half ) {
				$out .= '<span class="rsaip-star rsaip-star-half">★</span>';
			} else {
				$out .= '<span class="rsaip-star rsaip-star-empty">☆</span>';
			}
		}
		return $out;
	}

	private function metric_card_html( string $label, string $value ): string {
		if ( trim( $value ) === '' ) {
			return '';
		}
		return '<div class="rsaip-recipe-card__stat"><span class="rsaip-recipe-card__stat-label">' . esc_html( $label ) . '</span><span class="rsaip-recipe-card__stat-value">' . esc_html( $value ) . '</span></div>';
	}

	private function plain_text_from_content( string $content ): string {
		$text = preg_replace( '/<\s*br\s*\/?>/i', "\n", $content );
		$text = preg_replace( '/<\/(p|div|li|ul|ol|h1|h2|h3|h4|h5|h6|table|tr)>/i', "$0\n", (string) $text );
		$text = wp_strip_all_tags( (string) $text );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( "/\r\n?", "\n", (string) $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", (string) $text );
		return trim( (string) $text );
	}

	private function extract_list_block( string $text, array $headings, array $stop_headings ): array {
		$heading_pattern = implode( '|', array_map( 'preg_quote', $headings ) );
		$stop_pattern = implode( '|', array_map( 'preg_quote', $stop_headings ) );

		if ( ! preg_match( '/(?:^|\n)\s*(?:' . $heading_pattern . ')\s*:?\s*\n(.+?)(?=\n\s*(?:' . $stop_pattern . ')\b|\z)/isu', $text, $match ) ) {
			return array();
		}

		$block = trim( (string) ( $match[1] ?? '' ) );
		if ( $block === '' ) {
			return array();
		}

		$lines = preg_split( '/\n+/u', $block );
		$out = array();
		foreach ( (array) $lines as $line ) {
			$line = trim( (string) $line );
			$line = preg_replace( '/^[\-\*\•\d\.\)\(]+\s*/u', '', (string) $line );
			$line = preg_replace( '/\s+/u', ' ', (string) $line );
			$line = trim( (string) $line );
			if ( $line === '' ) {
				continue;
			}
			$out[] = $line;
			if ( count( $out ) >= 20 ) {
				break;
			}
		}

		return $out;
	}

	private function extract_html_list_section( string $content, array $headings ): array {
		$heading_pattern = implode( '|', array_map( 'preg_quote', $headings ) );
		if ( ! preg_match( '/<h[2-4][^>]*>\s*(?:' . $heading_pattern . ')\s*<\/h[2-4]>(.*?)(?=<h[2-4][^>]*>|$)/isu', $content, $match ) ) {
			return array();
		}

		$block = (string) ( $match[1] ?? '' );
		$items = array();

		if ( preg_match_all( '/<li[^>]*>(.*?)<\/li>/isu', $block, $li_matches ) ) {
			foreach ( (array) $li_matches[1] as $li ) {
				$text = trim( wp_strip_all_tags( html_entity_decode( (string) $li, ENT_QUOTES, 'UTF-8' ) ) );
				$text = preg_replace( '/\s+/u', ' ', (string) $text );
				if ( $text === '' ) {
					continue;
				}
				$items[] = $text;
			}
		}

		if ( empty( $items ) ) {
			$block = preg_replace( '/<br\s*\/?>/i', "\n", $block );
			$block = wp_strip_all_tags( (string) $block );
			$lines = preg_split( '/\n+/u', (string) $block );
			foreach ( (array) $lines as $line ) {
				$line = trim( preg_replace( '/^[\-\*\•\d\.\)\(]+\s*/u', '', (string) $line ) );
				$line = preg_replace( '/\s+/u', ' ', (string) $line );
				if ( $line === '' ) {
					continue;
				}
				$items[] = $line;
			}
		}

		return array_values( array_slice( array_unique( $items ), 0, 25 ) );
	}

	private function extract_html_notes_section( string $content, array $headings ): string {
		$heading_pattern = implode( '|', array_map( 'preg_quote', $headings ) );
		if ( preg_match( '/<h[2-4][^>]*>\s*(?:' . $heading_pattern . ')\s*<\/h[2-4]>(.*?)(?=<h[2-4][^>]*>|$)/isu', $content, $match ) ) {
			$text = trim( wp_strip_all_tags( html_entity_decode( (string) ( $match[1] ?? '' ), ENT_QUOTES, 'UTF-8' ) ) );
			$text = preg_replace( '/\s+/u', ' ', (string) $text );
			return $this->safe_truncate( (string) $text, 260 );
		}
		return '';
	}

	private function extract_recipe_schema_data( string $content ): array {
		$data = array(
			'ingredients'  => array(),
			'instructions' => array(),
			'prep_time'    => '',
			'cook_time'    => '',
			'servings'     => '',
			'nutrition'    => '',
			'description'  => '',
		);

		if ( ! preg_match_all( '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/isu', $content, $matches ) ) {
			return $data;
		}

		foreach ( (array) $matches[1] as $raw ) {
			$decoded = json_decode( trim( (string) $raw ), true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}
			$nodes = $this->flatten_schema_nodes( $decoded );
			foreach ( $nodes as $node ) {
				$type = $node['@type'] ?? '';
				if ( $type !== 'Recipe' && ( ! is_array( $type ) || ! in_array( 'Recipe', $type, true ) ) ) {
					continue;
				}

				$data['ingredients']  = $this->normalize_schema_ingredients( $node['recipeIngredient'] ?? array() );
				$data['instructions'] = $this->normalize_schema_instructions( $node['recipeInstructions'] ?? array() );
				$data['prep_time']    = $this->iso_to_label( (string) ( $node['prepTime'] ?? '' ) );
				$data['cook_time']    = $this->iso_to_label( (string) ( $node['cookTime'] ?? '' ) );
				$data['servings']     = sanitize_text_field( (string) ( $node['recipeYield'] ?? '' ) );
				if ( ! empty( $node['nutrition']['calories'] ) ) {
					$data['nutrition'] = sanitize_text_field( (string) $node['nutrition']['calories'] );
				}
				$data['description'] = sanitize_text_field( (string) ( $node['description'] ?? '' ) );
				return $data;
			}
		}

		return $data;
	}

	private function flatten_schema_nodes( $schema ): array {
		$out = array();
		if ( ! is_array( $schema ) ) {
			return $out;
		}

		$out[] = $schema;
		foreach ( $schema as $value ) {
			if ( is_array( $value ) ) {
				foreach ( $this->flatten_schema_nodes( $value ) as $child ) {
					$out[] = $child;
				}
			}
		}
		return $out;
	}

	private function normalize_schema_ingredients( $ingredients ): array {
		if ( ! is_array( $ingredients ) ) {
			return array();
		}
		$out = array();
		foreach ( $ingredients as $ingredient ) {
			$text = sanitize_text_field( is_string( $ingredient ) ? $ingredient : wp_json_encode( $ingredient ) );
			$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
			if ( $text !== '' ) {
				$out[] = $text;
			}
		}
		return array_values( array_slice( array_unique( $out ), 0, 25 ) );
	}

	private function normalize_schema_instructions( $instructions ): array {
		if ( is_string( $instructions ) && trim( $instructions ) !== '' ) {
			return array( sanitize_text_field( trim( $instructions ) ) );
		}
		if ( ! is_array( $instructions ) ) {
			return array();
		}

		$out = array();
		foreach ( $instructions as $instruction ) {
			if ( is_string( $instruction ) ) {
				$text = sanitize_text_field( trim( $instruction ) );
			} elseif ( is_array( $instruction ) ) {
				$text = sanitize_text_field( (string) ( $instruction['text'] ?? $instruction['name'] ?? '' ) );
			} else {
				$text = '';
			}
			$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
			if ( $text !== '' ) {
				$out[] = $text;
			}
		}
		return array_values( array_slice( array_unique( $out ), 0, 25 ) );
	}

	private function build_recipe_description( WP_Post $post, array $schema_data ): string {
		if ( ! empty( $schema_data['description'] ) ) {
			return (string) $schema_data['description'];
		}
		$excerpt = has_excerpt( $post ) ? (string) $post->post_excerpt : (string) wp_trim_words( wp_strip_all_tags( $post->post_content ), 28, '' );
		$excerpt = trim( preg_replace( '/\s+/u', ' ', (string) $excerpt ) );
		return $this->safe_truncate( $excerpt, 180 );
	}

	private function get_primary_category_label( int $post_id ): string {
		$categories = get_the_terms( $post_id, 'category' );
		if ( is_array( $categories ) && ! empty( $categories[0]->name ) ) {
			return sanitize_text_field( (string) $categories[0]->name );
		}
		return '';
	}

	private function extract_inline_value( string $text, array $labels ): string {
		$label_pattern = implode( '|', array_map( 'preg_quote', $labels ) );
		if ( preg_match( '/(?:' . $label_pattern . ')\s*[:\-]?\s*([^\n]{1,80})/iu', $text, $match ) ) {
			return trim( (string) $match[1] );
		}
		return '';
	}

	private function build_total_time_label( string $prep_time, string $cook_time ): string {
		$prep_minutes = $this->label_to_minutes( $prep_time );
		$cook_minutes = $this->label_to_minutes( $cook_time );
		$total = $prep_minutes + $cook_minutes;

		if ( $total <= 0 ) {
			return '';
		}

		$hours = (int) floor( $total / 60 );
		$minutes = $total % 60;
		if ( $hours > 0 && $minutes > 0 ) {
			return $hours . ' hour ' . $minutes . ' min';
		}
		if ( $hours > 0 ) {
			return $hours . ' hour';
		}
		return $minutes . ' min';
	}

	private function label_to_minutes( string $label ): int {
		$label = strtolower( trim( $label ) );
		if ( $label === '' ) {
			return 0;
		}

		$total = 0;
		if ( preg_match( '/(\d+)\s*(hour|hours|hr|hrs|ساعة|ساعات)/u', $label, $hours ) ) {
			$total += absint( $hours[1] ) * 60;
		}
		if ( preg_match( '/(\d+)\s*(minute|minutes|min|mins|دقيقة|دقائق)/u', $label, $mins ) ) {
			$total += absint( $mins[1] );
		}
		if ( 0 === $total && preg_match( '/^\d+$/', $label ) ) {
			$total = absint( $label );
		}

		return $total;
	}

	private function label_to_iso_duration( string $label ): string {
		$minutes = $this->label_to_minutes( $label );
		if ( $minutes <= 0 ) {
			return '';
		}

		$hours = (int) floor( $minutes / 60 );
		$mins = $minutes % 60;
		$iso = 'PT';
		if ( $hours > 0 ) {
			$iso .= $hours . 'H';
		}
		if ( $mins > 0 ) {
			$iso .= $mins . 'M';
		}
		return 'PT' === $iso ? '' : $iso;
	}

	private function iso_to_label( string $iso ): string {
		$iso = trim( strtoupper( $iso ) );
		if ( $iso === '' || strpos( $iso, 'PT' ) !== 0 ) {
			return '';
		}

		$hours = 0;
		$mins = 0;
		if ( preg_match( '/(\d+)H/', $iso, $hour_match ) ) {
			$hours = absint( $hour_match[1] );
		}
		if ( preg_match( '/(\d+)M/', $iso, $min_match ) ) {
			$mins = absint( $min_match[1] );
		}

		if ( $hours > 0 && $mins > 0 ) {
			return $hours . ' hour ' . $mins . ' min';
		}
		if ( $hours > 0 ) {
			return $hours . ' hour';
		}
		if ( $mins > 0 ) {
			return $mins . ' min';
		}
		return '';
	}

	private function safe_truncate( string $text, int $length ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $text, 0, $length );
		}
		return substr( $text, 0, $length );
	}
}
