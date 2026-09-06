<?php
declare(strict_types=1);

/**
 * Schema Validator module skeleton (Phase 1).
 *
 * Legacy runtime owner(s): RSAIP_Schema_Validator
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Schema;

use RecipeSeoAiPro\Modules\AbstractModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SchemaModule
 */
final class SchemaModule extends AbstractModule {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'schema';
	}
}
