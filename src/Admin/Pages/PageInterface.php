<?php
declare(strict_types=1);

/**
 * Contract for future admin page controllers.
 *
 * Phase 1: interface only. Pages still render via RSAIP_Admin::render_*.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface PageInterface
 */
interface PageInterface {

	/**
	 * Admin menu / page slug (must match existing rsaip-* slugs when migrated).
	 */
	public function slug(): string;

	/**
	 * Render the page. Not called in Phase 1.
	 */
	public function render(): void;
}
