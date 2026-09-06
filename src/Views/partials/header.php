<?php
/**
 * Unified premium admin shell header.
 *
 * Opens .wrap.rsaip-wrap + app chrome. Closed by partials/footer.
 *
 * @var string      $title         Page title.
 * @var string|null $description   Optional subtitle (auto-mapped by page when empty).
 * @var string|null $breadcrumb    Optional breadcrumb label override.
 * @var bool|null   $hide_sidebar  Hide context sidebar (dense tools).
 * @var string|null $project_name  Optional project badge.
 * @var string|null $ai_provider   Optional provider key override.
 * @var bool|null   $ai_ready      Optional AI ready override.
 *
 * @package RecipeSeoAiPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title        = isset( $title ) ? (string) $title : '';
$description  = isset( $description ) ? (string) $description : '';
$breadcrumb   = isset( $breadcrumb ) ? (string) $breadcrumb : '';
$hide_sidebar = ! empty( $hide_sidebar );
$project_name = isset( $project_name ) ? (string) $project_name : '';
$current_page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$descriptions = array(
	'rsaip-dashboard'             => __( 'Monitor SEO health, link graph coverage, and daily optimization opportunities.', 'recipe-seo-ai-pro' ),
	'rsaip-seo-workspace'         => __( 'Your command center for the full SEO content workflow.', 'recipe-seo-ai-pro' ),
	'rsaip-seo-projects'          => __( 'Organize keywords, briefs, tasks, and assets by project.', 'recipe-seo-ai-pro' ),
	'rsaip-keyword-workspace'     => __( 'Manage clusters, roadmap phases, priorities, and keyword status.', 'recipe-seo-ai-pro' ),
	'rsaip-keyword-research'      => __( 'Discover keyword opportunities with AI-assisted research.', 'recipe-seo-ai-pro' ),
	'rsaip-serp-intelligence'     => __( 'Analyze search results, entities, and content gaps.', 'recipe-seo-ai-pro' ),
	'rsaip-content-brief'         => __( 'Generate structured content briefs ready for writers.', 'recipe-seo-ai-pro' ),
	'rsaip-content-brief-library' => __( 'Browse, filter, and export saved content briefs.', 'recipe-seo-ai-pro' ),
	'rsaip-ai'                    => __( 'Titles, FAQs, articles, recipes, and bulk AI workflows.', 'recipe-seo-ai-pro' ),
	'rsaip-ai-providers'          => __( 'Connect providers, test health, manage models, and monitor AI usage.', 'recipe-seo-ai-pro' ),
	'rsaip-content-optimizer'     => __( 'Analyze and rewrite content with scored SEO workflows.', 'recipe-seo-ai-pro' ),
	'rsaip-content-calendar'      => __( 'Plan, reschedule, and queue content for publishing.', 'recipe-seo-ai-pro' ),
	'rsaip-recipe-builder'        => __( 'Build structured recipes with ingredients, steps, and live preview.', 'recipe-seo-ai-pro' ),
	'rsaip-recipe-ai'             => __( 'Improve recipes with AI assistants, compare, and apply safely.', 'recipe-seo-ai-pro' ),
	'rsaip-internal-linking'      => __( 'Generate high-quality internal link suggestions.', 'recipe-seo-ai-pro' ),
	'rsaip-auto-linking'          => __( 'Control automatic internal link insertion rules.', 'recipe-seo-ai-pro' ),
	'rsaip-orphans'               => __( 'Find posts with weak or missing internal link coverage.', 'recipe-seo-ai-pro' ),
	'rsaip-audit'                 => __( 'Run technical and on-page SEO audits across your content.', 'recipe-seo-ai-pro' ),
	'rsaip-image-seo'             => __( 'Improve image alt text and media SEO signals.', 'recipe-seo-ai-pro' ),
	'rsaip-gsc'                   => __( 'Connect Search Console and surface ranking opportunities.', 'recipe-seo-ai-pro' ),
	'rsaip-low-hanging'           => __( 'Prioritize pages close to page-one rankings.', 'recipe-seo-ai-pro' ),
	'rsaip-recipe-optimizer'      => __( 'Optimize recipe posts for rich results and SEO.', 'recipe-seo-ai-pro' ),
	'rsaip-schema'                => __( 'Validate and improve structured data markup.', 'recipe-seo-ai-pro' ),
	'rsaip-sitemap'               => __( 'Audit XML sitemap coverage and missing URLs.', 'recipe-seo-ai-pro' ),
	'rsaip-performance'           => __( 'Review performance signals that affect rankings.', 'recipe-seo-ai-pro' ),
	'rsaip-reports'               => __( 'Export SEO reports as CSV, XLSX, or PDF.', 'recipe-seo-ai-pro' ),
	'rsaip-settings'              => __( 'Configure AI providers, linking rules, and plugin options.', 'recipe-seo-ai-pro' ),
);

if ( $description === '' && isset( $descriptions[ $current_page ] ) ) {
	$description = $descriptions[ $current_page ];
}

$wide_pages = array(
	'rsaip-recipe-builder',
	'rsaip-recipe-ai',
	'rsaip-content-calendar',
	'rsaip-keyword-workspace',
	'rsaip-content-optimizer',
	'rsaip-seo-projects',
	'rsaip-ai',
);
if ( in_array( $current_page, $wide_pages, true ) ) {
	$hide_sidebar = true;
}

$settings = function_exists( 'rsaip_get_settings' ) ? rsaip_get_settings() : array();
$provider = isset( $ai_provider ) ? sanitize_key( (string) $ai_provider ) : ( isset( $settings['ai_provider'] ) ? sanitize_key( (string) $settings['ai_provider'] ) : '' );
$endpoint = isset( $settings['ai_endpoint'] ) ? trim( (string) $settings['ai_endpoint'] ) : '';
$model    = isset( $settings['ai_model'] ) ? trim( (string) $settings['ai_model'] ) : '';
$has_key  = function_exists( 'rsaip_setting_has_secret' )
	? rsaip_setting_has_secret( $settings, 'ai_api_key' )
	: ( isset( $settings['ai_api_key'] ) && trim( (string) $settings['ai_api_key'] ) !== '' );

if ( ! isset( $ai_ready ) ) {
	$ai_ready = ( $provider === 'openai_compatible' ) && $endpoint !== '' && $model !== '' && $has_key;
}

$provider_label = $provider;
if ( $provider_label === '' ) {
	$provider_label = __( 'None', 'recipe-seo-ai-pro' );
} elseif ( $provider_label === 'openai_compatible' ) {
	$provider_label = __( 'OpenAI Compatible', 'recipe-seo-ai-pro' );
}

$status_label = ! empty( $ai_ready ) ? __( 'Ready', 'recipe-seo-ai-pro' ) : __( 'Not Configured', 'recipe-seo-ai-pro' );
$crumb_label  = $breadcrumb !== '' ? $breadcrumb : $title;

$shell_class = 'rsaip-app-shell';
if ( $hide_sidebar ) {
	$shell_class .= ' rsaip-app-shell--wide';
}

$layout_class = 'rsaip-wrap rsaip-app';
?>
<div class="wrap <?php echo esc_attr( $layout_class ); ?>">
	<div class="<?php echo esc_attr( $shell_class ); ?>">
		<?php
		\RecipeSeoAiPro\Views\View::render(
			'partials/app-nav',
			array( 'current_page' => $current_page )
		);
		?>
		<div class="rsaip-app-main">
			<header class="rsaip-page-header">
				<nav class="rsaip-breadcrumb" aria-label="<?php echo esc_attr__( 'Breadcrumb', 'recipe-seo-ai-pro' ); ?>">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsaip-dashboard' ) ); ?>"><?php echo esc_html__( 'Recipe SEO AI Pro', 'recipe-seo-ai-pro' ); ?></a>
					<span class="rsaip-breadcrumb-sep" aria-hidden="true">/</span>
					<span class="rsaip-breadcrumb-current"><?php echo esc_html( $crumb_label ); ?></span>
				</nav>
				<div class="rsaip-page-header-row">
					<div class="rsaip-page-header-copy">
						<h1 class="rsaip-page-title"><?php echo esc_html( $title ); ?></h1>
						<?php if ( $description !== '' ) : ?>
							<p class="rsaip-page-desc"><?php echo esc_html( $description ); ?></p>
						<?php endif; ?>
					</div>
					<div class="rsaip-page-header-meta">
						<span class="rsaip-badge rsaip-badge-provider">
							<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
							<?php echo esc_html( $provider_label ); ?>
						</span>
						<?php if ( $project_name !== '' ) : ?>
							<span class="rsaip-badge rsaip-badge-project">
								<span class="dashicons dashicons-portfolio" aria-hidden="true"></span>
								<?php echo esc_html( $project_name ); ?>
							</span>
						<?php endif; ?>
						<span class="rsaip-badge <?php echo ! empty( $ai_ready ) ? 'rsaip-badge-ok' : 'rsaip-badge-warn'; ?>">
							<span class="dashicons <?php echo ! empty( $ai_ready ) ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
							<?php echo esc_html( $status_label ); ?>
						</span>
						<a class="button button-secondary rsaip-header-action" href="<?php echo esc_url( admin_url( 'admin.php?page=rsaip-settings' ) ); ?>">
							<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
							<?php echo esc_html__( 'Settings', 'recipe-seo-ai-pro' ); ?>
						</a>
					</div>
				</div>
			</header>
			<div class="rsaip-app-content">
