<?php
/**
 * Admin: Google Search Console Integration.
 *
 * @var string               $title
 * @var array<string, mixed> $settings
 * @var string               $option_key
 * @var string               $secret_placeholder
 * @var bool                 $has_pem_secret
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
	<h2><?php echo esc_html__( 'Service Account Settings', 'recipe-seo-ai-pro' ); ?></h2>
	<?php
	View::render(
		'partials/notice',
		array(
			'message' => __( 'Add the service account email as an Owner/User in Google Search Console for the selected property.', 'recipe-seo-ai-pro' ),
			'tag'     => 'p',
		)
	);
	?>

	<div class="rsaip-grid">
		<div>
			<label><?php echo esc_html__( 'Site URL (Property)', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" name="<?php echo esc_attr( $opt ); ?>[gsc_site_url]" value="<?php echo esc_attr( (string) $settings['gsc_site_url'] ); ?>" placeholder="https://example.com/"/>
		</div>
		<div>
			<label><?php echo esc_html__( 'Service Account Email', 'recipe-seo-ai-pro' ); ?></label>
			<input type="email" name="<?php echo esc_attr( $opt ); ?>[gsc_service_account_email]" value="<?php echo esc_attr( (string) $settings['gsc_service_account_email'] ); ?>"/>
		</div>
	</div>

	<label><?php echo esc_html__( 'Private Key (PEM)', 'recipe-seo-ai-pro' ); ?></label>
	<?php
	View::render(
		'partials/secret-field',
		array(
			'name'        => $opt . '[gsc_private_key_pem]',
			'type'        => 'textarea',
			'has_secret'  => $has_pem_secret,
			'placeholder' => $secret_placeholder,
			'saved_note'  => __( 'A private key is saved. Leave blank to keep it, or paste a new key to replace it.', 'recipe-seo-ai-pro' ),
			'rows'        => 8,
		)
	);
	?>

	<div class="rsaip-grid">
		<div>
			<label><?php echo esc_html__( 'Lookback Days', 'recipe-seo-ai-pro' ); ?></label>
			<input type="number" min="7" max="365" name="<?php echo esc_attr( $opt ); ?>[gsc_lookback_days]" value="<?php echo esc_attr( (string) $settings['gsc_lookback_days'] ); ?>"/>
		</div>
		<div>
			<label><?php echo esc_html__( 'Token Cache (seconds)', 'recipe-seo-ai-pro' ); ?></label>
			<input type="number" min="300" max="7200" name="<?php echo esc_attr( $opt ); ?>[gsc_token_cache_seconds]" value="<?php echo esc_attr( (string) $settings['gsc_token_cache_seconds'] ); ?>"/>
		</div>
	</div>

	<p><button type="submit" class="button button-primary"><?php echo esc_html__( 'Save GSC Settings', 'recipe-seo-ai-pro' ); ?></button></p>
</div>
<?php
View::render( 'partials/form-close' );
?>
<div class="rsaip-actions">
	<button class="button rsaip-btn" data-action="rsaip_gsc_test_connection"><?php echo esc_html__( 'Test Connection', 'recipe-seo-ai-pro' ); ?></button>
	<button class="button button-primary rsaip-btn" data-action="rsaip_gsc_top_opportunities"><?php echo esc_html__( 'Load Top Opportunities', 'recipe-seo-ai-pro' ); ?></button>
</div>
<?php
View::render(
	'partials/table',
	array(
		'columns'   => array(
			__( 'Keyword', 'recipe-seo-ai-pro' ),
			__( 'Position', 'recipe-seo-ai-pro' ),
			__( 'Impressions', 'recipe-seo-ai-pro' ),
			__( 'Clicks', 'recipe-seo-ai-pro' ),
			__( 'CTR', 'recipe-seo-ai-pro' ),
			__( 'Recommendation', 'recipe-seo-ai-pro' ),
		),
		'tbody_id'  => 'rsaip-gsc-body',
		'data_load' => 'rsaip_gsc_top_opportunities',
	)
);
View::render( 'partials/footer' );
