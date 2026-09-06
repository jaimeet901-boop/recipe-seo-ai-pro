<?php
/**
 * Admin: SEO Dashboard.
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

<section class="rsaip-panel rsaip-fade-in" aria-label="<?php echo esc_attr__( 'Quick launch', 'recipe-seo-ai-pro' ); ?>">
	<h2><?php echo esc_html__( 'Quick launch', 'recipe-seo-ai-pro' ); ?></h2>
	<div class="rsaip-quick-launch">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsaip-seo-workspace' ) ); ?>">
			<span class="dashicons dashicons-networking" aria-hidden="true"></span>
			<?php echo esc_html__( 'Workspace', 'recipe-seo-ai-pro' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsaip-content-optimizer' ) ); ?>">
			<span class="dashicons dashicons-performance" aria-hidden="true"></span>
			<?php echo esc_html__( 'Optimizer', 'recipe-seo-ai-pro' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsaip-keyword-workspace' ) ); ?>">
			<span class="dashicons dashicons-tag" aria-hidden="true"></span>
			<?php echo esc_html__( 'Keywords', 'recipe-seo-ai-pro' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsaip-content-brief-library' ) ); ?>">
			<span class="dashicons dashicons-book" aria-hidden="true"></span>
			<?php echo esc_html__( 'Briefs', 'recipe-seo-ai-pro' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsaip-recipe-builder' ) ); ?>">
			<span class="dashicons dashicons-carrot" aria-hidden="true"></span>
			<?php echo esc_html__( 'Recipes', 'recipe-seo-ai-pro' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsaip-seo-projects' ) ); ?>">
			<span class="dashicons dashicons-portfolio" aria-hidden="true"></span>
			<?php echo esc_html__( 'Projects', 'recipe-seo-ai-pro' ); ?>
		</a>
	</div>
</section>

<section class="rsaip-panel rsaip-fade-in" aria-labelledby="rsaip-dash-health-heading">
	<div class="rsaip-actions rsaip-dash-toolbar">
		<h2 id="rsaip-dash-health-heading" class="rsaip-dash-heading"><?php echo esc_html__( 'SEO health KPIs', 'recipe-seo-ai-pro' ); ?></h2>
		<button type="button" class="button button-primary rsaip-btn" data-action="rsaip_get_dashboard_stats"><?php echo esc_html__( 'Refresh Dashboard', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button rsaip-btn" data-action="rsaip_rebuild_link_graph"><?php echo esc_html__( 'Rebuild Link Graph', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button rsaip-btn" data-action="rsaip_run_full_audit"><?php echo esc_html__( 'Run Full Audit', 'recipe-seo-ai-pro' ); ?></button>
	</div>
	<p class="rsaip-note"><?php echo esc_html__( 'Live site metrics from your content audit and link graph.', 'recipe-seo-ai-pro' ); ?></p>
	<?php
	View::render(
		'partials/cards',
		array(
			'id'        => 'rsaip-dashboard-cards',
			'data_load' => 'rsaip_get_dashboard_stats',
			'class'     => 'rsaip-cards rsaip-loading',
		)
	);
	?>
</section>

<div class="rsaip-kpi-grid rsaip-fade-in">
	<section class="rsaip-panel rsaip-kpi rsaip-kpi-accent-blue">
		<div class="rsaip-kpi-icon"><span class="dashicons dashicons-chart-area" aria-hidden="true"></span></div>
		<div class="rsaip-kpi-label"><?php echo esc_html__( 'Articles & optimization', 'recipe-seo-ai-pro' ); ?></div>
		<div class="rsaip-kpi-value"><?php echo esc_html__( 'Studio', 'recipe-seo-ai-pro' ); ?></div>
		<p class="rsaip-kpi-hint"><?php echo esc_html__( 'Open AI Studio and Content Optimizer to improve posts.', 'recipe-seo-ai-pro' ); ?></p>
		<p class="rsaip-actions rsaip-kpi-actions">
			<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rsaip-ai' ) ); ?>"><?php echo esc_html__( 'AI Studio', 'recipe-seo-ai-pro' ); ?></a>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rsaip-content-optimizer' ) ); ?>"><?php echo esc_html__( 'Optimize', 'recipe-seo-ai-pro' ); ?></a>
		</p>
	</section>
	<section class="rsaip-panel rsaip-kpi rsaip-kpi-accent-purple">
		<div class="rsaip-kpi-icon"><span class="dashicons dashicons-tag" aria-hidden="true"></span></div>
		<div class="rsaip-kpi-label"><?php echo esc_html__( 'Keywords & briefs', 'recipe-seo-ai-pro' ); ?></div>
		<div class="rsaip-kpi-value"><?php echo esc_html__( 'Research', 'recipe-seo-ai-pro' ); ?></div>
		<p class="rsaip-kpi-hint"><?php echo esc_html__( 'Grow clusters, run research, and generate briefs.', 'recipe-seo-ai-pro' ); ?></p>
		<p class="rsaip-actions rsaip-kpi-actions">
			<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rsaip-keyword-workspace' ) ); ?>"><?php echo esc_html__( 'Keywords', 'recipe-seo-ai-pro' ); ?></a>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rsaip-content-brief' ) ); ?>"><?php echo esc_html__( 'New Brief', 'recipe-seo-ai-pro' ); ?></a>
		</p>
	</section>
	<section class="rsaip-panel rsaip-kpi rsaip-kpi-accent-green">
		<div class="rsaip-kpi-icon"><span class="dashicons dashicons-carrot" aria-hidden="true"></span></div>
		<div class="rsaip-kpi-label"><?php echo esc_html__( 'Recipe library', 'recipe-seo-ai-pro' ); ?></div>
		<div class="rsaip-kpi-value"><?php echo esc_html__( 'Builder', 'recipe-seo-ai-pro' ); ?></div>
		<p class="rsaip-kpi-hint"><?php echo esc_html__( 'Build structured recipes and improve them with Recipe AI.', 'recipe-seo-ai-pro' ); ?></p>
		<p class="rsaip-actions rsaip-kpi-actions">
			<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rsaip-recipe-builder' ) ); ?>"><?php echo esc_html__( 'Builder', 'recipe-seo-ai-pro' ); ?></a>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rsaip-recipe-ai' ) ); ?>"><?php echo esc_html__( 'Recipe AI', 'recipe-seo-ai-pro' ); ?></a>
		</p>
	</section>
</div>

<div class="rsaip-grid rsaip-dash-split rsaip-fade-in">
	<section class="rsaip-panel">
		<h2><?php echo esc_html__( 'Recent activity', 'recipe-seo-ai-pro' ); ?></h2>
		<div class="rsaip-empty-state">
			<span class="dashicons dashicons-backup" aria-hidden="true"></span>
			<h3><?php echo esc_html__( 'Project activity lives here', 'recipe-seo-ai-pro' ); ?></h3>
			<p><?php echo esc_html__( 'Open a project dashboard to review recent tasks, attachments, and team activity.', 'recipe-seo-ai-pro' ); ?></p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rsaip-seo-projects' ) ); ?>"><?php echo esc_html__( 'Open Projects', 'recipe-seo-ai-pro' ); ?></a>
		</div>
	</section>
	<section class="rsaip-panel">
		<h2><?php echo esc_html__( 'Upcoming tasks', 'recipe-seo-ai-pro' ); ?></h2>
		<div class="rsaip-empty-state">
			<span class="dashicons dashicons-clock" aria-hidden="true"></span>
			<h3><?php echo esc_html__( 'No tasks on this screen yet', 'recipe-seo-ai-pro' ); ?></h3>
			<p><?php echo esc_html__( 'Schedule work in Projects or the Content Calendar publishing queue.', 'recipe-seo-ai-pro' ); ?></p>
			<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rsaip-content-calendar' ) ); ?>"><?php echo esc_html__( 'Open Calendar', 'recipe-seo-ai-pro' ); ?></a>
		</div>
	</section>
</div>

<?php
View::render(
	'partials/notice',
	array(
		'message'     => __( 'Tip: Run “Rebuild Link Graph” first for accurate orphan/internal link counts.', 'recipe-seo-ai-pro' ),
		'extra_class' => 'rsaip-alert rsaip-alert-info',
	)
);
View::render( 'partials/footer' );
