<?php
declare(strict_types=1);

/**
 * Reports module skeleton (Phase 1).
 *
 * Legacy runtime owner(s): RSAIP_Reports, RSAIP_Export_PDF, RSAIP_Export_XLSX
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Reports;

use RecipeSeoAiPro\Modules\AbstractModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ReportsModule
 */
final class ReportsModule extends AbstractModule {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'reports';
	}
}
