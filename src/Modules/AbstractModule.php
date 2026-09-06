<?php
declare(strict_types=1);

/**
 * Shared empty-module base for Phase 1 skeletons.
 *
 * Subclasses only declare id(). register()/boot() stay no-ops so the modern
 * bootstrap cannot change WordPress runtime behavior.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules;

use RecipeSeoAiPro\Contracts\ModuleInterface;
use RecipeSeoAiPro\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AbstractModule
 */
abstract class AbstractModule implements ModuleInterface {

	/**
	 * @inheritDoc
	 */
	public function register( Container $container ): void {
		// Phase 1: no bindings. Legacy services remain constructed in RSAIP_Plugin.
	}

	/**
	 * @inheritDoc
	 */
	public function boot( Container $container ): void {
		// Phase 1: no hooks. Legacy RSAIP_* classes own all WP integrations.
	}
}
