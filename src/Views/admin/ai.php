<?php
/**
 * Admin: AI SEO Recommendations (+ bulk queue UI).
 *
 * @var string $title
 *
 * @package RecipeSeoAiPro
 */

use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

View::render( 'partials/header', array( 'title' => $title ) );
?>
<div class="rsaip-actions">
	<label class="rsaip-inline">
		<?php echo esc_html__( 'Post ID', 'recipe-seo-ai-pro' ); ?>
		<input type="number" min="1" class="small-text rsaip-input" data-key="ai_post_id" />
	</label>
	<button class="button rsaip-btn" data-action="rsaip_ai_analyze_post"><?php echo esc_html__( 'Analyze Post', 'recipe-seo-ai-pro' ); ?></button>
	<button class="button rsaip-btn" data-action="rsaip_ai_generate_titles"><?php echo esc_html__( 'Generate Titles', 'recipe-seo-ai-pro' ); ?></button>
	<button class="button button-primary rsaip-btn" data-action="rsaip_ai_recommendations"><?php echo esc_html__( 'Generate Recommendations', 'recipe-seo-ai-pro' ); ?></button>
	<button class="button rsaip-btn" data-action="rsaip_ai_generate_meta_desc"><?php echo esc_html__( 'Generate Meta Description', 'recipe-seo-ai-pro' ); ?></button>
	<button class="button rsaip-btn" data-action="rsaip_ai_generate_faq"><?php echo esc_html__( 'Generate FAQ', 'recipe-seo-ai-pro' ); ?></button>
	<button class="button button-primary rsaip-btn" data-action="rsaip_ai_fix_post" disabled="disabled" title="<?php echo esc_attr__( 'Fix With AI is disabled in Milestone 4.', 'recipe-seo-ai-pro' ); ?>"><?php echo esc_html__( 'Fix With AI', 'recipe-seo-ai-pro' ); ?></button>
</div>
<div id="rsaip-ai-result" class="rsaip-note" hidden></div>

<div class="rsaip-panel">
	<h2><?php echo esc_html__( 'AI Analysis', 'recipe-seo-ai-pro' ); ?></h2>
	<div id="rsaip-ai-analysis" class="rsaip-note"><?php echo esc_html__( 'Analyze a post for a read-only OpenAI SEO proposal (topic, keywords, titles, meta, headings, FAQ, ALT, links, gaps). Nothing is applied.', 'recipe-seo-ai-pro' ); ?></div>
</div>

<div class="rsaip-panel" id="rsaip-ai-suggestions-panel">
	<h2><?php echo esc_html__( 'Read-Only SEO Suggestions', 'recipe-seo-ai-pro' ); ?></h2>
	<p class="rsaip-sub"><?php echo esc_html__( 'Suggestions only — no changes are applied. Populated by Analyze Post from the unified SEO proposal.', 'recipe-seo-ai-pro' ); ?></p>
	<div id="rsaip-ai-suggest-headings" class="rsaip-note">
		<strong><?php echo esc_html__( 'Heading Suggestions', 'recipe-seo-ai-pro' ); ?></strong>
		<div class="rsaip-sub"><?php echo esc_html__( 'H2/H3 ideas with rationale. No Apply.', 'recipe-seo-ai-pro' ); ?></div>
	</div>
	<div id="rsaip-ai-suggest-faq" class="rsaip-note">
		<strong><?php echo esc_html__( 'FAQ Suggestions', 'recipe-seo-ai-pro' ); ?></strong>
		<div class="rsaip-sub"><?php echo esc_html__( 'Read-only Q&A. No insertion, no FAQ schema.', 'recipe-seo-ai-pro' ); ?></div>
	</div>
	<div id="rsaip-ai-suggest-alts" class="rsaip-note">
		<strong><?php echo esc_html__( 'Image ALT Suggestions', 'recipe-seo-ai-pro' ); ?></strong>
		<div class="rsaip-sub"><?php echo esc_html__( 'Featured/inline ALT ideas. No media writes.', 'recipe-seo-ai-pro' ); ?></div>
	</div>
	<div id="rsaip-ai-suggest-links" class="rsaip-note">
		<strong><?php echo esc_html__( 'Internal Link Suggestions', 'recipe-seo-ai-pro' ); ?></strong>
		<div class="rsaip-sub"><?php echo esc_html__( 'Anchor + target hint + reason. No URLs invented, no auto-linking.', 'recipe-seo-ai-pro' ); ?></div>
	</div>
	<div id="rsaip-ai-suggest-gaps" class="rsaip-note">
		<strong><?php echo esc_html__( 'Content Gaps', 'recipe-seo-ai-pro' ); ?></strong>
		<div class="rsaip-sub"><?php echo esc_html__( 'Observable gaps only. Suggestions — no content writes.', 'recipe-seo-ai-pro' ); ?></div>
	</div>
	<div id="rsaip-ai-suggest-recs" class="rsaip-note">
		<strong><?php echo esc_html__( 'SEO Recommendations', 'recipe-seo-ai-pro' ); ?></strong>
		<div class="rsaip-sub"><?php echo esc_html__( 'applies_via limited to title / meta / keywords / none. No Apply for headings, FAQ, ALT, links, or schema.', 'recipe-seo-ai-pro' ); ?></div>
	</div>
</div>

<div class="rsaip-panel">
	<h2><?php echo esc_html__( 'Title Optimization', 'recipe-seo-ai-pro' ); ?></h2>
	<div id="rsaip-ai-title-list" class="rsaip-note"><?php echo esc_html__( 'Generate titles from the unified SEO proposal (recommended + alternatives, max 60 characters).', 'recipe-seo-ai-pro' ); ?></div>
	<div class="rsaip-actions">
		<input type="text" id="rsaip-ai-title-input" class="regular-text" placeholder="<?php echo esc_attr__( 'Choose or edit a title', 'recipe-seo-ai-pro' ); ?>"/>
		<button class="button rsaip-btn" data-action="rsaip_ai_propose_title"><?php echo esc_html__( 'Preview Title', 'recipe-seo-ai-pro' ); ?></button>
		<button class="button rsaip-btn rsaip-apply-btn" data-action="rsaip_ai_apply_title" data-apply-type="title" disabled="disabled" title="<?php echo esc_attr__( 'Disabled until a fresh Preview. Safe Article Mode also blocks Apply.', 'recipe-seo-ai-pro' ); ?>"><?php echo esc_html__( 'Apply Title', 'recipe-seo-ai-pro' ); ?></button>
	</div>
	<div id="rsaip-ai-title-preview" class="rsaip-note"><?php echo esc_html__( 'Preview compares original vs normalized proposal. Apply stays frozen under Safe Article Mode.', 'recipe-seo-ai-pro' ); ?></div>
</div>

<div class="rsaip-panel">
	<h2><?php echo esc_html__( 'Focus Keywords', 'recipe-seo-ai-pro' ); ?></h2>
	<p class="rsaip-sub"><?php echo esc_html__( 'Primary focus keyword is required. Secondary keywords are optional — only checked items are proposed/applied. Entities are display-only.', 'recipe-seo-ai-pro' ); ?></p>
	<div id="rsaip-ai-keywords-picker" class="rsaip-note"><?php echo esc_html__( 'Click “Generate Keywords” to load primary / secondary keywords from the unified SEO proposal.', 'recipe-seo-ai-pro' ); ?></div>
	<textarea id="rsaip-ai-keywords" rows="2" spellcheck="true" readonly="readonly" placeholder="<?php echo esc_attr__( 'Apply candidate (primary + selected secondaries) appears here.', 'recipe-seo-ai-pro' ); ?>"></textarea>
	<div class="rsaip-actions">
		<select id="rsaip-ai-keywords-target" class="rsaip-input">
			<option value="auto"><?php echo esc_html__( 'Auto (detect plugin)', 'recipe-seo-ai-pro' ); ?></option>
			<option value="rankmath"><?php echo esc_html__( 'Rank Math', 'recipe-seo-ai-pro' ); ?></option>
			<option value="yoast"><?php echo esc_html__( 'Yoast', 'recipe-seo-ai-pro' ); ?></option>
		</select>
		<button class="button rsaip-btn" data-action="rsaip_ai_generate_keywords"><?php echo esc_html__( 'Generate Keywords', 'recipe-seo-ai-pro' ); ?></button>
		<button class="button rsaip-btn" data-action="rsaip_ai_propose_keywords"><?php echo esc_html__( 'Preview Keywords', 'recipe-seo-ai-pro' ); ?></button>
		<button class="button button-primary rsaip-btn rsaip-apply-btn" data-action="rsaip_ai_apply_keywords" data-apply-type="keywords" disabled="disabled" title="<?php echo esc_attr__( 'Disabled until a fresh Preview. Safe Article Mode also blocks Apply.', 'recipe-seo-ai-pro' ); ?>"><?php echo esc_html__( 'Apply Keywords', 'recipe-seo-ai-pro' ); ?></button>
	</div>
	<div id="rsaip-ai-keywords-status" class="rsaip-sub"></div>
	<div id="rsaip-ai-keywords-preview" class="rsaip-note"><?php echo esc_html__( 'Preview shows original vs normalized keyword list. Owner is server-resolved. Apply stays frozen.', 'recipe-seo-ai-pro' ); ?></div>
</div>

<div class="rsaip-panel">
	<h2><?php echo esc_html__( 'Meta Description', 'recipe-seo-ai-pro' ); ?></h2>
	<textarea id="rsaip-ai-metadesc" rows="3" spellcheck="true" placeholder="<?php echo esc_attr__( 'Click “Generate Meta Description” to fill this field.', 'recipe-seo-ai-pro' ); ?>"></textarea>
	<div class="rsaip-actions">
		<select id="rsaip-ai-metadesc-target" class="rsaip-input">
			<option value="auto"><?php echo esc_html__( 'Auto (detect plugin)', 'recipe-seo-ai-pro' ); ?></option>
			<option value="rankmath"><?php echo esc_html__( 'Rank Math', 'recipe-seo-ai-pro' ); ?></option>
			<option value="yoast"><?php echo esc_html__( 'Yoast', 'recipe-seo-ai-pro' ); ?></option>
		</select>
		<button class="button rsaip-btn" data-action="rsaip_ai_propose_meta_desc"><?php echo esc_html__( 'Preview Meta', 'recipe-seo-ai-pro' ); ?></button>
		<button class="button button-primary rsaip-btn rsaip-apply-btn" data-action="rsaip_ai_apply_meta_desc" data-apply-type="meta_description" disabled="disabled" title="<?php echo esc_attr__( 'Disabled until a fresh Preview. Safe Article Mode also blocks Apply.', 'recipe-seo-ai-pro' ); ?>"><?php echo esc_html__( 'Apply Meta Description', 'recipe-seo-ai-pro' ); ?></button>
		<button class="button rsaip-btn" data-action="rsaip_ai_bulk_apply_meta_desc" data-limit="20" disabled="disabled" title="<?php echo esc_attr__( 'Bulk meta Apply is not enabled in Milestone 4.', 'recipe-seo-ai-pro' ); ?>"><?php echo esc_html__( 'Apply Missing Meta (Next 20)', 'recipe-seo-ai-pro' ); ?></button>
	</div>
	<div id="rsaip-ai-meta-status" class="rsaip-sub"></div>
	<div id="rsaip-ai-meta-preview" class="rsaip-note"><?php echo esc_html__( 'Preview shows original vs normalized meta. Apply stays frozen under Safe Article Mode.', 'recipe-seo-ai-pro' ); ?></div>
</div>

<div class="rsaip-panel">
	<h2><?php echo esc_html__( 'FAQ Suggestions', 'recipe-seo-ai-pro' ); ?></h2>
	<div id="rsaip-ai-faq-result" class="rsaip-note"><?php echo esc_html__( 'Click “Generate FAQ” to get suggested Q&A for the post.', 'recipe-seo-ai-pro' ); ?></div>
</div>

<div class="rsaip-panel">
	<h2><?php echo esc_html__( 'NEW AI Article Generator (Preview)', 'recipe-seo-ai-pro' ); ?></h2>
	<p class="rsaip-sub"><?php echo esc_html__( 'Recipe-aware + SEO-aware generation. Generate a Preview first, then explicitly Create Draft. No automatic draft creation.', 'recipe-seo-ai-pro' ); ?></p>
	<div class="rsaip-actions">
		<label class="rsaip-inline">
			<?php echo esc_html__( 'Template', 'recipe-seo-ai-pro' ); ?>
			<select id="rsaip-new-article-template" class="rsaip-input">
				<option value="general"><?php echo esc_html__( 'General Article', 'recipe-seo-ai-pro' ); ?></option>
				<option value="recipe_seo_midjourney"><?php echo esc_html__( 'Recipe SEO (Midjourney Prompts)', 'recipe-seo-ai-pro' ); ?></option>
			</select>
		</label>
	</div>
	<div class="rsaip-grid">
		<div>
			<label><?php echo esc_html__( 'Article Title / Topic', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-new-article-title" class="regular-text" placeholder="<?php echo esc_attr__( 'e.g. Quinoa Black Bean Salad', 'recipe-seo-ai-pro' ); ?>" />
		</div>
		<div>
			<label><?php echo esc_html__( 'Keywords', 'recipe-seo-ai-pro' ); ?></label>
			<textarea id="rsaip-new-article-keywords" rows="3" placeholder="<?php echo esc_attr__( 'Primary keyword first. One per line or comma separated.', 'recipe-seo-ai-pro' ); ?>"></textarea>
		</div>
		<div>
			<label><?php echo esc_html__( 'Target Word Count', 'recipe-seo-ai-pro' ); ?></label>
			<input type="number" min="400" max="4000" step="50" id="rsaip-new-article-word-count" class="small-text" value="1000" />
		</div>
	</div>
	<label><?php echo esc_html__( 'Optional recipe HTML / content context (read-only)', 'recipe-seo-ai-pro' ); ?></label>
	<textarea id="rsaip-new-article-content" rows="4" placeholder="<?php echo esc_attr__( 'Paste existing recipe HTML or JSON-LD to ground known facts. Leave empty for a brief-only preview.', 'recipe-seo-ai-pro' ); ?>"></textarea>
	<label><?php echo esc_html__( 'Image URLs (data only — not downloaded)', 'recipe-seo-ai-pro' ); ?></label>
	<textarea id="rsaip-new-article-images" rows="3" placeholder="<?php echo esc_attr__( 'Paste one image URL per line (optional)', 'recipe-seo-ai-pro' ); ?>"></textarea>
	<div class="rsaip-actions">
		<button type="button" class="button button-primary rsaip-btn" id="rsaip-new-article-preview-btn" data-action="rsaip_ai_article_preview_generate"><?php echo esc_html__( 'Generate Preview', 'recipe-seo-ai-pro' ); ?></button>
		<button type="button" class="button rsaip-btn" id="rsaip-new-article-create-draft-btn" data-action="rsaip_ai_article_create_draft" disabled hidden><?php echo esc_html__( 'Create Draft (NEW Generator)', 'recipe-seo-ai-pro' ); ?></button>
	</div>
	<input type="hidden" id="rsaip-new-article-proposal-id" value="" />
	<input type="hidden" id="rsaip-new-article-fingerprint" value="" />
	<div id="rsaip-new-article-preview-status" class="rsaip-sub" role="status" aria-live="polite"></div>
	<div id="rsaip-new-article-draft-status" class="rsaip-sub" role="status" aria-live="polite"></div>
	<div id="rsaip-new-article-preview" class="rsaip-note"><?php echo esc_html__( 'Submit a brief to generate a validated article preview. Create Draft appears after a successful preview.', 'recipe-seo-ai-pro' ); ?></div>
</div>

<div class="rsaip-panel">
	<h2><?php echo esc_html__( 'Legacy Article Generator', 'recipe-seo-ai-pro' ); ?></h2>
	<p class="rsaip-sub"><?php echo esc_html__( 'Existing generator (unchanged). Prefer the NEW preview flow above when testing the upgraded architecture.', 'recipe-seo-ai-pro' ); ?></p>
	<div class="rsaip-actions">
		<label class="rsaip-inline">
			<?php echo esc_html__( 'Template', 'recipe-seo-ai-pro' ); ?>
			<select id="rsaip-article-template" class="rsaip-input">
				<option value="general"><?php echo esc_html__( 'General Article', 'recipe-seo-ai-pro' ); ?></option>
				<option value="recipe_seo_midjourney"><?php echo esc_html__( 'Recipe SEO (Midjourney Prompts)', 'recipe-seo-ai-pro' ); ?></option>
			</select>
		</label>
	</div>
	<div class="rsaip-grid">
		<div>
			<label><?php echo esc_html__( 'Article Title', 'recipe-seo-ai-pro' ); ?></label>
			<input type="text" id="rsaip-article-title" class="regular-text" placeholder="<?php echo esc_attr__( 'Enter the article title', 'recipe-seo-ai-pro' ); ?>" />
		</div>
		<div>
			<label><?php echo esc_html__( 'Keywords', 'recipe-seo-ai-pro' ); ?></label>
			<textarea id="rsaip-article-keywords" rows="3" placeholder="<?php echo esc_attr__( 'One keyword per line or comma separated', 'recipe-seo-ai-pro' ); ?>"></textarea>
		</div>
		<div>
			<label><?php echo esc_html__( 'Target Word Count', 'recipe-seo-ai-pro' ); ?></label>
			<input type="number" min="400" max="4000" step="50" id="rsaip-article-word-count" class="small-text" value="1000" />
		</div>
	</div>
	<label><?php echo esc_html__( 'Image URLs', 'recipe-seo-ai-pro' ); ?></label>
	<textarea id="rsaip-article-images" rows="4" placeholder="<?php echo esc_attr__( 'Paste one image URL per line', 'recipe-seo-ai-pro' ); ?>"></textarea>
	<div class="rsaip-actions">
		<button class="button rsaip-btn" data-action="rsaip_ai_generate_article"><?php echo esc_html__( 'Generate Professional Article', 'recipe-seo-ai-pro' ); ?></button>
		<button class="button button-primary rsaip-btn" data-action="rsaip_ai_create_article_draft"><?php echo esc_html__( 'Create Draft Automatically', 'recipe-seo-ai-pro' ); ?></button>
	</div>
	<div id="rsaip-article-generator-status" class="rsaip-sub"></div>
	<div id="rsaip-article-generator-result" class="rsaip-note"><?php echo esc_html__( 'Enter title, keywords, and image URLs to generate a full draft article.', 'recipe-seo-ai-pro' ); ?></div>
</div>

<div class="rsaip-panel">
	<h2><?php echo esc_html__( 'Recipe Table', 'recipe-seo-ai-pro' ); ?></h2>
	<div class="rsaip-actions">
		<button class="button rsaip-btn" data-action="rsaip_recipe_generate_card" data-source="ai-post"><?php echo esc_html__( 'Generate Recipe Table', 'recipe-seo-ai-pro' ); ?></button>
		<button class="button button-primary rsaip-btn" data-action="rsaip_recipe_insert_card" data-source="ai-post"><?php echo esc_html__( 'Insert Recipe Table', 'recipe-seo-ai-pro' ); ?></button>
	</div>
	<div id="rsaip-ai-recipe-card" class="rsaip-note"><?php echo esc_html__( 'Generate a recipe card/table from the current post content.', 'recipe-seo-ai-pro' ); ?></div>
</div>

<div class="rsaip-panel">
	<h2><?php echo esc_html__( 'Bulk Optimization Queue', 'recipe-seo-ai-pro' ); ?></h2>
	<div class="rsaip-actions">
		<label class="rsaip-inline">
			<?php echo esc_html__( 'Limit', 'recipe-seo-ai-pro' ); ?>
			<input type="number" min="1" max="500" class="small-text rsaip-input" data-key="bulk_limit" value="100" />
		</label>
		<button class="button rsaip-btn" data-action="rsaip_bulk_enqueue_fix_queue"><?php echo esc_html__( 'Queue Posts', 'recipe-seo-ai-pro' ); ?></button>
		<button class="button rsaip-btn" data-action="rsaip_bulk_process_queue"><?php echo esc_html__( 'Process Queue Now', 'recipe-seo-ai-pro' ); ?></button>
		<button class="button rsaip-btn" data-action="rsaip_bulk_queue_stats"><?php echo esc_html__( 'Refresh Queue Stats', 'recipe-seo-ai-pro' ); ?></button>
	</div>
	<div id="rsaip-bulk-queue-status" class="rsaip-note"><?php echo esc_html__( 'Queue stats will appear here.', 'recipe-seo-ai-pro' ); ?></div>
</div>

<div id="rsaip-ai-result" class="rsaip-panel"></div>
<?php
View::render( 'partials/footer' );
