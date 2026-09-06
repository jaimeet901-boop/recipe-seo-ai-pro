<?php
/**
 * Admin: Keyword Workspace (Phase 3.4 — management only).
 *
 * @var string                $title
 * @var int                   $project_id
 * @var list<array<string,mixed>> $projects
 * @var array<string, string> $statuses
 * @var array<string, string> $intents
 * @var array<string, string> $phases
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
	)
);
?>

<div class="rsaip-panel">
	<div class="rsaip-actions">
		<label class="rsaip-inline">
			<?php echo esc_html__( 'Project', 'recipe-seo-ai-pro' ); ?>
			<select id="rsaip-kw-project" class="rsaip-input">
				<option value="0"><?php echo esc_html__( 'All projects', 'recipe-seo-ai-pro' ); ?></option>
				<?php foreach ( $projects as $p ) : ?>
					<option value="<?php echo esc_attr( (string) $p['id'] ); ?>" <?php selected( (int) $project_id, (int) $p['id'] ); ?>>
						<?php echo esc_html( (string) $p['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>
		<input type="search" id="rsaip-kw-q" class="regular-text" placeholder="<?php echo esc_attr__( 'Search keywords…', 'recipe-seo-ai-pro' ); ?>" />
		<select id="rsaip-kw-status" class="rsaip-input">
			<?php foreach ( $statuses as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<select id="rsaip-kw-intent" class="rsaip-input">
			<?php foreach ( $intents as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="button" class="button" id="rsaip-kw-refresh"><?php echo esc_html__( 'Refresh', 'recipe-seo-ai-pro' ); ?></button>
		<span id="rsaip-kw-status-text" class="rsaip-sub"></span>
	</div>
</div>

<div class="rsaip-panel">
	<h2><?php echo esc_html__( 'Add keyword (manual)', 'recipe-seo-ai-pro' ); ?></h2>
	<div class="rsaip-grid">
		<div>
			<label for="rsaip-kw-primary"><?php echo esc_html__( 'Primary keyword', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-kw-primary" class="regular-text" />
		</div>
		<div>
			<label for="rsaip-kw-add-intent"><?php echo esc_html__( 'Intent', 'recipe-seo-ai-pro' ); ?></label>
			<select id="rsaip-kw-add-intent" class="rsaip-input">
				<option value="informational"><?php echo esc_html__( 'Informational', 'recipe-seo-ai-pro' ); ?></option>
				<option value="commercial"><?php echo esc_html__( 'Commercial', 'recipe-seo-ai-pro' ); ?></option>
				<option value="transactional"><?php echo esc_html__( 'Transactional', 'recipe-seo-ai-pro' ); ?></option>
				<option value="navigational"><?php echo esc_html__( 'Navigational', 'recipe-seo-ai-pro' ); ?></option>
			</select>
		</div>
		<div>
			<label for="rsaip-kw-priority"><?php echo esc_html__( 'Priority', 'recipe-seo-ai-pro' ); ?></label>
			<input type="number" min="0" max="100" id="rsaip-kw-priority" class="small-text" value="50" />
		</div>
		<div>
			<label for="rsaip-kw-difficulty"><?php echo esc_html__( 'Difficulty', 'recipe-seo-ai-pro' ); ?></label>
			<input type="number" min="0" max="100" id="rsaip-kw-difficulty" class="small-text" value="0" />
		</div>
		<div>
			<label for="rsaip-kw-phase"><?php echo esc_html__( 'Roadmap phase', 'recipe-seo-ai-pro' ); ?></label>
			<select id="rsaip-kw-phase" class="rsaip-input">
				<?php foreach ( $phases as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div>
			<label for="rsaip-kw-url"><?php echo esc_html__( 'Target URL', 'recipe-seo-ai-pro' ); ?></label>
			<input type="url" id="rsaip-kw-url" class="regular-text" />
		</div>
	</div>
	<div class="rsaip-actions">
		<button type="button" class="button button-primary" id="rsaip-kw-create"><?php echo esc_html__( 'Add Keyword', 'recipe-seo-ai-pro' ); ?></button>
	</div>
</div>

<div class="rsaip-panel">
	<div class="rsaip-actions">
		<button type="button" class="button" id="rsaip-kw-bulk-status"><?php echo esc_html__( 'Bulk: Change Status', 'recipe-seo-ai-pro' ); ?></button>
		<select id="rsaip-kw-bulk-status-value" class="rsaip-input">
			<?php foreach ( $statuses as $value => $label ) : ?>
				<?php if ( 'all' === $value ) { continue; } ?>
				<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="button" class="button" id="rsaip-kw-bulk-phase"><?php echo esc_html__( 'Bulk: Roadmap Phase', 'recipe-seo-ai-pro' ); ?></button>
		<select id="rsaip-kw-bulk-phase-value" class="rsaip-input">
			<?php foreach ( $phases as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="button" class="button" id="rsaip-kw-bulk-delete"><?php echo esc_html__( 'Bulk: Delete', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button" id="rsaip-kw-view-clusters"><?php echo esc_html__( 'Cluster View', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button" id="rsaip-kw-view-roadmap"><?php echo esc_html__( 'Roadmap', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button" id="rsaip-kw-view-timeline"><?php echo esc_html__( 'Timeline', 'recipe-seo-ai-pro' ); ?></button>
	</div>

	<table class="widefat striped rsaip-table">
		<thead>
			<tr>
				<th><input type="checkbox" id="rsaip-kw-check-all" /></th>
				<th><?php echo esc_html__( 'Keyword', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Intent', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Status', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Priority', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Cluster', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Brief', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Article', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Actions', 'recipe-seo-ai-pro' ); ?></th>
			</tr>
		</thead>
		<tbody id="rsaip-kw-body">
			<tr><td colspan="9"><?php echo esc_html__( 'Loading…', 'recipe-seo-ai-pro' ); ?></td></tr>
		</tbody>
	</table>
</div>

<div class="rsaip-panel" id="rsaip-kw-detail" hidden>
	<h2 id="rsaip-kw-detail-title"><?php echo esc_html__( 'Keyword detail', 'recipe-seo-ai-pro' ); ?></h2>
	<div class="rsaip-actions">
		<label class="rsaip-inline">
			<?php echo esc_html__( 'Brief ID', 'recipe-seo-ai-pro' ); ?>
			<input type="number" min="0" id="rsaip-kw-brief-id" class="small-text" />
		</label>
		<button type="button" class="button" id="rsaip-kw-assign-brief"><?php echo esc_html__( 'Assign Brief', 'recipe-seo-ai-pro' ); ?></button>
		<label class="rsaip-inline">
			<?php echo esc_html__( 'Article / Post ID', 'recipe-seo-ai-pro' ); ?>
			<input type="number" min="0" id="rsaip-kw-article-id" class="small-text" />
		</label>
		<button type="button" class="button" id="rsaip-kw-assign-article"><?php echo esc_html__( 'Assign Article', 'recipe-seo-ai-pro' ); ?></button>
		<select id="rsaip-kw-detail-status" class="rsaip-input">
			<?php foreach ( $statuses as $value => $label ) : ?>
				<?php if ( 'all' === $value ) { continue; } ?>
				<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="button" class="button button-primary" id="rsaip-kw-save-status"><?php echo esc_html__( 'Change Status', 'recipe-seo-ai-pro' ); ?></button>
	</div>
	<label for="rsaip-kw-note"><?php echo esc_html__( 'Add note', 'recipe-seo-ai-pro' ); ?></label>
	<textarea id="rsaip-kw-note" rows="2"></textarea>
	<div class="rsaip-actions">
		<button type="button" class="button" id="rsaip-kw-add-note"><?php echo esc_html__( 'Save Note', 'recipe-seo-ai-pro' ); ?></button>
	</div>
	<div id="rsaip-kw-detail-meta" class="rsaip-note"></div>
</div>

<div class="rsaip-panel" id="rsaip-kw-side-view" hidden>
	<h2 id="rsaip-kw-side-title"></h2>
	<div id="rsaip-kw-side-body"></div>
</div>

<div class="rsaip-panel">
	<h3><?php echo esc_html__( 'Create cluster', 'recipe-seo-ai-pro' ); ?></h3>
	<div class="rsaip-actions">
		<input type="text" id="rsaip-kw-cluster-name" class="regular-text" placeholder="<?php echo esc_attr__( 'Cluster name', 'recipe-seo-ai-pro' ); ?>" />
		<button type="button" class="button" id="rsaip-kw-create-cluster"><?php echo esc_html__( 'Create Cluster', 'recipe-seo-ai-pro' ); ?></button>
	</div>
</div>
<?php
View::render( 'partials/footer' );
