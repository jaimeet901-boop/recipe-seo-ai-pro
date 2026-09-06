<?php
/**
 * Admin: AI Keyword Research (Phase 3.5).
 *
 * @var string                $title
 * @var bool                  $ai_ready
 * @var string                $ai_provider
 * @var int                   $project_id
 * @var list<array{id:int,name:string}> $projects
 * @var string                $locale
 * @var array<string, string> $categories
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
			<?php echo esc_html__( 'Configure an OpenAI-compatible AI provider, endpoint, model, and API key under Settings before running research.', 'recipe-seo-ai-pro' ); ?>
			<?php if ( ! empty( $ai_provider ) ) : ?>
				<?php echo ' ' . esc_html( sprintf( /* translators: %s: provider id */ __( 'Current provider: %s', 'recipe-seo-ai-pro' ), (string) $ai_provider ) ); ?>
			<?php endif; ?>
		</div>
	</div>
<?php endif; ?>

<div class="rsaip-panel">
	<h2><?php echo esc_html__( 'Research inputs', 'recipe-seo-ai-pro' ); ?></h2>
	<div class="rsaip-grid">
		<div>
			<label for="rsaip-kr-seed"><?php echo esc_html__( 'Seed topic / keyword', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-kr-seed" class="regular-text" maxlength="300" placeholder="<?php echo esc_attr__( 'e.g. air fryer chicken recipes', 'recipe-seo-ai-pro' ); ?>" />
		</div>
		<div>
			<label for="rsaip-kr-project"><?php echo esc_html__( 'Save to project', 'recipe-seo-ai-pro' ); ?></label>
			<select id="rsaip-kr-project" class="rsaip-input">
				<option value="0"><?php echo esc_html__( 'Select project…', 'recipe-seo-ai-pro' ); ?></option>
				<?php foreach ( $projects as $p ) : ?>
					<option value="<?php echo esc_attr( (string) $p['id'] ); ?>" <?php selected( (int) $project_id, (int) $p['id'] ); ?>>
						<?php echo esc_html( (string) $p['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div>
			<label for="rsaip-kr-audience"><?php echo esc_html__( 'Audience (optional)', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-kr-audience" class="regular-text" />
		</div>
		<div>
			<label for="rsaip-kr-niche"><?php echo esc_html__( 'Niche (optional)', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-kr-niche" class="regular-text" />
		</div>
		<div>
			<label for="rsaip-kr-language"><?php echo esc_html__( 'Language', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-kr-language" class="regular-text" value="<?php echo esc_attr( (string) $locale ); ?>" />
		</div>
		<div>
			<label for="rsaip-kr-country"><?php echo esc_html__( 'Country', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-kr-country" class="regular-text" maxlength="8" placeholder="US" />
		</div>
	</div>
	<label for="rsaip-kr-notes"><?php echo esc_html__( 'Notes (optional)', 'recipe-seo-ai-pro' ); ?></label>
	<textarea id="rsaip-kr-notes" rows="3"></textarea>
	<div class="rsaip-actions">
		<button type="button" class="button button-primary" id="rsaip-kr-generate" <?php disabled( empty( $ai_ready ) ); ?>>
			<?php echo esc_html__( 'Generate Keywords', 'recipe-seo-ai-pro' ); ?>
		</button>
		<span id="rsaip-kr-status" class="rsaip-sub"></span>
	</div>
</div>

<div class="rsaip-panel" id="rsaip-kr-preview" hidden>
	<div class="rsaip-actions">
		<button type="button" class="button" id="rsaip-kr-select-all"><?php echo esc_html__( 'Select all', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button" id="rsaip-kr-select-none"><?php echo esc_html__( 'Select none', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button button-primary" id="rsaip-kr-save"><?php echo esc_html__( 'Save Selected to Workspace', 'recipe-seo-ai-pro' ); ?></button>
		<a class="button" id="rsaip-kr-open-workspace" href="#"><?php echo esc_html__( 'Open Keyword Workspace', 'recipe-seo-ai-pro' ); ?></a>
	</div>
	<p id="rsaip-kr-entities" class="rsaip-note"></p>
	<table class="widefat striped rsaip-table">
		<thead>
			<tr>
				<th><input type="checkbox" id="rsaip-kr-check-all" /></th>
				<th><?php echo esc_html__( 'Keyword', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Category', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Intent', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Difficulty', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Priority', 'recipe-seo-ai-pro' ); ?></th>
				<th><?php echo esc_html__( 'Suggested cluster', 'recipe-seo-ai-pro' ); ?></th>
			</tr>
		</thead>
		<tbody id="rsaip-kr-body"></tbody>
	</table>
</div>
<?php
View::render( 'partials/footer' );
