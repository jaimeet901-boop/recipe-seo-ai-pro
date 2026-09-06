<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
	wp_clear_scheduled_hook( 'rsaip_cron_rebuild_link_graph' );
	wp_clear_scheduled_hook( 'rsaip_cron_check_broken_links' );
	wp_clear_scheduled_hook( 'rsaip_cron_process_bulk_queue' );
}

$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_link_graph' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_post_metrics' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_link_suggestions' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_bulk_queue' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_recipe_votes' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_content_briefs' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_project_activity' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_project_tasks' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_project_assets' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_project_statistics' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_project_members' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_projects' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_keyword_history' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_keyword_notes' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_keywords' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_keyword_clusters' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_serp_analyses' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_publishing_queue' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_calendar_events' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_content_calendars' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_content_versions' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_content_optimizations' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_rb_steps' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_rb_ingredients' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_rb_sections' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_rb_recipes' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_recipe_ai_versions' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsaip_recipe_ai_runs' );

delete_option( 'rsaip_settings' );
delete_option( 'rsaip_db_version' );
delete_transient( 'rsaip_dashboard_stats' );
delete_transient( 'rsaip_gsc_token' );
delete_transient( 'rsaip_gsc_indexed_posts' );
