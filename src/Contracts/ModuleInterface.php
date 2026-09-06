<?php
declare(strict_types=1);

/**
 * Contract for application modules registered with the modern bootstrap.
 *
 * Phase 1: modules are empty skeletons. Business logic stays in legacy
 * RSAIP_* classes until a later phase migrates it behind this interface.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Contracts;

use RecipeSeoAiPro\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface ModuleInterface
 */
interface ModuleInterface {

	/**
	 * Stable module identifier (e.g. "dashboard", "ai").
	 */
	public static function id(): string;

	/**
	 * Register bindings on the DI container. Do not attach WordPress hooks here.
	 */
	public function register( Container $container ): void;

	/**
	 * Attach WordPress hooks / runtime behavior after all modules registered.
	 * Phase 1 implementations must remain no-ops to preserve behavior.
	 */
	public function boot( Container $container ): void;
}
