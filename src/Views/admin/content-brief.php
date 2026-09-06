<?php
/**
 * Admin: AI Content Brief (Phase 3.1 — isolated feature).
 *
 * @var string $title
 * @var bool   $ai_ready
 * @var string $ai_provider
 * @var string $locale
 * @var array<string, string> $sections
 *
 * @package RecipeSeoAiPro
 */

use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

View::render( 'partials/header', array( 'title' => $title ) );
?>
<p class="rsaip-note">
	<?php echo esc_html__( 'Generate a full SEO content brief from a topic or seed keyword. Version 1 uses AI only (no keyword scraping).', 'recipe-seo-ai-pro' ); ?>
</p>

<?php if ( empty( $ai_ready ) ) : ?>
	<div class="rsaip-panel">
		<p class="rsaip-note">
			<?php echo esc_html__( 'Configure an OpenAI-compatible AI provider, endpoint, model, and API key under Settings before generating briefs.', 'recipe-seo-ai-pro' ); ?>
			<?php if ( ! empty( $ai_provider ) ) : ?>
				<?php echo ' ' . esc_html( sprintf( /* translators: %s: provider id */ __( 'Current provider: %s', 'recipe-seo-ai-pro' ), (string) $ai_provider ) ); ?>
			<?php endif; ?>
		</p>
	</div>
<?php endif; ?>

<div class="rsaip-panel">
	<h2><?php echo esc_html__( 'Brief inputs', 'recipe-seo-ai-pro' ); ?></h2>
	<div class="rsaip-grid">
		<div>
			<label for="rsaip-brief-topic"><?php echo esc_html__( 'Topic / seed keyword', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-brief-topic" class="regular-text" maxlength="300" placeholder="<?php echo esc_attr__( 'e.g. gluten free chocolate cake', 'recipe-seo-ai-pro' ); ?>" />
		</div>
		<div>
			<label for="rsaip-brief-audience"><?php echo esc_html__( 'Audience (optional)', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-brief-audience" class="regular-text" placeholder="<?php echo esc_attr__( 'Home bakers, beginners…', 'recipe-seo-ai-pro' ); ?>" />
		</div>
		<div>
			<label for="rsaip-brief-locale"><?php echo esc_html__( 'Locale', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-brief-locale" class="regular-text" value="<?php echo esc_attr( (string) $locale ); ?>" />
		</div>
	</div>
	<label for="rsaip-brief-notes"><?php echo esc_html__( 'Notes (optional)', 'recipe-seo-ai-pro' ); ?></label>
	<textarea id="rsaip-brief-notes" rows="3" placeholder="<?php echo esc_attr__( 'Angle, constraints, brand voice…', 'recipe-seo-ai-pro' ); ?>"></textarea>
	<div class="rsaip-actions">
		<button type="button" class="button button-primary" id="rsaip-brief-generate" <?php disabled( empty( $ai_ready ) ); ?>>
			<?php echo esc_html__( 'Generate Content Brief', 'recipe-seo-ai-pro' ); ?>
		</button>
		<button type="button" class="button" id="rsaip-brief-save-library" disabled>
			<?php echo esc_html__( 'Save Brief', 'recipe-seo-ai-pro' ); ?>
		</button>
		<button type="button" class="button" id="rsaip-brief-copy" disabled>
			<?php echo esc_html__( 'Copy as text', 'recipe-seo-ai-pro' ); ?>
		</button>
		<span id="rsaip-brief-status" class="rsaip-sub"></span>
	</div>
</div>

<div class="rsaip-panel">
	<h2><?php echo esc_html__( 'Generated brief', 'recipe-seo-ai-pro' ); ?></h2>
	<div id="rsaip-brief-empty" class="rsaip-note">
		<?php echo esc_html__( 'Your brief will appear here after generation.', 'recipe-seo-ai-pro' ); ?>
	</div>
	<div id="rsaip-brief-result" class="rsaip-brief-result" hidden></div>
</div>
<?php
View::render( 'partials/footer' );
