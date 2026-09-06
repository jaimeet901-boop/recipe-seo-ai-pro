<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSAIP_DB {
	/**
	 * Schema version for idempotent upgrades (not the plugin marketing version).
	 */
	public static function schema_version(): string {
		return '1.0.11';
	}

	public static function table_ai_logs(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_ai_logs';
	}

	public static function table_link_graph(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_link_graph';
	}

	public static function table_post_metrics(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_post_metrics';
	}

	public static function table_link_suggestions(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_link_suggestions';
	}

	public static function table_bulk_queue(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_bulk_queue';
	}

	public static function table_recipe_votes(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_recipe_votes';
	}

	public static function table_content_briefs(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_content_briefs';
	}

	public static function table_projects(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_projects';
	}

	public static function table_project_members(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_project_members';
	}

	public static function table_project_statistics(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_project_statistics';
	}

	public static function table_project_assets(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_project_assets';
	}

	public static function table_project_tasks(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_project_tasks';
	}

	public static function table_project_activity(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_project_activity';
	}

	public static function table_keywords(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_keywords';
	}

	public static function table_keyword_clusters(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_keyword_clusters';
	}

	public static function table_keyword_notes(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_keyword_notes';
	}

	public static function table_keyword_history(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_keyword_history';
	}

	public static function table_serp_analyses(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_serp_analyses';
	}

	public static function table_content_calendars(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_content_calendars';
	}

	public static function table_calendar_events(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_calendar_events';
	}

	public static function table_publishing_queue(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_publishing_queue';
	}

	public static function table_content_optimizations(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_content_optimizations';
	}

	public static function table_content_versions(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_content_versions';
	}

	public static function table_rb_recipes(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_rb_recipes';
	}

	public static function table_rb_sections(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_rb_sections';
	}

	public static function table_rb_ingredients(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_rb_ingredients';
	}

	public static function table_rb_steps(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_rb_steps';
	}

	public static function table_recipe_ai_runs(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_recipe_ai_runs';
	}

	public static function table_recipe_ai_versions(): string {
		global $wpdb;
		return $wpdb->prefix . 'rsaip_recipe_ai_versions';
	}

	/**
	 * Run pending DB upgrades. Safe to call on every request (cheap no-op when done).
	 */
	public static function maybe_upgrade(): void {
		$installed = get_option( 'rsaip_db_version', '' );
		if ( is_string( $installed ) && version_compare( $installed, self::schema_version(), '>=' ) ) {
			return;
		}

		self::migrate_link_graph_external_uniqueness();
		self::migrate_recipe_votes_table();
		self::migrate_content_briefs_table();
		self::migrate_projects_tables();
		self::migrate_keywords_tables();
		self::migrate_serp_analyses_table();
		self::migrate_calendar_tables();
		self::migrate_optimizer_tables();
		self::migrate_recipe_builder_tables();
		self::migrate_recipe_ai_tables();
		self::migrate_ai_logs_table();

		// Do not bump version until the harmful unique index is confirmed gone
		// (or the table is not installed yet — CREATE schema already omits it).
		if ( self::link_graph_table_exists() && self::link_graph_has_index( 'uniq_internal' ) ) {
			return;
		}

		if ( ! self::recipe_votes_table_exists() ) {
			return;
		}

		if ( ! self::content_briefs_table_exists() ) {
			return;
		}

		if ( ! self::projects_tables_ready() ) {
			return;
		}

		if ( ! self::keywords_tables_ready() ) {
			return;
		}

		if ( ! self::serp_analyses_table_ready() ) {
			return;
		}

		if ( ! self::calendar_tables_ready() ) {
			return;
		}

		if ( ! self::optimizer_tables_ready() ) {
			return;
		}

		if ( ! self::recipe_builder_tables_ready() ) {
			return;
		}

		if ( ! self::recipe_ai_tables_ready() ) {
			return;
		}

		if ( ! self::ai_logs_table_ready() ) {
			return;
		}

		update_option( 'rsaip_db_version', self::schema_version(), false );
	}

	/**
	 * Create AI Hub request logs table if missing. Idempotent.
	 */
	public static function migrate_ai_logs_table(): void {
		if ( self::ai_logs_table_ready() ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		self::install_ai_logs_table();
	}

	public static function ai_logs_table_ready(): bool {
		return self::table_exists( self::table_ai_logs() );
	}

	public static function install_ai_logs_table(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$table   = self::table_ai_logs();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			provider VARCHAR(40) NOT NULL DEFAULT '',
			model VARCHAR(120) NOT NULL DEFAULT '',
			latency_ms INT UNSIGNED NOT NULL DEFAULT 0,
			request_size INT UNSIGNED NOT NULL DEFAULT 0,
			response_size INT UNSIGNED NOT NULL DEFAULT 0,
			prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
			completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
			total_tokens INT UNSIGNED NOT NULL DEFAULT 0,
			estimated_cost DECIMAL(12,6) NOT NULL DEFAULT 0,
			success TINYINT(1) NOT NULL DEFAULT 0,
			error_message VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY provider (provider),
			KEY success (success),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * Create AI Recipe Assistant tables if missing. Idempotent.
	 * Isolated from Recipe Builder 2.0 and legacy Recipe Engine.
	 */
	public static function migrate_recipe_ai_tables(): void {
		if ( self::recipe_ai_tables_ready() ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		self::install_recipe_ai_tables();
	}

	public static function recipe_ai_tables_ready(): bool {
		return self::table_exists( self::table_recipe_ai_runs() )
			&& self::table_exists( self::table_recipe_ai_versions() );
	}

	public static function install_recipe_ai_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset  = $wpdb->get_charset_collate();
		$runs     = self::table_recipe_ai_runs();
		$versions = self::table_recipe_ai_versions();

		$sql_runs = "CREATE TABLE {$runs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			rb_recipe_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			action_type VARCHAR(40) NOT NULL DEFAULT '',
			mode VARCHAR(60) NOT NULL DEFAULT '',
			status VARCHAR(30) NOT NULL DEFAULT 'draft',
			original_json LONGTEXT NULL,
			optimized_json LONGTEXT NULL,
			analysis_json LONGTEXT NULL,
			diff_json LONGTEXT NULL,
			summary TEXT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY rb_recipe_id (rb_recipe_id),
			KEY action_type (action_type),
			KEY status (status),
			KEY updated_at (updated_at)
		) {$charset};";

		$sql_versions = "CREATE TABLE {$versions} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			run_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			rb_recipe_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			label VARCHAR(40) NOT NULL DEFAULT '',
			version_no INT UNSIGNED NOT NULL DEFAULT 1,
			recipe_json LONGTEXT NULL,
			meta_json LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY run_id (run_id),
			KEY rb_recipe_id (rb_recipe_id),
			KEY label (label),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta( $sql_runs );
		dbDelta( $sql_versions );
	}

	/**
	 * Create Recipe Builder 2.0 tables if missing. Idempotent.
	 * Does not alter legacy recipe card storage/rendering.
	 */
	public static function migrate_recipe_builder_tables(): void {
		if ( self::recipe_builder_tables_ready() ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		self::install_recipe_builder_tables();
	}

	public static function recipe_builder_tables_ready(): bool {
		return self::table_exists( self::table_rb_recipes() )
			&& self::table_exists( self::table_rb_sections() )
			&& self::table_exists( self::table_rb_ingredients() )
			&& self::table_exists( self::table_rb_steps() );
	}

	public static function install_recipe_builder_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$recipes = self::table_rb_recipes();
		$sections = self::table_rb_sections();
		$ingredients = self::table_rb_ingredients();
		$steps = self::table_rb_steps();

		$sql_recipes = "CREATE TABLE {$recipes} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			title VARCHAR(255) NOT NULL DEFAULT '',
			description TEXT NULL,
			servings DECIMAL(8,2) NOT NULL DEFAULT 4,
			prep_time INT UNSIGNED NOT NULL DEFAULT 0,
			cook_time INT UNSIGNED NOT NULL DEFAULT 0,
			total_time INT UNSIGNED NOT NULL DEFAULT 0,
			notes LONGTEXT NULL,
			tips LONGTEXT NULL,
			equipment LONGTEXT NULL,
			unit_system VARCHAR(20) NOT NULL DEFAULT 'metric',
			status VARCHAR(30) NOT NULL DEFAULT 'draft',
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY status (status),
			KEY updated_at (updated_at)
		) {$charset};";

		$sql_sections = "CREATE TABLE {$sections} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			recipe_id BIGINT UNSIGNED NOT NULL,
			section_type VARCHAR(30) NOT NULL DEFAULT 'ingredient_group',
			title VARCHAR(255) NOT NULL DEFAULT '',
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY recipe_id (recipe_id),
			KEY sort_order (sort_order)
		) {$charset};";

		$sql_ingredients = "CREATE TABLE {$ingredients} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			recipe_id BIGINT UNSIGNED NOT NULL,
			section_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			name VARCHAR(255) NOT NULL DEFAULT '',
			quantity DECIMAL(12,4) NOT NULL DEFAULT 0,
			unit VARCHAR(40) NOT NULL DEFAULT '',
			note VARCHAR(255) NOT NULL DEFAULT '',
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY recipe_id (recipe_id),
			KEY section_id (section_id),
			KEY sort_order (sort_order)
		) {$charset};";

		$sql_steps = "CREATE TABLE {$steps} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			recipe_id BIGINT UNSIGNED NOT NULL,
			section_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			instruction LONGTEXT NULL,
			image_url VARCHAR(2048) NOT NULL DEFAULT '',
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY recipe_id (recipe_id),
			KEY section_id (section_id),
			KEY sort_order (sort_order)
		) {$charset};";

		dbDelta( $sql_recipes );
		dbDelta( $sql_sections );
		dbDelta( $sql_ingredients );
		dbDelta( $sql_steps );
	}

	/**
	 * Create Content Optimizer tables if missing. Idempotent.
	 */
	public static function migrate_optimizer_tables(): void {
		if ( self::optimizer_tables_ready() ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		self::install_optimizer_tables();
	}

	public static function optimizer_tables_ready(): bool {
		return self::table_exists( self::table_content_optimizations() )
			&& self::table_exists( self::table_content_versions() );
	}

	public static function install_optimizer_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$runs    = self::table_content_optimizations();
		$vers    = self::table_content_versions();

		$sql_runs = "CREATE TABLE {$runs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			workflow VARCHAR(60) NOT NULL DEFAULT '',
			scope VARCHAR(40) NOT NULL DEFAULT 'full_article',
			status VARCHAR(30) NOT NULL DEFAULT 'draft',
			seo_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			readability_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			eeat_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			recipe_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			title VARCHAR(255) NOT NULL DEFAULT '',
			meta_description TEXT NULL,
			analysis_json LONGTEXT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY workflow (workflow),
			KEY status (status),
			KEY updated_at (updated_at)
		) {$charset};";

		$sql_vers = "CREATE TABLE {$vers} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			optimization_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			version_no INT UNSIGNED NOT NULL DEFAULT 1,
			label VARCHAR(30) NOT NULL DEFAULT 'original',
			content LONGTEXT NULL,
			title VARCHAR(255) NOT NULL DEFAULT '',
			meta_description TEXT NULL,
			meta_json LONGTEXT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY optimization_id (optimization_id),
			KEY post_id (post_id),
			KEY label (label),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta( $sql_runs );
		dbDelta( $sql_vers );
	}

	/**
	 * Create Content Calendar tables if missing. Idempotent.
	 */
	public static function migrate_calendar_tables(): void {
		if ( self::calendar_tables_ready() ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		self::install_calendar_tables();
	}

	public static function calendar_tables_ready(): bool {
		return self::table_exists( self::table_content_calendars() )
			&& self::table_exists( self::table_calendar_events() )
			&& self::table_exists( self::table_publishing_queue() );
	}

	public static function install_calendar_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset   = $wpdb->get_charset_collate();
		$calendars = self::table_content_calendars();
		$events    = self::table_calendar_events();
		$queue     = self::table_publishing_queue();

		$sql_calendars = "CREATE TABLE {$calendars} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL DEFAULT '',
			project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
			description TEXT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'active',
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY project_id (project_id),
			KEY status (status),
			KEY name (name(191))
		) {$charset};";

		$sql_events = "CREATE TABLE {$events} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			calendar_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			title VARCHAR(255) NOT NULL DEFAULT '',
			project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			keyword_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			brief_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			article_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			publish_at DATETIME NOT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'planned',
			priority SMALLINT UNSIGNED NOT NULL DEFAULT 50,
			assigned_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			publishing_channel VARCHAR(60) NOT NULL DEFAULT 'blog',
			timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
			notes LONGTEXT NULL,
			roadmap_phase VARCHAR(40) NOT NULL DEFAULT 'now',
			color VARCHAR(20) NOT NULL DEFAULT '',
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY calendar_id (calendar_id),
			KEY project_id (project_id),
			KEY keyword_id (keyword_id),
			KEY brief_id (brief_id),
			KEY article_id (article_id),
			KEY publish_at (publish_at),
			KEY status (status),
			KEY assigned_user_id (assigned_user_id),
			KEY roadmap_phase (roadmap_phase)
		) {$charset};";

		$sql_queue = "CREATE TABLE {$queue} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			article_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			channel VARCHAR(60) NOT NULL DEFAULT 'blog',
			scheduled_at DATETIME NOT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'pending',
			attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			last_error TEXT NULL,
			meta LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY event_id (event_id),
			KEY project_id (project_id),
			KEY article_id (article_id),
			KEY scheduled_at (scheduled_at),
			KEY status (status)
		) {$charset};";

		dbDelta( $sql_calendars );
		dbDelta( $sql_events );
		dbDelta( $sql_queue );
	}

	/**
	 * Create SERP Intelligence table if missing. Idempotent.
	 */
	public static function migrate_serp_analyses_table(): void {
		if ( self::serp_analyses_table_ready() ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		self::install_serp_analyses_table();
	}

	public static function serp_analyses_table_ready(): bool {
		return self::table_exists( self::table_serp_analyses() );
	}

	public static function install_serp_analyses_table(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$table   = self::table_serp_analyses();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			query_text VARCHAR(255) NOT NULL DEFAULT '',
			language VARCHAR(20) NOT NULL DEFAULT '',
			country VARCHAR(8) NOT NULL DEFAULT '',
			search_intent TEXT NULL,
			recommended_article_type VARCHAR(120) NOT NULL DEFAULT '',
			recommended_word_count INT UNSIGNED NOT NULL DEFAULT 0,
			project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			keyword_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			brief_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			payload LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY query_text (query_text),
			KEY project_id (project_id),
			KEY keyword_id (keyword_id),
			KEY brief_id (brief_id),
			KEY user_id (user_id),
			KEY updated_at (updated_at)
		) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * Create Keyword Workspace tables if missing. Idempotent.
	 */
	public static function migrate_keywords_tables(): void {
		if ( self::keywords_tables_ready() ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		self::install_keywords_tables();
	}

	public static function keywords_tables_ready(): bool {
		return self::table_exists( self::table_keywords() )
			&& self::table_exists( self::table_keyword_clusters() )
			&& self::table_exists( self::table_keyword_notes() )
			&& self::table_exists( self::table_keyword_history() );
	}

	public static function install_keywords_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset  = $wpdb->get_charset_collate();
		$keywords = self::table_keywords();
		$clusters = self::table_keyword_clusters();
		$notes    = self::table_keyword_notes();
		$history  = self::table_keyword_history();

		$sql_clusters = "CREATE TABLE {$clusters} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			name VARCHAR(255) NOT NULL DEFAULT '',
			description TEXT NULL,
			color VARCHAR(20) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY project_id (project_id),
			KEY name (name(191))
		) {$charset};";

		$sql_keywords = "CREATE TABLE {$keywords} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			primary_keyword VARCHAR(255) NOT NULL DEFAULT '',
			intent VARCHAR(40) NOT NULL DEFAULT '',
			difficulty SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			priority SMALLINT UNSIGNED NOT NULL DEFAULT 50,
			status VARCHAR(30) NOT NULL DEFAULT 'idea',
			project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			brief_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			article_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			target_url VARCHAR(2048) NOT NULL DEFAULT '',
			cluster_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			parent_keyword_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			language VARCHAR(20) NOT NULL DEFAULT '',
			country VARCHAR(8) NOT NULL DEFAULT '',
			roadmap_phase VARCHAR(40) NOT NULL DEFAULT '',
			roadmap_order INT UNSIGNED NOT NULL DEFAULT 0,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY primary_keyword (primary_keyword),
			KEY status (status),
			KEY project_id (project_id),
			KEY brief_id (brief_id),
			KEY article_id (article_id),
			KEY cluster_id (cluster_id),
			KEY parent_keyword_id (parent_keyword_id),
			KEY priority (priority),
			KEY updated_at (updated_at)
		) {$charset};";

		$sql_notes = "CREATE TABLE {$notes} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			keyword_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			note LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY keyword_id (keyword_id),
			KEY created_at (created_at)
		) {$charset};";

		$sql_history = "CREATE TABLE {$history} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			keyword_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			action VARCHAR(60) NOT NULL DEFAULT '',
			field_name VARCHAR(60) NOT NULL DEFAULT '',
			old_value TEXT NULL,
			new_value TEXT NULL,
			message VARCHAR(500) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY keyword_id (keyword_id),
			KEY created_at (created_at),
			KEY action (action)
		) {$charset};";

		dbDelta( $sql_clusters );
		dbDelta( $sql_keywords );
		dbDelta( $sql_notes );
		dbDelta( $sql_history );
	}

	/**
	 * Create SEO Projects tables if missing. Idempotent.
	 */
	public static function migrate_projects_tables(): void {
		if ( self::projects_tables_ready() ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		self::install_projects_tables();
	}

	public static function projects_tables_ready(): bool {
		return self::table_exists( self::table_projects() )
			&& self::table_exists( self::table_project_members() )
			&& self::table_exists( self::table_project_statistics() );
	}

	private static function table_exists( string $table ): bool {
		global $wpdb;
		$like  = $wpdb->esc_like( $table );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		return $found === $table;
	}

	public static function install_projects_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$projects = self::table_projects();
		$members  = self::table_project_members();
		$stats    = self::table_project_statistics();
		$assets   = self::table_project_assets();
		$tasks    = self::table_project_tasks();
		$activity = self::table_project_activity();

		$sql_projects = "CREATE TABLE {$projects} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL DEFAULT '',
			description LONGTEXT NULL,
			target_country VARCHAR(8) NOT NULL DEFAULT '',
			language VARCHAR(20) NOT NULL DEFAULT '',
			niche VARCHAR(255) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			owner_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY owner_user_id (owner_user_id),
			KEY updated_at (updated_at),
			KEY name (name(191))
		) {$charset};";

		$sql_members = "CREATE TABLE {$members} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			role VARCHAR(40) NOT NULL DEFAULT 'member',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_project_user (project_id, user_id),
			KEY user_id (user_id),
			KEY role (role)
		) {$charset};";

		$sql_stats = "CREATE TABLE {$stats} (
			project_id BIGINT UNSIGNED NOT NULL,
			briefs_count INT UNSIGNED NOT NULL DEFAULT 0,
			keywords_count INT UNSIGNED NOT NULL DEFAULT 0,
			articles_count INT UNSIGNED NOT NULL DEFAULT 0,
			completion_pct SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			meta LONGTEXT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (project_id),
			KEY completion_pct (completion_pct),
			KEY updated_at (updated_at)
		) {$charset};";

		// Future-compatible asset links (briefs now; keywords/articles/clusters later).
		$sql_assets = "CREATE TABLE {$assets} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id BIGINT UNSIGNED NOT NULL,
			asset_type VARCHAR(40) NOT NULL,
			asset_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			title VARCHAR(255) NOT NULL DEFAULT '',
			meta LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_project_asset (project_id, asset_type, asset_id),
			KEY asset_type (asset_type),
			KEY asset_id (asset_id),
			KEY project_id (project_id)
		) {$charset};";

		$sql_tasks = "CREATE TABLE {$tasks} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(255) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			due_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY project_id (project_id),
			KEY status (status),
			KEY due_at (due_at)
		) {$charset};";

		$sql_activity = "CREATE TABLE {$activity} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			action VARCHAR(60) NOT NULL DEFAULT '',
			message VARCHAR(500) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY project_id (project_id),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta( $sql_projects );
		dbDelta( $sql_members );
		dbDelta( $sql_stats );
		dbDelta( $sql_assets );
		dbDelta( $sql_tasks );
		dbDelta( $sql_activity );
	}

	/**
	 * Create content briefs library table if missing. Idempotent.
	 */
	public static function migrate_content_briefs_table(): void {
		if ( self::content_briefs_table_exists() ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		self::install_content_briefs_table();
	}

	public static function content_briefs_table_exists(): bool {
		global $wpdb;
		$table = self::table_content_briefs();
		$like  = $wpdb->esc_like( $table );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		return $found === $table;
	}

	public static function install_content_briefs_table(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::table_content_briefs();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL DEFAULT '',
			topic VARCHAR(500) NOT NULL DEFAULT '',
			search_intent TEXT NULL,
			primary_keyword VARCHAR(255) NOT NULL DEFAULT '',
			secondary_keywords LONGTEXT NULL,
			long_tail_keywords LONGTEXT NULL,
			semantic_keywords LONGTEXT NULL,
			entities LONGTEXT NULL,
			faq LONGTEXT NULL,
			outline LONGTEXT NULL,
			meta_description TEXT NULL,
			slug VARCHAR(255) NOT NULL DEFAULT '',
			internal_links LONGTEXT NULL,
			external_links LONGTEXT NULL,
			schema_recommendation TEXT NULL,
			eeat LONGTEXT NULL,
			word_count INT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			payload LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY user_id (user_id),
			KEY primary_keyword (primary_keyword),
			KEY updated_at (updated_at),
			KEY title (title(191))
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Create recipe votes ledger if missing. Idempotent; does not alter existing rows.
	 */
	public static function migrate_recipe_votes_table(): void {
		if ( self::recipe_votes_table_exists() ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		self::install_recipe_votes_table();
	}

	public static function install_recipe_votes_table(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::table_recipe_votes();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			voter_key VARCHAR(128) NOT NULL,
			rating TINYINT UNSIGNED NOT NULL,
			ip_hash VARCHAR(64) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_vote (post_id, voter_key),
			KEY post_id (post_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	public static function recipe_votes_table_ready(): bool {
		return self::recipe_votes_table_exists();
	}

	private static function recipe_votes_table_exists(): bool {
		global $wpdb;
		$table = self::table_recipe_votes();
		$like  = $wpdb->esc_like( $table );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		return $found === $table;
	}

	/**
	 * Drop UNIQUE (from_post_id, to_post_id) which collapsed all external links
	 * (to_post_id = 0) to a single row per source post. Preserve all rows.
	 * Idempotent: checks index existence before ALTER.
	 */
	public static function migrate_link_graph_external_uniqueness(): void {
		global $wpdb;

		if ( ! self::link_graph_table_exists() ) {
			return;
		}

		$table = self::table_link_graph();

		if ( self::link_graph_has_index( 'uniq_internal' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is prefix-derived, not user input.
			$wpdb->query( "ALTER TABLE `{$table}` DROP INDEX `uniq_internal`" );
		}

		// Non-unique composite replaces lookup usefulness of the old unique index.
		if ( ! self::link_graph_has_index( 'from_to' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is prefix-derived, not user input.
			$wpdb->query( "ALTER TABLE `{$table}` ADD KEY `from_to` (from_post_id, to_post_id)" );
		}
	}

	private static function link_graph_table_exists(): bool {
		global $wpdb;
		$table = self::table_link_graph();
		$like  = $wpdb->esc_like( $table );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		return $found === $table;
	}

	private static function link_graph_has_index( string $key_name ): bool {
		global $wpdb;
		$table = self::table_link_graph();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is prefix-derived, not user input.
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW INDEX FROM `{$table}` WHERE Key_name = %s",
				$key_name
			),
			ARRAY_A
		);
		return ! empty( $rows );
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$link_graph = self::table_link_graph();
		$post_m     = self::table_post_metrics();
		$sugg       = self::table_link_suggestions();
		$queue      = self::table_bulk_queue();
		$votes      = self::table_recipe_votes();
		$briefs     = self::table_content_briefs();

		$sql1 = "CREATE TABLE {$link_graph} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			from_post_id BIGINT UNSIGNED NOT NULL,
			to_post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			url VARCHAR(2048) NOT NULL,
			final_url VARCHAR(2048) NULL,
			anchor_text VARCHAR(255) NULL,
			link_type VARCHAR(20) NOT NULL DEFAULT 'internal',
			http_status SMALLINT NULL,
			redirect_hops SMALLINT NULL,
			last_checked DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY from_post_id (from_post_id),
			KEY to_post_id (to_post_id),
			KEY from_to (from_post_id, to_post_id),
			KEY link_type (link_type),
			KEY http_status (http_status),
			KEY redirect_hops (redirect_hops),
			UNIQUE KEY uniq_external (from_post_id, url(191))
		) {$charset_collate};";

		$sql2 = "CREATE TABLE {$post_m} (
			post_id BIGINT UNSIGNED NOT NULL,
			post_type VARCHAR(40) NOT NULL,
			post_status VARCHAR(40) NOT NULL,
			word_count INT UNSIGNED NOT NULL DEFAULT 0,
			internal_out INT UNSIGNED NOT NULL DEFAULT 0,
			internal_in INT UNSIGNED NOT NULL DEFAULT 0,
			has_featured TINYINT(1) NOT NULL DEFAULT 0,
			missing_meta_desc TINYINT(1) NOT NULL DEFAULT 0,
			missing_featured TINYINT(1) NOT NULL DEFAULT 0,
			missing_h2 TINYINT(1) NOT NULL DEFAULT 0,
			missing_h3 TINYINT(1) NOT NULL DEFAULT 0,
			missing_alt_images INT UNSIGNED NOT NULL DEFAULT 0,
			low_internal_links TINYINT(1) NOT NULL DEFAULT 0,
			missing_external_links TINYINT(1) NOT NULL DEFAULT 0,
			thin_content TINYINT(1) NOT NULL DEFAULT 0,
			orphan TINYINT(1) NOT NULL DEFAULT 0,
			seo_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			last_scanned DATETIME NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (post_id),
			KEY post_type (post_type),
			KEY post_status (post_status),
			KEY orphan (orphan),
			KEY thin_content (thin_content),
			KEY missing_meta_desc (missing_meta_desc),
			KEY missing_h2 (missing_h2),
			KEY missing_h3 (missing_h3),
			KEY missing_external_links (missing_external_links),
			KEY low_internal_links (low_internal_links),
			KEY seo_score (seo_score),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		$sql3 = "CREATE TABLE {$sugg} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			suggested_post_id BIGINT UNSIGNED NOT NULL,
			score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			reasons TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_pair (post_id, suggested_post_id),
			KEY score (score),
			KEY post_id (post_id),
			KEY suggested_post_id (suggested_post_id)
		) {$charset_collate};";

		$sql4 = "CREATE TABLE {$queue} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			task_type VARCHAR(40) NOT NULL DEFAULT 'fix_with_ai',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			last_error TEXT NULL,
			payload LONGTEXT NULL,
			locked_at DATETIME NULL,
			processed_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_post_task (post_id, task_type),
			KEY status (status),
			KEY locked_at (locked_at),
			KEY processed_at (processed_at)
		) {$charset_collate};";

		$sql5 = "CREATE TABLE {$votes} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			voter_key VARCHAR(128) NOT NULL,
			rating TINYINT UNSIGNED NOT NULL,
			ip_hash VARCHAR(64) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_vote (post_id, voter_key),
			KEY post_id (post_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql6 = "CREATE TABLE {$briefs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL DEFAULT '',
			topic VARCHAR(500) NOT NULL DEFAULT '',
			search_intent TEXT NULL,
			primary_keyword VARCHAR(255) NOT NULL DEFAULT '',
			secondary_keywords LONGTEXT NULL,
			long_tail_keywords LONGTEXT NULL,
			semantic_keywords LONGTEXT NULL,
			entities LONGTEXT NULL,
			faq LONGTEXT NULL,
			outline LONGTEXT NULL,
			meta_description TEXT NULL,
			slug VARCHAR(255) NOT NULL DEFAULT '',
			internal_links LONGTEXT NULL,
			external_links LONGTEXT NULL,
			schema_recommendation TEXT NULL,
			eeat LONGTEXT NULL,
			word_count INT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			payload LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY user_id (user_id),
			KEY primary_keyword (primary_keyword),
			KEY updated_at (updated_at),
			KEY title (title(191))
		) {$charset_collate};";

		dbDelta( $sql1 );
		dbDelta( $sql2 );
		dbDelta( $sql3 );
		dbDelta( $sql4 );
		dbDelta( $sql5 );
		dbDelta( $sql6 );
		self::install_projects_tables();
		self::install_keywords_tables();
		self::install_serp_analyses_table();
		self::install_calendar_tables();
		self::install_optimizer_tables();
		self::install_recipe_builder_tables();
		self::install_recipe_ai_tables();
		self::install_ai_logs_table();
	}

	public static function now_gmt_sql(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}
}
