<?php
/**
 * Plugin Name: Recipe SEO AI Pro
 * Plugin URI: https://example.com/recipe-seo-ai-pro
 * Description: AI-powered SEO tools for recipe and content websites.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Recipe SEO AI Pro
 * License: GPL-2.0+
 * Text Domain: recipe-seo-ai-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'RSAIP_PLUGIN_DIR' ) ) {
	define( 'RSAIP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'RSAIP_PLUGIN_URL' ) ) {
	define( 'RSAIP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'RSAIP_PLUGIN_FILE' ) ) {
	define( 'RSAIP_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'RSAIP_PLUGIN_BASENAME' ) ) {
	define( 'RSAIP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'RSAIP_VERSION' ) ) {
	define( 'RSAIP_VERSION', '1.0.0' );
}

/*
 * Modern architecture autoload must load before helpers.php so settings facades
 * can resolve RecipeSeoAiPro\Modules\Settings\* during activation and runtime.
 * Composer vendor/autoload.php is preferred when present; otherwise src/Autoloader.php.
 */
require_once RSAIP_PLUGIN_DIR . 'src/Autoloader.php';
\RecipeSeoAiPro\Autoloader::bootstrap( RSAIP_PLUGIN_DIR );

require_once RSAIP_PLUGIN_DIR . 'includes/helpers.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-db.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-link-graph.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-internal-link-suggester.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-auto-linker.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-audit.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-image-optimizer.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-gsc.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-schema-validator.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-recipe-optimizer.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-sitemap-auditor.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-performance.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-ai.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-bulk-optimizer.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-export-pdf.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-export-xlsx.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-reports.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-plugin.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-admin.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-ajax.php';
require_once RSAIP_PLUGIN_DIR . 'includes/class-rsaip-activator.php';

register_activation_hook( __FILE__, array( 'RSAIP_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RSAIP_Activator', 'deactivate' ) );

/**
 * Bootstrap legacy runtime and the modern architecture skeleton.
 *
 * Order: DB upgrade → modern Plugin::boot() (no-op modules) → legacy RSAIP_Plugin::init().
 * Modern boot must not register competing hooks in Phase 1.
 */
function rsaip_bootstrap_plugin(): void {
	try {
		if ( class_exists( 'RSAIP_DB' ) ) {
			RSAIP_DB::maybe_upgrade();
		}
	} catch ( \Throwable $e ) {
		// Keep the site up if a migration step fails; retry on next request.
	}

	try {
		if ( class_exists( \RecipeSeoAiPro\Plugin::class ) ) {
			\RecipeSeoAiPro\Plugin::instance()->boot();
		}
	} catch ( \Throwable $e ) {
		// Modern module boot must never white-screen wp-admin.
	}

	try {
		if ( class_exists( 'RSAIP_Plugin' ) ) {
			RSAIP_Plugin::instance()->init();
		}
	} catch ( \Throwable $e ) {
		// Legacy init failure should not take down the whole site.
	}
}

/**
 * Accessor for the modern DI-backed application (Phase 1 skeleton).
 * Legacy code does not depend on this yet.
 *
 * @return \RecipeSeoAiPro\Plugin|null
 */
function rsaip_app() {
	if ( ! class_exists( \RecipeSeoAiPro\Plugin::class ) ) {
		return null;
	}
	return \RecipeSeoAiPro\Plugin::instance();
}

add_action( 'plugins_loaded', 'rsaip_bootstrap_plugin' );
