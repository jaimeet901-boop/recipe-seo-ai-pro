<?php
declare(strict_types=1);

/**
 * Templates module skeleton (Phase 1).
 *
 * Future content/recipe templates. No legacy class yet; empty boundary only.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Templates;

use RecipeSeoAiPro\Modules\AbstractModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TemplatesModule
 */
final class TemplatesModule extends AbstractModule {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'templates';
	}
}
