<?php
/**
 * Admin: XML Sitemap Auditor.
 *
 * @var string $title
 * @var string $sitemap_placeholder
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
	<label class="rsaip-inline">
		<?php echo esc_html__( 'Sitemap URL', 'recipe-seo-ai-pro' ); ?>
		<input type="text" class="regular-text rsaip-input" data-key="sitemap_url" placeholder="<?php echo esc_attr( $sitemap_placeholder ); ?>"/>
	</label>
	<button class="button button-primary rsaip-btn" data-action="rsaip_sitemap_audit"><?php echo esc_html__( 'Audit Sitemap', 'recipe-seo-ai-pro' ); ?></button>
</div>
<?php
View::render(
	'partials/table',
	array(
		'columns'     => array(
			__( 'URL', 'recipe-seo-ai-pro' ),
			__( 'HTTP', 'recipe-seo-ai-pro' ),
			__( 'Indexed', 'recipe-seo-ai-pro' ),
			__( 'Notes', 'recipe-seo-ai-pro' ),
		),
		'tbody_id'    => 'rsaip-sitemap-body',
		'tbody_class' => '',
		'data_load'   => '',
	)
);
View::render( 'partials/footer' );
