<?php
declare(strict_types=1);

/**
 * Sitemap Auditor module skeleton (Phase 1).
 *
 * Legacy runtime owner(s): RSAIP_Sitemap_Auditor
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Sitemap;

use RecipeSeoAiPro\Modules\AbstractModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SitemapModule
 */
final class SitemapModule extends AbstractModule {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'sitemap';
	}
}
