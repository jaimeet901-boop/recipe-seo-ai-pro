<?php
declare(strict_types=1);

/**
 * Bulk Optimizer module skeleton (Phase 1).
 *
 * Legacy runtime owner(s): RSAIP_Bulk_Optimizer
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Bulk;

use RecipeSeoAiPro\Modules\AbstractModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BulkModule
 */
final class BulkModule extends AbstractModule {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'bulk';
	}
}
