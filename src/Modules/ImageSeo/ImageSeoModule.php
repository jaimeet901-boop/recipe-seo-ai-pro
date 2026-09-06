<?php
declare(strict_types=1);

/**
 * Image SEO module skeleton (Phase 1).
 *
 * Legacy runtime owner(s): RSAIP_Image_Optimizer
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\ImageSeo;

use RecipeSeoAiPro\Modules\AbstractModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ImageSeoModule
 */
final class ImageSeoModule extends AbstractModule {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'image_seo';
	}
}
