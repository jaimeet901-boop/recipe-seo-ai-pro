<?php
/**
 * Admin: Image SEO Optimizer.
 *
 * @var string               $title
 * @var array<string, mixed> $settings
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
	<button class="button button-primary rsaip-btn" data-action="rsaip_list_image_issues"><?php echo esc_html__( 'Scan Images', 'recipe-seo-ai-pro' ); ?></button>
	<span class="rsaip-inline">
		<label><?php echo esc_html__( 'Large image threshold (KB)', 'recipe-seo-ai-pro' ); ?></label>
		<input type="number" min="50" max="5000" class="small-text rsaip-input" data-setting="image_large_threshold_kb" value="<?php echo esc_attr( (string) $settings['image_large_threshold_kb'] ); ?>"/>
	</span>
</div>
<?php
View::render(
	'partials/table',
	array(
		'columns'   => array(
			__( 'Image', 'recipe-seo-ai-pro' ),
			__( 'Problems', 'recipe-seo-ai-pro' ),
			__( 'Actions', 'recipe-seo-ai-pro' ),
		),
		'tbody_id'  => 'rsaip-images-body',
		'data_load' => 'rsaip_list_image_issues',
	)
);
View::render( 'partials/footer' );
