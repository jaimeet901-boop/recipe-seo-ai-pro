<?php
declare(strict_types=1);

/**
 * Recipe Optimizer module skeleton (Phase 1).
 *
 * Legacy runtime owner(s): RSAIP_Recipe_Optimizer, public rating AJAX
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Recipe;

use RecipeSeoAiPro\Modules\AbstractModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeModule
 */
final class RecipeModule extends AbstractModule {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'recipe';
	}
}
