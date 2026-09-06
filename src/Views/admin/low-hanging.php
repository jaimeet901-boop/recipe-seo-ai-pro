<?php
/**
 * Admin: Low Hanging Fruits Finder.
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
	<button class="button button-primary rsaip-btn" data-action="rsaip_gsc_low_hanging"><?php echo esc_html__( 'Find Opportunities', 'recipe-seo-ai-pro' ); ?></button>
</div>
<?php
View::render(
	'partials/table',
	array(
		'columns'   => array(
			__( 'Page', 'recipe-seo-ai-pro' ),
			__( 'Query', 'recipe-seo-ai-pro' ),
			__( 'Position', 'recipe-seo-ai-pro' ),
			__( 'Impressions', 'recipe-seo-ai-pro' ),
			__( 'Clicks', 'recipe-seo-ai-pro' ),
			__( 'CTR', 'recipe-seo-ai-pro' ),
		),
		'tbody_id'  => 'rsaip-low-hanging-body',
		'data_load' => 'rsaip_gsc_low_hanging',
	)
);
View::render( 'partials/footer' );
