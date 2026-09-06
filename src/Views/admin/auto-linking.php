<?php
/**
 * Admin: Auto Internal Linking.
 *
 * @var string               $title
 * @var array<string, mixed> $settings
 * @var string               $option_key
 * @var array<string, object> $post_types
 * @var array<string, string> $statuses
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
	<h2><?php echo esc_html__( 'Auto Insert Internal Links', 'recipe-seo-ai-pro' ); ?></h2>
	<label class="rsaip-switch">
		<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[auto_insert_internal_links]" value="1" <?php checked( 1, (int) $settings['auto_insert_internal_links'] ); ?> />
		<span><?php echo esc_html__( 'Enable', 'recipe-seo-ai-pro' ); ?></span>
	</label>

	<div class="rsaip-grid">
		<div>
			<label><?php echo esc_html__( 'Minimum Relevance Score', 'recipe-seo-ai-pro' ); ?></label>
			<input type="number" min="0" max="100" name="<?php echo esc_attr( $opt ); ?>[min_relevance_score]" value="<?php echo esc_attr( (string) $settings['min_relevance_score'] ); ?>"/>
		</div>
		<div>
			<label><?php echo esc_html__( 'Maximum Links Per Post', 'recipe-seo-ai-pro' ); ?></label>
			<input type="number" min="1" max="25" name="<?php echo esc_attr( $opt ); ?>[max_links_per_post]" value="<?php echo esc_attr( (string) $settings['max_links_per_post'] ); ?>"/>
		</div>
	</div>

	<h3><?php echo esc_html__( 'Scope', 'recipe-seo-ai-pro' ); ?></h3>
	<div class="rsaip-grid">
		<div>
			<label><?php echo esc_html__( 'Post Types', 'recipe-seo-ai-pro' ); ?></label>
			<div class="rsaip-checklist">
				<?php foreach ( $post_types as $pt ) : ?>
					<?php $checked = in_array( $pt->name, $settings['auto_insert_post_types'], true ); ?>
					<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[auto_insert_post_types][]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( true, $checked ); ?> /> <?php echo esc_html( $pt->label ); ?></label>
				<?php endforeach; ?>
			</div>
		</div>
		<div>
			<label><?php echo esc_html__( 'Statuses', 'recipe-seo-ai-pro' ); ?></label>
			<div class="rsaip-checklist">
				<?php foreach ( $statuses as $k => $label ) : ?>
					<?php $checked = in_array( $k, $settings['auto_insert_statuses'], true ); ?>
					<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[auto_insert_statuses][]" value="<?php echo esc_attr( $k ); ?>" <?php checked( true, $checked ); ?> /> <?php echo esc_html( $label ); ?></label>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<p><button type="submit" class="button button-primary"><?php echo esc_html__( 'Save Settings', 'recipe-seo-ai-pro' ); ?></button></p>
</div>
<?php
View::render( 'partials/form-close' );
?>
<div class="rsaip-actions">
	<button class="button rsaip-btn" data-action="rsaip_auto_link_run_batch"><?php echo esc_html__( 'Run Auto Linking Batch Now', 'recipe-seo-ai-pro' ); ?></button>
</div>
<?php
View::render( 'partials/footer' );
