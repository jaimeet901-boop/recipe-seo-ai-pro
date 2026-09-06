<?php
declare(strict_types=1);

/**
 * Licensing skeleton (Phase 1).
 *
 * Not registered with WordPress. No license checks run. Existing installs
 * continue with full current feature access.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LicenseService
 */
final class LicenseService {

	/**
	 * Placeholder status. Always "unlicensed_local" in Phase 1 (no gating).
	 */
	public function status(): string {
		return 'unlicensed_local';
	}
}
