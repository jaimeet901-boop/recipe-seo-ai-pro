<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSAIP_AI {
	/**
	 * Milestone 5C: titles from unified SeoOptimizationProposal (no independent AI call / no heuristic fallback).
	 * Candidates only — Apply remains Propose → Preview → PMS.
	 */
	public function generate_title_suggestions_for_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array(
				'ok'      => false,
				'code'    => 'rsaip_seo_opt_post_not_found',
				'message' => __( 'Post not found.', 'recipe-seo-ai-pro' ),
				'source'  => 'seo_optimization',
			);
		}

		$title   = get_the_title( $post_id );
		$service = new \RecipeSeoAiPro\Modules\Ai\SeoOptimization\SeoOptimizationService();
		$result  = $service->get_or_create_proposal_for_post( $post_id );
		if ( ! $result->ok() || ! $result->proposal() ) {
			return array(
				'ok'      => false,
				'post_id' => $post_id,
				'current' => $title,
				'code'    => $result->code(),
				'message' => $result->message() !== '' ? $result->message() : __( 'SEO title generation failed.', 'recipe-seo-ai-pro' ),
				'source'  => 'seo_optimization',
			);
		}

		$proposal = $result->proposal();
		$payload  = $service->titles_from_proposal( $proposal, $post_id );

		// 4B hard gate: keep only titles that preserve the validated recipe entity.
		$focus = rsaip_get_focus_keywords( $post_id );
		$kw    = $focus ? (string) $focus[0] : (string) $proposal->primary_focus_keyword();
		$topic = $this->title_topic_selector()->resolve( $title, $kw );
		if ( (string) $proposal->recipe_name() !== '' ) {
			$topic = $this->title_topic_selector()->resolve( (string) $proposal->recipe_name(), (string) $proposal->primary_focus_keyword() );
		}

		$titles = $this->sanitize_titles( is_array( $payload['titles'] ?? null ) ? $payload['titles'] : array() );
		$titles = $this->title_topic_selector()->keep_titles_with_topic( $titles, $topic );
		if ( ! $titles ) {
			return array(
				'ok'      => false,
				'post_id' => $post_id,
				'current' => $title,
				'code'    => 'rsaip_seo_opt_titles_rejected',
				'message' => __( 'SEO title suggestions did not preserve the recipe topic.', 'recipe-seo-ai-pro' ),
				'source'  => 'seo_optimization',
			);
		}

		$recommended = (string) ( $payload['recommended_title'] ?? '' );
		if ( $recommended !== '' && ! in_array( $recommended, $titles, true ) ) {
			$recommended = (string) $titles[0];
		} elseif ( $recommended === '' ) {
			$recommended = (string) $titles[0];
		}

		return array(
			'ok'                    => true,
			'post_id'               => $post_id,
			'current'               => $title,
			'topic'                 => (string) $proposal->topic(),
			'recipe_name'           => (string) $proposal->recipe_name(),
			'recommended_title'     => $recommended,
			'titles'                => $titles,
			'seo_title_suggestions' => $titles,
			'source'                => 'seo_optimization',
		);
	}

	public function apply_post_title( int $post_id, string $title ): array {
		if ( ! function_exists( 'rsaip_never_modify_posts' ) || rsaip_never_modify_posts() ) {
			return array(
				'ok'      => false,
				'message' => function_exists( 'rsaip_existing_post_mutation_frozen_message' )
					? rsaip_existing_post_mutation_frozen_message()
					: 'This existing-post mutation is temporarily disabled while Safe Article Mode is enabled.',
			);
		}

		if ( ! function_exists( 'rsaip_post_mutation_service' ) ) {
			return array(
				'ok'      => false,
				'code'    => 'writer_missing',
				'message' => __( 'Post mutation service is unavailable.', 'recipe-seo-ai-pro' ),
			);
		}

		$result = rsaip_post_mutation_service()->apply_fresh(
			array(
				'post_id'       => $post_id,
				'mutation_type' => \RecipeSeoAiPro\Modules\PostMutation\MutationType::TITLE,
				'value'         => $title,
			)
		);

		$out = array(
			'ok'      => $result->ok(),
			'code'    => $result->code(),
			'message' => $result->message(),
			'post_id' => $post_id,
		);
		if ( $result->ok() ) {
			$out['title'] = $result->new_value();
		}
		return $out;
	}

	/**
	 * Milestone 5C: keywords from unified SeoOptimizationProposal.
	 * Primary and secondary stay separated; entities are display-only.
	 * Default Apply list = primary only (secondaries require UI selection).
	 */
	public function generate_keywords_for_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array(
				'ok'      => false,
				'code'    => 'rsaip_seo_opt_post_not_found',
				'message' => __( 'Post not found.', 'recipe-seo-ai-pro' ),
				'source'  => 'seo_optimization',
			);
		}

		$title   = get_the_title( $post_id );
		$service = new \RecipeSeoAiPro\Modules\Ai\SeoOptimization\SeoOptimizationService();
		$result  = $service->get_or_create_proposal_for_post( $post_id );
		if ( ! $result->ok() || ! $result->proposal() ) {
			return array(
				'ok'      => false,
				'post_id' => $post_id,
				'title'   => $title,
				'code'    => $result->code(),
				'message' => $result->message() !== '' ? $result->message() : __( 'SEO keyword generation failed.', 'recipe-seo-ai-pro' ),
				'source'  => 'seo_optimization',
			);
		}

		$payload = $service->keywords_from_proposal( $result->proposal(), $post_id );
		$payload['title'] = $title;
		return $payload;
	}

	public function apply_keywords_to_post( int $post_id, array $keywords, string $target = 'auto' ): array {
		if ( ! function_exists( 'rsaip_never_modify_posts' ) || rsaip_never_modify_posts() ) {
			return array(
				'ok'      => false,
				'message' => function_exists( 'rsaip_existing_post_mutation_frozen_message' )
					? rsaip_existing_post_mutation_frozen_message()
					: 'This existing-post mutation is temporarily disabled while Safe Article Mode is enabled.',
			);
		}

		if ( ! function_exists( 'rsaip_post_mutation_service' ) ) {
			return array(
				'ok'      => false,
				'code'    => 'writer_missing',
				'message' => __( 'Post mutation service is unavailable.', 'recipe-seo-ai-pro' ),
			);
		}

		$result = rsaip_post_mutation_service()->apply_fresh(
			array(
				'post_id'       => $post_id,
				'mutation_type' => \RecipeSeoAiPro\Modules\PostMutation\MutationType::KEYWORDS,
				'value'         => $keywords,
				'target'        => $target,
			)
		);

		$out = array(
			'ok'       => $result->ok(),
			'code'     => $result->code(),
			'message'  => $result->message(),
			'post_id'  => $post_id,
			'keywords' => $result->ok() ? $result->new_value() : $keywords,
		);
		return $out;
	}

	/**
	 * Milestone 5C: meta description from unified SeoOptimizationProposal.
	 * PMS MetaDescriptionWriter remains the Apply normalizer — no local rewrite.
	 */
	public function generate_meta_description_for_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array(
				'ok'      => false,
				'code'    => 'rsaip_seo_opt_post_not_found',
				'message' => __( 'Post not found.', 'recipe-seo-ai-pro' ),
				'source'  => 'seo_optimization',
			);
		}

		$title   = get_the_title( $post_id );
		$service = new \RecipeSeoAiPro\Modules\Ai\SeoOptimization\SeoOptimizationService();
		$result  = $service->get_or_create_proposal_for_post( $post_id );
		if ( ! $result->ok() || ! $result->proposal() ) {
			return array(
				'ok'      => false,
				'post_id' => $post_id,
				'title'   => $title,
				'code'    => $result->code(),
				'message' => $result->message() !== '' ? $result->message() : __( 'SEO meta description generation failed.', 'recipe-seo-ai-pro' ),
				'source'  => 'seo_optimization',
			);
		}

		$payload = $service->meta_from_proposal( $result->proposal(), $post_id );
		$payload['title'] = $title;
		return $payload;
	}

	public function apply_meta_description_to_post( int $post_id, string $metadesc, string $target = 'auto' ): array {
		if ( ! function_exists( 'rsaip_never_modify_posts' ) || rsaip_never_modify_posts() ) {
			return array(
				'ok'      => false,
				'message' => function_exists( 'rsaip_existing_post_mutation_frozen_message' )
					? rsaip_existing_post_mutation_frozen_message()
					: 'This existing-post mutation is temporarily disabled while Safe Article Mode is enabled.',
			);
		}

		if ( ! function_exists( 'rsaip_post_mutation_service' ) ) {
			return array(
				'ok'      => false,
				'code'    => 'writer_missing',
				'message' => __( 'Post mutation service is unavailable.', 'recipe-seo-ai-pro' ),
			);
		}

		$result = rsaip_post_mutation_service()->apply_fresh(
			array(
				'post_id'       => $post_id,
				'mutation_type' => \RecipeSeoAiPro\Modules\PostMutation\MutationType::META_DESCRIPTION,
				'value'         => $metadesc,
				'target'        => $target,
			)
		);

		$out = array(
			'ok'       => $result->ok(),
			'code'     => $result->code(),
			'message'  => $result->message(),
			'post_id'  => $post_id,
		);
		if ( $result->ok() ) {
			$out['metadesc'] = $result->new_value();
		}
		return $out;
	}

	public function generate_faq_suggestions_for_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array(
				'ok'      => false,
				'message' => __( 'Post not found.', 'recipe-seo-ai-pro' ),
			);
		}

		$title = get_the_title( $post_id );
		$focus = rsaip_get_focus_keywords( $post_id );
		$kw    = $focus ? (string) $focus[0] : '';

		if ( $this->use_ai() ) {
			$prompt = 'Create 5 SEO-friendly FAQs for a recipe blog post. Output STRICT JSON only: { "faqs": [ { "q": "...", "a": "..." } ] }. Keep answers 30-60 words.' . "\n"
				. 'Title: ' . $title . "\n"
				. 'Main keyword: ' . $kw . "\n"
				. 'Content excerpt: ' . wp_strip_all_tags( wp_trim_words( (string) $post->post_content, 120, '' ) );
			$res = $this->chat( $prompt, 700 );
			if ( ! is_wp_error( $res ) ) {
				$decoded = $this->try_decode_json( (string) $res );
				$faqs = is_array( $decoded ) ? ( $decoded['faqs'] ?? null ) : null;
				$faqs = is_array( $faqs ) ? $this->sanitize_faqs( $faqs ) : array();
				if ( $faqs ) {
					return array(
						'ok'      => true,
						'post_id' => $post_id,
						'title'   => sanitize_text_field( wp_strip_all_tags( (string) $title ) ),
						'faqs'    => $faqs,
						'source'  => 'ai',
					);
				}
			}
		}

		$faqs = $this->sanitize_faqs( $this->heuristic_faqs( $title ) );
		return array(
			'ok'      => true,
			'post_id' => $post_id,
			'title'   => sanitize_text_field( wp_strip_all_tags( (string) $title ) ),
			'faqs'    => $faqs,
			'source'  => 'heuristic',
		);
	}

	public function analyze_post_ai( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array(
				'ok'      => false,
				'message' => __( 'Post not found.', 'recipe-seo-ai-pro' ),
				'code'    => 'rsaip_seo_opt_post_not_found',
				'source'  => 'seo_optimization',
			);
		}

		// Milestone 5B/5C: unified OpenAI SEO engine (read-only). Stores proposal for Generate reuse.
		$service = new \RecipeSeoAiPro\Modules\Ai\SeoOptimization\SeoOptimizationService();
		$result  = $service->analyze_post( $post_id );
		$context = $result->context();
		if ( ! $context instanceof \RecipeSeoAiPro\Modules\Ai\SeoOptimization\SeoOptimizationContext ) {
			$context = $service->context_builder()->from_post_id( $post_id );
		}
		return $service->to_analyze_response(
			$result,
			$post_id,
			$context instanceof \RecipeSeoAiPro\Modules\Ai\SeoOptimization\SeoOptimizationContext ? $context : null
		);
	}

	public function generate_semantic_seo_for_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array( 'ok' => false, 'message' => 'post_not_found' );
		}

		$title = get_the_title( $post_id );
		$focus = rsaip_get_focus_keywords( $post_id );
		$text  = $this->normalize_spaces( wp_strip_all_tags( (string) $post->post_content ) );
		$tokens = preg_split( '/\s+/u', mb_strtolower( $title . ' ' . $text ) );
		$stop = array_fill_keys( array( 'the', 'and', 'for', 'with', 'recipe', 'your', 'this', 'that', 'you', 'how', 'from', 'are', 'was', 'have', 'has', 'but', 'can', 'use', 'make' ), true );
		$freq = array();
		foreach ( (array) $tokens as $token ) {
			$token = preg_replace( '/[^\p{L}\p{N}\-]/u', '', (string) $token );
			if ( $token === '' || isset( $stop[ $token ] ) || mb_strlen( $token ) < 4 ) {
				continue;
			}
			if ( ! isset( $freq[ $token ] ) ) {
				$freq[ $token ] = 0;
			}
			$freq[ $token ]++;
		}
		arsort( $freq );
		$keywords = array_slice( array_keys( $freq ), 0, 12 );
		$entities = array_values( array_unique( array_merge( $focus, array_slice( $keywords, 0, 8 ) ) ) );
		$placement = array(
			'title',
			'first 100 words',
			'at least one H2',
			'image alt text',
			'meta description',
			'FAQ answers',
		);

		return array(
			'ok'            => true,
			'nlp_keywords'  => $keywords,
			'entities'      => $entities,
			'placement'     => $placement,
		);
	}

	public function generate_heading_improvements_for_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array( 'ok' => false, 'message' => 'post_not_found' );
		}

		$content = (string) $post->post_content;
		$has_h2  = preg_match( '/<h2\b[^>]*>/i', $content ) === 1;
		$has_h3  = preg_match( '/<h3\b[^>]*>/i', $content ) === 1;
		$title   = get_the_title( $post_id );
		$ar      = $this->looks_arabic( $title . ' ' . $content );

		$suggestions = array();
		if ( ! $has_h2 ) {
			$suggestions[] = $ar ? 'مكونات ونصائح التحضير' : 'Ingredients and Prep Tips';
			$suggestions[] = $ar ? 'خطوات التحضير بالتفصيل' : 'Step-by-Step Instructions';
		}
		if ( ! $has_h3 ) {
			$suggestions[] = $ar ? 'أخطاء شائعة يجب تجنبها' : 'Common Mistakes to Avoid';
			$suggestions[] = $ar ? 'اقتراحات التقديم والتخزين' : 'Serving and Storage Tips';
		}

		return array(
			'ok'         => true,
			'missing_h2' => ! $has_h2,
			'missing_h3' => ! $has_h3,
			'suggestions'=> array_values( array_unique( $suggestions ) ),
		);
	}

	public function generate_external_authority_links_for_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array( 'ok' => false, 'message' => 'post_not_found' );
		}

		$title = $this->normalize_spaces( get_the_title( $post_id ) );
		$query = rawurlencode( $title );
		$links = array(
			array(
				'title' => 'Wikipedia',
				'url'   => 'https://en.wikipedia.org/wiki/Special:Search?search=' . $query,
			),
			array(
				'title' => 'USDA FoodData Central',
				'url'   => 'https://fdc.nal.usda.gov/fdc-app.html#/?query=' . $query,
			),
			array(
				'title' => 'FDA Nutrition',
				'url'   => 'https://www.fda.gov/food',
			),
			array(
				'title' => 'Healthline Nutrition',
				'url'   => 'https://www.healthline.com/search?q1=' . $query,
			),
			array(
				'title' => 'NHS Health Advice',
				'url'   => 'https://www.nhs.uk/search/results?q=' . $query,
			),
		);

		return array(
			'ok'    => true,
			'links' => array_slice( $links, 0, (int) rsaip_get_settings()['max_external_links_per_post'] ),
		);
	}

	public function generate_content_expansion_for_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array( 'ok' => false, 'message' => 'post_not_found' );
		}

		$title = get_the_title( $post_id );
		$ar    = $this->looks_arabic( $title . ' ' . $post->post_content );
		$paragraphs = array();

		if ( $this->use_ai() ) {
			$prompt = 'Write 2 short additional paragraphs for a recipe blog post. Do not rewrite the article. Only add useful weak-section improvements such as tips, storage, substitutions, or serving ideas. Return STRICT JSON only: { "paragraphs": ["...", "..."] }.' . "\n"
				. 'Title: ' . $title . "\n"
				. 'Content excerpt: ' . wp_strip_all_tags( wp_trim_words( (string) $post->post_content, 120, '' ) );
			$res = $this->chat( $prompt, 500 );
			if ( ! is_wp_error( $res ) ) {
				$decoded = $this->try_decode_json( (string) $res );
				$paragraphs = isset( $decoded['paragraphs'] ) && is_array( $decoded['paragraphs'] ) ? array_values( array_filter( array_map( array( $this, 'normalize_spaces' ), $decoded['paragraphs'] ) ) ) : array();
			}
		}

		if ( empty( $paragraphs ) ) {
			if ( $ar ) {
				$paragraphs[] = 'لأفضل نتيجة، احرص على تحضير المكونات مسبقاً وضبط الحرارة تدريجياً أثناء الطهي. هذا يساعد على تحسين القوام والنكهة ويجعل النتيجة النهائية أكثر توازناً.';
				$paragraphs[] = 'يمكن أيضاً تعديل الوصفة حسب المتوفر لديك، مع مراعاة التوازن بين القوام والتتبيلة. قدّم الطبق مع سلطة خفيفة أو خبز مناسب، واحفظ البقايا في علبة محكمة لسهولة الاستخدام لاحقاً.';
			} else {
				$paragraphs[] = 'For the best results, prep the ingredients ahead of time and control the heat gradually while cooking. This helps improve texture, flavor balance, and overall consistency.';
				$paragraphs[] = 'You can also customize the recipe based on what you have on hand while keeping the seasoning balanced. Serve it with a light side dish and store leftovers in an airtight container for easy reheating.';
			}
		}

		return array(
			'ok'         => true,
			'paragraphs' => array_slice( $paragraphs, 0, 2 ),
		);
	}

	public function fix_post_with_ai( int $post_id, RSAIP_Internal_Link_Suggester $suggester, RSAIP_Auto_Linker $auto_linker, RSAIP_Recipe_Optimizer $recipe_optimizer ): array {
		if ( ! function_exists( 'rsaip_fix_with_ai_blocked' ) || rsaip_fix_with_ai_blocked() ) {
			return array(
				'ok'      => false,
				'code'    => 'fix_with_ai_blocked',
				'blocked' => true,
				'message' => function_exists( 'rsaip_fix_with_ai_frozen_message' )
					? rsaip_fix_with_ai_frozen_message()
					: 'Fix With AI is disabled in Milestone 4.',
			);
		}

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

		$changes = array();
		$title_suggestions = $this->generate_title_suggestions_for_post( $post_id );
		if ( ! empty( $title_suggestions['titles'][0] ) ) {
			$apply_title = $this->apply_post_title( $post_id, (string) $title_suggestions['titles'][0] );
			if ( ! empty( $apply_title['ok'] ) ) {
				$changes[] = 'title';
			}
		}

		$meta = $this->generate_meta_description_for_post( $post_id );
		if ( ! empty( $meta['metadesc'] ) ) {
			$apply_meta = $this->apply_meta_description_to_post( $post_id, (string) $meta['metadesc'], 'auto' );
			if ( ! empty( $apply_meta['ok'] ) ) {
				$changes[] = 'meta_description';
			}
		}

		$content = (string) $post->post_content;
		$faq     = $this->generate_faq_suggestions_for_post( $post_id );
		$headings = $this->generate_heading_improvements_for_post( $post_id );
		$expand   = $this->generate_content_expansion_for_post( $post_id );
		$external = $this->generate_external_authority_links_for_post( $post_id );

		$new_content = $content;
		if ( ! empty( $headings['suggestions'] ) ) {
			foreach ( (array) $headings['suggestions'] as $heading ) {
				$level = ( empty( $headings['missing_h2'] ) ? 'h3' : 'h2' );
				$new_content .= "\n<" . $level . '>' . esc_html( (string) $heading ) . '</' . $level . '>';
			}
			$changes[] = 'headings';
		}

		if ( ! empty( $expand['paragraphs'] ) ) {
			foreach ( (array) $expand['paragraphs'] as $paragraph ) {
				$new_content .= "\n<p>" . esc_html( (string) $paragraph ) . '</p>';
			}
			$changes[] = 'weak_sections';
		}

		if ( ! empty( $faq['faqs'] ) ) {
			$new_content .= $this->render_faq_block( (array) $faq['faqs'] );
			$changes[] = 'faq';
		}

		if ( ! empty( $external['links'] ) ) {
			$new_content .= $this->render_external_links_block( (array) $external['links'] );
			$changes[] = 'external_links';
		}

		if ( $new_content !== $content ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => wp_slash( $new_content ),
				)
			);
		}

		$suggester->generate_for_post( $post_id, (int) rsaip_get_settings()['max_links_per_post'] );
		$link_result = $auto_linker->insert_for_post_id( $post_id );
		if ( ! empty( $link_result['inserted'] ) ) {
			$changes[] = 'internal_links';
		}

		$keywords_result = $this->generate_keywords_for_post( $post_id );
		if ( ! empty( $keywords_result['keywords'] ) ) {
			$apply_keywords = $this->apply_keywords_to_post( $post_id, (array) $keywords_result['keywords'], 'auto' );
			if ( ! empty( $apply_keywords['ok'] ) ) {
				$changes[] = 'keywords';
			}
		}

		$recipe_result = $recipe_optimizer->insert_recipe_card_for_post( $post_id );
		if ( ! empty( $recipe_result['ok'] ) ) {
			$changes[] = 'recipe_table';
		}

		$this->audit_post_refresh( $post_id );

		return array(
			'ok'      => true,
			'post_id' => $post_id,
			'changes' => array_values( array_unique( $changes ) ),
		);
	}

	public function generate_alt_text( string $recipe_name, string $main_ingredient = '' ): string {

		$settings = rsaip_get_settings();
		if ( (string) $settings['ai_provider'] !== 'openai_compatible' ) {
			return '';
		}
		if ( trim( (string) $settings['ai_api_key'] ) === '' ) {
			return '';
		}

		$recipe_name = trim( $recipe_name );
		if ( $recipe_name === '' ) {
			return '';
		}

		$prompt = 'Generate a short, natural image ALT text for a recipe blog image. Output only the alt text, no quotes. Use this format: "Recipe Name + Main Ingredient". If main ingredient is already included in the recipe name, just return the recipe name.' . "\n"
			. 'Recipe Name: ' . $recipe_name . "\n"
			. 'Main Ingredient: ' . trim( $main_ingredient );

		$res = $this->chat( $prompt, 120 );
		if ( is_wp_error( $res ) ) {
			return '';
		}
		$text = trim( (string) $res );
		$text = preg_replace( '/^["“”]+|["“”]+$/u', '', (string) $text );
		$text = trim( (string) $text );
		return mb_substr( $text, 0, 160 );
	}

	public function generate_seo_recommendations_for_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array(
				'ok'      => false,
				'message' => __( 'Post not found.', 'recipe-seo-ai-pro' ),
			);
		}

		$title   = get_the_title( $post_id );
		$url     = get_permalink( $post_id );
		$issues  = get_post_meta( $post_id, 'rsaip_audit_issues', true );
		$issues  = is_string( $issues ) ? (array) json_decode( $issues, true ) : array();
		$issues  = is_array( $issues ) ? array_values( array_filter( array_map( 'sanitize_text_field', $issues ) ) ) : array();
		$focus   = rsaip_get_focus_keywords( $post_id );

		$sugg = $this->get_top_link_suggestions_for_post( $post_id, 8 );

		$settings = rsaip_get_settings();
		$use_ai = ( (string) $settings['ai_provider'] === 'openai_compatible' ) && trim( (string) $settings['ai_api_key'] ) !== '';

		if ( ! $use_ai ) {
			$data = $this->heuristic_recommendations( $post_id, $issues, $sugg, $focus );
			return array(
				'ok'    => true,
				'title' => $title,
				'url'   => $url,
				'data'  => $data,
				'html'  => $this->render_recommendations_html( $title, $url, $data ),
			);
		}

		$prompt = $this->build_seo_prompt( $title, $url, $issues, $focus, $sugg, $post );
		$raw = $this->chat( $prompt, 900 );
		if ( is_wp_error( $raw ) ) {
			$data = $this->heuristic_recommendations( $post_id, $issues, $sugg, $focus );
			return array(
				'ok'    => true,
				'title' => $title,
				'url'   => $url,
				'data'  => $data,
				'html'  => $this->render_recommendations_html( $title, $url, $data ),
			);
		}

		$decoded = $this->try_decode_json( (string) $raw );
		if ( ! is_array( $decoded ) ) {
			$data = $this->heuristic_recommendations( $post_id, $issues, $sugg, $focus );
			$data['ai_raw'] = trim( (string) $raw );
			return array(
				'ok'    => true,
				'title' => $title,
				'url'   => $url,
				'data'  => $data,
				'html'  => $this->render_recommendations_html( $title, $url, $data ),
			);
		}

		return array(
			'ok'    => true,
			'title' => $title,
			'url'   => $url,
			'data'  => $decoded,
			'html'  => $this->render_recommendations_html( $title, $url, $decoded ),
		);
	}

	public function generate_article_from_brief( string $title, array $keywords, array $images, int $target_words = 1000, string $template = 'general' ): array {
		$title    = $this->normalize_spaces( $title );
		$keywords = $this->sanitize_keywords( $keywords );
		$images   = $this->sanitize_image_urls( $images );
		$template = sanitize_key( $template );
		$target_words = max( 400, min( 4000, absint( $target_words ) ) );
		if ( $template === 'recipe_seo_midjourney' ) {
			$target_words = max( 1400, $target_words );
		}

		if ( $title === '' ) {
			return array(
				'ok'      => false,
				'message' => __( 'Title is required.', 'recipe-seo-ai-pro' ),
			);
		}

		if ( $template === 'general' && $this->looks_like_recipe_title( $title ) ) {
			$template     = 'recipe_seo_midjourney';
			$target_words = max( 1400, $target_words );
		}

		if ( empty( $keywords ) ) {
			$keywords = $this->heuristic_keywords_from_title( $title );
		}

		if ( $this->use_ai() ) {
			$min_words = max( 300, $target_words - 50 );
			$max_words = $target_words + 120;
			if ( $template === 'recipe_seo_midjourney' ) {
				$prompt = $this->build_recipe_midjourney_prompt( $title, $keywords, $target_words, $min_words, $max_words, $images );
			} else {
				$prompt = 'You are a senior WordPress SEO writer for food and lifestyle blogs. Create a professional article from the provided brief. Return STRICT JSON only: { "title": "...", "excerpt": "...", "meta_description": "...", "keywords": ["..."], "content_html": "..." }. Rules: use valid HTML only inside content_html, include a strong introduction, multiple H2/H3 sections, practical tips, FAQ section, short conclusion, and natural keyword usage. Do not output markdown fences. If images are provided, place the exact placeholders [[IMAGE_1]], [[IMAGE_2]], etc inside content_html where they fit naturally.' . "\n"
					. 'Title: ' . $title . "\n"
					. 'Keywords: ' . implode( ', ', $keywords ) . "\n"
					. 'Target length: about ' . $target_words . ' words (min ' . $min_words . ', max ' . $max_words . ').' . "\n"
					. 'Available image placeholders: ' . $this->build_image_placeholder_hint( $images );
			}
			$res = $this->chat( $prompt, 2200 );
			if ( ! is_wp_error( $res ) ) {
				$decoded = $this->try_decode_json( (string) $res );
				if ( is_array( $decoded ) ) {
					$article = $this->sanitize_generated_article(
						array(
							'title'            => (string) ( $decoded['title'] ?? $title ),
							'excerpt'          => (string) ( $decoded['excerpt'] ?? '' ),
							'meta_description' => (string) ( $decoded['meta_description'] ?? '' ),
							'keywords'         => is_array( $decoded['keywords'] ?? null ) ? $decoded['keywords'] : $keywords,
							'content_html'     => (string) ( $decoded['content_html'] ?? '' ),
						),
						$title,
						$keywords,
						$images
					);
					if ( $article['content_html'] !== '' ) {
						$article['content_html'] = $this->ensure_target_word_count( (string) $article['content_html'], $title, (array) $article['keywords'], $target_words );
						$article['word_count']   = $this->count_words_from_html( (string) $article['content_html'] );
						$article['ok']     = true;
						$article['source'] = 'ai';
						return $article;
					}
				}
			}
		}

		if ( $template === 'recipe_seo_midjourney' ) {
			$article = $this->heuristic_recipe_article_midjourney( $title, $keywords, $images, $target_words );
		} else {
			$article = $this->heuristic_article_from_brief( $title, $keywords, $images, $target_words );
		}
		$article['word_count'] = $this->count_words_from_html( (string) $article['content_html'] );
		$article['ok']     = true;
		$article['source'] = 'heuristic';
		return $article;
	}

	public function create_draft_from_brief( string $title, array $keywords, array $images, int $target_words = 1000, string $template = 'general' ): array {
		$article = $this->generate_article_from_brief( $title, $keywords, $images, $target_words, $template );
		if ( empty( $article['ok'] ) ) {
			return $article;
		}

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => 'post',
					'post_status'  => 'draft',
					'post_title'   => (string) $article['title'],
					'post_excerpt' => (string) $article['excerpt'],
					'post_content' => (string) $article['content_html'],
				)
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return array(
				'ok'      => false,
				'message' => $post_id->get_error_message(),
			);
		}

		$this->apply_keywords_to_post( (int) $post_id, (array) $article['keywords'], 'auto' );
		$this->apply_meta_description_to_post( (int) $post_id, (string) $article['meta_description'], 'auto' );

		return array(
			'ok'               => true,
			'post_id'          => (int) $post_id,
			'edit_url'         => get_edit_post_link( (int) $post_id, 'raw' ),
			'preview_url'      => get_preview_post_link( (int) $post_id ),
			'title'            => (string) $article['title'],
			'content_html'     => (string) $article['content_html'],
			'keywords'         => (array) $article['keywords'],
			'meta_description' => (string) $article['meta_description'],
			'word_count'       => isset( $article['word_count'] ) ? (int) $article['word_count'] : $this->count_words_from_html( (string) $article['content_html'] ),
			'source'           => (string) ( $article['source'] ?? 'heuristic' ),
		);
	}

	private function heuristic_recommendations( int $post_id, array $issues, array $suggestions, array $focus ): array {
		$rec = array(
			'internal_linking_suggestions' => array(),
			'keyword_opportunities'        => array(),
			'content_expansion_opportunities' => array(),
			'featured_snippet_opportunities'  => array(),
		);

		foreach ( $suggestions as $s ) {
			$rec['internal_linking_suggestions'][] = array(
				'title' => (string) $s['title'],
				'url'   => (string) $s['url'],
				'score' => (int) $s['score'],
			);
		}

		if ( in_array( 'missing_meta_description', $issues, true ) ) {
			$rec['content_expansion_opportunities'][] = __( 'Write a unique meta description (140–160 chars) that includes the main keyword and a clear benefit.', 'recipe-seo-ai-pro' );
		}
		if ( in_array( 'thin_content', $issues, true ) ) {
			$rec['content_expansion_opportunities'][] = __( 'Expand the recipe post: add tips, substitutions, storage, variations, FAQs, and step photos.', 'recipe-seo-ai-pro' );
		}
		if ( in_array( 'low_internal_links', $issues, true ) ) {
			$rec['internal_linking_suggestions'][] = __( 'Add 5–10 contextual internal links (from related recipes, sauces, sides, and guides).', 'recipe-seo-ai-pro' );
		}

		foreach ( $focus as $k ) {
			$rec['keyword_opportunities'][] = $k;
		}

		$rec['featured_snippet_opportunities'][] = __( 'Add a short “How to make …” numbered list near the top to target featured snippets.', 'recipe-seo-ai-pro' );
		$rec['featured_snippet_opportunities'][] = __( 'Add an FAQ section with concise answers (40–60 words each).', 'recipe-seo-ai-pro' );

		return $rec;
	}

	private function build_seo_prompt( string $title, string $url, array $issues, array $focus, array $suggestions, WP_Post $post ): string {
		$sugg_lines = array();
		foreach ( $suggestions as $s ) {
			$sugg_lines[] = '- ' . (string) $s['title'] . ' (' . (string) $s['url'] . ') score=' . (string) $s['score'];
		}
		$focus_lines = $focus ? implode( ', ', $focus ) : '';
		$issues_lines = $issues ? implode( ', ', $issues ) : '';
		$excerpt = wp_strip_all_tags( wp_trim_words( (string) $post->post_content, 120, '' ) );

		return 'You are a technical SEO expert for recipe blogs. Generate actionable SEO recommendations for this post. Return STRICT JSON only, no markdown, no extra text.' . "\n"
			. 'JSON keys (all required): internal_linking_suggestions (array), keyword_opportunities (array), content_expansion_opportunities (array), featured_snippet_opportunities (array).' . "\n"
			. 'Each internal_linking_suggestions item: { "title": "...", "url": "...", "anchor_text": "...", "why": "..." }.' . "\n"
			. 'Constraints: do not suggest more than 10 internal links. Use existing suggestion list when relevant.' . "\n\n"
			. 'Title: ' . $title . "\n"
			. 'URL: ' . $url . "\n"
			. 'Focus keywords: ' . $focus_lines . "\n"
			. 'Audit issues: ' . $issues_lines . "\n"
			. 'Content excerpt: ' . $excerpt . "\n"
			. 'Suggested related pages:' . "\n"
			. ( $sugg_lines ? implode( "\n", $sugg_lines ) : '- (none)' );
	}

	private function get_top_link_suggestions_for_post( int $post_id, int $limit ): array {
		$rows = rsaip_repo( \RecipeSeoAiPro\Database\Repositories\LinkSuggestionRepository::class )->select_for_post_top(
			$post_id,
			$limit
		);
		$out = array();
		foreach ( $rows as $r ) {
			$sid = absint( $r['suggested_post_id'] ?? 0 );
			if ( $sid <= 0 ) {
				continue;
			}
			$out[] = array(
				'post_id' => $sid,
				'title'   => get_the_title( $sid ),
				'url'     => get_permalink( $sid ),
				'score'   => absint( $r['score'] ?? 0 ),
			);
		}
		return $out;
	}

	private function sanitize_image_urls( array $images ): array {
		$out = array();
		foreach ( $images as $image ) {
			$url = esc_url_raw( $this->normalize_spaces( (string) $image ) );
			if ( $url === '' ) {
				continue;
			}
			$out[] = $url;
			if ( count( $out ) >= 8 ) {
				break;
			}
		}
		return array_values( array_unique( $out ) );
	}

	private function build_image_placeholder_hint( array $images ): string {
		if ( empty( $images ) ) {
			return 'none';
		}
		$parts = array();
		foreach ( $images as $index => $image ) {
			$parts[] = '[[IMAGE_' . ( $index + 1 ) . ']]';
		}
		return implode( ', ', $parts );
	}

	private function sanitize_generated_article( array $article, string $fallback_title, array $fallback_keywords, array $images ): array {
		$title   = $this->normalize_spaces( (string) ( $article['title'] ?? $fallback_title ) );
		$excerpt = $this->fit_length( (string) ( $article['excerpt'] ?? '' ), 80, 180 );
		$meta    = $this->fit_length( (string) ( $article['meta_description'] ?? '' ), 120, 160 );
		$keys    = $this->sanitize_keywords( is_array( $article['keywords'] ?? null ) ? $article['keywords'] : $fallback_keywords );
		$html    = (string) ( $article['content_html'] ?? '' );
		$html    = $this->replace_image_placeholders( $html, $images, $title !== '' ? $title : $fallback_title );
		$html    = wp_kses_post( $html );

		if ( $excerpt === '' ) {
			$excerpt = $this->fit_length( wp_trim_words( wp_strip_all_tags( $html ), 26, '' ), 80, 180 );
		}
		if ( $meta === '' ) {
			$meta = $this->fit_length( wp_trim_words( wp_strip_all_tags( $html ), 28, '' ), 120, 160 );
		}

		return array(
			'title'            => $title !== '' ? $title : $fallback_title,
			'excerpt'          => $excerpt,
			'meta_description' => $meta,
			'keywords'         => ! empty( $keys ) ? $keys : $fallback_keywords,
			'content_html'     => $html,
		);
	}

	private function heuristic_article_from_brief( string $title, array $keywords, array $images, int $target_words ): array {
		$ar         = $this->looks_arabic( $title . ' ' . implode( ' ', $keywords ) );
		$primary_kw = ! empty( $keywords[0] ) ? (string) $keywords[0] : $title;
		$secondary  = ! empty( $keywords[1] ) ? (string) $keywords[1] : $title;
		$third      = ! empty( $keywords[2] ) ? (string) $keywords[2] : $primary_kw;
		$faq        = $this->render_faq_block( $this->heuristic_faqs( $title ) );

		if ( $ar ) {
			$intro = '<p>' . esc_html( $title ) . ' من المواضيع المهمة التي تحتاج إلى شرح واضح ومنظم. في هذا المقال ستجد نظرة عملية تساعدك على فهم الفكرة الأساسية، وتطبيقها بشكل صحيح، وتحسين جودة المحتوى وتجربة القارئ في الوقت نفسه.</p>';
			$sections = array(
				'<h2>ما أهمية ' . esc_html( $primary_kw ) . '؟</h2><p>عندما يكون المحتوى مبنيًا حول ' . esc_html( $primary_kw ) . ' بشكل واضح، يصبح أكثر قابلية للفهم والمشاركة، كما ترتفع فرص ظهوره في نتائج البحث. المهم هو أن تشرح الفكرة بلغة بسيطة مع أمثلة ونقاط عملية.</p><p>محركات البحث تفضّل الصفحات التي تعطي جوابًا مباشرًا ثم تتوسع في الشرح بشكل منطقي، لذلك من الأفضل أن تبدأ بالأهم ثم تنتقل إلى التفاصيل.</p>',
				'<h2>كيف تبني مقالًا احترافيًا حول ' . esc_html( $title ) . '؟</h2><h3>ابدأ بمقدمة مركزة</h3><p>المقدمة يجب أن توضح بسرعة ما الذي سيستفيده القارئ من المقال. من الجيد إدخال الكلمة المفتاحية الأساسية داخل أول فقرة بشكل طبيعي.</p><h3>نظم المحتوى بعناوين واضحة</h3><p>قسّم المقال إلى عناوين H2 وH3 حتى يصبح سهل القراءة والمسح السريع. هذا يحسن تجربة المستخدم ويدعم الـSEO الداخلي.</p>',
				'<h2>نصائح عملية لتحسين ' . esc_html( $secondary ) . '</h2><p>استخدم أمثلة مباشرة، وأضف صورًا في أماكن مناسبة، وادعم الشرح بفقرات قصيرة وروابط داخلية ذات صلة. كما يستحسن إضافة جزء خاص بالنصائح أو الأخطاء الشائعة لأن هذا يزيد من قيمة المقال.</p><p>كلما كانت اللغة واضحة والأفكار مرتبة، زادت فرص احتفاظ الزائر بالمحتوى واستفادته منه.</p>',
				'<h2>أخطاء شائعة ينبغي تجنبها</h2><p>من الأخطاء المتكررة الحشو الزائد للكلمات المفتاحية مثل ' . esc_html( $third ) . '، أو كتابة فقرات طويلة بدون تقسيم، أو وضع الصور بدون توضيح أو سياق. كما أن العنوان الضعيف والوصف غير الجذاب يقللان من فرصة النقر.</p>',
				'<h2>الخلاصة</h2><p>لإنشاء مقال قوي حول ' . esc_html( $title ) . '، اجمع بين عنوان واضح، وكلمات مفتاحية مناسبة، وعناوين فرعية منظمة، وصور مفيدة، وخاتمة مختصرة تلخص أهم النقاط للقارئ.</p>',
			);
			$meta = $this->fit_length( $title . ' - مقال احترافي منظم مع شرح واضح ونصائح عملية وصياغة تساعد على تحسين الفهم والظهور في نتائج البحث.', 120, 160 );
		} else {
			$intro = '<p>' . esc_html( $title ) . ' is a topic that benefits from a clear, structured, and practical explanation. This article gives readers an easy-to-follow overview, actionable tips, and a stronger content structure for both users and search engines.</p>';
			$sections = array(
				'<h2>Why ' . esc_html( $primary_kw ) . ' matters</h2><p>A well-structured article around ' . esc_html( $primary_kw ) . ' helps readers understand the subject faster and gives search engines better topical signals. The key is to balance clarity, usefulness, and natural keyword placement.</p><p>Pages that answer the core question quickly and then expand with helpful sections often perform better over time.</p>',
				'<h2>How to build a professional article about ' . esc_html( $title ) . '</h2><h3>Write a focused introduction</h3><p>Your opening should explain what the article covers and why it matters. Mention the main keyword naturally in the introduction and set the right expectations.</p><h3>Use clear subheadings</h3><p>Break the topic into H2 and H3 sections so readers can scan quickly and find what they need. This also supports stronger on-page SEO and readability.</p>',
				'<h2>Practical tips to improve ' . esc_html( $secondary ) . '</h2><p>Add contextual images, short paragraphs, relevant internal links, and practical examples. A dedicated section for tips or mistakes to avoid often increases usefulness and keeps readers engaged longer.</p><p>Good formatting and logical flow can make even a simple topic feel much more authoritative.</p>',
				'<h2>Common mistakes to avoid</h2><p>Avoid overusing keywords such as ' . esc_html( $third ) . ', writing long unbroken text blocks, or placing images without purpose. Weak titles and generic descriptions can also reduce click-through rate.</p>',
				'<h2>Final thoughts</h2><p>To publish a strong article about ' . esc_html( $title ) . ', combine a focused title, well-selected keywords, strong subheadings, useful images, and a concise conclusion that reinforces the main value.</p>',
			);
			$meta = $this->fit_length( $title . ' - a professional article with clear structure, practical tips, useful sections, and SEO-friendly wording to improve quality and visibility.', 120, 160 );
		}

		$html  = '<div class="rsaip-generated-article">';
		$html .= '<h1>' . esc_html( $title ) . '</h1>';
		$html .= $intro;
		$html .= $this->render_brief_images( $images, $title, 0, 2 );
		$html .= $sections[0] . $sections[1];
		$html .= $this->render_brief_images( $images, $title, 1, 2 );
		$html .= $sections[2] . $sections[3] . $faq . $sections[4];
		$html .= $this->build_length_expansion_html( $title, $keywords, $target_words, $ar, $html );
		$html .= '</div>';

		return array(
			'title'            => $title,
			'excerpt'          => $this->fit_length( wp_trim_words( wp_strip_all_tags( $html ), 26, '' ), 80, 180 ),
			'meta_description' => $meta,
			'keywords'         => ! empty( $keywords ) ? $keywords : array( $title ),
			'content_html'     => $html,
		);
	}

	private function ensure_target_word_count( string $html, string $title, array $keywords, int $target_words ): string {
		$target_words = max( 400, min( 4000, absint( $target_words ) ) );
		$current      = $this->count_words_from_html( $html );
		if ( $current >= ( $target_words - 50 ) ) {
			return $html;
		}

		$marker = '<!-- RSAIP_INSERT_BEFORE_CONCLUSION -->';
		if ( strpos( $html, $marker ) !== false && $this->looks_like_recipe_title( $title ) ) {
			for ( $i = 0; $i < 3; $i++ ) {
				$current = $this->count_words_from_html( $html );
				if ( $current >= ( $target_words - 50 ) ) {
					break;
				}
				$extra = $this->build_recipe_pre_conclusion_expansion_html( $title, $keywords );
				if ( $extra === '' ) {
					break;
				}
				$html = str_replace( $marker, $extra . $marker, $html );
			}
			$html = str_replace( $marker, '', $html );
			$current = $this->count_words_from_html( $html );
			if ( $current >= ( $target_words - 50 ) ) {
				return $html;
			}
		}

		$ar = $this->looks_arabic( $title . ' ' . implode( ' ', $keywords ) );
		$wrap = '<div class="rsaip-generated-article">' . $html . '</div>';
		$extra = $this->build_length_expansion_html( $title, $keywords, $target_words, $ar, $wrap );
		$html  = $html . $extra;

		$current = $this->count_words_from_html( $html );
		if ( $current >= ( $target_words - 50 ) ) {
			return $html;
		}

		$extra2 = $this->build_length_expansion_html( $title, $keywords, $target_words, $ar, '<div class="rsaip-generated-article">' . $html . '</div>' );
		return $html . $extra2;
	}

	private function looks_like_recipe_title( string $title ): bool {
		$t = mb_strtolower( $this->normalize_spaces( $title ) );
		if ( $t === '' ) {
			return false;
		}
		if ( preg_match( '/\b(recipe|ingredients|instructions|how to make|copycat|sandwich|pasta|salad|soup|chicken|beef|tuna|avocado)\b/i', $t ) ) {
			return true;
		}
		if ( preg_match( '/\p{Arabic}/u', $t ) ) {
			return preg_match( '/وصفة|مكونات|طريقة|تحضير/u', $t ) === 1;
		}
		return false;
	}

	private function build_recipe_pre_conclusion_expansion_html( string $title, array $keywords ): string {
		$ingredient = $this->guess_main_ingredient_from_title( $title );
		$primary    = ! empty( $keywords[0] ) ? (string) $keywords[0] : $title;

		$out  = '<h3>Serving Suggestions</h3>';
		$out .= '<p>Serve <strong>' . esc_html( $primary ) . '</strong> with a simple side to balance flavors and textures. A fresh, crunchy side helps keep the meal light and satisfying.</p>';
		$out .= '<ul>';
		$out .= '<li>Pair with a crisp green salad and a light lemon vinaigrette.</li>';
		$out .= '<li>Add a small bowl of soup for a more filling lunch.</li>';
		$out .= '<li>Serve with sliced veggies (cucumber, carrots) for extra crunch.</li>';
		$out .= '</ul>';

		$out .= '<h3>Storage & Make-Ahead</h3>';
		$out .= '<p>If your recipe includes avocado, store leftovers in an airtight container and press plastic wrap directly on the surface to reduce browning.</p>';
		$out .= '<ul>';
		$out .= '<li><strong>Fridge:</strong> best within 1–2 days for maximum freshness.</li>';
		$out .= '<li><strong>Tip:</strong> keep the bread separate and assemble right before eating.</li>';
		$out .= '</ul>';

		$out .= '<h3>Variations</h3>';
		$out .= '<p>Make this recipe your own with small swaps that still keep the flavor balanced.</p>';
		$out .= '<ul>';
		$out .= '<li>Use Greek yogurt instead of mayo for a lighter texture.</li>';
		$out .= '<li>Add herbs like dill or parsley for a fresher finish.</li>';
		if ( $ingredient !== '' ) {
			$out .= '<li>Try adding extra ' . esc_html( $ingredient ) . ' for a stronger, more savory taste.</li>';
		}
		$out .= '</ul>';

		return $out;
	}

	private function build_length_expansion_html( string $title, array $keywords, int $target_words, bool $ar, string $current_html ): string {
		$need = max( 0, ( $target_words - 50 ) - $this->count_words_from_html( $current_html ) );
		if ( $need <= 0 ) {
			return '';
		}

		$primary = ! empty( $keywords[0] ) ? (string) $keywords[0] : $title;
		$secondary = ! empty( $keywords[1] ) ? (string) $keywords[1] : $primary;
		$topics = array(
			array(
				'h2' => $ar ? 'خطوات عملية لتطبيق ' . $primary : 'Actionable steps to apply ' . $primary,
				'p1' => $ar ? 'ابدأ بتحديد الهدف من الصفحة: هل تريد شرحًا، مقارنة، أم دليل خطوات؟ بعد ذلك اجمع النقاط الأساسية التي يتوقع القارئ العثور عليها، ثم حول كل نقطة إلى عنوان فرعي واضح. هذا يجعل المقال منظمًا ويقلل التشتت.' : 'Start by clarifying the page intent: is it a how-to, comparison, or guide? Then list the key points readers expect and convert each into a clear subheading. This keeps the article structured and easy to follow.',
				'p2' => $ar ? 'ضع ' . $secondary . ' داخل عنوان فرعي واحد على الأقل، وكرره مرة أو مرتين فقط داخل النص بشكل طبيعي. الأفضل أن تشرح الفكرة بدل تكرار الكلمة. أضف مثالًا أو سيناريو واقعي حتى يكون الكلام عمليًا.' : 'Include ' . $secondary . ' in at least one subheading and use it naturally once or twice in the body. Explain the concept instead of repeating the phrase. Add a real example or scenario so the section feels practical.',
			),
			array(
				'h2' => $ar ? 'تحسينات سريعة لرفع الجودة' : 'Quick improvements to increase quality',
				'p1' => $ar ? 'راجع المقدمة وتأكد أنها تجيب عن: ما المشكلة؟ ما الحل؟ ولماذا هذا مهم؟ ثم ضع جملة تلخص ما سيتعلمه القارئ. هذه الخطوة وحدها ترفع قيمة المقال وتزيد قابلية القراءة.' : 'Review the introduction and make sure it answers: what is the problem, what is the solution, and why does it matter? Add one sentence summarizing what the reader will learn. This single step often improves clarity and engagement.',
				'p2' => $ar ? 'أضف قائمة قصيرة بالنقاط (3–6) داخل منتصف المقال: نصائح، أخطاء شائعة، أو أفضل الممارسات. القوائم تساعد على المسح السريع وتزيد فرصة الظهور في المقتطفات المميزة.' : 'Add a short bullet list (3–6 items) mid-article: tips, common mistakes, or best practices. Lists improve scanability and can help you win featured snippets.',
			),
			array(
				'h2' => $ar ? 'أسئلة إضافية يبحث عنها الناس' : 'Extra questions people also ask',
				'p1' => $ar ? 'قم بتوسيع قسم الأسئلة الشائعة بإجابات مختصرة وواضحة (40–70 كلمة). ركز على أسئلة مثل: كيف أبدأ؟ ما أفضل الأدوات؟ ما الأخطاء التي يجب تجنبها؟ وكيف أقيس النجاح؟' : 'Expand the FAQ with concise answers (40–70 words). Focus on questions like: how do I start, what tools help, what mistakes to avoid, and how to measure success.',
				'p2' => $ar ? 'إذا كان المقال عن وصفة أو طعام، أضف فقرة عن البدائل، التخزين، والتحضير المسبق. وإذا كان عن موضوع عام، أضف فقرة عن الخطوات العملية والنماذج البسيطة للتطبيق.' : 'If the topic is food/recipes, add a paragraph on substitutions, storage, and make-ahead tips. If it is a general topic, add a paragraph with practical steps and a simple template readers can follow.',
			),
			array(
				'h2' => $ar ? 'بدائل وتغييرات مقترحة' : 'Suggested substitutions and variations',
				'p1' => $ar ? 'إضافة البدائل تساعد القارئ على تطبيق الفكرة حتى لو لم تتوفر كل المكونات أو الأدوات. اذكر 3–5 بدائل منطقية مع ملاحظات قصيرة عن تأثير كل بديل على النتيجة.' : 'Substitutions help readers apply the idea even when they do not have the exact ingredients or tools. Provide 3–5 reasonable alternatives and a short note on how each change affects the result.',
				'p2' => $ar ? 'بالنسبة للوصفات، اذكر بدائل الصوص/البهارات/نوع الخبز أو الحشوة. وبالنسبة للمقالات العامة، اذكر بدائل الأدوات أو الطرق المختلفة للتطبيق حسب مستوى الخبرة.' : 'For recipes, suggest swaps for sauce, seasoning, bread, or fillings. For non-recipe topics, suggest alternative tools or approaches depending on the reader’s skill level.',
			),
			array(
				'h2' => $ar ? 'التخزين والتحضير المسبق' : 'Storage and make-ahead tips',
				'p1' => $ar ? 'فقرة التخزين والتحضير المسبق تزيد من قيمة المقال خصوصًا في محتوى الطعام. اشرح طريقة حفظ المحتوى/النتيجة، المدة المتوقعة، وأفضل طريقة لإعادة التسخين أو الاستخدام.' : 'A storage and make-ahead section adds real value, especially for food content. Explain how to store the result, the expected time window, and the best way to reheat or reuse it.',
				'p2' => $ar ? 'إذا كان المقال ليس وصفة، حوّل الفكرة إلى خطوات قابلة للتنفيذ: ما الذي يمكن تجهيزُه مسبقًا؟ وما الذي يفضّل فعله وقت التنفيذ؟' : 'If the topic is not a recipe, translate this into execution planning: what can be prepared in advance and what should be done at the moment of execution.',
			),
		);

		$out = '';
		foreach ( $topics as $t ) {
			if ( $this->count_words_from_html( $current_html . $out ) >= ( $target_words - 50 ) ) {
				break;
			}
			$out .= '<h2>' . esc_html( (string) $t['h2'] ) . '</h2>';
			$out .= '<p>' . esc_html( (string) $t['p1'] ) . '</p>';
			$out .= '<p>' . esc_html( (string) $t['p2'] ) . '</p>';
			$out .= '<ul><li>' . esc_html( $primary ) . '</li><li>' . esc_html( $secondary ) . '</li><li>' . esc_html( $title ) . '</li></ul>';
		}

		return $out;
	}

	private function heuristic_keywords_from_title( string $title ): array {
		$title = $this->normalize_spaces( $title );
		if ( $title === '' ) {
			return array();
		}

		$lower = mb_strtolower( $title );
		$lower = preg_replace( '/[^a-z0-9\p{Arabic}\s]+/u', ' ', (string) $lower );
		$lower = trim( preg_replace( '/\s+/u', ' ', (string) $lower ) );

		$stop = array(
			'a', 'an', 'the', 'and', 'or', 'of', 'to', 'for', 'with', 'at', 'in', 'on', 'from', 'by', 'make', 'recipe', 'copycat',
			'كيفية', 'طريقة', 'عمل', 'و', 'او', 'من', 'في', 'على', 'مع', 'أفضل',
		);

		$tokens = preg_split( '/\s+/u', $lower ) ?: array();
		$tokens = array_values(
			array_filter(
				array_map(
					static function ( $t ) use ( $stop ) {
						$t = trim( (string) $t );
						if ( $t === '' ) {
							return '';
						}
						if ( in_array( $t, $stop, true ) ) {
							return '';
						}
						if ( mb_strlen( $t ) <= 2 ) {
							return '';
						}
						return $t;
					},
					$tokens
				)
			)
		);

		$base = array();
		$base[] = $title;
		if ( count( $tokens ) >= 2 ) {
			$base[] = implode( ' ', array_slice( $tokens, 0, 2 ) );
		}
		if ( count( $tokens ) >= 3 ) {
			$base[] = implode( ' ', array_slice( $tokens, 0, 3 ) );
		}
		if ( strpos( $lower, 'recipe' ) !== false ) {
			$base[] = $title . ' recipe';
			$base[] = 'how to make ' . $title;
		}
		if ( strpos( $lower, 'copycat' ) !== false || strpos( $lower, 'joe' ) !== false ) {
			$base[] = $title . ' copycat';
			$base[] = 'copycat ' . $title;
		}

		return $this->sanitize_keywords( $base );
	}

	private function build_recipe_midjourney_prompt( string $title, array $keywords, int $target_words, int $min_words, int $max_words, array $images ): string {
		$primary_kw = ! empty( $keywords[0] ) ? (string) $keywords[0] : $title;
		$ingredient = $this->guess_main_ingredient_from_title( $title );

		return 'Write an SEO-optimized cooking recipe article in ENGLISH. Follow the exact structure below and output STRICT JSON only: { "title": "...", "excerpt": "...", "meta_description": "...", "keywords": ["..."], "content_html": "..." }.' . "\n"
			. 'Target length: about ' . $target_words . ' words (min ' . $min_words . ', max ' . $max_words . ').' . "\n"
			. 'SEO requirements: optimize for the primary keyword automatically, use H2/H3 headings, beginner-friendly, use numbering and bullet points, and include optimized ALT text for the image placeholders.' . "\n"
			. 'ALT rule: use the format "Recipe Name + Main Ingredient". If the main ingredient is already in the recipe name, use the recipe name only.' . "\n"
			. 'Recipe title (English): ' . $title . "\n"
			. 'Primary keyword: ' . $primary_kw . "\n"
			. 'Main ingredient (best guess): ' . $ingredient . "\n"
			. 'Placeholders: use [[IMAGE_1]], [[IMAGE_2]], [[IMAGE_3]] exactly in content_html where the images should go.' . "\n"
			. 'Also include a Midjourney prompt + ALT text above each placeholder in this format:' . "\n"
			. '<div class="rsaip-image-prompt"><p><strong>Midjourney prompt:</strong> ...</p><p><strong>ALT:</strong> ...</p>[[IMAGE_1]]</div>' . "\n\n"
			. 'Required article structure in content_html (in this order):' . "\n"
			. '1) A compelling introduction.' . "\n"
			. '2) Image 1 block: Midjourney prompt for the final plated dish + ALT text + [[IMAGE_1]].' . "\n"
			. '3) Cook time section + Ingredients list (bullet points).' . "\n"
			. '4) Image 2 block: Midjourney prompt for ingredients neatly arranged + ALT text + [[IMAGE_2]].' . "\n"
			. '5) Numbered preparation steps with clear H3 headings for key phases.' . "\n"
			. '6) Image 3 block: Midjourney prompt showing an important cooking step + ALT text + [[IMAGE_3]].' . "\n"
			. '7) Useful tips (bullet points).' . "\n"
			. '8) A conclusion encouraging readers to try it.' . "\n"
			. 'Make Midjourney prompts specific, photorealistic, food photography, 50mm, natural light, shallow depth of field, high detail.' . "\n"
			. 'If image URLs are provided (' . $this->build_image_placeholder_hint( $images ) . '), still keep placeholders in content_html. Do not remove them.';
	}

	private function guess_main_ingredient_from_title( string $title ): string {
		$t = mb_strtolower( $this->normalize_spaces( $title ) );
		$map = array(
			'chicken' => 'chicken',
			'beef'    => 'beef',
			'salmon'  => 'salmon',
			'shrimp'  => 'shrimp',
			'tuna'    => 'tuna',
			'avocado' => 'avocado',
			'potato'  => 'potato',
			'pasta'   => 'pasta',
			'rice'    => 'rice',
			'cheese'  => 'cheese',
			'egg'     => 'eggs',
			'tofu'    => 'tofu',
			'lentil'  => 'lentils',
			'carrot'  => 'carrots',
		);
		foreach ( $map as $needle => $label ) {
			if ( strpos( $t, $needle ) !== false ) {
				return $label;
			}
		}
		$parts = preg_split( '/\s+/u', $t ) ?: array();
		return ! empty( $parts[0] ) ? (string) $parts[0] : '';
	}

	private function heuristic_recipe_article_midjourney( string $title, array $keywords, array $images, int $target_words ): array {
		$title = $this->normalize_spaces( $title );
		$keywords = $this->sanitize_keywords( $keywords );
		if ( empty( $keywords ) ) {
			$keywords = $this->heuristic_keywords_from_title( $title );
		}

		$primary_kw = ! empty( $keywords[0] ) ? (string) $keywords[0] : $title;
		$ingredient = $this->guess_main_ingredient_from_title( $title );
		$meta = $this->fit_length( $title . ' - learn how to make this recipe step-by-step with ingredients, cooking times, tips, and optimized visuals for better SEO and clicks.', 120, 160 );

		$img1_prompt = 'Photorealistic food photography of the final plated ' . $title . ', natural light, 50mm lens, shallow depth of field, high detail, appetizing styling, crisp focus, studio quality, clean background';
		$img2_prompt = 'Top-down photorealistic shot of neatly arranged ingredients for ' . $title . ' on a clean kitchen counter, small bowls and measured ingredients, natural light, high detail, minimal styling, editorial food photography';
		$img3_prompt = 'Close-up photorealistic cooking step for ' . $title . ' showing ' . ( $ingredient !== '' ? $ingredient : 'the main ingredient' ) . ' being mixed or assembled, hands in frame, natural light, high detail, shallow depth of field, action shot';

		$alt1 = $ingredient !== '' && stripos( $title, $ingredient ) === false ? $title . ' ' . $ingredient : $title;
		$alt2 = $ingredient !== '' && stripos( $title, $ingredient ) === false ? $title . ' ingredients with ' . $ingredient : $title . ' ingredients';
		$alt3 = $ingredient !== '' && stripos( $title, $ingredient ) === false ? $title . ' cooking step with ' . $ingredient : $title . ' cooking step';

		$ingredients = $this->heuristic_recipe_ingredients_list( $title, $ingredient );
		$steps       = $this->heuristic_recipe_steps_list( $title, $ingredient );

		$conclusion = '<h2>Conclusion</h2><p>Now you know how to make <strong>' . esc_html( $primary_kw ) . '</strong> at home with clear steps, realistic timing, and beginner-friendly tips. Try it today, then share your results and any variations you loved.</p>';

		$html  = '<div class="rsaip-generated-article">';
		$html .= '<h1>' . esc_html( $title ) . '</h1>';
		$html .= '<p><strong>' . esc_html( $primary_kw ) . '</strong> is a flavorful, beginner-friendly recipe you can make at home with simple ingredients and a few smart techniques. In this guide, you will get a clear ingredient list, realistic cooking times, and step-by-step instructions you can follow even if you are new to cooking.</p>';
		$html .= '<p>This article also includes optimized image prompts and ALT text so you can create consistent visuals and improve on-page SEO without guesswork.</p>';

		$html .= '<div class="rsaip-image-prompt"><p><strong>Midjourney prompt:</strong> ' . esc_html( $img1_prompt ) . '</p><p><strong>ALT:</strong> ' . esc_html( $alt1 ) . '</p>[[IMAGE_1]]</div>';

		$html .= '<h2>Cook Time & Ingredients</h2>';
		$html .= '<p>Before you start, read the ingredients once and prepare everything on the counter. This makes the cooking process faster and helps you avoid missing steps.</p>';
		$html .= '<ul>';
		$html .= '<li><strong>Prep time:</strong> 10–15 minutes</li>';
		$html .= '<li><strong>Cook time:</strong> 5–10 minutes</li>';
		$html .= '<li><strong>Total time:</strong> 20–25 minutes</li>';
		$html .= '<li><strong>Servings:</strong> 2</li>';
		$html .= '</ul>';
		$html .= '<h3>Ingredients (Beginner-Friendly)</h3><ul>';
		foreach ( $ingredients as $it ) {
			$html .= '<li>' . esc_html( $it ) . '</li>';
		}
		$html .= '</ul>';
		$html .= '<h3>Optional Add-Ins</h3><ul>';
		$html .= '<li>Crushed red pepper or hot sauce for heat</li>';
		$html .= '<li>Pickles or capers for a salty, tangy bite</li>';
		$html .= '<li>Fresh herbs (dill, parsley, or cilantro)</li>';
		$html .= '</ul>';

		$html .= '<div class="rsaip-image-prompt"><p><strong>Midjourney prompt:</strong> ' . esc_html( $img2_prompt ) . '</p><p><strong>ALT:</strong> ' . esc_html( $alt2 ) . '</p>[[IMAGE_2]]</div>';

		$html .= '<h2>How to Make It (Step-by-Step)</h2>';
		$html .= '<p>Follow these numbered steps in order. If you have never made a sandwich filling like this before, focus on texture: creamy but not runny, seasoned but not salty.</p>';
		$html .= '<h3>Step 1: Prep the base</h3><ol>';
		foreach ( array_slice( $steps, 0, 2 ) as $step ) {
			$html .= '<li>' . esc_html( $step ) . '</li>';
		}
		$html .= '</ol>';
		$html .= '<h3>Step 2: Mix and balance flavor</h3><ol start="3">';
		foreach ( array_slice( $steps, 2, 2 ) as $step ) {
			$html .= '<li>' . esc_html( $step ) . '</li>';
		}
		$html .= '</ol>';
		$html .= '<h3>Step 3: Assemble and serve</h3><ol start="5">';
		foreach ( array_slice( $steps, 4 ) as $step ) {
			$html .= '<li>' . esc_html( $step ) . '</li>';
		}
		$html .= '</ol>';

		$html .= '<div class="rsaip-image-prompt"><p><strong>Midjourney prompt:</strong> ' . esc_html( $img3_prompt ) . '</p><p><strong>ALT:</strong> ' . esc_html( $alt3 ) . '</p>[[IMAGE_3]]</div>';

		$html .= '<h2>Useful Tips (For Better Results)</h2><ul>';
		$html .= '<li><strong>Texture:</strong> If the filling is too thick, add a small splash of lemon juice. If it is too loose, add more mashed avocado.</li>';
		$html .= '<li><strong>Seasoning:</strong> Add salt gradually and taste after mixing. Mustard and lemon add brightness without extra salt.</li>';
		$html .= '<li><strong>Bread:</strong> Toasting improves structure and helps prevent sogginess.</li>';
		$html .= '<li><strong>Crunch:</strong> Add cucumber, lettuce, or red onion for a fresh crunch.</li>';
		$html .= '<li><strong>Make-ahead:</strong> Store the filling in an airtight container. Press plastic wrap on the surface to reduce browning.</li>';
		$html .= '</ul>';

		$html .= '<!-- RSAIP_INSERT_BEFORE_CONCLUSION -->';
		$html .= $conclusion;
		$html .= '</div>';

		$html = $this->ensure_target_word_count( $html, $title, $keywords, $target_words );

		return array(
			'title'            => $title,
			'excerpt'          => $this->fit_length( wp_trim_words( wp_strip_all_tags( $html ), 28, '' ), 80, 180 ),
			'meta_description' => $meta,
			'keywords'         => $keywords,
			'content_html'     => $html,
		);
	}

	private function heuristic_recipe_ingredients_list( string $title, string $ingredient ): array {
		$base = array(
			$ingredient !== '' ? '1 cup ' . $ingredient : '1 cup main ingredient',
			'1 ripe avocado, mashed',
			'1–2 tbsp mayo or Greek yogurt',
			'1 tsp Dijon mustard',
			'1 tbsp lemon juice',
			'Salt and black pepper, to taste',
			'2 slices bread (or 1 sandwich roll)',
			'Optional: sliced tomato, lettuce, cucumber, red onion',
		);
		return array_values( array_unique( $base ) );
	}

	private function heuristic_recipe_steps_list( string $title, string $ingredient ): array {
		return array(
			'Prep the ingredients: drain and flake the ' . ( $ingredient !== '' ? $ingredient : 'main ingredient' ) . ' and mash the avocado in a bowl.',
			'Mix the filling: add mayo (or yogurt), mustard, lemon juice, salt, and pepper, then stir until creamy.',
			'Adjust texture: if the mixture is too thick, add a splash of lemon juice; if too loose, add more avocado.',
			'Toast the bread (optional) for better structure and flavor.',
			'Assemble the sandwich: spread the filling evenly and add optional veggies for crunch.',
			'Slice and serve immediately, or wrap tightly for a quick meal on the go.',
		);
	}

	private function count_words_from_html( string $html ): int {
		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
		if ( $text === '' ) {
			return 0;
		}
		$parts = preg_split( '/\s+/u', $text );
		return is_array( $parts ) ? count( array_filter( $parts ) ) : 0;
	}

	private function replace_image_placeholders( string $html, array $images, string $title ): string {
		if ( empty( $images ) ) {
			return $html;
		}

		foreach ( $images as $index => $url ) {
			$html = str_replace( '[[IMAGE_' . ( $index + 1 ) . ']]', $this->build_figure_html( $url, $title, $index + 1 ), $html );
		}

		return (string) preg_replace( '/\[\[IMAGE_\d+\]\]/', '', $html );
	}

	private function render_brief_images( array $images, string $title, int $offset, int $step ): string {
		if ( empty( $images ) ) {
			return '';
		}
		$html = '';
		for ( $i = $offset; $i < count( $images ); $i += $step ) {
			$html .= $this->build_figure_html( (string) $images[ $i ], $title, $i + 1 );
		}
		return $html;
	}

	private function build_figure_html( string $url, string $title, int $index ): string {
		return '<figure class="rsaip-generated-figure"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $title . ' ' . $index ) . '" loading="lazy" /><figcaption>' . esc_html( $title ) . '</figcaption></figure>';
	}

	private function use_ai(): bool {
		$settings = rsaip_get_settings();
		return ( (string) $settings['ai_provider'] === 'openai_compatible' ) && trim( (string) $settings['ai_api_key'] ) !== '';
	}

	private function normalize_spaces( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', (string) $text );
		$text = trim( (string) $text );
		return $text;
	}

	private function fit_length( string $text, int $min, int $max ): string {
		$text = $this->normalize_spaces( $text );
		if ( $text === '' ) {
			return '';
		}
		if ( mb_strlen( $text ) > $max ) {
			$text = mb_substr( $text, 0, $max );
			$text = preg_replace( '/\s+\S*$/u', '', (string) $text );
			$text = rtrim( (string) $text, " \t\n\r\0\x0B,.-–—|" );
		}
		if ( mb_strlen( $text ) < $min ) {
			return $text;
		}
		return $text;
	}

	private function heuristic_meta_description( WP_Post $post, string $keyword ): string {
		$title = get_the_title( $post->ID );
		$raw = $this->normalize_spaces( (string) $post->post_content );
		$raw = preg_replace( '/\s+/', ' ', (string) $raw );
		$raw = trim( (string) $raw );

		$base = $raw !== '' ? $raw : $title;
		$base = wp_trim_words( $base, 28, '' );
		$base = $this->normalize_spaces( $base );

		if ( $base === '' ) {
			$base = $this->normalize_spaces( (string) $title );
		}

		$keyword = $this->normalize_spaces( (string) $keyword );
		if ( $keyword !== '' ) {
			$hay = mb_strtolower( $base );
			$need = mb_strtolower( $keyword );
			if ( mb_stripos( $hay, $need ) === false ) {
				$base = $keyword . ' - ' . $base;
			}
		}

		$base = $this->fit_length( $base, 120, 160 );

		if ( mb_strlen( $base ) < 140 ) {
			$suffix = $this->looks_arabic( $title ) ? ' خطوة بخطوة مع نصائح وتقديمات.' : ' Step-by-step with tips and serving ideas.';
			$base = $this->fit_length( $base . $suffix, 140, 160 );
		}

		return $base;
	}

	private function looks_arabic( string $text ): bool {
		return preg_match( '/\p{Arabic}/u', $text ) === 1;
	}

	private function heuristic_faqs( string $title ): array {
		$ar = $this->looks_arabic( $title );
		$t  = $this->normalize_spaces( wp_strip_all_tags( $title ) );

		if ( $ar ) {
			return array(
				array(
					'q' => 'هل يمكن تحضير ' . $t . ' مسبقاً؟',
					'a' => 'نعم، يمكنك التحضير مسبقاً ثم حفظ المكونات أو الطبق بعد التبريد. عند التقديم سخّن/سخّني على نار هادئة أو بالفرن مع إضافة قليل من السائل إذا احتاج.',
				),
				array(
					'q' => 'كيف أحفظ ' . $t . ' في الثلاجة؟',
					'a' => 'يُحفظ في علبة محكمة الإغلاق بعد أن يبرد تماماً. غالباً يبقى جيداً 3–4 أيام. أعد التسخين تدريجياً لتجنب جفاف القوام.',
				),
				array(
					'q' => 'هل يمكن تجميد ' . $t . '؟',
					'a' => 'في معظم الحالات نعم. اتركه يبرد ثم جمّده في عبوات مناسبة. عند الاستخدام أذبه في الثلاجة ليلة كاملة ثم سخّنه بلطف. بعض الصلصات الكريمية قد تتغير قليلاً.',
				),
				array(
					'q' => 'ما أفضل بدائل المكونات في ' . $t . '؟',
					'a' => 'يمكن تبديل بعض المكونات حسب المتوفر: استبدال نوع البروتين، استخدام بديل للحليب/الكريمة، أو تغيير نوع المعكرونة/الخضار. حافظ على التوازن بين الملح والبهارات.',
				),
				array(
					'q' => 'ما الذي يناسب ' . $t . ' للتقديم؟',
					'a' => 'قدّمها مع سلطة خفيفة، خبز/توست، أو خضار مشوية. إضافة عنصر مقرمش أو حامض (ليمون/مخلل) يوازن النكهة بشكل ممتاز.',
				),
			);
		}

		return array(
			array(
				'q' => 'Can I make ' . $t . ' ahead of time?',
				'a' => 'Yes. Prep components in advance and store them separately if possible. Reheat gently and add a splash of liquid (broth, water, or milk) to restore the texture before serving.',
			),
			array(
				'q' => 'How do I store leftover ' . $t . '?',
				'a' => 'Let it cool, then store in an airtight container in the fridge. It typically keeps well for 3–4 days. Reheat slowly to avoid drying out or splitting sauces.',
			),
			array(
				'q' => 'Can I freeze ' . $t . '?',
				'a' => 'Often yes. Cool completely, freeze in freezer-safe containers, then thaw overnight in the fridge. Some creamy sauces may change texture slightly—stir well while reheating.',
			),
			array(
				'q' => 'What are the best substitutions for ' . $t . '?',
				'a' => 'Swap proteins, use a dairy-free alternative, or change the pasta/rice depending on your needs. Taste and adjust seasoning after substitutions to keep flavors balanced.',
			),
			array(
				'q' => 'What should I serve with ' . $t . '?',
				'a' => 'Pair it with a fresh salad, garlic bread, roasted vegetables, or a light soup. A crunchy or acidic side helps balance rich flavors.',
			),
		);
	}

	private function sanitize_faqs( array $faqs ): array {
		$out = array();
		foreach ( $faqs as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			$q = isset( $f['q'] ) ? $this->normalize_spaces( wp_strip_all_tags( (string) $f['q'] ) ) : '';
			$a = isset( $f['a'] ) ? $this->normalize_spaces( wp_strip_all_tags( (string) $f['a'] ) ) : '';
			$q = sanitize_text_field( $q );
			$a = sanitize_textarea_field( $a );
			if ( $q === '' || $a === '' ) {
				continue;
			}
			$out[] = array(
				'q' => mb_substr( $q, 0, 140 ),
				'a' => mb_substr( $a, 0, 500 ),
			);
			if ( count( $out ) >= 8 ) {
				break;
			}
		}
		return $out;
	}

	private function sanitize_titles( array $titles ): array {
		$out = array();
		foreach ( $titles as $title ) {
			$title = $this->normalize_spaces( (string) $title );
			if ( $title === '' ) {
				continue;
			}
			$title = mb_substr( $title, 0, 60 );
			$out[] = $title;
			if ( count( $out ) >= 5 ) {
				break;
			}
		}
		return array_values( array_unique( $out ) );
	}

	private function sanitize_keywords( array $keywords ): array {
		$out = array();
		foreach ( $keywords as $keyword ) {
			$keyword = $this->normalize_spaces( (string) $keyword );
			$keyword = trim( preg_replace( '/^[\-\*\•\d\.\)\(,\s]+/u', '', $keyword ) );
			if ( $keyword === '' ) {
				continue;
			}
			$words = preg_split( '/\s+/u', $keyword );
			if ( ! is_array( $words ) || count( array_filter( $words ) ) > 6 ) {
				$keyword = implode( ' ', array_slice( (array) $words, 0, 6 ) );
			}
			$out[] = mb_substr( $keyword, 0, 80 );
			if ( count( $out ) >= 8 ) {
				break;
			}
		}
		return array_values( array_unique( $out ) );
	}

	private function title_topic_selector(): \RecipeSeoAiPro\Modules\Ai\TitleGeneration\TitleTopicSelector {
		return new \RecipeSeoAiPro\Modules\Ai\TitleGeneration\TitleTopicSelector();
	}

	private function heuristic_titles( string $current_title, string $keyword ): array {
		$selector = $this->title_topic_selector();
		$topic    = $selector->resolve( $current_title, $keyword );
		$titles   = $this->sanitize_titles( $selector->build_heuristic_titles( $topic ) );
		$titles   = $selector->keep_titles_with_topic( $titles, $topic );
		if ( $titles ) {
			return $titles;
		}
		$fallback = $this->sanitize_titles( array( $topic->base() !== '' ? $topic->base() : $this->normalize_spaces( $current_title ) ) );
		return $selector->keep_titles_with_topic( $fallback, $topic );
	}

	private function heuristic_keywords( WP_Post $post ): array {
		$title   = $this->normalize_spaces( get_the_title( $post->ID ) );
		$content = $this->normalize_spaces( wp_trim_words( (string) $post->post_content, 140, '' ) );
		$base    = array();

		if ( $title !== '' ) {
			$base[] = $title;
			$tokens = preg_split( '/\s+/u', $title );
			if ( is_array( $tokens ) && count( $tokens ) >= 2 ) {
				$base[] = implode( ' ', array_slice( $tokens, 0, min( 3, count( $tokens ) ) ) );
			}
		}

		$categories = get_the_terms( $post->ID, 'category' );
		if ( is_array( $categories ) ) {
			foreach ( $categories as $category ) {
				if ( ! empty( $category->name ) ) {
					$base[] = $title !== '' ? $title . ' ' . sanitize_text_field( $category->name ) : sanitize_text_field( $category->name );
				}
			}
		}

		$tags = get_the_terms( $post->ID, 'post_tag' );
		if ( is_array( $tags ) ) {
			foreach ( array_slice( $tags, 0, 3 ) as $tag ) {
				if ( ! empty( $tag->name ) ) {
					$base[] = sanitize_text_field( $tag->name );
				}
			}
		}

		$content_words = preg_split( '/\s+/u', $content );
		if ( is_array( $content_words ) && count( $content_words ) >= 6 ) {
			$base[] = implode( ' ', array_slice( $content_words, 0, 3 ) );
			$base[] = implode( ' ', array_slice( $content_words, 3, 3 ) );
		}

		return $this->sanitize_keywords( $base );
	}

	private function render_faq_block( array $faqs ): string {
		if ( empty( $faqs ) ) {
			return '';
		}
		$html = "\n<h2>FAQ</h2>\n";
		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => array(),
		);

		foreach ( $faqs as $faq ) {
			if ( empty( $faq['q'] ) || empty( $faq['a'] ) ) {
				continue;
			}
			$q = $this->normalize_spaces( (string) $faq['q'] );
			$a = $this->normalize_spaces( (string) $faq['a'] );
			$html .= '<h3>' . esc_html( $q ) . "</h3>\n";
			$html .= '<p>' . esc_html( $a ) . "</p>\n";
			$schema['mainEntity'][] = array(
				'@type'          => 'Question',
				'name'           => $q,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $a,
				),
			);
		}

		if ( ! empty( $schema['mainEntity'] ) ) {
			$html .= '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>';
		}

		return $html;
	}

	private function render_external_links_block( array $links ): string {
		if ( empty( $links ) ) {
			return '';
		}
		$html = "\n<h2>Useful Resources</h2>\n<p>";
		$items = array();
		foreach ( $links as $link ) {
			if ( empty( $link['url'] ) || empty( $link['title'] ) ) {
				continue;
			}
			$items[] = '<a href="' . esc_url( (string) $link['url'] ) . '" rel="nofollow noopener" target="_blank">' . esc_html( (string) $link['title'] ) . '</a>';
		}
		$html .= implode( ' | ', $items );
		$html .= "</p>\n";
		return $html;
	}

	private function audit_post_refresh( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		RSAIP_Plugin::instance()->link_graph()->index_post_links( $post );
	}

	private function try_decode_json( string $text ): ?array {
		$text = trim( $text );
		if ( $text === '' ) {
			return null;
		}
		$decoded = json_decode( $text, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}
		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );
		if ( $start === false || $end === false || $end <= $start ) {
			return null;
		}
		$maybe = substr( $text, $start, $end - $start + 1 );
		$decoded = json_decode( $maybe, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	private function render_recommendations_html( string $title, string $url, array $data ): string {
		$out = '<h2>' . esc_html( $title ) . '</h2>';
		$out .= '<div class="rsaip-sub"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $url ) . '</a></div>';

		$out .= $this->render_section_list( __( 'Internal Linking Suggestions', 'recipe-seo-ai-pro' ), $data['internal_linking_suggestions'] ?? array() );
		$out .= $this->render_section_list( __( 'Keyword Opportunities', 'recipe-seo-ai-pro' ), $data['keyword_opportunities'] ?? array() );
		$out .= $this->render_section_list( __( 'Content Expansion Opportunities', 'recipe-seo-ai-pro' ), $data['content_expansion_opportunities'] ?? array() );
		$out .= $this->render_section_list( __( 'Featured Snippet Opportunities', 'recipe-seo-ai-pro' ), $data['featured_snippet_opportunities'] ?? array() );

		if ( ! empty( $data['ai_raw'] ) && is_string( $data['ai_raw'] ) ) {
			$out .= '<h3>' . esc_html__( 'AI Output (raw)', 'recipe-seo-ai-pro' ) . '</h3>';
			$out .= '<pre class="rsaip-pre">' . esc_html( $data['ai_raw'] ) . '</pre>';
		}

		return $out;
	}

	private function render_section_list( string $title, $items ): string {
		$out = '<h3>' . esc_html( $title ) . '</h3>';
		if ( ! is_array( $items ) || empty( $items ) ) {
			return $out . '<div>—</div>';
		}
		$out .= '<ul class="rsaip-list">';
		foreach ( $items as $it ) {
			if ( is_string( $it ) ) {
				$out .= '<li>' . esc_html( $it ) . '</li>';
				continue;
			}
			if ( is_array( $it ) ) {
				$line = '';
				if ( ! empty( $it['title'] ) && ! empty( $it['url'] ) ) {
					$line .= '<a href="' . esc_url( (string) $it['url'] ) . '" target="_blank" rel="noopener">' . esc_html( (string) $it['title'] ) . '</a>';
				} elseif ( ! empty( $it['title'] ) ) {
					$line .= esc_html( (string) $it['title'] );
				} else {
					$line .= esc_html( wp_json_encode( $it ) );
				}
				if ( ! empty( $it['anchor_text'] ) ) {
					$line .= ' <span class="rsaip-sub">' . esc_html__( 'Anchor:', 'recipe-seo-ai-pro' ) . ' ' . esc_html( (string) $it['anchor_text'] ) . '</span>';
				}
				if ( ! empty( $it['why'] ) ) {
					$line .= '<div class="rsaip-sub">' . esc_html( (string) $it['why'] ) . '</div>';
				}
				$out .= '<li>' . $line . '</li>';
			}
		}
		$out .= '</ul>';
		return $out;
	}

	/**
	 * HTTP completion facade → AiProviderRegistry / OpenAiCompatibleProvider.
	 *
	 * Historical chat() never inspected settings.ai_provider (callers did). To keep
	 * request/response behavior identical, this always uses the openai_compatible
	 * transport. DisabledProvider remains available via the registry for settings
	 * resolution without changing existing chat() semantics.
	 *
	 * @param string $prompt     Prompt text (owned/built by RSAIP_AI features).
	 * @param int    $max_tokens Max tokens option passed through to the provider.
	 * @return string|WP_Error
	 */
	private function chat( string $prompt, int $max_tokens ) {
		$registry = rsaip_ai_provider_registry();
		$provider = $registry->get( 'openai_compatible' );

		return $provider->complete(
			$prompt,
			array(
				'max_tokens' => $max_tokens,
			)
		);
	}
}
