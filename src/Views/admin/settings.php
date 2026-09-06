<?php
/**
 * Admin: Settings.
 *
 * @var string               $title
 * @var array<string, mixed> $settings
 * @var string               $option_key
 * @var string               $secret_placeholder
 * @var bool                 $has_ai_secret
 * @var array<string, string> $providers
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

<nav class="rsaip-settings-toc rsaip-fade-in" aria-label="<?php echo esc_attr__( 'Settings sections', 'recipe-seo-ai-pro' ); ?>">
	<a href="#rsaip-settings-ai"><span class="dashicons dashicons-cloud" aria-hidden="true"></span><?php echo esc_html__( 'AI Providers', 'recipe-seo-ai-pro' ); ?></a>
	<a href="#rsaip-settings-seo"><span class="dashicons dashicons-chart-area" aria-hidden="true"></span><?php echo esc_html__( 'SEO', 'recipe-seo-ai-pro' ); ?></a>
	<a href="#rsaip-settings-links"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span><?php echo esc_html__( 'Link Graph', 'recipe-seo-ai-pro' ); ?></a>
	<a href="#rsaip-settings-license"><span class="dashicons dashicons-awards" aria-hidden="true"></span><?php echo esc_html__( 'License', 'recipe-seo-ai-pro' ); ?></a>
	<a href="#rsaip-settings-updates"><span class="dashicons dashicons-update" aria-hidden="true"></span><?php echo esc_html__( 'Updates', 'recipe-seo-ai-pro' ); ?></a>
	<a href="#rsaip-settings-security"><span class="dashicons dashicons-shield" aria-hidden="true"></span><?php echo esc_html__( 'Security', 'recipe-seo-ai-pro' ); ?></a>
	<a href="#rsaip-settings-advanced"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><?php echo esc_html__( 'Advanced', 'recipe-seo-ai-pro' ); ?></a>
</nav>

<section class="rsaip-panel rsaip-settings-section rsaip-fade-in" aria-labelledby="rsaip-settings-ai">
	<div class="rsaip-opt-card-head rsaip-settings-head">
		<span class="dashicons dashicons-cloud" aria-hidden="true"></span>
		<h2 id="rsaip-settings-ai"><?php echo esc_html__( 'AI Providers', 'recipe-seo-ai-pro' ); ?></h2>
	</div>
	<p class="rsaip-note">
		<?php echo esc_html__( 'Legacy quick settings. Prefer the full AI Provider Hub for multi-provider configuration, health checks, and failover.', 'recipe-seo-ai-pro' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsaip-ai-providers' ) ); ?>"><?php echo esc_html__( 'Open AI Provider Hub', 'recipe-seo-ai-pro' ); ?></a>
	</p>
	<div class="rsaip-grid">
		<div>
			<label for="rsaip-ai-provider"><?php echo esc_html__( 'Provider', 'recipe-seo-ai-pro' ); ?></label>
			<select id="rsaip-ai-provider" name="<?php echo esc_attr( $opt ); ?>[ai_provider]">
				<?php foreach ( $providers as $k => $label ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $settings['ai_provider'], $k ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div>
			<label for="rsaip-ai-model"><?php echo esc_html__( 'Model', 'recipe-seo-ai-pro' ); ?></label>
			<input id="rsaip-ai-model" type="text" name="<?php echo esc_attr( $opt ); ?>[ai_model]" value="<?php echo esc_attr( (string) $settings['ai_model'] ); ?>"/>
		</div>
		<div class="rsaip-settings-span-2">
			<label for="rsaip-ai-endpoint"><?php echo esc_html__( 'Endpoint', 'recipe-seo-ai-pro' ); ?></label>
			<input id="rsaip-ai-endpoint" type="url" name="<?php echo esc_attr( $opt ); ?>[ai_endpoint]" value="<?php echo esc_attr( (string) $settings['ai_endpoint'] ); ?>"/>
		</div>
		<div class="rsaip-settings-span-2">
			<label for="rsaip-ai-key"><?php echo esc_html__( 'API Key', 'recipe-seo-ai-pro' ); ?></label>
			<?php
			View::render(
				'partials/secret-field',
				array(
					'name'         => $opt . '[ai_api_key]',
					'type'         => 'password',
					'has_secret'   => $has_ai_secret,
					'placeholder'  => $secret_placeholder,
					'saved_note'   => __( 'An API key is saved. Leave blank to keep it, or enter a new key to replace it.', 'recipe-seo-ai-pro' ),
					'autocomplete' => 'new-password',
				)
			);
			?>
		</div>
		<div>
			<label for="rsaip-ai-timeout"><?php echo esc_html__( 'Timeout (seconds)', 'recipe-seo-ai-pro' ); ?></label>
			<input id="rsaip-ai-timeout" type="number" min="5" max="60" name="<?php echo esc_attr( $opt ); ?>[ai_timeout_seconds]" value="<?php echo esc_attr( (string) $settings['ai_timeout_seconds'] ); ?>"/>
		</div>
	</div>
</section>

<section class="rsaip-panel rsaip-settings-section rsaip-fade-in" aria-labelledby="rsaip-settings-seo">
	<div class="rsaip-opt-card-head rsaip-settings-head">
		<span class="dashicons dashicons-chart-area" aria-hidden="true"></span>
		<h2 id="rsaip-settings-seo"><?php echo esc_html__( 'SEO & Performance', 'recipe-seo-ai-pro' ); ?></h2>
	</div>
	<div class="rsaip-grid">
		<div>
			<label for="rsaip-thin-words"><?php echo esc_html__( 'Thin Content Minimum Words', 'recipe-seo-ai-pro' ); ?></label>
			<input id="rsaip-thin-words" type="number" min="50" max="5000" name="<?php echo esc_attr( $opt ); ?>[thin_content_min_words]" value="<?php echo esc_attr( (string) $settings['thin_content_min_words'] ); ?>"/>
		</div>
		<div>
			<label for="rsaip-cache-ttl"><?php echo esc_html__( 'Cache TTL (seconds)', 'recipe-seo-ai-pro' ); ?></label>
			<input id="rsaip-cache-ttl" type="number" min="60" max="7200" name="<?php echo esc_attr( $opt ); ?>[cache_ttl_seconds]" value="<?php echo esc_attr( (string) $settings['cache_ttl_seconds'] ); ?>"/>
		</div>
		<div>
			<label for="rsaip-bulk-batch"><?php echo esc_html__( 'Bulk Queue Batch Size', 'recipe-seo-ai-pro' ); ?></label>
			<input id="rsaip-bulk-batch" type="number" min="1" max="50" name="<?php echo esc_attr( $opt ); ?>[bulk_queue_batch_size]" value="<?php echo esc_attr( (string) $settings['bulk_queue_batch_size'] ); ?>"/>
		</div>
		<div>
			<label for="rsaip-max-ext"><?php echo esc_html__( 'Max External Links Per Post', 'recipe-seo-ai-pro' ); ?></label>
			<input id="rsaip-max-ext" type="number" min="1" max="10" name="<?php echo esc_attr( $opt ); ?>[max_external_links_per_post]" value="<?php echo esc_attr( (string) $settings['max_external_links_per_post'] ); ?>"/>
		</div>
	</div>
</section>

<section class="rsaip-panel rsaip-settings-section rsaip-fade-in" aria-labelledby="rsaip-settings-links">
	<div class="rsaip-opt-card-head rsaip-settings-head">
		<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
		<h2 id="rsaip-settings-links"><?php echo esc_html__( 'Link Graph', 'recipe-seo-ai-pro' ); ?></h2>
	</div>
	<div class="rsaip-grid">
		<div>
			<label for="rsaip-lg-batch"><?php echo esc_html__( 'Rebuild Batch Size', 'recipe-seo-ai-pro' ); ?></label>
			<input id="rsaip-lg-batch" type="number" min="10" max="300" name="<?php echo esc_attr( $opt ); ?>[link_graph_rebuild_batch]" value="<?php echo esc_attr( (string) $settings['link_graph_rebuild_batch'] ); ?>"/>
		</div>
		<div>
			<label for="rsaip-broken-timeout"><?php echo esc_html__( 'Broken Link Timeout (seconds)', 'recipe-seo-ai-pro' ); ?></label>
			<input id="rsaip-broken-timeout" type="number" min="2" max="20" name="<?php echo esc_attr( $opt ); ?>[broken_link_timeout_seconds]" value="<?php echo esc_attr( (string) $settings['broken_link_timeout_seconds'] ); ?>"/>
		</div>
	</div>
</section>

<section class="rsaip-panel rsaip-settings-section rsaip-fade-in" aria-labelledby="rsaip-settings-license" id="rsaip-settings-license-panel">
	<div class="rsaip-settings-head">
		<span class="dashicons dashicons-awards" aria-hidden="true"></span>
		<h2 id="rsaip-settings-license"><?php echo esc_html__( 'License', 'recipe-seo-ai-pro' ); ?></h2>
	</div>
	<div class="rsaip-empty-state">
		<span class="dashicons dashicons-awards" aria-hidden="true"></span>
		<h3><?php echo esc_html__( 'Commercial license portal', 'recipe-seo-ai-pro' ); ?></h3>
		<p><?php echo esc_html__( 'License activation UI is ready for your commercial release. Existing plugin settings continue to work without a key.', 'recipe-seo-ai-pro' ); ?></p>
	</div>
</section>

<section class="rsaip-panel rsaip-settings-section rsaip-fade-in" aria-labelledby="rsaip-settings-updates">
	<div class="rsaip-settings-head">
		<span class="dashicons dashicons-update" aria-hidden="true"></span>
		<h2 id="rsaip-settings-updates"><?php echo esc_html__( 'Updates', 'recipe-seo-ai-pro' ); ?></h2>
	</div>
	<div class="rsaip-empty-state">
		<span class="dashicons dashicons-update" aria-hidden="true"></span>
		<h3><?php echo esc_html__( 'Update channel', 'recipe-seo-ai-pro' ); ?></h3>
		<p><?php echo esc_html__( 'Keep the plugin updated through your WordPress updates screen. Automatic update branding can plug in here later.', 'recipe-seo-ai-pro' ); ?></p>
	</div>
</section>

<section class="rsaip-panel rsaip-settings-section rsaip-fade-in" aria-labelledby="rsaip-settings-security">
	<div class="rsaip-settings-head">
		<span class="dashicons dashicons-shield" aria-hidden="true"></span>
		<h2 id="rsaip-settings-security"><?php echo esc_html__( 'Security', 'recipe-seo-ai-pro' ); ?></h2>
	</div>
	<ul class="rsaip-list">
		<li><?php echo esc_html__( 'API keys are stored as WordPress options and never echoed back in full.', 'recipe-seo-ai-pro' ); ?></li>
		<li><?php echo esc_html__( 'Admin actions require capability checks and nonces.', 'recipe-seo-ai-pro' ); ?></li>
		<li><?php echo esc_html__( 'Remote URL helpers block private/reserved destinations where enabled.', 'recipe-seo-ai-pro' ); ?></li>
	</ul>
	<div class="rsaip-grid" style="margin-top:12px;">
		<div class="rsaip-settings-span-2">
			<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[never_modify_posts_present]" value="1" />
			<label>
				<input
					type="checkbox"
					name="<?php echo esc_attr( $opt ); ?>[never_modify_posts]"
					value="1"
					<?php checked( 1, (int) ( $settings['never_modify_posts'] ?? 1 ) ); ?>
				/>
				<?php echo esc_html__( 'Safe Article Mode — never modify existing posts (recommended)', 'recipe-seo-ai-pro' ); ?>
			</label>
			<p class="rsaip-note">
				<?php echo esc_html__( 'When enabled, AI Fix, recipe card insert, schema fix, auto-linking, bulk/cron mutation jobs, Content Optimizer Apply, and Recipe AI Apply to WordPress posts are refused server-side. Analysis, generation, and new drafts still work.', 'recipe-seo-ai-pro' ); ?>
			</p>
		</div>
	</div>
</section>

<section class="rsaip-panel rsaip-settings-section rsaip-fade-in" aria-labelledby="rsaip-settings-advanced">
	<div class="rsaip-settings-head">
		<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
		<h2 id="rsaip-settings-advanced"><?php echo esc_html__( 'Advanced', 'recipe-seo-ai-pro' ); ?></h2>
	</div>
	<p class="rsaip-note"><?php echo esc_html__( 'Batch sizes, timeouts, and cache TTL above control performance under load. Prefer smaller batches on shared hosting.', 'recipe-seo-ai-pro' ); ?></p>
</section>

<div class="rsaip-actions">
	<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save Settings', 'recipe-seo-ai-pro' ); ?></button>
</div>

<?php
View::render( 'partials/form-close' );
View::render( 'partials/footer' );
