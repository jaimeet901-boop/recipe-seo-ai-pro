<?php
/**
 * Admin: Internal Linking Engine.
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
	<button class="button button-primary rsaip-btn" data-action="rsaip_generate_suggestions"><?php echo esc_html__( 'Generate Suggestions', 'recipe-seo-ai-pro' ); ?></button>
	<span class="rsaip-inline">
		<label><?php echo esc_html__( 'Limit per post', 'recipe-seo-ai-pro' ); ?></label>
		<input type="number" min="1" max="20" class="small-text rsaip-input" data-setting="link_suggestion_limit" value="<?php echo esc_attr( (string) $settings['link_suggestion_limit'] ); ?>"/>
	</span>
</div>
<?php
View::render(
	'partials/table',
	array(
		'columns'   => array(
			__( 'Post Title', 'recipe-seo-ai-pro' ),
			__( 'Suggested Links', 'recipe-seo-ai-pro' ),
			__( 'Link Score', 'recipe-seo-ai-pro' ),
			__( 'Actions', 'recipe-seo-ai-pro' ),
		),
		'tbody_id'  => 'rsaip-suggestions-body',
		'data_load' => 'rsaip_list_suggestions',
	)
);
View::render( 'partials/footer' );
