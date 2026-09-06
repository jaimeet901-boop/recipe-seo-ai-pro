<?php
/**
 * In-app left navigation (UI only).
 *
 * @var string $current_page Current rsaip-* page slug.
 *
 * @package RecipeSeoAiPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_page = isset( $current_page ) ? sanitize_key( (string) $current_page ) : '';

$groups = array(
	array(
		'label' => __( 'Overview', 'recipe-seo-ai-pro' ),
		'items' => array(
			array( 'page' => 'rsaip-dashboard', 'label' => __( 'Dashboard', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-dashboard' ),
			array( 'page' => 'rsaip-seo-workspace', 'label' => __( 'SEO Workspace', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-networking' ),
			array( 'page' => 'rsaip-seo-projects', 'label' => __( 'Projects', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-portfolio' ),
		),
	),
	array(
		'label' => __( 'Research', 'recipe-seo-ai-pro' ),
		'items' => array(
			array( 'page' => 'rsaip-keyword-workspace', 'label' => __( 'Keywords', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-tag' ),
			array( 'page' => 'rsaip-keyword-research', 'label' => __( 'Keyword Research', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-search' ),
			array( 'page' => 'rsaip-serp-intelligence', 'label' => __( 'SERP Intelligence', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-chart-area' ),
			array( 'page' => 'rsaip-content-brief', 'label' => __( 'Content Brief', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-media-text' ),
			array( 'page' => 'rsaip-content-brief-library', 'label' => __( 'Brief Library', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-book' ),
		),
	),
	array(
		'label' => __( 'Content AI', 'recipe-seo-ai-pro' ),
		'items' => array(
			array( 'page' => 'rsaip-ai-providers', 'label' => __( 'AI Providers', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-cloud' ),
			array( 'page' => 'rsaip-ai', 'label' => __( 'AI Studio', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-admin-customizer' ),
			array( 'page' => 'rsaip-content-optimizer', 'label' => __( 'Content Optimizer', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-performance' ),
			array( 'page' => 'rsaip-content-calendar', 'label' => __( 'Content Calendar', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-calendar-alt' ),
			array( 'page' => 'rsaip-recipe-builder', 'label' => __( 'Recipe Builder', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-carrot' ),
			array( 'page' => 'rsaip-recipe-ai', 'label' => __( 'Recipe AI', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-carrot' ),
		),
	),
	array(
		'label' => __( 'SEO Tools', 'recipe-seo-ai-pro' ),
		'items' => array(
			array( 'page' => 'rsaip-internal-linking', 'label' => __( 'Internal Linking', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-admin-links' ),
			array( 'page' => 'rsaip-auto-linking', 'label' => __( 'Auto Linking', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-randomize' ),
			array( 'page' => 'rsaip-orphans', 'label' => __( 'Orphan Posts', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-editor-unlink' ),
			array( 'page' => 'rsaip-audit', 'label' => __( 'SEO Audit', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-yes-alt' ),
			array( 'page' => 'rsaip-image-seo', 'label' => __( 'Image SEO', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-format-image' ),
			array( 'page' => 'rsaip-gsc', 'label' => __( 'Search Console', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-chart-bar' ),
			array( 'page' => 'rsaip-low-hanging', 'label' => __( 'Low Hanging Fruits', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-star-filled' ),
			array( 'page' => 'rsaip-recipe-optimizer', 'label' => __( 'Recipe Optimizer', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-clipboard' ),
			array( 'page' => 'rsaip-schema', 'label' => __( 'Schema', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-editor-code' ),
			array( 'page' => 'rsaip-sitemap', 'label' => __( 'Sitemap Auditor', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-networking' ),
			array( 'page' => 'rsaip-performance', 'label' => __( 'Performance', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-dashboard' ),
		),
	),
	array(
		'label' => __( 'System', 'recipe-seo-ai-pro' ),
		'items' => array(
			array( 'page' => 'rsaip-reports', 'label' => __( 'Reports', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-media-spreadsheet' ),
			array( 'page' => 'rsaip-settings', 'label' => __( 'Settings', 'recipe-seo-ai-pro' ), 'icon' => 'dashicons-admin-generic' ),
		),
	),
);
?>
<nav class="rsaip-app-nav" aria-label="<?php echo esc_attr__( 'Recipe SEO AI Pro', 'recipe-seo-ai-pro' ); ?>">
	<div class="rsaip-app-brand">
		<span class="rsaip-app-brand-mark" aria-hidden="true">
			<span class="dashicons dashicons-chart-area"></span>
		</span>
		<div class="rsaip-app-brand-text">
			<span class="rsaip-app-brand-name"><?php echo esc_html__( 'Recipe SEO AI Pro', 'recipe-seo-ai-pro' ); ?></span>
			<span class="rsaip-app-brand-sub"><?php echo esc_html__( 'Premium SEO Suite', 'recipe-seo-ai-pro' ); ?></span>
		</div>
	</div>
	<?php foreach ( $groups as $group ) : ?>
		<div class="rsaip-app-nav-group">
			<p class="rsaip-app-nav-label"><?php echo esc_html( $group['label'] ); ?></p>
			<ul class="rsaip-app-nav-list">
				<?php foreach ( $group['items'] as $item ) : ?>
					<?php
					$is_active = ( $current_page === $item['page'] );
					$url       = admin_url( 'admin.php?page=' . $item['page'] );
					?>
					<li>
						<a
							class="rsaip-app-nav-link<?php echo $is_active ? ' is-active' : ''; ?>"
							href="<?php echo esc_url( $url ); ?>"
							<?php echo $is_active ? ' aria-current="page"' : ''; ?>
						>
							<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
							<span><?php echo esc_html( $item['label'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endforeach; ?>
</nav>
