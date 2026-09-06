<?php
/**
 * Admin: AI Content Calendar (Phase 3.7).
 *
 * @var string $title
 * @var int    $project_id
 * @var list<array<string,mixed>> $projects
 * @var list<array<string,mixed>> $calendars
 * @var array<string, string> $statuses
 * @var array<string, string> $channels
 * @var array<string, string> $phases
 * @var array<string, string> $views
 * @var string $note
 * @var string $timezone
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
	<div class="rsaip-actions rsaip-cal-toolbar">
		<label class="rsaip-inline">
			<?php echo esc_html__( 'Project', 'recipe-seo-ai-pro' ); ?>
			<select id="rsaip-cal-project" class="rsaip-input">
				<option value="0"><?php echo esc_html__( 'All projects', 'recipe-seo-ai-pro' ); ?></option>
				<?php foreach ( $projects as $p ) : ?>
					<option value="<?php echo esc_attr( (string) $p['id'] ); ?>" <?php selected( (int) $project_id, (int) $p['id'] ); ?>>
						<?php echo esc_html( (string) $p['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="rsaip-inline">
			<?php echo esc_html__( 'Calendar', 'recipe-seo-ai-pro' ); ?>
			<select id="rsaip-cal-calendar" class="rsaip-input">
				<option value="0"><?php echo esc_html__( 'All calendars', 'recipe-seo-ai-pro' ); ?></option>
				<?php foreach ( $calendars as $c ) : ?>
					<option value="<?php echo esc_attr( (string) $c['id'] ); ?>"><?php echo esc_html( (string) $c['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<select id="rsaip-cal-status" class="rsaip-input">
			<?php foreach ( $statuses as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<div class="rsaip-cal-views" role="tablist">
			<?php foreach ( $views as $value => $label ) : ?>
				<button type="button" class="button rsaip-cal-view-btn<?php echo 'month' === $value ? ' button-primary' : ''; ?>" data-view="<?php echo esc_attr( $value ); ?>">
					<?php echo esc_html( $label ); ?>
				</button>
			<?php endforeach; ?>
		</div>
		<button type="button" class="button" id="rsaip-cal-prev">&larr;</button>
		<button type="button" class="button" id="rsaip-cal-today"><?php echo esc_html__( 'Today', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button" id="rsaip-cal-next">&rarr;</button>
		<strong id="rsaip-cal-label"></strong>
		<span id="rsaip-cal-status-text" class="rsaip-sub"></span>
	</div>
</div>

<div class="rsaip-panel">
	<h2><?php echo esc_html__( 'Add event', 'recipe-seo-ai-pro' ); ?></h2>
	<div class="rsaip-grid">
		<div>
			<label for="rsaip-cal-title"><?php echo esc_html__( 'Title', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-cal-title" class="regular-text" />
		</div>
		<div>
			<label for="rsaip-cal-publish"><?php echo esc_html__( 'Publish date', 'recipe-seo-ai-pro' ); ?></label>
			<input type="datetime-local" id="rsaip-cal-publish" />
		</div>
		<div>
			<label for="rsaip-cal-ev-status"><?php echo esc_html__( 'Status', 'recipe-seo-ai-pro' ); ?></label>
			<select id="rsaip-cal-ev-status" class="rsaip-input">
				<?php foreach ( $statuses as $value => $label ) : ?>
					<?php if ( 'all' === $value ) { continue; } ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, 'planned' ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div>
			<label for="rsaip-cal-channel"><?php echo esc_html__( 'Channel', 'recipe-seo-ai-pro' ); ?></label>
			<select id="rsaip-cal-channel" class="rsaip-input">
				<?php foreach ( $channels as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div>
			<label for="rsaip-cal-priority"><?php echo esc_html__( 'Priority', 'recipe-seo-ai-pro' ); ?></label>
			<input type="number" min="0" max="100" id="rsaip-cal-priority" class="small-text" value="50" />
		</div>
		<div>
			<label for="rsaip-cal-phase"><?php echo esc_html__( 'Roadmap phase', 'recipe-seo-ai-pro' ); ?></label>
			<select id="rsaip-cal-phase" class="rsaip-input">
				<?php foreach ( $phases as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div>
			<label for="rsaip-cal-keyword"><?php echo esc_html__( 'Keyword ID', 'recipe-seo-ai-pro' ); ?></label>
			<input type="number" min="0" id="rsaip-cal-keyword" class="small-text" value="0" />
		</div>
		<div>
			<label for="rsaip-cal-brief"><?php echo esc_html__( 'Brief ID', 'recipe-seo-ai-pro' ); ?></label>
			<input type="number" min="0" id="rsaip-cal-brief" class="small-text" value="0" />
		</div>
		<div>
			<label for="rsaip-cal-article"><?php echo esc_html__( 'Article ID', 'recipe-seo-ai-pro' ); ?></label>
			<input type="number" min="0" id="rsaip-cal-article" class="small-text" value="0" />
		</div>
		<div>
			<label for="rsaip-cal-assignee"><?php echo esc_html__( 'Assigned user ID', 'recipe-seo-ai-pro' ); ?></label>
			<input type="number" min="0" id="rsaip-cal-assignee" class="small-text" value="0" />
		</div>
		<div>
			<label for="rsaip-cal-tz"><?php echo esc_html__( 'Timezone', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-cal-tz" class="regular-text" value="<?php echo esc_attr( (string) $timezone ); ?>" />
		</div>
	</div>
	<label for="rsaip-cal-notes"><?php echo esc_html__( 'Notes', 'recipe-seo-ai-pro' ); ?></label>
	<textarea id="rsaip-cal-notes" rows="2"></textarea>
	<div class="rsaip-actions">
		<button type="button" class="button button-primary" id="rsaip-cal-create"><?php echo esc_html__( 'Add Event', 'recipe-seo-ai-pro' ); ?></button>
		<input type="text" id="rsaip-cal-new-name" class="regular-text" placeholder="<?php echo esc_attr__( 'New calendar name…', 'recipe-seo-ai-pro' ); ?>" />
		<button type="button" class="button" id="rsaip-cal-create-cal"><?php echo esc_html__( 'Create Calendar', 'recipe-seo-ai-pro' ); ?></button>
	</div>
</div>

<div class="rsaip-panel">
	<div class="rsaip-actions">
		<button type="button" class="button" id="rsaip-cal-bulk-move"><?php echo esc_html__( 'Bulk Move (+7 days)', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button" id="rsaip-cal-bulk-delete"><?php echo esc_html__( 'Bulk Delete', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button" id="rsaip-cal-show-upcoming"><?php echo esc_html__( 'Upcoming', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button" id="rsaip-cal-show-overdue"><?php echo esc_html__( 'Overdue', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button" id="rsaip-cal-show-queue"><?php echo esc_html__( 'Publishing Queue', 'recipe-seo-ai-pro' ); ?></button>
	</div>
	<div id="rsaip-cal-board" class="rsaip-cal-board" aria-live="polite"></div>
</div>

<div class="rsaip-panel" id="rsaip-cal-detail" hidden>
	<h2 id="rsaip-cal-detail-title"><?php echo esc_html__( 'Event', 'recipe-seo-ai-pro' ); ?></h2>
	<div id="rsaip-cal-detail-meta" class="rsaip-note"></div>
	<div class="rsaip-actions">
		<button type="button" class="button" id="rsaip-cal-dup"><?php echo esc_html__( 'Duplicate', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button" id="rsaip-cal-del"><?php echo esc_html__( 'Delete', 'recipe-seo-ai-pro' ); ?></button>
		<label class="rsaip-inline">
			<?php echo esc_html__( 'Reschedule', 'recipe-seo-ai-pro' ); ?>
			<input type="datetime-local" id="rsaip-cal-reschedule" />
		</label>
		<button type="button" class="button button-primary" id="rsaip-cal-reschedule-btn"><?php echo esc_html__( 'Save Date', 'recipe-seo-ai-pro' ); ?></button>
	</div>
</div>

<div class="rsaip-panel" id="rsaip-cal-side" hidden>
	<h2 id="rsaip-cal-side-title"></h2>
	<div id="rsaip-cal-side-body"></div>
</div>
<?php
View::render( 'partials/footer' );
