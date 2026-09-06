<?php
/**
 * Admin: Recipe Builder 2.0 (Phase 5.1).
 *
 * @var string               $title
 * @var int                  $recipe_id
 * @var array<string,mixed>|null $recipe
 * @var list<array<string,mixed>> $recipes
 * @var array<string,string> $unit_systems
 * @var array<string,string> $statuses
 * @var string               $note
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

<div class="rsaip-rb-layout">
	<aside class="rsaip-panel rsaip-rb-library">
		<h2><?php echo esc_html__( 'Your recipes', 'recipe-seo-ai-pro' ); ?></h2>
		<button type="button" class="button button-primary" id="rsaip-rb-new"><?php echo esc_html__( 'New recipe', 'recipe-seo-ai-pro' ); ?></button>
		<ul id="rsaip-rb-recipe-list" class="rsaip-rb-recipe-list">
			<?php foreach ( $recipes as $row ) : ?>
				<li>
					<button type="button" class="button-link rsaip-rb-open" data-id="<?php echo esc_attr( (string) ( $row['id'] ?? 0 ) ); ?>">
						<?php echo esc_html( (string) ( $row['title'] ?? '' ) ); ?>
					</button>
					<span class="rsaip-sub"><?php echo esc_html( (string) ( $row['status'] ?? 'draft' ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</aside>

	<div class="rsaip-rb-main">
		<div class="rsaip-panel">
			<div class="rsaip-rb-toolbar">
				<input type="hidden" id="rsaip-rb-id" value="<?php echo esc_attr( (string) $recipe_id ); ?>" />
				<label>
					<?php echo esc_html__( 'Title', 'recipe-seo-ai-pro' ); ?>
					<input type="text" id="rsaip-rb-title" class="regular-text" value="<?php echo esc_attr( (string) ( $recipe['title'] ?? '' ) ); ?>" />
				</label>
				<label>
					<?php echo esc_html__( 'Status', 'recipe-seo-ai-pro' ); ?>
					<select id="rsaip-rb-status">
						<?php foreach ( $statuses as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) ( $recipe['status'] ?? 'draft' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<?php echo esc_html__( 'Linked post ID', 'recipe-seo-ai-pro' ); ?>
					<input type="number" min="0" id="rsaip-rb-post-id" class="small-text" value="<?php echo esc_attr( (string) ( $recipe['post_id'] ?? 0 ) ); ?>" />
				</label>
				<div class="rsaip-actions">
					<button type="button" class="button button-primary" id="rsaip-rb-save"><?php echo esc_html__( 'Save', 'recipe-seo-ai-pro' ); ?></button>
					<button type="button" class="button" id="rsaip-rb-delete"><?php echo esc_html__( 'Delete', 'recipe-seo-ai-pro' ); ?></button>
				</div>
			</div>

			<label for="rsaip-rb-description"><?php echo esc_html__( 'Description', 'recipe-seo-ai-pro' ); ?></label>
			<textarea id="rsaip-rb-description" rows="2"><?php echo esc_textarea( (string) ( $recipe['description'] ?? '' ) ); ?></textarea>

			<div class="rsaip-rb-meta-grid">
				<label>
					<?php echo esc_html__( 'Servings', 'recipe-seo-ai-pro' ); ?>
					<input type="number" min="0.25" step="0.25" id="rsaip-rb-servings" value="<?php echo esc_attr( (string) ( $recipe['servings'] ?? 4 ) ); ?>" />
				</label>
				<label>
					<?php echo esc_html__( 'Prep (min)', 'recipe-seo-ai-pro' ); ?>
					<input type="number" min="0" id="rsaip-rb-prep" value="<?php echo esc_attr( (string) ( $recipe['prep_time'] ?? 0 ) ); ?>" />
				</label>
				<label>
					<?php echo esc_html__( 'Cook (min)', 'recipe-seo-ai-pro' ); ?>
					<input type="number" min="0" id="rsaip-rb-cook" value="<?php echo esc_attr( (string) ( $recipe['cook_time'] ?? 0 ) ); ?>" />
				</label>
				<label>
					<?php echo esc_html__( 'Total (min)', 'recipe-seo-ai-pro' ); ?>
					<input type="number" min="0" id="rsaip-rb-total" value="<?php echo esc_attr( (string) ( $recipe['total_time'] ?? 0 ) ); ?>" />
				</label>
				<label>
					<?php echo esc_html__( 'Unit system', 'recipe-seo-ai-pro' ); ?>
					<select id="rsaip-rb-unit-system">
						<?php foreach ( $unit_systems as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) ( $recipe['unit_system'] ?? 'metric' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
		</div>

		<div class="rsaip-panel">
			<div class="rsaip-rb-section-head">
				<h2><?php echo esc_html__( 'Ingredient groups', 'recipe-seo-ai-pro' ); ?></h2>
				<button type="button" class="button" id="rsaip-rb-add-group"><?php echo esc_html__( 'Add group', 'recipe-seo-ai-pro' ); ?></button>
			</div>
			<div id="rsaip-rb-sections" class="rsaip-rb-sections"></div>
			<button type="button" class="button" id="rsaip-rb-add-ingredient"><?php echo esc_html__( 'Add ingredient', 'recipe-seo-ai-pro' ); ?></button>
		</div>

		<div class="rsaip-panel">
			<div class="rsaip-rb-section-head">
				<h2><?php echo esc_html__( 'Steps', 'recipe-seo-ai-pro' ); ?></h2>
				<button type="button" class="button" id="rsaip-rb-add-step"><?php echo esc_html__( 'Add step', 'recipe-seo-ai-pro' ); ?></button>
			</div>
			<ol id="rsaip-rb-steps" class="rsaip-rb-steps"></ol>
		</div>

		<div class="rsaip-panel rsaip-grid">
			<div>
				<label for="rsaip-rb-equipment"><?php echo esc_html__( 'Equipment (one per line)', 'recipe-seo-ai-pro' ); ?></label>
				<textarea id="rsaip-rb-equipment" rows="4"><?php
					$equip = isset( $recipe['equipment'] ) && is_array( $recipe['equipment'] ) ? $recipe['equipment'] : array();
					echo esc_textarea( implode( "\n", $equip ) );
				?></textarea>
			</div>
			<div>
				<label for="rsaip-rb-tips"><?php echo esc_html__( 'Tips', 'recipe-seo-ai-pro' ); ?></label>
				<textarea id="rsaip-rb-tips" rows="4"><?php echo esc_textarea( (string) ( $recipe['tips'] ?? '' ) ); ?></textarea>
			</div>
			<div>
				<label for="rsaip-rb-notes"><?php echo esc_html__( 'Notes', 'recipe-seo-ai-pro' ); ?></label>
				<textarea id="rsaip-rb-notes" rows="4"><?php echo esc_textarea( (string) ( $recipe['notes'] ?? '' ) ); ?></textarea>
			</div>
		</div>
	</div>

	<aside class="rsaip-panel rsaip-rb-preview-panel">
		<div class="rsaip-rb-section-head">
			<h2><?php echo esc_html__( 'Live preview', 'recipe-seo-ai-pro' ); ?></h2>
			<button type="button" class="button" id="rsaip-rb-refresh-preview"><?php echo esc_html__( 'Refresh', 'recipe-seo-ai-pro' ); ?></button>
		</div>
		<div class="rsaip-rb-preview-controls">
			<label>
				<?php echo esc_html__( 'Preview servings', 'recipe-seo-ai-pro' ); ?>
				<input type="number" min="0.25" step="0.25" id="rsaip-rb-preview-servings" value="<?php echo esc_attr( (string) ( $recipe['servings'] ?? 4 ) ); ?>" />
			</label>
			<label>
				<?php echo esc_html__( 'Preview units', 'recipe-seo-ai-pro' ); ?>
				<select id="rsaip-rb-preview-units">
					<?php foreach ( $unit_systems as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button type="button" class="button" id="rsaip-rb-apply-servings"><?php echo esc_html__( 'Scale servings', 'recipe-seo-ai-pro' ); ?></button>
			<button type="button" class="button" id="rsaip-rb-apply-units"><?php echo esc_html__( 'Convert units', 'recipe-seo-ai-pro' ); ?></button>
		</div>
		<div id="rsaip-rb-preview" class="rsaip-rb-preview" aria-live="polite"></div>
		<p id="rsaip-rb-status-msg" class="rsaip-sub" role="status"></p>
	</aside>
</div>

<script type="application/json" id="rsaip-rb-initial"><?php echo wp_json_encode( $recipe ); ?></script>
<?php
View::render( 'partials/footer' );
