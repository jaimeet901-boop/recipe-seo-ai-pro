<?php
declare(strict_types=1);

/**
 * Automatic updates skeleton (Phase 1).
 *
 * Does not hook into pre_set_site_transient_update_plugins. Update behavior
 * remains whatever WordPress / the host already provides.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Updates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UpdateService
 */
final class UpdateService {

	/**
	 * Placeholder — no remote check in Phase 1.
	 */
	public function check(): void {
		// No-op.
	}
}
