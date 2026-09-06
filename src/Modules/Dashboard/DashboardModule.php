<?php
declare(strict_types=1);

/**
 * Dashboard module skeleton (Phase 1).
 *
 * Legacy runtime owner(s): RSAIP_Admin::render_dashboard, RSAIP_Audit::get_dashboard_stats
 *
 * No business logic is registered here yet. This class exists so the modern
 * Plugin bootstrap can discover and load the module boundary without changing
 * plugin behavior.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Dashboard;

use RecipeSeoAiPro\Modules\AbstractModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DashboardModule
 */
final class DashboardModule extends AbstractModule {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'dashboard';
	}
}
