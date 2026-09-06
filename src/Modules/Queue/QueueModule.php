<?php
declare(strict_types=1);

/**
 * Queue module skeleton (Phase 1).
 *
 * Future unified job queue. Bulk queue remains in RSAIP_Bulk_Optimizer for now.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Queue;

use RecipeSeoAiPro\Modules\AbstractModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class QueueModule
 */
final class QueueModule extends AbstractModule {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'queue';
	}
}
