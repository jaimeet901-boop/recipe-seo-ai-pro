<?php
/**
 * Context sidebar (UI only).
 *
 * @var string $current_page
 * @var bool   $ai_ready
 * @var string $ai_provider_label
 * @var string $status_label
 *
 * @package RecipeSeoAiPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_page       = isset( $current_page ) ? sanitize_key( (string) $current_page ) : '';
$ai_ready           = ! empty( $ai_ready );
$ai_provider_label  = isset( $ai_provider_label ) ? (string) $ai_provider_label : __( 'Not set', 'recipe-seo-ai-pro' );
$status_label       = isset( $status_label ) ? (string) $status_label : ( $ai_ready ? __( 'Ready', 'recipe-seo-ai-pro' ) : __( 'Not Configured', 'recipe-seo-ai-pro' ) );

$tips = array(
	'rsaip-dashboard'           => __( 'Rebuild the link graph weekly to keep orphan and suggestion data fresh.', 'recipe-seo-ai-pro' ),
	'rsaip-content-optimizer'   => __( 'Run Analyze first, then a workflow. Apply always creates a backup.', 'recipe-seo-ai-pro' ),
	'rsaip-keyword-workspace'   => __( 'Cluster keywords by intent before assigning briefs and articles.', 'recipe-seo-ai-pro' ),
	'rsaip-serp-intelligence'   => __( 'Save SERP analyses to a project so briefs inherit competitor gaps.', 'recipe-seo-ai-pro' ),
	'rsaip-content-brief'       => __( 'Attach a keyword and SERP analysis before generating the brief.', 'recipe-seo-ai-pro' ),
	'rsaip-recipe-builder'      => __( 'Use sections for ingredient groups; preview servings before publishing.', 'recipe-seo-ai-pro' ),
	'rsaip-recipe-ai'           => __( 'Compare Original vs Optimized, then Apply with undo available.', 'recipe-seo-ai-pro' ),
	'rsaip-content-calendar'    => __( 'Switch Agenda and Queue views to manage the publishing pipeline.', 'recipe-seo-ai-pro' ),
	'rsaip-settings'            => __( 'Store API keys securely. Leave blank when saving to keep the existing secret.', 'recipe-seo-ai-pro' ),
	'rsaip-seo-workspace'       => __( 'Follow the workflow strip left-to-right for a complete content sprint.', 'recipe-seo-ai-pro' ),
);

$tip = isset( $tips[ $current_page ] )
	? $tips[ $current_page ]
	: __( 'Use Projects to connect keywords, briefs, SERP, calendar, and optimizer in one workspace.', 'recipe-seo-ai-pro' );

$quick = array(
	array( 'page' => 'rsaip-seo-workspace', 'label' => __( 'Open Workspace', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-networking' ),
	array( 'page' => 'rsaip-content-optimizer', 'label' => __( 'Optimize Content', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-performance' ),
	array( 'page' => 'rsaip-keyword-research', 'label' => __( 'Research Keywords', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-search' ),
	array( 'page' => 'rsaip-reports', 'label' => __( 'View Reports', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-chart-bar' ),
);
?>
<aside class="rsaip-app-sidebar" aria-label="<?php echo esc_attr__( 'Context', 'recipe-seo-ai-pro' ); ?>">
	<section class="rsaip-side-card">
		<h3 class="rsaip-side-card-title">
			<span class="dashicons dashicons-cloud" aria-hidden="true"></span>
			<?php echo esc_html__( 'AI Status', 'recipe-seo-ai-pro' ); ?>
		</h3>
		<div class="rsaip-side-stat">
			<span class="rsaip-side-stat-label"><?php echo esc_html__( 'Provider', 'recipe-seo-ai-pro' ); ?></span>
			<span class="rsaip-side-stat-value"><?php echo esc_html( $ai_provider_label ); ?></span>
		</div>
		<div class="rsaip-side-stat">
			<span class="rsaip-side-stat-label"><?php echo esc_html__( 'Status', 'recipe-seo-ai-pro' ); ?></span>
			<span class="rsaip-badge <?php echo $ai_ready ? 'rsaip-badge-ok' : 'rsaip-badge-warn'; ?>">
				<?php echo esc_html( $status_label ); ?>
			</span>
		</div>
		<?php if ( ! $ai_ready ) : ?>
			<a class="button button-secondary rsaip-side-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=rsaip-settings' ) ); ?>">
				<?php echo esc_html__( 'Configure AI', 'recipe-seo-ai-pro' ); ?>
			</a>
		<?php endif; ?>
	</section>

	<section class="rsaip-side-card">
		<h3 class="rsaip-side-card-title">
			<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
			<?php echo esc_html__( 'Tip', 'recipe-seo-ai-pro' ); ?>
		</h3>
		<p class="rsaip-side-tip"><?php echo esc_html( $tip ); ?></p>
	</section>

	<section class="rsaip-side-card">
		<h3 class="rsaip-side-card-title">
			<span class="dashicons dashicons-migrate" aria-hidden="true"></span>
			<?php echo esc_html__( 'Quick actions', 'recipe-seo-ai-pro' ); ?>
		</h3>
		<ul class="rsaip-side-links">
			<?php foreach ( $quick as $item ) : ?>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $item['page'] ) ); ?>">
						<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
						<?php echo esc_html( $item['label'] ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<section class="rsaip-side-card">
		<h3 class="rsaip-side-card-title">
			<span class="dashicons dashicons-clock" aria-hidden="true"></span>
			<?php echo esc_html__( 'Workflow', 'recipe-seo-ai-pro' ); ?>
		</h3>
		<ol class="rsaip-side-steps">
			<li><?php echo esc_html__( 'Research keywords', 'recipe-seo-ai-pro' ); ?></li>
			<li><?php echo esc_html__( 'Analyze SERP', 'recipe-seo-ai-pro' ); ?></li>
			<li><?php echo esc_html__( 'Build brief', 'recipe-seo-ai-pro' ); ?></li>
			<li><?php echo esc_html__( 'Write & optimize', 'recipe-seo-ai-pro' ); ?></li>
			<li><?php echo esc_html__( 'Schedule publish', 'recipe-seo-ai-pro' ); ?></li>
		</ol>
	</section>
</aside>
