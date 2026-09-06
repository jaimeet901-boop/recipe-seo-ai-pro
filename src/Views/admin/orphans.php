<?php
/**
 * Admin: Orphan Posts Detector.
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
	<button class="button rsaip-btn" data-action="rsaip_rebuild_link_graph"><?php echo esc_html__( 'Rebuild Link Graph', 'recipe-seo-ai-pro' ); ?></button>
	<button class="button button-primary rsaip-btn" data-action="rsaip_list_orphans"><?php echo esc_html__( 'Refresh', 'recipe-seo-ai-pro' ); ?></button>
</div>
<?php
View::render(
	'partials/table',
	array(
		'columns'   => array(
			__( 'Title', 'recipe-seo-ai-pro' ),
			__( 'Category', 'recipe-seo-ai-pro' ),
			__( 'Publish Date', 'recipe-seo-ai-pro' ),
			__( 'Word Count', 'recipe-seo-ai-pro' ),
			__( 'Actions', 'recipe-seo-ai-pro' ),
		),
		'tbody_id'  => 'rsaip-orphans-body',
		'data_load' => 'rsaip_list_orphans',
	)
);
View::render( 'partials/footer' );
