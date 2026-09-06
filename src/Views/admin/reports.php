<?php
/**
 * Admin: Reports.
 *
 * @var string $title
 *
 * @package RecipeSeoAiPro
 */

use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

View::render( 'partials/header', array( 'title' => $title ) );
?>

<section class="rsaip-panel rsaip-reports-toolbar rsaip-fade-in" aria-label="<?php echo esc_attr__( 'Report controls', 'recipe-seo-ai-pro' ); ?>">
	<label class="rsaip-inline" for="rsaip-report-type">
		<span><?php echo esc_html__( 'Period', 'recipe-seo-ai-pro' ); ?></span>
		<select id="rsaip-report-type" class="rsaip-input" data-key="report_type">
			<option value="weekly"><?php echo esc_html__( 'Weekly', 'recipe-seo-ai-pro' ); ?></option>
			<option value="monthly"><?php echo esc_html__( 'Monthly', 'recipe-seo-ai-pro' ); ?></option>
		</select>
	</label>
	<button type="button" class="button button-primary rsaip-btn" data-action="rsaip_report_preview">
		<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
		<?php echo esc_html__( 'Preview', 'recipe-seo-ai-pro' ); ?>
	</button>
	<button type="button" class="button rsaip-btn" data-action="rsaip_report_export_csv"><?php echo esc_html__( 'Export CSV', 'recipe-seo-ai-pro' ); ?></button>
	<button type="button" class="button rsaip-btn" data-action="rsaip_report_export_xlsx"><?php echo esc_html__( 'Export Excel', 'recipe-seo-ai-pro' ); ?></button>
	<button type="button" class="button rsaip-btn" data-action="rsaip_report_export_pdf"><?php echo esc_html__( 'Export PDF', 'recipe-seo-ai-pro' ); ?></button>
</section>

<div class="rsaip-kpi-grid rsaip-fade-in">
	<section class="rsaip-panel rsaip-kpi rsaip-kpi-accent-blue">
		<div class="rsaip-kpi-icon"><span class="dashicons dashicons-chart-bar" aria-hidden="true"></span></div>
		<div class="rsaip-kpi-label"><?php echo esc_html__( 'Analytics', 'recipe-seo-ai-pro' ); ?></div>
		<div class="rsaip-kpi-value"><?php echo esc_html__( 'Preview', 'recipe-seo-ai-pro' ); ?></div>
		<p class="rsaip-kpi-hint"><?php echo esc_html__( 'Generate an on-screen preview before exporting.', 'recipe-seo-ai-pro' ); ?></p>
	</section>
	<section class="rsaip-panel rsaip-kpi rsaip-kpi-accent-green">
		<div class="rsaip-kpi-icon"><span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span></div>
		<div class="rsaip-kpi-label"><?php echo esc_html__( 'Exports', 'recipe-seo-ai-pro' ); ?></div>
		<div class="rsaip-kpi-value">CSV · XLSX · PDF</div>
		<p class="rsaip-kpi-hint"><?php echo esc_html__( 'Share reports with clients and stakeholders.', 'recipe-seo-ai-pro' ); ?></p>
	</section>
	<section class="rsaip-panel rsaip-kpi rsaip-kpi-accent-purple">
		<div class="rsaip-kpi-icon"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span></div>
		<div class="rsaip-kpi-label"><?php echo esc_html__( 'Cadence', 'recipe-seo-ai-pro' ); ?></div>
		<div class="rsaip-kpi-value"><?php echo esc_html__( 'Weekly / Monthly', 'recipe-seo-ai-pro' ); ?></div>
		<p class="rsaip-kpi-hint"><?php echo esc_html__( 'Choose the reporting window that matches your workflow.', 'recipe-seo-ai-pro' ); ?></p>
	</section>
</div>

<div id="rsaip-report-preview" class="rsaip-panel rsaip-fade-in" aria-live="polite"></div>
<?php
View::render( 'partials/footer' );
