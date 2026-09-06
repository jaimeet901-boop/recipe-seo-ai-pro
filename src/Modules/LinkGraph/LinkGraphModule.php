<?php
declare(strict_types=1);

/**
 * Link Graph module skeleton (Phase 1).
 *
 * Legacy runtime owner(s): RSAIP_Link_Graph
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\LinkGraph;

use RecipeSeoAiPro\Modules\AbstractModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LinkGraphModule
 */
final class LinkGraphModule extends AbstractModule {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'link_graph';
	}
}
