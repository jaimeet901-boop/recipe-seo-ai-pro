<?php
/**
 * Admin: AI Content Optimizer (Phase 4.1).
 *
 * @var string $title
 * @var bool   $ai_ready
 * @var string $ai_provider
 * @var int    $post_id
 * @var string $post_title
 * @var array<string, string> $workflows
 * @var array<string, string> $scopes
 * @var string $note
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
<div class="rsaip-opt-page">

	<?php if ( empty( $ai_ready ) ) : ?>
		<div class="rsaip-alert rsaip-alert-warning" role="status">
			<span class="dashicons dashicons-info" aria-hidden="true"></span>
			<div>
				<strong><?php echo esc_html__( 'AI provider required', 'recipe-seo-ai-pro' ); ?></strong>
				<p class="rsaip-note">
					<?php echo esc_html__( 'Configure an OpenAI-compatible AI provider under Settings before optimizing.', 'recipe-seo-ai-pro' ); ?>
					<?php if ( ! empty( $ai_provider ) ) : ?>
						<?php echo ' ' . esc_html( sprintf( /* translators: %s provider */ __( 'Current provider: %s', 'recipe-seo-ai-pro' ), (string) $ai_provider ) ); ?>
					<?php endif; ?>
				</p>
			</div>
		</div>
	<?php endif; ?>

	<div class="rsaip-opt-layout">

		<div class="rsaip-opt-col rsaip-opt-col-left">

			<section class="rsaip-panel rsaip-opt-card" aria-labelledby="rsaip-opt-source-heading">
				<div class="rsaip-opt-card-head">
					<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
					<h2 id="rsaip-opt-source-heading"><?php echo esc_html__( 'Content Source', 'recipe-seo-ai-pro' ); ?></h2>
				</div>
				<div class="rsaip-opt-fields">
					<div class="rsaip-opt-field rsaip-opt-field-post">
						<label for="rsaip-opt-post-id"><?php echo esc_html__( 'Post ID', 'recipe-seo-ai-pro' ); ?></label>
						<div class="rsaip-opt-inline">
							<input type="number" min="0" id="rsaip-opt-post-id" class="small-text" value="<?php echo esc_attr( (string) $post_id ); ?>" />
							<button type="button" class="button button-secondary" id="rsaip-opt-load-post"><?php echo esc_html__( 'Load Post', 'recipe-seo-ai-pro' ); ?></button>
						</div>
						<span id="rsaip-opt-post-label" class="rsaip-sub"><?php echo esc_html( (string) $post_title ); ?></span>
					</div>
					<div class="rsaip-opt-field">
						<label for="rsaip-opt-scope"><?php echo esc_html__( 'Scope', 'recipe-seo-ai-pro' ); ?></label>
						<select id="rsaip-opt-scope" class="rsaip-input">
							<?php foreach ( $scopes as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="rsaip-opt-field">
						<label for="rsaip-opt-workflow"><?php echo esc_html__( 'Workflow', 'recipe-seo-ai-pro' ); ?></label>
						<select id="rsaip-opt-workflow" class="rsaip-input">
							<?php foreach ( $workflows as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="rsaip-opt-field rsaip-opt-field-full">
						<label for="rsaip-opt-keywords"><?php echo esc_html__( 'Target keywords (optional)', 'recipe-seo-ai-pro' ); ?></label>
						<input type="text" id="rsaip-opt-keywords" class="regular-text" />
					</div>
				</div>
			</section>

			<section class="rsaip-panel rsaip-opt-card" aria-labelledby="rsaip-opt-actions-heading">
				<div class="rsaip-opt-card-head">
					<span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
					<h2 id="rsaip-opt-actions-heading"><?php echo esc_html__( 'Actions', 'recipe-seo-ai-pro' ); ?></h2>
				</div>
				<div class="rsaip-actions rsaip-opt-actions">
					<button type="button" class="button button-primary" id="rsaip-opt-analyze" <?php disabled( empty( $ai_ready ) ); ?>>
						<span class="dashicons dashicons-chart-area" aria-hidden="true"></span>
						<?php echo esc_html__( 'Analyze', 'recipe-seo-ai-pro' ); ?>
					</button>
					<button type="button" class="button button-primary" id="rsaip-opt-run" <?php disabled( empty( $ai_ready ) ); ?>>
						<span class="dashicons dashicons-update" aria-hidden="true"></span>
						<?php echo esc_html__( 'Run Workflow', 'recipe-seo-ai-pro' ); ?>
					</button>
					<a class="button button-secondary" href="#rsaip-opt-compare">
						<span class="dashicons dashicons-leftright" aria-hidden="true"></span>
						<?php echo esc_html__( 'Compare', 'recipe-seo-ai-pro' ); ?>
					</a>
					<button type="button" class="button button-secondary" id="rsaip-opt-apply">
						<span class="dashicons dashicons-yes" aria-hidden="true"></span>
						<?php echo esc_html__( 'Apply', 'recipe-seo-ai-pro' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="rsaip-opt-undo">
						<span class="dashicons dashicons-undo" aria-hidden="true"></span>
						<?php echo esc_html__( 'Undo', 'recipe-seo-ai-pro' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="rsaip-opt-history">
						<span class="dashicons dashicons-backup" aria-hidden="true"></span>
						<?php echo esc_html__( 'Restore / History', 'recipe-seo-ai-pro' ); ?>
					</button>
				</div>
				<div id="rsaip-opt-status" class="rsaip-sub rsaip-opt-status" role="status" aria-live="polite"></div>
			</section>

		</div>

		<div class="rsaip-opt-col rsaip-opt-col-right">

			<section class="rsaip-panel rsaip-opt-card" aria-labelledby="rsaip-opt-preview-heading">
				<div class="rsaip-opt-card-head">
					<span class="dashicons dashicons-edit" aria-hidden="true"></span>
					<h2 id="rsaip-opt-preview-heading"><?php echo esc_html__( 'Content Preview', 'recipe-seo-ai-pro' ); ?></h2>
				</div>
				<div class="rsaip-opt-fields rsaip-opt-fields-stack">
					<div class="rsaip-opt-field rsaip-opt-field-full">
						<label for="rsaip-opt-title"><?php echo esc_html__( 'Title', 'recipe-seo-ai-pro' ); ?></label>
						<input type="text" id="rsaip-opt-title" class="regular-text" />
					</div>
					<div class="rsaip-opt-field rsaip-opt-field-full">
						<label for="rsaip-opt-meta"><?php echo esc_html__( 'Meta description', 'recipe-seo-ai-pro' ); ?></label>
						<textarea id="rsaip-opt-meta" rows="2"></textarea>
					</div>
					<div class="rsaip-opt-field rsaip-opt-field-full">
						<label for="rsaip-opt-notes"><?php echo esc_html__( 'Notes', 'recipe-seo-ai-pro' ); ?></label>
						<textarea id="rsaip-opt-notes" rows="2"></textarea>
					</div>
					<div class="rsaip-opt-field rsaip-opt-field-full">
						<label for="rsaip-opt-selected"><?php echo esc_html__( 'Selected paragraph / heading (for scoped optimize)', 'recipe-seo-ai-pro' ); ?></label>
						<textarea id="rsaip-opt-selected" rows="3"></textarea>
					</div>
					<div class="rsaip-opt-field rsaip-opt-field-full">
						<label for="rsaip-opt-content"><?php echo esc_html__( 'Content', 'recipe-seo-ai-pro' ); ?></label>
						<textarea id="rsaip-opt-content" class="rsaip-opt-editor" rows="12"></textarea>
					</div>
				</div>
			</section>

			<section class="rsaip-panel rsaip-opt-card" id="rsaip-opt-scores" hidden aria-labelledby="rsaip-opt-scores-heading">
				<div class="rsaip-opt-card-head">
					<span class="dashicons dashicons-performance" aria-hidden="true"></span>
					<h2 id="rsaip-opt-scores-heading"><?php echo esc_html__( 'Analysis scores', 'recipe-seo-ai-pro' ); ?></h2>
				</div>
				<div class="rsaip-cards rsaip-opt-score-grid" id="rsaip-opt-score-cards"></div>
				<div class="rsaip-opt-issues-wrap">
					<h3 class="rsaip-opt-issues-title">
						<span class="dashicons dashicons-flag" aria-hidden="true"></span>
						<?php echo esc_html__( 'Detected issues', 'recipe-seo-ai-pro' ); ?>
					</h3>
					<ul id="rsaip-opt-issues"></ul>
				</div>
			</section>

			<section class="rsaip-panel rsaip-opt-card" id="rsaip-opt-compare" hidden aria-labelledby="rsaip-opt-compare-heading">
				<div class="rsaip-opt-card-head">
					<span class="dashicons dashicons-leftright" aria-hidden="true"></span>
					<h2 id="rsaip-opt-compare-heading"><?php echo esc_html__( 'Compare', 'recipe-seo-ai-pro' ); ?></h2>
				</div>
				<div class="rsaip-opt-compare-tabs" role="tablist" aria-label="<?php echo esc_attr__( 'Compare views', 'recipe-seo-ai-pro' ); ?>">
					<button type="button" class="button button-primary rsaip-opt-tab" data-tab="original"><?php echo esc_html__( 'Original', 'recipe-seo-ai-pro' ); ?></button>
					<button type="button" class="button rsaip-opt-tab" data-tab="optimized"><?php echo esc_html__( 'Optimized', 'recipe-seo-ai-pro' ); ?></button>
					<button type="button" class="button rsaip-opt-tab" data-tab="diff"><?php echo esc_html__( 'Diff View', 'recipe-seo-ai-pro' ); ?></button>
				</div>
				<div class="rsaip-opt-compare-split">
					<div class="rsaip-opt-compare-col">
						<h3 class="rsaip-opt-compare-label"><?php echo esc_html__( 'Original', 'recipe-seo-ai-pro' ); ?></h3>
						<div id="rsaip-opt-pane-original" class="rsaip-opt-pane"></div>
					</div>
					<div class="rsaip-opt-compare-col">
						<h3 class="rsaip-opt-compare-label"><?php echo esc_html__( 'Optimized', 'recipe-seo-ai-pro' ); ?></h3>
						<div id="rsaip-opt-pane-optimized" class="rsaip-opt-pane" hidden></div>
					</div>
				</div>
				<div class="rsaip-opt-diff-wrap">
					<h3 class="rsaip-opt-compare-label rsaip-opt-diff-heading"><?php echo esc_html__( 'Diff View', 'recipe-seo-ai-pro' ); ?></h3>
					<div id="rsaip-opt-pane-diff" class="rsaip-opt-pane rsaip-opt-diff" hidden></div>
				</div>
				<p class="rsaip-opt-diff-legend" aria-hidden="true">
					<span class="rsaip-opt-legend rsaip-opt-legend-added"><?php echo esc_html__( 'Added', 'recipe-seo-ai-pro' ); ?></span>
					<span class="rsaip-opt-legend rsaip-opt-legend-removed"><?php echo esc_html__( 'Removed', 'recipe-seo-ai-pro' ); ?></span>
					<span class="rsaip-opt-legend rsaip-opt-legend-same"><?php echo esc_html__( 'Unchanged', 'recipe-seo-ai-pro' ); ?></span>
				</p>
			</section>

			<section class="rsaip-panel rsaip-opt-card" aria-labelledby="rsaip-opt-history-heading">
				<div class="rsaip-opt-card-head">
					<span class="dashicons dashicons-backup" aria-hidden="true"></span>
					<h2 id="rsaip-opt-history-heading"><?php echo esc_html__( 'History', 'recipe-seo-ai-pro' ); ?></h2>
				</div>
				<div id="rsaip-opt-history-body" class="rsaip-note rsaip-opt-history-body"><?php echo esc_html__( 'Load a post to see optimization history.', 'recipe-seo-ai-pro' ); ?></div>
			</section>

		</div>
	</div>
</div>
<?php
View::render( 'partials/footer' );
