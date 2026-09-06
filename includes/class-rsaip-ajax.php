<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSAIP_AJAX {
	private RSAIP_Plugin $plugin;

	public function __construct( RSAIP_Plugin $plugin ) {
		$this->plugin = $plugin;

		$actions = array(
			'rsaip_get_dashboard_stats',
			'rsaip_rebuild_link_graph',
			'rsaip_run_full_audit',
			'rsaip_generate_suggestions',
			'rsaip_list_suggestions',
			'rsaip_insert_links_for_post',
			'rsaip_auto_link_run_batch',
			'rsaip_list_orphans',
			'rsaip_list_audit_issues',
			'rsaip_list_image_issues',
			'rsaip_generate_alt_text',
			'rsaip_gsc_test_connection',
			'rsaip_gsc_top_opportunities',
			'rsaip_gsc_low_hanging',
			'rsaip_recipe_scan',
			'rsaip_recipe_generate_card',
			'rsaip_recipe_insert_card',
			'rsaip_schema_scan',
			'rsaip_schema_fix_post',
			'rsaip_schema_fix_batch',
			'rsaip_schema_enqueue_queue',
			'rsaip_schema_process_queue',
			'rsaip_schema_queue_stats',
			'rsaip_sitemap_audit',
			'rsaip_performance_analyze',
			'rsaip_ai_recommendations',
			'rsaip_ai_analyze_post',
			'rsaip_ai_generate_titles',
			'rsaip_ai_propose_title',
			'rsaip_ai_apply_title',
			'rsaip_ai_generate_keywords',
			'rsaip_ai_propose_keywords',
			'rsaip_ai_apply_keywords',
			'rsaip_ai_generate_meta_desc',
			'rsaip_ai_propose_meta_desc',
			'rsaip_ai_apply_meta_desc',
			'rsaip_ai_generate_faq',
			'rsaip_ai_generate_article',
			'rsaip_ai_create_article_draft',
			'rsaip_ai_article_preview_generate',
			'rsaip_ai_article_preview_get',
			'rsaip_ai_article_create_draft',
			'rsaip_ai_fix_post',
			'rsaip_ai_bulk_apply_meta_desc',
			'rsaip_bulk_enqueue_fix_queue',
			'rsaip_bulk_process_queue',
			'rsaip_bulk_queue_stats',
			'rsaip_report_preview',
			'rsaip_report_export_csv',
			'rsaip_report_export_xlsx',
			'rsaip_report_export_pdf',
			'rsaip_update_settings_inline',
		);

		foreach ( $actions as $a ) {
			add_action( 'wp_ajax_' . $a, array( $this, $a ) );
		}

		// Public recipe rating (logged-in + visitors). Not gated by manage_options.
		add_action( 'wp_ajax_rsaip_public_save_recipe_rating', array( $this, 'rsaip_public_save_recipe_rating' ) );
		add_action( 'wp_ajax_nopriv_rsaip_public_save_recipe_rating', array( $this, 'rsaip_public_save_recipe_rating' ) );
	}

	private function check(): void {
		if ( ! current_user_can( rsaip_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'recipe-seo-ai-pro' ) ), 403 );
		}
		check_ajax_referer( 'rsaip_nonce', 'nonce' );
	}

	private function post_int( string $key, int $default = 0 ): int {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}
		return absint( wp_unslash( $_POST[ $key ] ) );
	}

	private function post_text( string $key, string $default = '' ): string {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}
		return sanitize_text_field( (string) wp_unslash( $_POST[ $key ] ) );
	}

	private function post_proposal_ticket(): string {
		if ( ! isset( $_POST['proposal_ticket'] ) ) {
			return '';
		}
		$raw = (string) wp_unslash( $_POST['proposal_ticket'] );
		$raw = preg_replace( '/[^A-Za-z0-9._=-]/', '', $raw );
		return is_string( $raw ) ? $raw : '';
	}

	/**
	 * Build propose() input and surface banned client meta-key fields for rejection.
	 *
	 * @param array<string, mixed> $base Base input.
	 * @return array<string, mixed>
	 */
	private function propose_input( array $base ): array {
		foreach ( array( 'meta_key', 'meta_keys', 'key', 'keys' ) as $banned ) {
			if ( isset( $_POST[ $banned ] ) ) {
				$base[ $banned ] = wp_unslash( $_POST[ $banned ] );
			}
		}
		return $base;
	}

	public function rsaip_update_settings_inline(): void {
		$this->check();
		$key   = $this->post_text( 'key' );
		$value = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : null;
		$settings = rsaip_get_settings();

		if ( ! array_key_exists( $key, $settings ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown setting', 'recipe-seo-ai-pro' ) ), 400 );
		}

		// Secrets must only be changed via Settings forms (never via inline AJAX).
		if ( in_array( $key, rsaip_secret_setting_keys(), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Secret settings cannot be updated inline.', 'recipe-seo-ai-pro' ) ), 403 );
		}

		$updates = array();
		if ( is_numeric( $settings[ $key ] ) ) {
			$updates[ $key ] = absint( $value );
		} else {
			$updates[ $key ] = is_string( $value ) ? sanitize_text_field( (string) $value ) : $settings[ $key ];
		}

		rsaip_update_settings( $updates );
		wp_send_json_success( array( 'ok' => true ) );
	}

	public function rsaip_get_dashboard_stats(): void {
		$this->check();
		$stats = $this->plugin->audit()->get_dashboard_stats();
		wp_send_json_success( $stats );
	}

	public function rsaip_rebuild_link_graph(): void {
		$this->check();
		$result = $this->plugin->link_graph()->rebuild_all( true );
		wp_send_json_success( $result );
	}

	public function rsaip_run_full_audit(): void {
		$this->check();
		$result = $this->plugin->audit()->run_full_audit( true );
		delete_transient( 'rsaip_dashboard_stats' );
		wp_send_json_success( $result );
	}

	public function rsaip_generate_suggestions(): void {
		$this->check();
		$settings = rsaip_get_settings();
		$limit    = $this->post_int( 'limit', (int) $settings['link_suggestion_limit'] );
		$limit    = max( 1, min( 20, $limit ) );
		$result   = $this->plugin->suggester()->generate_for_all_posts( $limit );
		wp_send_json_success( $result );
	}

	public function rsaip_list_suggestions(): void {
		$this->check();
		$rows = $this->plugin->suggester()->get_latest_suggestions_grouped( 50 );
		$html = $this->plugin->suggester()->render_suggestions_rows_html( $rows );
		wp_send_json_success( array( 'html' => $html ) );
	}

	public function rsaip_insert_links_for_post(): void {
		$this->check();
		$post_id = $this->post_int( 'post_id' );
		$result  = $this->plugin->auto_linker()->insert_for_post_id( $post_id );
		wp_send_json_success( $result );
	}

	public function rsaip_auto_link_run_batch(): void {
		$this->check();
		$result = $this->plugin->auto_linker()->run_batch();
		wp_send_json_success( $result );
	}

	public function rsaip_list_orphans(): void {
		$this->check();
		$rows = $this->plugin->audit()->get_orphan_posts( 100 );
		$html = $this->plugin->audit()->render_orphans_rows_html( $rows );
		wp_send_json_success( array( 'html' => $html ) );
	}

	public function rsaip_list_audit_issues(): void {
		$this->check();
		$rows = $this->plugin->audit()->get_audit_rows( 100 );
		$html = $this->plugin->audit()->render_audit_rows_html( $rows );
		wp_send_json_success( array( 'html' => $html ) );
	}

	public function rsaip_list_image_issues(): void {
		$this->check();
		$rows = $this->plugin->image_optimizer()->scan_images( 100 );
		$html = $this->plugin->image_optimizer()->render_image_rows_html( $rows );
		wp_send_json_success( array( 'html' => $html ) );
	}

	public function rsaip_generate_alt_text(): void {
		$this->check();
		$attachment_id = $this->post_int( 'attachment_id' );
		$post_id       = $this->post_int( 'post_id' );
		$result        = $this->plugin->image_optimizer()->generate_alt_text_for_attachment( $attachment_id, $post_id );
		wp_send_json_success( $result );
	}

	public function rsaip_gsc_test_connection(): void {
		$this->check();
		$result = $this->plugin->gsc()->test_connection();
		wp_send_json_success( $result );
	}

	public function rsaip_gsc_top_opportunities(): void {
		$this->check();
		$rows = $this->plugin->gsc()->get_top_opportunities( 50 );
		$html = $this->plugin->gsc()->render_opportunities_rows_html( $rows );
		wp_send_json_success( array( 'html' => $html ) );
	}

	public function rsaip_gsc_low_hanging(): void {
		$this->check();
		$rows = $this->plugin->gsc()->get_low_hanging_fruits( 100 );
		$html = $this->plugin->gsc()->render_low_hanging_rows_html( $rows );
		wp_send_json_success( array( 'html' => $html ) );
	}

	public function rsaip_recipe_scan(): void {
		$this->check();
		$rows = $this->plugin->recipe_optimizer()->scan( 100 );
		$html = $this->plugin->recipe_optimizer()->render_rows_html( $rows );
		wp_send_json_success( array( 'html' => $html ) );
	}

	public function rsaip_recipe_generate_card(): void {
		$this->check();
		$post_id = $this->post_int( 'post_id' );
		$result  = $this->plugin->recipe_optimizer()->generate_recipe_card_for_post( $post_id );
		wp_send_json_success( $result );
	}

	public function rsaip_recipe_insert_card(): void {
		$this->check();
		$post_id = $this->post_int( 'post_id' );
		$result  = $this->plugin->recipe_optimizer()->insert_recipe_card_for_post( $post_id );
		wp_send_json_success( $result );
	}

	/**
	 * Public endpoint: save a recipe rating for visitors and logged-in users.
	 */
	public function rsaip_public_save_recipe_rating(): void {
		if ( ! check_ajax_referer( 'rsaip_public_rating', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'recipe-seo-ai-pro' ) ), 403 );
		}

		// Honeypot: bots that fill hidden fields are rejected silently.
		$honeypot = isset( $_POST['website'] ) ? trim( (string) wp_unslash( $_POST['website'] ) ) : '';
		if ( $honeypot !== '' ) {
			wp_send_json_error( array( 'message' => __( 'Unable to save rating.', 'recipe-seo-ai-pro' ) ), 400 );
		}

		$post_id = $this->post_int( 'post_id' );
		$rating  = $this->post_int( 'rating' );

		if ( $post_id <= 0 || $rating < 1 || $rating > 5 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid rating.', 'recipe-seo-ai-pro' ) ), 400 );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			wp_send_json_error( array( 'message' => __( 'Recipe not available for rating.', 'recipe-seo-ai-pro' ) ), 404 );
		}

		if ( ! RSAIP_DB::recipe_votes_table_ready() ) {
			RSAIP_DB::migrate_recipe_votes_table();
			if ( ! RSAIP_DB::recipe_votes_table_ready() ) {
				wp_send_json_error( array( 'message' => __( 'Rating storage unavailable.', 'recipe-seo-ai-pro' ) ), 503 );
			}
		}

		$ip_hash = $this->rating_ip_hash();
		if ( $this->rating_is_rate_limited( $ip_hash ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many rating attempts. Please try again later.', 'recipe-seo-ai-pro' ) ), 429 );
		}

		$voter_key = $this->rating_voter_key();
		if ( $voter_key === '' ) {
			wp_send_json_error( array( 'message' => __( 'Unable to verify voter.', 'recipe-seo-ai-pro' ) ), 400 );
		}

		global $wpdb;
		$table = RSAIP_DB::table_recipe_votes();
		$now   = RSAIP_DB::now_gmt_sql();

		// Duplicate check before insert (unique index is the hard guarantee).
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE post_id = %d AND voter_key = %s LIMIT 1",
				$post_id,
				$voter_key
			)
		);
		if ( $existing ) {
			wp_send_json_error( array( 'message' => __( 'You have already rated this recipe.', 'recipe-seo-ai-pro' ) ), 409 );
		}

		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (post_id, voter_key, rating, ip_hash, created_at) VALUES (%d, %s, %d, %s, %s)",
				$post_id,
				$voter_key,
				$rating,
				$ip_hash,
				$now
			)
		);

		if ( false === $inserted ) {
			// Unique collision from a concurrent request.
			wp_send_json_error( array( 'message' => __( 'You have already rated this recipe.', 'recipe-seo-ai-pro' ) ), 409 );
		}

		$this->rating_bump_rate_limit( $ip_hash );

		$agg = $this->rating_recalculate_aggregate( $post_id );

		wp_send_json_success(
			array(
				'ok'           => true,
				'rating_value' => $agg['rating_value'],
				'review_count' => $agg['review_count'],
			)
		);
	}

	private function rating_ip_hash(): string {
		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = (string) wp_unslash( $_SERVER['REMOTE_ADDR'] );
		}
		$ip = sanitize_text_field( $ip );
		if ( $ip === '' ) {
			$ip = 'unknown';
		}
		return hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
	}

	private function rating_is_rate_limited( string $ip_hash ): bool {
		$burst_key = 'rsaip_rate_burst_' . substr( $ip_hash, 0, 32 );
		$hour_key  = 'rsaip_rate_hour_' . substr( $ip_hash, 0, 32 );

		if ( false !== get_transient( $burst_key ) ) {
			return true;
		}

		$hour_count = (int) get_transient( $hour_key );
		return $hour_count >= 10;
	}

	private function rating_bump_rate_limit( string $ip_hash ): void {
		$burst_key = 'rsaip_rate_burst_' . substr( $ip_hash, 0, 32 );
		$hour_key  = 'rsaip_rate_hour_' . substr( $ip_hash, 0, 32 );

		set_transient( $burst_key, 1, 3 );

		$hour_count = (int) get_transient( $hour_key );
		set_transient( $hour_key, $hour_count + 1, HOUR_IN_SECONDS );
	}

	private function rating_voter_key(): string {
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			return 'user:' . $user_id;
		}

		$cookie_name = 'rsaip_voter';
		$token       = '';
		if ( isset( $_COOKIE[ $cookie_name ] ) ) {
			$raw = sanitize_text_field( (string) wp_unslash( $_COOKIE[ $cookie_name ] ) );
			if ( preg_match( '/^[a-f0-9]{32,64}$/', $raw ) ) {
				$token = $raw;
			}
		}

		if ( $token === '' ) {
			// Fallback if the page-view cookie was blocked; still bind this request.
			try {
				$token = bin2hex( random_bytes( 16 ) );
			} catch ( Exception $e ) {
				$token = wp_generate_password( 32, false, false );
			}
		}

		return 'guest:' . hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	private function rating_recalculate_aggregate( int $post_id ): array {
		global $wpdb;
		$table = RSAIP_DB::table_recipe_votes();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM {$table} WHERE post_id = %d",
				$post_id
			),
			ARRAY_A
		);

		$review_count = isset( $row['review_count'] ) ? absint( $row['review_count'] ) : 0;
		$avg          = isset( $row['avg_rating'] ) ? (float) $row['avg_rating'] : 0.0;
		$rating_value = $review_count > 0 ? round( $avg, 1 ) : 0.0;

		update_post_meta( $post_id, 'rsaip_recipe_rating_value', $rating_value );
		update_post_meta( $post_id, 'rsaip_recipe_review_count', $review_count );

		return array(
			'rating_value' => $rating_value,
			'review_count' => $review_count,
		);
	}

	public function rsaip_schema_scan(): void {
		$this->check();
		$rows = $this->plugin->schema_validator()->scan( 100 );
		$html = $this->plugin->schema_validator()->render_rows_html( $rows );
		wp_send_json_success( array( 'html' => $html ) );
	}

	public function rsaip_schema_fix_post(): void {
		$this->check();
		$post_id = $this->post_int( 'post_id' );
		$result  = $this->plugin->schema_validator()->fix_post_schema( $post_id );
		wp_send_json_success( $result );
	}

	public function rsaip_schema_fix_batch(): void {
		$this->check();
		$limit  = $this->post_int( 'limit', 50 );
		$limit  = max( 1, min( 200, $limit ) );
		$result = $this->plugin->schema_validator()->fix_batch( $limit );
		wp_send_json_success( $result );
	}

	public function rsaip_schema_enqueue_queue(): void {
		$this->check();
		$limit          = $this->post_int( 'limit', 200 );
		$result         = $this->plugin->bulk_optimizer()->enqueue_missing_posts( $limit, 'schema_fix' );
		$result['stats'] = $this->plugin->bulk_optimizer()->get_queue_stats();
		wp_send_json_success( $result );
	}

	public function rsaip_schema_process_queue(): void {
		$this->check();
		$limit          = $this->post_int( 'limit', (int) rsaip_get_settings()['bulk_queue_batch_size'] );
		$result         = $this->plugin->bulk_optimizer()->process_queue( $limit );
		$result['stats'] = $this->plugin->bulk_optimizer()->get_queue_stats();
		wp_send_json_success( $result );
	}

	public function rsaip_schema_queue_stats(): void {
		$this->check();
		wp_send_json_success( $this->plugin->bulk_optimizer()->get_queue_stats() );
	}

	public function rsaip_sitemap_audit(): void {
		$this->check();
		$url  = $this->post_text( 'sitemap_url' );
		$rows = $this->plugin->sitemap_auditor()->audit( $url, 200 );
		$html = $this->plugin->sitemap_auditor()->render_rows_html( $rows );
		wp_send_json_success( array( 'html' => $html ) );
	}

	public function rsaip_performance_analyze(): void {
		$this->check();
		$url      = $this->post_text( 'url' );
		$strategy = $this->post_text( 'strategy', 'mobile' );
		$result   = $this->plugin->performance()->analyze( $url, $strategy );
		wp_send_json_success( $result );
	}

	public function rsaip_ai_recommendations(): void {
		$this->check();
		$post_id = $this->post_int( 'post_id' );
		$result  = $this->plugin->ai()->generate_seo_recommendations_for_post( $post_id );
		wp_send_json_success( $result );
	}

	public function rsaip_ai_analyze_post(): void {
		$this->check();
		$post_id = $this->post_int( 'post_id' );
		$result  = $this->plugin->ai()->analyze_post_ai( $post_id );
		wp_send_json_success( $result );
	}

	public function rsaip_ai_generate_titles(): void {
		$this->check();
		$post_id = $this->post_int( 'post_id' );
		$result  = $this->plugin->ai()->generate_title_suggestions_for_post( $post_id );
		wp_send_json_success( $result );
	}

	/**
	 * Milestone 3A: read-only title proposal / preview. Never applies.
	 */
	public function rsaip_ai_propose_title(): void {
		$this->check();
		$title = isset( $_POST['title'] ) ? (string) wp_unslash( $_POST['title'] ) : '';
		if ( $title === '' && isset( $_POST['value'] ) ) {
			$title = (string) wp_unslash( $_POST['value'] );
		}
		$input = $this->propose_input(
			array(
				'post_id'       => $this->post_int( 'post_id' ),
				'mutation_type' => \RecipeSeoAiPro\Modules\PostMutation\MutationType::TITLE,
				'value'         => $title,
			)
		);
		$result = rsaip_post_mutation_service()->propose( $input );
		wp_send_json_success( $result->to_preview_array() );
	}

	public function rsaip_ai_apply_title(): void {
		$this->check();
		$title = isset( $_POST['title'] ) ? (string) wp_unslash( $_POST['title'] ) : '';
		if ( $title === '' && isset( $_POST['value'] ) ) {
			$title = (string) wp_unslash( $_POST['value'] );
		}
		$input = $this->propose_input(
			array(
				'post_id'          => $this->post_int( 'post_id' ),
				'value'            => $title,
				'proposal_ticket'  => $this->post_proposal_ticket(),
			)
		);
		$result = rsaip_post_mutation_service()->apply_from_preview(
			$input,
			\RecipeSeoAiPro\Modules\PostMutation\MutationType::TITLE
		);
		wp_send_json_success( $result->to_apply_array() );
	}

	public function rsaip_ai_generate_keywords(): void {
		$this->check();
		$post_id = $this->post_int( 'post_id' );
		$result  = $this->plugin->ai()->generate_keywords_for_post( $post_id );
		wp_send_json_success( $result );
	}

	/**
	 * Milestone 3A: read-only keywords list proposal. Never applies. Never uses FOCUS_KEYWORD.
	 */
	public function rsaip_ai_propose_keywords(): void {
		$this->check();
		$keywords = isset( $_POST['keywords'] ) ? (string) wp_unslash( $_POST['keywords'] ) : '';
		if ( $keywords === '' && isset( $_POST['value'] ) ) {
			$keywords = (string) wp_unslash( $_POST['value'] );
		}
		$target = $this->post_text( 'target', 'auto' );
		$input  = $this->propose_input(
			array(
				'post_id'       => $this->post_int( 'post_id' ),
				'mutation_type' => \RecipeSeoAiPro\Modules\PostMutation\MutationType::KEYWORDS,
				'value'         => $keywords,
				'target'        => $target,
			)
		);
		$result = rsaip_post_mutation_service()->propose( $input );
		wp_send_json_success( $result->to_preview_array() );
	}

	public function rsaip_ai_apply_keywords(): void {
		$this->check();
		$keywords = isset( $_POST['keywords'] ) ? (string) wp_unslash( $_POST['keywords'] ) : '';
		if ( $keywords === '' && isset( $_POST['value'] ) ) {
			$keywords = (string) wp_unslash( $_POST['value'] );
		}
		$input = $this->propose_input(
			array(
				'post_id'         => $this->post_int( 'post_id' ),
				'value'           => $keywords,
				'target'          => $this->post_text( 'target', 'auto' ),
				'proposal_ticket' => $this->post_proposal_ticket(),
			)
		);
		$result = rsaip_post_mutation_service()->apply_from_preview(
			$input,
			\RecipeSeoAiPro\Modules\PostMutation\MutationType::KEYWORDS
		);
		wp_send_json_success( $result->to_apply_array() );
	}

	public function rsaip_ai_generate_meta_desc(): void {
		$this->check();
		$post_id = $this->post_int( 'post_id' );
		$result  = $this->plugin->ai()->generate_meta_description_for_post( $post_id );
		wp_send_json_success( $result );
	}

	/**
	 * Milestone 3A: read-only meta description proposal. Never applies.
	 */
	public function rsaip_ai_propose_meta_desc(): void {
		$this->check();
		$metadesc = isset( $_POST['metadesc'] ) ? (string) wp_unslash( $_POST['metadesc'] ) : '';
		if ( $metadesc === '' && isset( $_POST['value'] ) ) {
			$metadesc = (string) wp_unslash( $_POST['value'] );
		}
		$target = $this->post_text( 'target', 'auto' );
		$input  = $this->propose_input(
			array(
				'post_id'       => $this->post_int( 'post_id' ),
				'mutation_type' => \RecipeSeoAiPro\Modules\PostMutation\MutationType::META_DESCRIPTION,
				'value'         => $metadesc,
				'target'        => $target,
			)
		);
		$result = rsaip_post_mutation_service()->propose( $input );
		wp_send_json_success( $result->to_preview_array() );
	}

	public function rsaip_ai_apply_meta_desc(): void {
		$this->check();
		$metadesc = isset( $_POST['metadesc'] ) ? (string) wp_unslash( $_POST['metadesc'] ) : '';
		if ( $metadesc === '' && isset( $_POST['value'] ) ) {
			$metadesc = (string) wp_unslash( $_POST['value'] );
		}
		$input = $this->propose_input(
			array(
				'post_id'         => $this->post_int( 'post_id' ),
				'value'           => $metadesc,
				'target'          => $this->post_text( 'target', 'auto' ),
				'proposal_ticket' => $this->post_proposal_ticket(),
			)
		);
		$result = rsaip_post_mutation_service()->apply_from_preview(
			$input,
			\RecipeSeoAiPro\Modules\PostMutation\MutationType::META_DESCRIPTION
		);
		wp_send_json_success( $result->to_apply_array() );
	}

	public function rsaip_ai_generate_faq(): void {
		$this->check();
		$post_id = $this->post_int( 'post_id' );
		$result  = $this->plugin->ai()->generate_faq_suggestions_for_post( $post_id );
		wp_send_json_success( $result );
	}

	public function rsaip_ai_generate_article(): void {
		$this->check();
		$title    = isset( $_POST['title'] ) ? (string) wp_unslash( $_POST['title'] ) : '';
		$keywords = isset( $_POST['keywords'] ) ? (string) wp_unslash( $_POST['keywords'] ) : '';
		$images   = isset( $_POST['images'] ) ? (string) wp_unslash( $_POST['images'] ) : '';
		$words    = $this->post_int( 'word_count', 1000 );
		$words    = max( 400, min( 4000, $words ) );
		$template = $this->post_text( 'template', 'general' );
		$result   = $this->plugin->ai()->generate_article_from_brief(
			$title,
			array_filter( array_map( 'trim', preg_split( '/[,|\n]+/u', $keywords ) ?: array() ) ),
			array_filter( array_map( 'trim', preg_split( '/[\r\n]+/u', $images ) ?: array() ) ),
			$words,
			$template
		);
		wp_send_json_success( $result );
	}

	public function rsaip_ai_create_article_draft(): void {
		$this->check();
		$title    = isset( $_POST['title'] ) ? (string) wp_unslash( $_POST['title'] ) : '';
		$keywords = isset( $_POST['keywords'] ) ? (string) wp_unslash( $_POST['keywords'] ) : '';
		$images   = isset( $_POST['images'] ) ? (string) wp_unslash( $_POST['images'] ) : '';
		$words    = $this->post_int( 'word_count', 1000 );
		$words    = max( 400, min( 4000, $words ) );
		$template = $this->post_text( 'template', 'general' );
		$result   = $this->plugin->ai()->create_draft_from_brief(
			$title,
			array_filter( array_map( 'trim', preg_split( '/[,|\n]+/u', $keywords ) ?: array() ) ),
			array_filter( array_map( 'trim', preg_split( '/[\r\n]+/u', $images ) ?: array() ) ),
			$words,
			$template
		);
		wp_send_json_success( $result );
	}

	/**
	 * NEW Article Generator preview (Milestone D). Read-only — never creates posts/drafts.
	 */
	public function rsaip_ai_article_preview_generate(): void {
		$this->check();
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $user_id <= 0 ) {
			wp_send_json_success(
				array(
					'ok'            => false,
					'code'          => 'rsaip_article_preview_unauthorized',
					'message'       => 'You must be logged in to generate a preview.',
					'source'        => 'article_generation_preview',
					'apply_allowed' => false,
					'draft_allowed' => false,
					'create_draft'  => false,
				)
			);
		}

		$title    = isset( $_POST['title'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['title'] ) ) : '';
		$keywords = isset( $_POST['keywords'] ) ? (string) wp_unslash( $_POST['keywords'] ) : '';
		$images   = isset( $_POST['images'] ) ? (string) wp_unslash( $_POST['images'] ) : '';
		$words    = $this->post_int( 'word_count', 1000 );
		$template = $this->post_text( 'template', 'general' );
		$content  = isset( $_POST['content'] ) ? (string) wp_unslash( $_POST['content'] ) : '';
		$content  = $content !== '' ? wp_kses_post( $content ) : '';

		$kw_list = array_values(
			array_filter(
				array_map(
					'sanitize_text_field',
					array_map( 'trim', preg_split( '/[,|\n]+/u', $keywords ) ?: array() )
				)
			)
		);

		$brief = array(
			'title'      => $title,
			'keywords'   => $kw_list,
			'images'     => array_values(
				array_filter(
					array_map( 'trim', preg_split( '/[\r\n]+/u', $images ) ?: array() )
				)
			),
			'word_count' => $words,
			'template'   => $template,
		);
		if ( $content !== '' ) {
			$brief['content'] = $content;
		}
		if ( isset( $kw_list[0] ) ) {
			$brief['primary_focus_keyword'] = $kw_list[0];
		}

		if ( ! class_exists( \RecipeSeoAiPro\Modules\Ai\ArticleGeneration\ArticleGenerationService::class ) ) {
			wp_send_json_success(
				array(
					'ok'            => false,
					'code'          => 'rsaip_article_preview_unavailable',
					'message'       => 'Article generation preview is unavailable.',
					'source'        => 'article_generation_preview',
					'apply_allowed' => false,
					'draft_allowed' => false,
					'create_draft'  => false,
				)
			);
		}

		$service = new \RecipeSeoAiPro\Modules\Ai\ArticleGeneration\ArticleGenerationService();
		$result  = $service->generate_preview_from_brief( $brief, $user_id );
		wp_send_json_success( $result );
	}

	/**
	 * Retrieve a stored NEW Article Generator preview (read-only integrity check for Milestone E prep).
	 */
	public function rsaip_ai_article_preview_get(): void {
		$this->check();
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $user_id <= 0 ) {
			wp_send_json_success(
				array(
					'ok'            => false,
					'code'          => 'rsaip_article_preview_unauthorized',
					'message'       => 'You must be logged in to load a preview.',
					'source'        => 'article_generation_preview',
					'apply_allowed' => false,
					'draft_allowed' => false,
					'create_draft'  => false,
				)
			);
		}

		$proposal_id = isset( $_POST['proposal_id'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['proposal_id'] ) ) : '';
		$fingerprint = isset( $_POST['fingerprint'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['fingerprint'] ) ) : '';

		if ( ! class_exists( \RecipeSeoAiPro\Modules\Ai\ArticleGeneration\ArticleGenerationService::class ) ) {
			wp_send_json_success(
				array(
					'ok'            => false,
					'code'          => 'rsaip_article_preview_unavailable',
					'message'       => 'Article generation preview is unavailable.',
					'source'        => 'article_generation_preview',
					'apply_allowed' => false,
					'draft_allowed' => false,
					'create_draft'  => false,
				)
			);
		}

		$service = new \RecipeSeoAiPro\Modules\Ai\ArticleGeneration\ArticleGenerationService();
		$result  = $service->retrieve_preview( $proposal_id, $fingerprint, $user_id );
		wp_send_json_success( $result );
	}

	/**
	 * NEW Article Generator — explicit Create Draft (Milestone E).
	 * Creates ONLY a NEW draft from a server-stored preview proposal.
	 */
	public function rsaip_ai_article_create_draft(): void {
		$this->check();
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $user_id <= 0 ) {
			wp_send_json_success(
				array(
					'ok'      => false,
					'code'    => 'rsaip_article_draft_unauthorized',
					'message' => 'You must be logged in to create a draft.',
					'source'  => 'article_generation_draft',
				)
			);
		}

		// Ignore any client post_id / content — strip before service.
		$proposal_id = isset( $_POST['proposal_id'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['proposal_id'] ) ) : '';
		$fingerprint = isset( $_POST['fingerprint'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['fingerprint'] ) ) : '';

		if ( ! class_exists( \RecipeSeoAiPro\Modules\Ai\ArticleGeneration\ArticleGenerationDraftCreator::class ) ) {
			wp_send_json_success(
				array(
					'ok'      => false,
					'code'    => 'rsaip_article_draft_unavailable',
					'message' => 'Article draft creation is unavailable.',
					'source'  => 'article_generation_draft',
				)
			);
		}

		$creator = new \RecipeSeoAiPro\Modules\Ai\ArticleGeneration\ArticleGenerationDraftCreator();
		$result  = $creator->create_from_preview(
			array(
				'proposal_id' => $proposal_id,
				'fingerprint' => $fingerprint,
			),
			$user_id
		);
		wp_send_json_success( $result );
	}

	public function rsaip_ai_fix_post(): void {
		$this->check();
		if ( ! function_exists( 'rsaip_fix_with_ai_blocked' ) || rsaip_fix_with_ai_blocked() ) {
			wp_send_json_success(
				array(
					'ok'      => false,
					'code'    => 'fix_with_ai_blocked',
					'blocked' => true,
					'message' => function_exists( 'rsaip_fix_with_ai_frozen_message' )
						? rsaip_fix_with_ai_frozen_message()
						: 'Fix With AI is disabled in Milestone 4.',
				)
			);
		}

		$post_id = $this->post_int( 'post_id' );
		$result  = $this->plugin->ai()->fix_post_with_ai( $post_id, $this->plugin->suggester(), $this->plugin->auto_linker(), $this->plugin->recipe_optimizer() );
		wp_send_json_success( $result );
	}

	public function rsaip_ai_bulk_apply_meta_desc(): void {
		$this->check();
		if ( ! function_exists( 'rsaip_bulk_apply_meta_blocked' ) || rsaip_bulk_apply_meta_blocked() ) {
			wp_send_json_success(
				array(
					'ok'      => false,
					'checked' => 0,
					'updated' => 0,
					'code'    => 'bulk_apply_not_enabled',
					'message' => function_exists( 'rsaip_bulk_apply_meta_frozen_message' )
						? rsaip_bulk_apply_meta_frozen_message()
						: 'Bulk meta Apply is not enabled in Milestone 4.',
				)
			);
		}

		if ( ! function_exists( 'rsaip_never_modify_posts' ) || rsaip_never_modify_posts() ) {
			wp_send_json_success(
				array(
					'ok'      => false,
					'checked' => 0,
					'updated' => 0,
					'message' => function_exists( 'rsaip_existing_post_mutation_frozen_message' )
						? rsaip_existing_post_mutation_frozen_message()
						: 'This existing-post mutation is temporarily disabled while Safe Article Mode is enabled.',
				)
			);
		}

		$limit = $this->post_int( 'limit', 20 );
		$limit = max( 1, min( 50, $limit ) );

		$settings = rsaip_get_settings();
		$post_types = (array) ( $settings['auto_insert_post_types'] ?? array( 'post' ) );
		$statuses   = (array) ( $settings['auto_insert_statuses'] ?? array( 'publish' ) );

		$q = new WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => $statuses,
				'posts_per_page'         => 200,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$checked = 0;
		$updated = 0;

		foreach ( (array) $q->posts as $p ) {
			if ( ! $p instanceof WP_Post ) {
				continue;
			}
			$checked++;
			$pid = (int) $p->ID;
			$existing = rsaip_get_meta_description( $pid );
			if ( $existing !== '' ) {
				continue;
			}
			$gen = $this->plugin->ai()->generate_meta_description_for_post( $pid );
			$metadesc = isset( $gen['metadesc'] ) ? (string) $gen['metadesc'] : '';
			if ( $metadesc === '' ) {
				continue;
			}
			$apply = $this->plugin->ai()->apply_meta_description_to_post( $pid, $metadesc, 'auto' );
			if ( ! empty( $apply['ok'] ) ) {
				$updated++;
			}
			if ( $updated >= $limit ) {
				break;
			}
		}

		wp_send_json_success(
			array(
				'checked' => $checked,
				'updated' => $updated,
				'limit'   => $limit,
			)
		);
	}

	public function rsaip_bulk_enqueue_fix_queue(): void {
		$this->check();
		$limit  = $this->post_int( 'limit', 100 );
		$result = $this->plugin->bulk_optimizer()->enqueue_missing_posts( $limit, 'fix_with_ai' );
		$result['stats'] = $this->plugin->bulk_optimizer()->get_queue_stats();
		wp_send_json_success( $result );
	}

	public function rsaip_bulk_process_queue(): void {
		$this->check();
		$limit  = $this->post_int( 'limit', (int) rsaip_get_settings()['bulk_queue_batch_size'] );
		$result = $this->plugin->bulk_optimizer()->process_queue( $limit );
		$result['stats'] = $this->plugin->bulk_optimizer()->get_queue_stats();
		wp_send_json_success( $result );
	}

	public function rsaip_bulk_queue_stats(): void {
		$this->check();
		wp_send_json_success( $this->plugin->bulk_optimizer()->get_queue_stats() );
	}

	public function rsaip_report_preview(): void {
		$this->check();
		$type   = $this->post_text( 'type', 'weekly' );
		$result = $this->plugin->reports()->build_report( $type );
		wp_send_json_success( $result );
	}

	private function download_check(): void {
		if ( ! current_user_can( rsaip_capability() ) ) {
			wp_die( esc_html__( 'Access denied', 'recipe-seo-ai-pro' ), 403 );
		}
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( (string) wp_unslash( $_REQUEST['nonce'] ) ), 'rsaip_nonce' ) ) {
			wp_die( esc_html__( 'Invalid nonce', 'recipe-seo-ai-pro' ), 403 );
		}
	}

	public function rsaip_report_export_csv(): void {
		$this->download_check();
		$type   = isset( $_REQUEST['type'] ) ? sanitize_text_field( (string) wp_unslash( $_REQUEST['type'] ) ) : 'weekly';
		$report = $this->plugin->reports()->build_report( $type );
		$this->plugin->reports()->stream_csv( $report, 'rsaip-' . $type . '-report.csv' );
	}

	public function rsaip_report_export_xlsx(): void {
		$this->download_check();
		$type   = isset( $_REQUEST['type'] ) ? sanitize_text_field( (string) wp_unslash( $_REQUEST['type'] ) ) : 'weekly';
		$report = $this->plugin->reports()->build_report( $type );
		$this->plugin->reports()->stream_xlsx( $report, 'rsaip-' . $type . '-report.xlsx' );
	}

	public function rsaip_report_export_pdf(): void {
		$this->download_check();
		$type   = isset( $_REQUEST['type'] ) ? sanitize_text_field( (string) wp_unslash( $_REQUEST['type'] ) ) : 'weekly';
		$report = $this->plugin->reports()->build_report( $type );
		$this->plugin->reports()->stream_pdf( $report, 'rsaip-' . $type . '-report.pdf' );
	}
}
