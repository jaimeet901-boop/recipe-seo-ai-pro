<?php
/**
 * Admin: Performance Analyzer.
 *
 * @var string               $title
 * @var array<string, mixed> $settings
 * @var string               $option_key
 * @var string               $secret_placeholder
 * @var bool                 $has_psi_secret
 * @var string               $perf_url_placeholder
 *
 * @package RecipeSeoAiPro
 */

use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$opt = $option_key;

View::render( 'partials/header', array( 'title' => $title ) );
View::render( 'partials/form-open', array( 'settings_group' => 'rsaip_settings_group' ) );
?>
<div class="rsaip-panel">
	<h2><?php echo esc_html__( 'PageSpeed Insights API Key', 'recipe-seo-ai-pro' ); ?></h2>
	<?php
	View::render(
		'partials/secret-field',
		array(
			'name'          => $opt . '[psi_api_key]',
			'type'          => 'password',
			'has_secret'    => $has_psi_secret,
			'placeholder'   => $secret_placeholder,
			'saved_note'    => __( 'An API key is saved. Leave blank to keep it, or enter a new key to replace it.', 'recipe-seo-ai-pro' ),
			'autocomplete'  => 'new-password',
		)
	);
	?>
	<p><button type="submit" class="button button-primary"><?php echo esc_html__( 'Save', 'recipe-seo-ai-pro' ); ?></button></p>
</div>
<?php
View::render( 'partials/form-close' );
?>
<div class="rsaip-actions">
	<label class="rsaip-inline">
		<?php echo esc_html__( 'URL', 'recipe-seo-ai-pro' ); ?>
		<input type="text" class="regular-text rsaip-input" data-key="perf_url" placeholder="<?php echo esc_attr( $perf_url_placeholder ); ?>"/>
	</label>
	<select class="rsaip-input" data-key="perf_strategy">
		<option value="mobile"><?php echo esc_html__( 'Mobile', 'recipe-seo-ai-pro' ); ?></option>
		<option value="desktop"><?php echo esc_html__( 'Desktop', 'recipe-seo-ai-pro' ); ?></option>
	</select>
	<button class="button button-primary rsaip-btn" data-action="rsaip_performance_analyze"><?php echo esc_html__( 'Analyze', 'recipe-seo-ai-pro' ); ?></button>
</div>
<div id="rsaip-performance-result" class="rsaip-panel rsaip-performance"></div>
<?php
View::render( 'partials/footer' );
