<?php
declare(strict_types=1);

/**
 * Google Search Console module skeleton (Phase 1).
 *
 * Legacy runtime owner(s): RSAIP_GSC
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Gsc;

use RecipeSeoAiPro\Modules\AbstractModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GscModule
 */
final class GscModule extends AbstractModule {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'gsc';
	}
}
