<?php
/**
 * Admin: SEO Audit.
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
<div class="rsaip-actions">
	<button class="button button-primary rsaip-btn" data-action="rsaip_run_full_audit"><?php echo esc_html__( 'Run Audit', 'recipe-seo-ai-pro' ); ?></button>
	<button class="button rsaip-btn" data-action="rsaip_list_audit_issues"><?php echo esc_html__( 'Refresh', 'recipe-seo-ai-pro' ); ?></button>
</div>
<?php
View::render(
	'partials/table',
	array(
		'columns'   => array(
			__( 'Post', 'recipe-seo-ai-pro' ),
			__( 'Issues', 'recipe-seo-ai-pro' ),
			__( 'SEO Score', 'recipe-seo-ai-pro' ),
			__( 'Actions', 'recipe-seo-ai-pro' ),
		),
		'tbody_id'  => 'rsaip-audit-body',
		'data_load' => 'rsaip_list_audit_issues',
	)
);
View::render( 'partials/footer' );
