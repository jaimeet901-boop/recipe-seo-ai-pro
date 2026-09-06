<?php
/**
 * Admin: AI Content Brief Library (Phase 3.2).
 *
 * @var string                $title
 * @var array<string, string> $labels
 * @var array<string, string> $statuses
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
	<?php echo esc_html__( 'Search, open, duplicate, archive, and export saved content briefs. Generation stays on the AI Content Brief page.', 'recipe-seo-ai-pro' ); ?>
</p>

<div class="rsaip-panel">
	<div class="rsaip-actions">
		<label class="rsaip-inline">
			<?php echo esc_html__( 'Search', 'recipe-seo-ai-pro' ); ?>
			<input type="search" id="rsaip-lib-q" class="regular-text" placeholder="<?php echo esc_attr__( 'Title, topic, keyword…', 'recipe-seo-ai-pro' ); ?>" />
		</label>
		<label class="rsaip-inline">
			<?php echo esc_html__( 'Status', 'recipe-seo-ai-pro' ); ?>
			<select id="rsaip-lib-status" class="rsaip-input">
				<?php foreach ( $statuses as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="rsaip-inline">
			<?php echo esc_html__( 'Sort', 'recipe-seo-ai-pro' ); ?>
			<select id="rsaip-lib-sort" class="rsaip-input">
				<option value="updated_at"><?php echo esc_html__( 'Updated', 'recipe-seo-ai-pro' ); ?></option>
				<option value="created_at"><?php echo esc_html__( 'Created', 'recipe-seo-ai-pro' ); ?></option>
				<option value="title"><?php echo esc_html__( 'Title', 'recipe-seo-ai-pro' ); ?></option>
				<option value="primary_keyword"><?php echo esc_html__( 'Primary keyword', 'recipe-seo-ai-pro' ); ?></option>
				<option value="word_count"><?php echo esc_html__( 'Word count', 'recipe-seo-ai-pro' ); ?></option>
			</select>
		</label>
		<select id="rsaip-lib-order" class="rsaip-input">
			<option value="DESC"><?php echo esc_html__( 'Desc', 'recipe-seo-ai-pro' ); ?></option>
			<option value="ASC"><?php echo esc_html__( 'Asc', 'recipe-seo-ai-pro' ); ?></option>
		</select>
		<button type="button" class="button button-primary" id="rsaip-lib-refresh"><?php echo esc_html__( 'Refresh', 'recipe-seo-ai-pro' ); ?></button>
		<span id="rsaip-lib-status-text" class="rsaip-sub"></span>
	</div>

	<table class="widefat striped rsaip-table">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Title', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Primary Keyword', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Status', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Words', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Updated', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Actions', 'recipe-seo-ai-pro' ); ?></th>
			</tr>
		</thead>
		<tbody id="rsaip-lib-body">
			<tr><td colspan="6"><?php echo esc_html__( 'Loading…', 'recipe-seo-ai-pro' ); ?></td></tr>
		</tbody>
	</table>
	<div class="rsaip-actions">
		<button type="button" class="button" id="rsaip-lib-prev" disabled><?php echo esc_html__( 'Previous', 'recipe-seo-ai-pro' ); ?></button>
		<span id="rsaip-lib-page-label" class="rsaip-sub"></span>
		<button type="button" class="button" id="rsaip-lib-next" disabled><?php echo esc_html__( 'Next', 'recipe-seo-ai-pro' ); ?></button>
	</div>
</div>

<div class="rsaip-panel" id="rsaip-lib-detail-panel" hidden>
	<h2 id="rsaip-lib-detail-title"><?php echo esc_html__( 'Brief detail', 'recipe-seo-ai-pro' ); ?></h2>
	<div class="rsaip-actions">
		<button type="button" class="button button-primary" id="rsaip-lib-save-update"><?php echo esc_html__( 'Update Brief', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button" id="rsaip-lib-mark-complete"><?php echo esc_html__( 'Mark Completed', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button" id="rsaip-lib-export-md"><?php echo esc_html__( 'Export Markdown', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button" id="rsaip-lib-export-json"><?php echo esc_html__( 'Export JSON', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button" id="rsaip-lib-export-pdf"><?php echo esc_html__( 'Export PDF', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button" id="rsaip-lib-copy"><?php echo esc_html__( 'Copy to Clipboard', 'recipe-seo-ai-pro' ); ?></button>
	</div>
	<div class="rsaip-grid">
		<div>
			<label for="rsaip-lib-edit-title"><?php echo esc_html__( 'Title', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-lib-edit-title" class="regular-text" />
		</div>
		<div>
			<label for="rsaip-lib-edit-status"><?php echo esc_html__( 'Status', 'recipe-seo-ai-pro' ); ?></label>
			<select id="rsaip-lib-edit-status" class="rsaip-input">
				<option value="draft"><?php echo esc_html__( 'Draft', 'recipe-seo-ai-pro' ); ?></option>
				<option value="completed"><?php echo esc_html__( 'Completed', 'recipe-seo-ai-pro' ); ?></option>
				<option value="archived"><?php echo esc_html__( 'Archived', 'recipe-seo-ai-pro' ); ?></option>
			</select>
		</div>
	</div>
	<div id="rsaip-lib-detail" class="rsaip-brief-result"></div>
</div>
<?php
View::render( 'partials/footer' );
