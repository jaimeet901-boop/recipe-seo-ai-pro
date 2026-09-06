<?php
declare(strict_types=1);

/**
 * Workflow module skeleton (Phase 1).
 *
 * Future multi-step SEO workflows. No legacy class yet; empty boundary only.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Workflow;

use RecipeSeoAiPro\Modules\AbstractModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WorkflowModule
 */
final class WorkflowModule extends AbstractModule {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'workflow';
	}
}
