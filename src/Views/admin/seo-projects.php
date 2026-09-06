<?php
/**
 * Admin: AI SEO Projects (Phase 3.3).
 *
 * @var string                $title
 * @var array<string, string> $statuses
 * @var list<string>          $future_modules
 * @var string                $locale
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
	<?php echo esc_html__( 'Organize content briefs and future SEO assets (keywords, articles, calendar, competitors) into projects.', 'recipe-seo-ai-pro' ); ?>
</p>

<div class="rsaip-panel">
	<h2><?php echo esc_html__( 'Create project', 'recipe-seo-ai-pro' ); ?></h2>
	<div class="rsaip-grid">
		<div>
			<label for="rsaip-proj-name"><?php echo esc_html__( 'Name', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-proj-name" class="regular-text" />
		</div>
		<div>
			<label for="rsaip-proj-niche"><?php echo esc_html__( 'Niche', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-proj-niche" class="regular-text" />
		</div>
		<div>
			<label for="rsaip-proj-country"><?php echo esc_html__( 'Target country', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-proj-country" class="regular-text" placeholder="US" maxlength="8" />
		</div>
		<div>
			<label for="rsaip-proj-language"><?php echo esc_html__( 'Language', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-proj-language" class="regular-text" value="<?php echo esc_attr( (string) $locale ); ?>" />
		</div>
	</div>
	<label for="rsaip-proj-description"><?php echo esc_html__( 'Description', 'recipe-seo-ai-pro' ); ?></label>
	<textarea id="rsaip-proj-description" rows="3"></textarea>
	<div class="rsaip-actions">
		<button type="button" class="button button-primary" id="rsaip-proj-create"><?php echo esc_html__( 'Create Project', 'recipe-seo-ai-pro' ); ?></button>
		<span id="rsaip-proj-status" class="rsaip-sub"></span>
	</div>
</div>

<div class="rsaip-panel">
	<div class="rsaip-actions">
		<label class="rsaip-inline">
			<?php echo esc_html__( 'Search', 'recipe-seo-ai-pro' ); ?>
			<input type="search" id="rsaip-proj-q" class="regular-text" />
		</label>
		<select id="rsaip-proj-filter-status" class="rsaip-input">
			<?php foreach ( $statuses as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="button" class="button" id="rsaip-proj-refresh"><?php echo esc_html__( 'Refresh', 'recipe-seo-ai-pro' ); ?></button>
	</div>
	<table class="widefat striped rsaip-table">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Name', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Niche', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Status', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Briefs', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Keywords', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Articles', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Completion', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Actions', 'recipe-seo-ai-pro' ); ?></th>
			</tr>
		</thead>
		<tbody id="rsaip-proj-body">
			<tr><td colspan="8"><?php echo esc_html__( 'Loading…', 'recipe-seo-ai-pro' ); ?></td></tr>
		</tbody>
	</table>
</div>

<div class="rsaip-panel" id="rsaip-proj-dashboard" hidden>
	<h2 id="rsaip-proj-dash-title"><?php echo esc_html__( 'Project dashboard', 'recipe-seo-ai-pro' ); ?></h2>
	<div id="rsaip-proj-cards" class="rsaip-cards"></div>

	<div class="rsaip-grid">
		<div>
			<label for="rsaip-proj-edit-name"><?php echo esc_html__( 'Name', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-proj-edit-name" class="regular-text" />
		</div>
		<div>
			<label for="rsaip-proj-edit-status"><?php echo esc_html__( 'Status', 'recipe-seo-ai-pro' ); ?></label>
			<select id="rsaip-proj-edit-status" class="rsaip-input">
				<option value="active"><?php echo esc_html__( 'Active', 'recipe-seo-ai-pro' ); ?></option>
				<option value="paused"><?php echo esc_html__( 'Paused', 'recipe-seo-ai-pro' ); ?></option>
				<option value="archived"><?php echo esc_html__( 'Archived', 'recipe-seo-ai-pro' ); ?></option>
			</select>
		</div>
		<div>
			<label for="rsaip-proj-edit-niche"><?php echo esc_html__( 'Niche', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-proj-edit-niche" class="regular-text" />
		</div>
		<div>
			<label for="rsaip-proj-edit-country"><?php echo esc_html__( 'Target country', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-proj-edit-country" class="regular-text" maxlength="8" />
		</div>
		<div>
			<label for="rsaip-proj-edit-language"><?php echo esc_html__( 'Language', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-proj-edit-language" class="regular-text" />
		</div>
	</div>
	<label for="rsaip-proj-edit-description"><?php echo esc_html__( 'Description', 'recipe-seo-ai-pro' ); ?></label>
	<textarea id="rsaip-proj-edit-description" rows="3"></textarea>
	<div class="rsaip-actions">
		<button type="button" class="button button-primary" id="rsaip-proj-save"><?php echo esc_html__( 'Update Project', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button" id="rsaip-proj-refresh-stats"><?php echo esc_html__( 'Refresh Stats', 'recipe-seo-ai-pro' ); ?></button>
		<a class="button button-primary" id="rsaip-proj-open-keywords" href="#"><?php echo esc_html__( 'Keyword Workspace', 'recipe-seo-ai-pro' ); ?></a>
	</div>

	<h3><?php echo esc_html__( 'Attach content brief', 'recipe-seo-ai-pro' ); ?></h3>
	<div class="rsaip-actions">
		<input type="number" min="1" id="rsaip-proj-brief-id" class="small-text" placeholder="<?php echo esc_attr__( 'Brief ID', 'recipe-seo-ai-pro' ); ?>" />
		<button type="button" class="button" id="rsaip-proj-attach-brief"><?php echo esc_html__( 'Attach Brief', 'recipe-seo-ai-pro' ); ?></button>
	</div>

	<h3><?php echo esc_html__( 'Add task', 'recipe-seo-ai-pro' ); ?></h3>
	<div class="rsaip-actions">
		<input type="text" id="rsaip-proj-task-title" class="regular-text" placeholder="<?php echo esc_attr__( 'Upcoming task…', 'recipe-seo-ai-pro' ); ?>" />
		<input type="date" id="rsaip-proj-task-due" />
		<button type="button" class="button" id="rsaip-proj-add-task"><?php echo esc_html__( 'Add Task', 'recipe-seo-ai-pro' ); ?></button>
	</div>

	<div class="rsaip-grid">
		<div>
			<h3><?php echo esc_html__( 'Upcoming tasks', 'recipe-seo-ai-pro' ); ?></h3>
			<ul id="rsaip-proj-tasks"></ul>
		</div>
		<div>
			<h3><?php echo esc_html__( 'Recent activity', 'recipe-seo-ai-pro' ); ?></h3>
			<ul id="rsaip-proj-activity"></ul>
		</div>
		<div>
			<h3><?php echo esc_html__( 'Members', 'recipe-seo-ai-pro' ); ?></h3>
			<ul id="rsaip-proj-members"></ul>
			<div class="rsaip-actions">
				<input type="number" min="1" id="rsaip-proj-member-id" class="small-text" placeholder="<?php echo esc_attr__( 'User ID', 'recipe-seo-ai-pro' ); ?>" />
				<button type="button" class="button" id="rsaip-proj-add-member"><?php echo esc_html__( 'Add Member', 'recipe-seo-ai-pro' ); ?></button>
			</div>
		</div>
	</div>

	<p class="rsaip-note">
		<strong><?php echo esc_html__( 'Planned for this project:', 'recipe-seo-ai-pro' ); ?></strong>
		<?php echo esc_html( implode( ' · ', $future_modules ) ); ?>
	</p>
</div>
<?php
View::render( 'partials/footer' );
