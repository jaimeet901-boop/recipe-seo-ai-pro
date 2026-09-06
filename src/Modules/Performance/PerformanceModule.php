<?php
declare(strict_types=1);

/**
 * Performance Analyzer module skeleton (Phase 1).
 *
 * Legacy runtime owner(s): RSAIP_Performance
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Performance;

use RecipeSeoAiPro\Modules\AbstractModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PerformanceModule
 */
final class PerformanceModule extends AbstractModule {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'performance';
	}
}
