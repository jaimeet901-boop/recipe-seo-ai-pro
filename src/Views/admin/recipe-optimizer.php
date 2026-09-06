<?php
/**
 * Admin: Recipe SEO Optimizer.
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
	<button class="button button-primary rsaip-btn" data-action="rsaip_recipe_scan"><?php echo esc_html__( 'Scan Recipe Posts', 'recipe-seo-ai-pro' ); ?></button>
</div>
<?php
View::render(
	'partials/table',
	array(
		'columns'   => array(
			__( 'Post', 'recipe-seo-ai-pro' ),
			__( 'Checklist', 'recipe-seo-ai-pro' ),
			__( 'Score', 'recipe-seo-ai-pro' ),
			__( 'Actions', 'recipe-seo-ai-pro' ),
		),
		'tbody_id'  => 'rsaip-recipe-body',
		'data_load' => 'rsaip_recipe_scan',
	)
);
View::render(
	'partials/notice',
	array(
		'id'          => 'rsaip-recipe-card-preview',
		'extra_class' => 'rsaip-panel',
		'message'     => __( 'Preview a recipe card here, then insert it automatically into the post.', 'recipe-seo-ai-pro' ),
	)
);
View::render( 'partials/footer' );
