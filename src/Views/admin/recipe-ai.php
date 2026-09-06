<?php
/**
 * Admin: AI Recipe Assistant (Phase 5.2).
 *
 * @var string $title
 * @var bool   $ai_ready
 * @var string $ai_provider
 * @var int    $rb_recipe_id
 * @var int    $post_id
 * @var string $source_type
 * @var list<array<string,mixed>> $recipes
 * @var list<array<string,mixed>> $runs
 * @var array<string,string> $actions
 * @var array<string,string> $ingredient_modes
 * @var array<string,string> $instruction_modes
 * @var array<string,string> $rewrite_modes
 * @var array<string,string> $variations
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

<?php if ( empty( $ai_ready ) ) : ?>
	<div class="rsaip-alert rsaip-alert-warning" role="status">
		<span class="dashicons dashicons-warning" aria-hidden="true"></span>
		<div>
			<?php echo esc_html__( 'Configure an OpenAI-compatible AI provider under Settings before running assistants.', 'recipe-seo-ai-pro' ); ?>
			<?php if ( ! empty( $ai_provider ) ) : ?>
				<?php echo ' ' . esc_html( sprintf( /* translators: %s provider */ __( 'Current provider: %s', 'recipe-seo-ai-pro' ), (string) $ai_provider ) ); ?>
			<?php endif; ?>
		</div>
	</div>
<?php endif; ?>

<div class="rsaip-rai-layout">
	<div class="rsaip-panel rsaip-rai-controls">
		<h2><?php echo esc_html__( 'Source recipe', 'recipe-seo-ai-pro' ); ?></h2>

		<?php
		$source_type = isset( $source_type ) ? sanitize_key( (string) $source_type ) : 'builder';
		$post_id     = isset( $post_id ) ? (int) $post_id : 0;
		if ( ! in_array( $source_type, array( 'builder', 'post' ), true ) ) {
			$source_type = 'builder';
		}
		?>

		<fieldset class="rsaip-rai-source-type">
			<legend><?php echo esc_html__( 'Source', 'recipe-seo-ai-pro' ); ?></legend>
			<label class="rsaip-rai-source-option">
				<input type="radio" name="rsaip_rai_source" value="builder" <?php checked( $source_type, 'builder' ); ?> />
				<?php echo esc_html__( 'Recipe Builder', 'recipe-seo-ai-pro' ); ?>
			</label>
			<label class="rsaip-rai-source-option">
				<input type="radio" name="rsaip_rai_source" value="post" <?php checked( $source_type, 'post' ); ?> />
				<?php echo esc_html__( 'WordPress Posts', 'recipe-seo-ai-pro' ); ?>
			</label>
		</fieldset>

		<div id="rsaip-rai-source-builder" class="rsaip-rai-source-panel" <?php echo $source_type === 'post' ? 'hidden' : ''; ?>>
			<label for="rsaip-rai-recipe">
				<?php echo esc_html__( 'Recipe Builder recipe', 'recipe-seo-ai-pro' ); ?>
			</label>
			<select id="rsaip-rai-recipe" class="rsaip-input">
				<option value="0"><?php echo esc_html__( '— Select —', 'recipe-seo-ai-pro' ); ?></option>
				<?php foreach ( $recipes as $row ) : ?>
					<option value="<?php echo esc_attr( (string) ( $row['id'] ?? 0 ) ); ?>" <?php selected( (int) $rb_recipe_id, (int) ( $row['id'] ?? 0 ) ); ?>>
						<?php echo esc_html( (string) ( $row['title'] ?? '' ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div id="rsaip-rai-source-post" class="rsaip-rai-source-panel" <?php echo $source_type === 'builder' ? 'hidden' : ''; ?>>
			<div
				id="rsaip-rai-post-picker"
				class="rsaip-rai-picker"
				role="region"
				aria-label="<?php echo esc_attr__( 'WordPress post picker', 'recipe-seo-ai-pro' ); ?>"
			>
				<div class="rsaip-rai-picker-search">
					<label for="rsaip-rai-post-search" class="screen-reader-text"><?php echo esc_html__( 'Search posts', 'recipe-seo-ai-pro' ); ?></label>
					<input
						type="search"
						id="rsaip-rai-post-search"
						class="rsaip-input"
						placeholder="<?php echo esc_attr__( 'Search by title, ID, or slug…', 'recipe-seo-ai-pro' ); ?>"
						autocomplete="off"
						aria-controls="rsaip-rai-picker-results"
						aria-autocomplete="list"
					/>
					<span class="rsaip-rai-picker-spinner" id="rsaip-rai-picker-spinner" hidden aria-hidden="true"></span>
				</div>

				<div class="rsaip-rai-picker-filters" role="group" aria-label="<?php echo esc_attr__( 'Filters', 'recipe-seo-ai-pro' ); ?>">
					<select id="rsaip-rai-filter-status" aria-label="<?php echo esc_attr__( 'Status', 'recipe-seo-ai-pro' ); ?>">
						<option value=""><?php echo esc_html__( 'All statuses', 'recipe-seo-ai-pro' ); ?></option>
						<option value="publish"><?php echo esc_html__( 'Published', 'recipe-seo-ai-pro' ); ?></option>
						<option value="draft"><?php echo esc_html__( 'Draft', 'recipe-seo-ai-pro' ); ?></option>
						<option value="private"><?php echo esc_html__( 'Private', 'recipe-seo-ai-pro' ); ?></option>
					</select>
					<select id="rsaip-rai-filter-category" aria-label="<?php echo esc_attr__( 'Category', 'recipe-seo-ai-pro' ); ?>">
						<option value="0"><?php echo esc_html__( 'All categories', 'recipe-seo-ai-pro' ); ?></option>
					</select>
					<select id="rsaip-rai-filter-date" aria-label="<?php echo esc_attr__( 'Date', 'recipe-seo-ai-pro' ); ?>">
						<option value=""><?php echo esc_html__( 'Any date', 'recipe-seo-ai-pro' ); ?></option>
						<option value="7d"><?php echo esc_html__( 'Last 7 days', 'recipe-seo-ai-pro' ); ?></option>
						<option value="30d"><?php echo esc_html__( 'Last 30 days', 'recipe-seo-ai-pro' ); ?></option>
						<option value="90d"><?php echo esc_html__( 'Last 90 days', 'recipe-seo-ai-pro' ); ?></option>
						<option value="year"><?php echo esc_html__( 'This year', 'recipe-seo-ai-pro' ); ?></option>
					</select>
					<label class="rsaip-rai-filter-check">
						<input type="checkbox" id="rsaip-rai-filter-recipe" />
						<span><?php echo esc_html__( 'Recipe detected only', 'recipe-seo-ai-pro' ); ?></span>
					</label>
				</div>

				<div class="rsaip-rai-picker-shelves" id="rsaip-rai-picker-shelves" hidden></div>

				<div
					id="rsaip-rai-picker-results"
					class="rsaip-rai-picker-results"
					role="listbox"
					aria-label="<?php echo esc_attr__( 'Search results', 'recipe-seo-ai-pro' ); ?>"
					tabindex="0"
				>
					<div class="rsaip-rai-picker-empty" id="rsaip-rai-picker-empty">
						<?php echo esc_html__( 'Start typing to search posts by title, ID, or slug.', 'recipe-seo-ai-pro' ); ?>
					</div>
				</div>

				<nav class="rsaip-rai-picker-pager" id="rsaip-rai-picker-pager" hidden aria-label="<?php echo esc_attr__( 'Results pagination', 'recipe-seo-ai-pro' ); ?>">
					<button type="button" class="button" id="rsaip-rai-picker-prev"><?php echo esc_html__( 'Previous', 'recipe-seo-ai-pro' ); ?></button>
					<span id="rsaip-rai-picker-page-label"></span>
					<button type="button" class="button" id="rsaip-rai-picker-next"><?php echo esc_html__( 'Next', 'recipe-seo-ai-pro' ); ?></button>
				</nav>

				<div class="rsaip-rai-picker-preview" id="rsaip-rai-picker-preview" hidden>
					<div class="rsaip-rai-preview-media">
						<img id="rsaip-rai-preview-img" alt="" loading="lazy" hidden />
						<div class="rsaip-rai-preview-placeholder" id="rsaip-rai-preview-ph" aria-hidden="true"></div>
					</div>
					<div class="rsaip-rai-preview-body">
						<h3 id="rsaip-rai-preview-title"></h3>
						<p id="rsaip-rai-preview-excerpt" class="rsaip-sub"></p>
						<ul class="rsaip-rai-preview-meta" id="rsaip-rai-preview-meta"></ul>
						<div class="rsaip-rai-preview-actions">
							<button type="button" class="button button-primary" id="rsaip-rai-preview-load"><?php echo esc_html__( 'Load', 'recipe-seo-ai-pro' ); ?></button>
							<a class="button" id="rsaip-rai-preview-open" href="#" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open Post', 'recipe-seo-ai-pro' ); ?></a>
							<button type="button" class="button-link" id="rsaip-rai-preview-fav" aria-pressed="false">☆</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Hidden select kept for backward-compatible JS wiring -->
			<select id="rsaip-rai-post" class="screen-reader-text" tabindex="-1" aria-hidden="true">
				<option value="0"><?php echo esc_html__( '— Select a post —', 'recipe-seo-ai-pro' ); ?></option>
				<?php if ( $post_id > 0 ) : ?>
					<?php
					$pre_post = get_post( $post_id );
					if ( $pre_post instanceof \WP_Post ) :
						?>
						<option value="<?php echo esc_attr( (string) $post_id ); ?>" selected>
							<?php echo esc_html( $pre_post->post_title ); ?>
						</option>
					<?php endif; ?>
				<?php endif; ?>
			</select>
		</div>

		<button type="button" class="button" id="rsaip-rai-load"><?php echo esc_html__( 'Load', 'recipe-seo-ai-pro' ); ?></button>
		<p class="rsaip-sub" id="rsaip-rai-recipe-label"></p>
		<input type="hidden" id="rsaip-rai-post-id" value="<?php echo esc_attr( (string) $post_id ); ?>" />

		<label for="rsaip-rai-action"><?php echo esc_html__( 'AI action', 'recipe-seo-ai-pro' ); ?></label>
		<select id="rsaip-rai-action" class="rsaip-input">
			<?php foreach ( $actions as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>

		<label for="rsaip-rai-mode"><?php echo esc_html__( 'Mode', 'recipe-seo-ai-pro' ); ?></label>
		<select id="rsaip-rai-mode" class="rsaip-input"></select>

		<label for="rsaip-rai-extra"><?php echo esc_html__( 'Extra notes (optional)', 'recipe-seo-ai-pro' ); ?></label>
		<textarea id="rsaip-rai-extra" rows="2"></textarea>

		<div class="rsaip-actions">
			<button type="button" class="button button-primary" id="rsaip-rai-run" <?php disabled( empty( $ai_ready ) ); ?>><?php echo esc_html__( 'Run AI', 'recipe-seo-ai-pro' ); ?></button>
			<button type="button" class="button" id="rsaip-rai-apply" disabled><?php echo esc_html__( 'Apply (backup first)', 'recipe-seo-ai-pro' ); ?></button>
			<button type="button" class="button" id="rsaip-rai-undo" disabled><?php echo esc_html__( 'Undo', 'recipe-seo-ai-pro' ); ?></button>
			<button type="button" class="button" id="rsaip-rai-history"><?php echo esc_html__( 'History', 'recipe-seo-ai-pro' ); ?></button>
		</div>
		<p id="rsaip-rai-status" class="rsaip-sub" role="status"></p>
		<input type="hidden" id="rsaip-rai-run-id" value="0" />
	</div>

	<div class="rsaip-panel rsaip-rai-compare">
		<h2><?php echo esc_html__( 'Compare', 'recipe-seo-ai-pro' ); ?></h2>
		<div class="rsaip-rai-compare-grid">
			<div>
				<h3><?php echo esc_html__( 'Original', 'recipe-seo-ai-pro' ); ?></h3>
				<pre id="rsaip-rai-original" class="rsaip-rai-pre"></pre>
			</div>
			<div>
				<h3><?php echo esc_html__( 'Optimized', 'recipe-seo-ai-pro' ); ?></h3>
				<pre id="rsaip-rai-optimized" class="rsaip-rai-pre"></pre>
			</div>
		</div>
		<h3><?php echo esc_html__( 'Diff', 'recipe-seo-ai-pro' ); ?></h3>
		<pre id="rsaip-rai-diff" class="rsaip-rai-pre"></pre>
		<h3><?php echo esc_html__( 'Analysis / suggestions', 'recipe-seo-ai-pro' ); ?></h3>
		<pre id="rsaip-rai-analysis" class="rsaip-rai-pre"></pre>
		<div id="rsaip-rai-scores" class="rsaip-rai-scores" hidden></div>
	</div>

	<aside class="rsaip-panel rsaip-rai-history-panel">
		<h2><?php echo esc_html__( 'Recent runs', 'recipe-seo-ai-pro' ); ?></h2>
		<ul id="rsaip-rai-runs" class="rsaip-rai-runs">
			<?php foreach ( $runs as $run ) : ?>
				<li>
					<button type="button" class="button-link rsaip-rai-open-run" data-id="<?php echo esc_attr( (string) ( $run['id'] ?? 0 ) ); ?>">
						#<?php echo esc_html( (string) ( $run['id'] ?? 0 ) ); ?>
						<?php echo esc_html( (string) ( $run['action_type'] ?? '' ) ); ?>
						<?php if ( ! empty( $run['mode'] ) ) : ?>
							(<?php echo esc_html( (string) $run['mode'] ); ?>)
						<?php endif; ?>
					</button>
					<span class="rsaip-sub"><?php echo esc_html( (string) ( $run['status'] ?? '' ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<h2><?php echo esc_html__( 'Versions', 'recipe-seo-ai-pro' ); ?></h2>
		<ul id="rsaip-rai-versions" class="rsaip-rai-runs"></ul>
	</aside>
</div>

<script type="application/json" id="rsaip-rai-mode-maps"><?php
	echo wp_json_encode(
		array(
			'ingredients'  => $ingredient_modes,
			'instructions' => $instruction_modes,
			'rewrite'      => $rewrite_modes,
			'variation'    => $variations,
			'improve'      => array( '' => __( 'Default', 'recipe-seo-ai-pro' ) ),
			'analyze'      => array( '' => __( 'Full analysis', 'recipe-seo-ai-pro' ) ),
			'nutrition'    => array( '' => __( 'Estimate + tips', 'recipe-seo-ai-pro' ) ),
		)
	);
?></script>
<?php
View::render( 'partials/footer' );
