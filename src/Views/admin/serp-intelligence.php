<?php
/**
 * Admin: SERP Intelligence (Phase 3.6).
 *
 * @var string                $title
 * @var bool                  $ai_ready
 * @var string                $ai_provider
 * @var int                   $project_id
 * @var list<array{id:int,name:string}> $projects
 * @var string                $locale
 * @var array<string, string> $sections
 * @var string                $note
 *
 * @package RecipeSeoAiPro
 */

use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

View::render(
	'partials/header',
	array(
		'title'       => $title,
		'description' => $note,
		'ai_ready'    => ! empty( $ai_ready ),
		'ai_provider' => isset( $ai_provider ) ? (string) $ai_provider : '',
	)
);
?>

<?php if ( empty( $ai_ready ) ) : ?>
	<div class="rsaip-alert rsaip-alert-warning" role="status">
		<span class="dashicons dashicons-warning" aria-hidden="true"></span>
		<div>
			<?php echo esc_html__( 'Configure an OpenAI-compatible AI provider, endpoint, model, and API key under Settings before analyzing.', 'recipe-seo-ai-pro' ); ?>
			<?php if ( ! empty( $ai_provider ) ) : ?>
				<?php echo ' ' . esc_html( sprintf( /* translators: %s: provider id */ __( 'Current provider: %s', 'recipe-seo-ai-pro' ), (string) $ai_provider ) ); ?>
			<?php endif; ?>
		</div>
	</div>
<?php endif; ?>

<div class="rsaip-panel">
	<h2><?php echo esc_html__( 'Analyze query', 'recipe-seo-ai-pro' ); ?></h2>
	<div class="rsaip-grid">
		<div>
			<label for="rsaip-serp-query"><?php echo esc_html__( 'Query / keyword', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-serp-query" class="regular-text" maxlength="300" placeholder="<?php echo esc_attr__( 'e.g. best air fryer for beginners', 'recipe-seo-ai-pro' ); ?>" />
		</div>
		<div>
			<label for="rsaip-serp-audience"><?php echo esc_html__( 'Audience (optional)', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-serp-audience" class="regular-text" />
		</div>
		<div>
			<label for="rsaip-serp-language"><?php echo esc_html__( 'Language', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-serp-language" class="regular-text" value="<?php echo esc_attr( (string) $locale ); ?>" />
		</div>
		<div>
			<label for="rsaip-serp-country"><?php echo esc_html__( 'Country', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-serp-country" class="regular-text" maxlength="8" placeholder="US" />
		</div>
	</div>
	<label for="rsaip-serp-notes"><?php echo esc_html__( 'Notes (optional)', 'recipe-seo-ai-pro' ); ?></label>
	<textarea id="rsaip-serp-notes" rows="3"></textarea>
	<div class="rsaip-actions">
		<button type="button" class="button button-primary" id="rsaip-serp-analyze" <?php disabled( empty( $ai_ready ) ); ?>>
			<?php echo esc_html__( 'Analyze SERP', 'recipe-seo-ai-pro' ); ?>
		</button>
		<span id="rsaip-serp-status" class="rsaip-sub"></span>
	</div>
</div>

<div class="rsaip-panel" id="rsaip-serp-result-panel" hidden>
	<h2><?php echo esc_html__( 'Analysis', 'recipe-seo-ai-pro' ); ?></h2>
	<div id="rsaip-serp-result"></div>

	<h3><?php echo esc_html__( 'Save & attach', 'recipe-seo-ai-pro' ); ?></h3>
	<div class="rsaip-grid">
		<div>
			<label for="rsaip-serp-project"><?php echo esc_html__( 'Project', 'recipe-seo-ai-pro' ); ?></label>
			<select id="rsaip-serp-project" class="rsaip-input">
				<option value="0"><?php echo esc_html__( 'None', 'recipe-seo-ai-pro' ); ?></option>
				<?php foreach ( $projects as $p ) : ?>
					<option value="<?php echo esc_attr( (string) $p['id'] ); ?>" <?php selected( (int) $project_id, (int) $p['id'] ); ?>>
						<?php echo esc_html( (string) $p['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div>
			<label for="rsaip-serp-keyword-id"><?php echo esc_html__( 'Keyword ID (optional)', 'recipe-seo-ai-pro' ); ?></label>
			<input type="number" min="0" id="rsaip-serp-keyword-id" class="small-text" value="0" />
		</div>
		<div>
			<label for="rsaip-serp-brief-id"><?php echo esc_html__( 'Content Brief ID (optional)', 'recipe-seo-ai-pro' ); ?></label>
			<input type="number" min="0" id="rsaip-serp-brief-id" class="small-text" value="0" />
		</div>
	</div>
	<div class="rsaip-actions">
		<button type="button" class="button button-primary" id="rsaip-serp-save"><?php echo esc_html__( 'Save Analysis', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button" id="rsaip-serp-attach-project"><?php echo esc_html__( 'Attach to Project', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button" id="rsaip-serp-attach-keyword"><?php echo esc_html__( 'Attach to Keyword', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button" id="rsaip-serp-attach-brief"><?php echo esc_html__( 'Attach to Brief', 'recipe-seo-ai-pro' ); ?></button>
	</div>
</div>

<div class="rsaip-panel">
	<div class="rsaip-actions">
		<label class="rsaip-inline">
			<?php echo esc_html__( 'Saved analyses', 'recipe-seo-ai-pro' ); ?>
			<input type="search" id="rsaip-serp-q" class="regular-text" placeholder="<?php echo esc_attr__( 'Filter…', 'recipe-seo-ai-pro' ); ?>" />
		</label>
		<button type="button" class="button" id="rsaip-serp-refresh"><?php echo esc_html__( 'Refresh', 'recipe-seo-ai-pro' ); ?></button>
	</div>
	<table class="widefat striped rsaip-table">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Query', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Article type', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Project', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Keyword', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Brief', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Updated', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Actions', 'recipe-seo-ai-pro' ); ?></th>
			</tr>
		</thead>
		<tbody id="rsaip-serp-list-body">
			<tr><td colspan="7"><?php echo esc_html__( 'Loading…', 'recipe-seo-ai-pro' ); ?></td></tr>
		</tbody>
	</table>
</div>
<?php
View::render( 'partials/footer' );
