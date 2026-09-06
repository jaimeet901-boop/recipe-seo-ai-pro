<?php
declare(strict_types=1);

/**
 * Future SaaS client skeleton (Phase 1).
 *
 * No network calls. Local-only operation is unchanged.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\SaaS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SaasClient
 */
final class SaasClient {

	/**
	 * Whether cloud mode is enabled. Always false in Phase 1.
	 */
	public function is_connected(): bool {
		return false;
	}
}
