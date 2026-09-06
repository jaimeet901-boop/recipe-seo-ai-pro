<?php
declare(strict_types=1);

/**
 * Internal Linking module skeleton (Phase 1).
 *
 * Legacy runtime owner(s): RSAIP_Internal_Link_Suggester, RSAIP_Auto_Linker
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\InternalLinking;

use RecipeSeoAiPro\Modules\AbstractModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class InternalLinkingModule
 */
final class InternalLinkingModule extends AbstractModule {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'internal_linking';
	}
}
