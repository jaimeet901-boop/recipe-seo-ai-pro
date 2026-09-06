<?php
declare(strict_types=1);

/**
 * SEO Audit module skeleton (Phase 1).
 *
 * Legacy runtime owner(s): RSAIP_Audit
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Audit;

use RecipeSeoAiPro\Modules\AbstractModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AuditModule
 */
final class AuditModule extends AbstractModule {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'audit';
	}
}
