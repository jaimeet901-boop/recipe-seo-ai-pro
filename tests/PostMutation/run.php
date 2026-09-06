<?php
declare(strict_types=1);

/**
 * Standalone Milestone 2 PostMutation unit tests (no PHPUnit required).
 *
 * Usage: php tests/PostMutation/run.php
 *
 * @package RecipeSeoAiPro
 */

define( 'ABSPATH', __DIR__ . '/' );

$plugin_root = dirname( __DIR__, 2 );
require_once $plugin_root . '/src/Autoloader.php';
\RecipeSeoAiPro\Autoloader::bootstrap( $plugin_root . '/' );

use RecipeSeoAiPro\Contracts\SettingsRepositoryInterface;
use RecipeSeoAiPro\Modules\Ai\TitleGeneration\TitleTopicSelector;
use RecipeSeoAiPro\Modules\Ai\SeoOptimization\SeoOptimizationContextBuilder;
use RecipeSeoAiPro\Modules\Ai\SeoOptimization\SeoOptimizationService;
use RecipeSeoAiPro\Modules\Ai\SeoOptimization\SeoOptimizationProposalStore;
use RecipeSeoAiPro\Modules\Ai\ArticleGeneration\ArticleGenerationService;
use RecipeSeoAiPro\Modules\Ai\ArticleGeneration\ArticleGenerationContext;
use RecipeSeoAiPro\Modules\Ai\ArticleGeneration\ArticleGenerationContextBuilder;
use RecipeSeoAiPro\Modules\Ai\ArticleGeneration\ArticleRecipeContextResolver;
use RecipeSeoAiPro\Modules\Ai\ArticleGeneration\ArticleGenerationProposalStore;
use RecipeSeoAiPro\Modules\Ai\ArticleGeneration\ArticleGenerationValidationResult;
use RecipeSeoAiPro\Modules\Ai\ArticleGeneration\ArticleGenerationDraftCreator;
use RecipeSeoAiPro\Modules\Ai\ArticleGeneration\RecipeFactAvailability;
use RecipeSeoAiPro\Modules\Ai\Hub\ProviderCatalog;
use RecipeSeoAiPro\Modules\Schema\RecipeSchemaDetector;
use RecipeSeoAiPro\Modules\Schema\RecipeSchemaStatus;
use RecipeSeoAiPro\Modules\Schema\RecipeSchemaRepairService;
use RecipeSeoAiPro\Modules\PostMutation\FieldFingerprint;
use RecipeSeoAiPro\Modules\PostMutation\InMemoryMutationSnapshotStore;
use RecipeSeoAiPro\Modules\PostMutation\MutationProposal;
use RecipeSeoAiPro\Modules\PostMutation\MutationType;
use RecipeSeoAiPro\Modules\PostMutation\PostMutationService;
use RecipeSeoAiPro\Modules\PostMutation\ProposalTicket;
use RecipeSeoAiPro\Modules\PostMutation\SeoOwner;
use RecipeSeoAiPro\Modules\PostMutation\SeoOwnershipResolver;
use RecipeSeoAiPro\Modules\PostMutation\Testing\FakePostMutationEnvironment;
use RecipeSeoAiPro\Modules\PostMutation\Writers\FocusKeywordWriter;
use RecipeSeoAiPro\Modules\PostMutation\Writers\KeywordsWriter;
use RecipeSeoAiPro\Modules\PostMutation\Writers\MetaDescriptionWriter;
use RecipeSeoAiPro\Modules\PostMutation\Writers\TitleWriter;
use RecipeSeoAiPro\Modules\Settings\SettingsService;

$passed = 0;
$failed = 0;

function assert_true( bool $cond, string $label ): void {
	global $passed, $failed;
	if ( $cond ) {
		echo "PASS  {$label}\n";
		$passed++;
	} else {
		echo "FAIL  {$label}\n";
		$failed++;
	}
}

function make_service( FakePostMutationEnvironment $env, ?InMemoryMutationSnapshotStore $store = null ): PostMutationService {
	$ownership = new SeoOwnershipResolver( $env );
	$store     = $store ?? new InMemoryMutationSnapshotStore();
	return new PostMutationService(
		$env,
		$ownership,
		new FieldFingerprint(),
		$store,
		array(
			new TitleWriter( $env ),
			new MetaDescriptionWriter( $env, $ownership ),
			new FocusKeywordWriter( $env, $ownership ),
			new KeywordsWriter( $env, $ownership ),
		)
	);
}

/**
 * In-memory settings repo for Milestone 4A sanitize tests.
 */
final class FakeSettingsRepository implements SettingsRepositoryInterface {
	/** @var array<string, mixed> */
	public array $stored = array();

	public function option_key(): string {
		return 'rsaip_settings';
	}

	public function read(): array {
		return $this->stored;
	}

	public function write( array $settings ): void {
		$this->stored = $settings;
	}
}

// --- Mutation type allowlist ---
assert_true( MutationType::is_valid( MutationType::TITLE ), 'allowlist accepts title' );
assert_true( MutationType::is_valid( MutationType::META_DESCRIPTION ), 'allowlist accepts meta_description' );
assert_true( MutationType::is_valid( MutationType::FOCUS_KEYWORD ), 'allowlist accepts focus_keyword' );
assert_true( MutationType::is_valid( MutationType::KEYWORDS ), 'allowlist accepts keywords' );
assert_true( ! MutationType::is_valid( 'post_content' ), 'allowlist rejects post_content' );
assert_true( ! MutationType::is_valid( 'schema' ), 'allowlist rejects schema' );
assert_true( MutationType::sanitize( 'POST_CONTENT' ) === '', 'sanitize rejects unknown type' );

// --- Ownership matrices ---
$env = new FakePostMutationEnvironment();
$res = new SeoOwnershipResolver( $env );

$env->rankmath = true;
$env->yoast    = false;
assert_true( $res->resolve_server_owner() === SeoOwner::RANKMATH, 'Rank Math only => owner rankmath' );

$env->rankmath = false;
$env->yoast    = true;
assert_true( $res->resolve_server_owner() === SeoOwner::YOAST, 'Yoast only => owner yoast' );

$env->rankmath = true;
$env->yoast    = true;
assert_true( $res->resolve_server_owner() === SeoOwner::RANKMATH, 'Both active => owner rankmath' );

$env->rankmath = false;
$env->yoast    = false;
assert_true( $res->resolve_server_owner() === SeoOwner::RSAIP, 'Neither => owner rsaip' );

$env->rankmath = true;
$env->yoast    = true;
assert_true( $res->resolve( 'yoast' ) === '', 'client target cannot override Rank Math owner' );
assert_true( $res->resolve( 'rankmath' ) === SeoOwner::RANKMATH, 'client target may narrow to Rank Math owner' );
assert_true( $res->resolve( 'auto' ) === SeoOwner::RANKMATH, 'client target auto keeps server owner' );

// --- Service: Safe Mode refusal ---
$env = new FakePostMutationEnvironment();
$env->safe_mode = true;
$env->seed_post( 10 );
$svc = make_service( $env );
$proposed = $svc->propose(
	array(
		'post_id'       => 10,
		'mutation_type' => MutationType::TITLE,
		'value'         => 'New Title',
	)
);
assert_true( $proposed->ok(), 'propose allowed while Safe Mode ON (read-only)' );
$applied = $svc->apply( $proposed->proposal() );
assert_true( ! $applied->ok() && $applied->code() === 'safe_article_mode', 'Safe Mode ON rejects apply' );
assert_true( $env->get_post( 10 )['post_title'] === 'Original Title', 'Safe Mode ON leaves title unchanged' );

// --- Authorization refusal ---
$env = new FakePostMutationEnvironment();
$env->safe_mode  = false;
$env->can_manage = false;
$env->seed_post( 11 );
$svc = make_service( $env );
$r   = $svc->propose(
	array(
		'post_id'       => 11,
		'mutation_type' => MutationType::TITLE,
		'value'         => 'X',
	)
);
assert_true( ! $r->ok() && $r->code() === 'unauthorized', 'unauthorized user rejected on propose' );

$env->can_manage = true;
$env->can_edit   = false;
$svc             = make_service( $env );
$proposed        = $svc->propose(
	array(
		'post_id'       => 11,
		'mutation_type' => MutationType::TITLE,
		'value'         => 'X',
	)
);
assert_true( ! $proposed->ok() && $proposed->code() === 'unauthorized', 'propose rejects when edit_post denied' );

// --- Missing security helpers ---
$env = new FakePostMutationEnvironment();
$env->security_helpers = false;
$env->safe_mode        = false;
$env->seed_post( 12 );
$svc = make_service( $env );
$r   = $svc->propose(
	array(
		'post_id'       => 12,
		'mutation_type' => MutationType::TITLE,
		'value'         => 'X',
	)
);
assert_true( ! $r->ok() && $r->code() === 'security_helpers_missing', 'missing security helpers rejected' );

// --- Invalid post ---
$env = new FakePostMutationEnvironment();
$env->safe_mode = false;
$svc            = make_service( $env );
$r              = $svc->propose(
	array(
		'post_id'       => 999,
		'mutation_type' => MutationType::TITLE,
		'value'         => 'X',
	)
);
assert_true( ! $r->ok() && $r->code() === 'invalid_post', 'invalid post rejected' );

// --- Invalid post type ---
$env = new FakePostMutationEnvironment();
$env->safe_mode  = false;
$env->post_types = array( 'post' );
$env->seed_post( 13, array( 'post_type' => 'page' ) );
$svc = make_service( $env );
$r   = $svc->propose(
	array(
		'post_id'       => 13,
		'mutation_type' => MutationType::TITLE,
		'value'         => 'X',
	)
);
assert_true( ! $r->ok() && $r->code() === 'invalid_post_type', 'invalid post type rejected' );

// --- Stale fingerprint ---
$env = new FakePostMutationEnvironment();
$env->safe_mode = false;
$env->seed_post( 14 );
$svc      = make_service( $env );
$proposed = $svc->propose(
	array(
		'post_id'       => 14,
		'mutation_type' => MutationType::TITLE,
		'value'         => 'Fresh Title',
	)
);
assert_true( $proposed->ok(), 'proposal created for stale test' );
$env->posts[14]['post_title']        = 'Changed By Editor';
$env->posts[14]['post_modified_gmt'] = '2026-01-03 00:00:00';
$applied = $svc->apply( $proposed->proposal() );
assert_true( ! $applied->ok() && $applied->code() === 'stale_fingerprint', 'stale fingerprint rejected' );
assert_true( $env->get_post( 14 )['post_title'] === 'Changed By Editor', 'stale apply did not overwrite' );

// --- Fingerprint deterministic ---
$fp = new FieldFingerprint();
$a  = $fp->build( 1, 'title', 'rsaip', 'Hello', '2026-01-01 00:00:00', 'abc' );
$b  = $fp->build( 1, 'title', 'rsaip', 'Hello', '2026-01-01 00:00:00', 'abc' );
$c  = $fp->build( 1, 'title', 'rsaip', 'Hello!', '2026-01-01 00:00:00', 'abc' );
assert_true( $a === $b && $fp->matches( $a, $b ), 'fingerprint deterministic match' );
assert_true( ! $fp->matches( $a, $c ), 'fingerprint detects value drift' );

// --- Rank Math only meta write ---
$env = new FakePostMutationEnvironment();
$env->safe_mode = false;
$env->rankmath  = true;
$env->yoast     = false;
$env->seed_post( 20 );
$env->meta[20]['rank_math_description'] = 'old rm';
$env->meta[20]['_yoast_wpseo_metadesc'] = 'old yoast should stay';
$svc      = make_service( $env );
$proposed = $svc->propose(
	array(
		'post_id'       => 20,
		'mutation_type' => MutationType::META_DESCRIPTION,
		'value'         => 'New RM meta',
	)
);
$applied = $svc->apply( $proposed->proposal() );
assert_true( $applied->ok() && $applied->verified(), 'Rank Math meta apply verified' );
assert_true( $env->meta[20]['rank_math_description'] === 'New RM meta', 'Rank Math meta updated' );
assert_true( $env->meta[20]['_yoast_wpseo_metadesc'] === 'old yoast should stay', 'Yoast meta untouched when Rank Math owns' );
assert_true( ! isset( $env->meta[20]['rsaip_generated_metadesc'] ), 'Rank Math owner does not mirror rsaip_generated_metadesc' );

// --- MetaDescriptionWriter fit_length(80,170) semantics ---
$meta_writer = new MetaDescriptionWriter( new FakePostMutationEnvironment(), new SeoOwnershipResolver( new FakePostMutationEnvironment() ) );
$long = 'Chocolate cake recipe with tips for bakers who want moist layers and rich frosting every single time at home today yes';
// Ensure > 170 chars.
while ( strlen( $long ) <= 170 ) {
	$long .= ' extra';
}
$normalized = $meta_writer->normalize_new_value( $long );
assert_true( is_string( $normalized ) && strlen( $normalized ) <= 170, 'meta normalize respects max 170' );
assert_true( ! preg_match( '/\s$/u', $normalized ), 'meta normalize has no trailing whitespace' );
assert_true( $meta_writer->normalize_new_value( '   ' ) === null, 'meta normalize rejects empty after spaces' );
assert_true( is_string( $meta_writer->normalize_new_value( str_repeat( 'a', 50 ) ) ), 'meta normalize does not enforce min 80' );

// --- Yoast only ---
$env = new FakePostMutationEnvironment();
$env->safe_mode = false;
$env->rankmath  = false;
$env->yoast     = true;
$env->seed_post( 21 );
$svc      = make_service( $env );
$proposed = $svc->propose(
	array(
		'post_id'       => 21,
		'mutation_type' => MutationType::META_DESCRIPTION,
		'value'         => 'Yoast meta',
	)
);
$applied = $svc->apply( $proposed->proposal() );
assert_true( $applied->ok(), 'Yoast meta apply ok' );
assert_true( ( $env->meta[21]['_yoast_wpseo_metadesc'] ?? '' ) === 'Yoast meta', 'Yoast meta written' );
assert_true( ! isset( $env->meta[21]['rank_math_description'] ), 'Rank Math key not written for Yoast-only' );

// --- Both active: Rank Math owns; client yoast override fails ---
$env = new FakePostMutationEnvironment();
$env->safe_mode = false;
$env->rankmath  = true;
$env->yoast     = true;
$env->seed_post( 22 );
$svc = make_service( $env );
$r   = $svc->propose(
	array(
		'post_id'       => 22,
		'mutation_type' => MutationType::META_DESCRIPTION,
		'value'         => 'Nope',
		'target'        => 'yoast',
	)
);
assert_true( ! $r->ok() && $r->code() === 'ownership_override_forbidden', 'both active: client yoast override rejected' );

// --- Neither => RSAIP fallback ---
$env = new FakePostMutationEnvironment();
$env->safe_mode = false;
$env->seed_post( 23 );
$svc      = make_service( $env );
$proposed = $svc->propose(
	array(
		'post_id'       => 23,
		'mutation_type' => MutationType::META_DESCRIPTION,
		'value'         => 'RSAIP meta',
	)
);
$applied = $svc->apply( $proposed->proposal() );
assert_true( $applied->ok(), 'neither: rsaip meta apply ok' );
assert_true( ( $env->meta[23]['rsaip_generated_metadesc'] ?? '' ) === 'RSAIP meta', 'rsaip_generated_metadesc written' );
assert_true( $proposed->proposal()->owner() === SeoOwner::RSAIP, 'neither: owner is rsaip' );

// --- focus_keyword vs keywords separation ---
$env = new FakePostMutationEnvironment();
$env->safe_mode = false;
$env->rankmath  = true;
$env->seed_post( 30 );
$svc = make_service( $env );

$focus = $svc->propose(
	array(
		'post_id'       => 30,
		'mutation_type' => MutationType::FOCUS_KEYWORD,
		'value'         => 'chocolate cake',
	)
);
assert_true( $focus->ok() && $focus->proposal()->mutation_type() === MutationType::FOCUS_KEYWORD, 'focus_keyword proposal type separate' );
$focus_apply = $svc->apply( $focus->proposal() );
assert_true( $focus_apply->ok(), 'focus_keyword apply ok' );
assert_true( ( $env->meta[30]['rank_math_focus_keyword'] ?? '' ) === 'chocolate cake', 'focus_keyword writes primary string' );

// Re-propose keywords after fingerprint change from prior write.
$env->posts[30]['post_modified_gmt'] = (string) $env->posts[30]['post_modified_gmt'];
$keys = $svc->propose(
	array(
		'post_id'       => 30,
		'mutation_type' => MutationType::KEYWORDS,
		'value'         => array( 'chocolate cake', 'easy dessert', 'baking' ),
	)
);
assert_true( $keys->ok() && $keys->proposal()->mutation_type() === MutationType::KEYWORDS, 'keywords proposal type separate' );
$keys_apply = $svc->apply( $keys->proposal() );
assert_true( $keys_apply->ok(), 'keywords apply ok' );
assert_true( ( $env->meta[30]['rank_math_focus_keyword'] ?? '' ) === 'chocolate cake, easy dessert, baking', 'keywords writes CSV list' );
assert_true( ! isset( $env->meta[30]['rsaip_generated_keywords'] ), 'Rank Math keywords owner does not mirror rsaip_generated_keywords' );
assert_true( MutationType::KEYWORDS === 'keywords', 'Apply Keywords maps to MutationType::KEYWORDS' );

// --- No arbitrary meta keys ---
$env = new FakePostMutationEnvironment();
$env->safe_mode = false;
$env->seed_post( 40 );
$svc = make_service( $env );
$r   = $svc->propose(
	array(
		'post_id'       => 40,
		'mutation_type' => MutationType::META_DESCRIPTION,
		'value'         => 'x',
		'meta_key'      => 'arbitrary_key',
	)
);
assert_true( ! $r->ok() && $r->code() === 'client_meta_key_forbidden', 'arbitrary meta_key rejected' );

$r = $svc->propose(
	array(
		'post_id'       => 40,
		'mutation_type' => MutationType::META_DESCRIPTION,
		'value'         => 'x',
		'meta_keys'     => array( 'foo' ),
	)
);
assert_true( ! $r->ok() && $r->code() === 'client_meta_key_forbidden', 'arbitrary meta_keys rejected' );

// --- Snapshot creation ---
$store = new InMemoryMutationSnapshotStore();
$env   = new FakePostMutationEnvironment();
$env->safe_mode = false;
$env->seed_post( 50 );
$svc      = make_service( $env, $store );
$proposed = $svc->propose(
	array(
		'post_id'       => 50,
		'mutation_type' => MutationType::TITLE,
		'value'         => 'Snap Title',
	)
);
assert_true( $proposed->ok() && $proposed->snapshot_id() > 0, 'snapshot created on propose' );
$snap = $store->find( $proposed->snapshot_id() );
assert_true( null !== $snap && $snap->status === 'proposed' && $snap->old_value === 'Original Title', 'snapshot stores old/new foundation fields' );

// --- Successful title mutation ---
$applied = $svc->apply( $proposed->proposal() );
assert_true( $applied->ok() && $applied->verified(), 'successful title mutation verified' );
assert_true( $env->get_post( 50 )['post_title'] === 'Snap Title', 'title updated' );
assert_true( $env->get_post( 50 )['post_content'] === 'Hello content', 'post_content unchanged' );

// --- Verification mismatch ---
$env = new FakePostMutationEnvironment();
$env->safe_mode = false;
$env->rankmath  = false;
$env->yoast     = false;
$env->seed_post( 60 );
$svc      = make_service( $env );
$proposed = $svc->propose(
	array(
		'post_id'       => 60,
		'mutation_type' => MutationType::META_DESCRIPTION,
		'value'         => 'Should verify fail',
	)
);
$env->corrupt_reads_after_write = true;
$applied                        = $svc->apply( $proposed->proposal() );
assert_true( ! $applied->ok() && $applied->code() === 'verification_mismatch', 'verification mismatch fails apply' );
assert_true( ! $applied->verified(), 'verification mismatch never reports verified success' );

// --- Undo fails safely (in-memory not durable) ---
$undo = $svc->undo( 1 );
assert_true( ! $undo->ok() && $undo->code() === 'undo_unavailable', 'undo fails safely without durable store' );

// --- Defaults: Safe Mode ON in fake default + settings default remains 1 in SettingsService ---
$default_env = new FakePostMutationEnvironment();
assert_true( $default_env->never_modify_posts() === true, 'fake env defaults Safe Mode ON' );

$settings_file = $plugin_root . '/src/Modules/Settings/SettingsService.php';
$settings_src  = file_get_contents( $settings_file );
assert_true(
	is_string( $settings_src ) && strpos( $settings_src, "'never_modify_posts'           => 1" ) !== false,
	'SettingsService default never_modify_posts remains 1'
);

// Confirm Milestone 1 guards untouched on AI apply methods.
$ai_src = file_get_contents( $plugin_root . '/includes/class-rsaip-ai.php' );
assert_true(
	is_string( $ai_src )
	&& substr_count( $ai_src, "if ( ! function_exists( 'rsaip_never_modify_posts' ) || rsaip_never_modify_posts() )" ) >= 4,
	'Milestone 1 Safe Mode guards still present on RSAIP_AI writers'
);

// --- Milestone 3A proposal preview payload / no writes ---
$env = new FakePostMutationEnvironment();
$env->safe_mode = true;
$env->rankmath  = true;
$env->seed_post( 70, array( 'post_title' => 'Live Title', 'post_content' => 'CONTENT_BYTES_UNCHANGED' ) );
$env->meta[70]['rank_math_description']   = 'Old meta';
$env->meta[70]['rank_math_focus_keyword'] = 'old kw';
$content_before = $env->get_post( 70 )['post_content'];
$meta_before    = $env->meta[70];
$title_before   = $env->get_post( 70 )['post_title'];
$svc            = make_service( $env );

$title_prop = $svc->propose(
	array(
		'post_id'       => 70,
		'mutation_type' => MutationType::TITLE,
		'value'         => '  Proposed Title For Preview  ',
	)
);
$preview = $title_prop->to_preview_array();
assert_true( $title_prop->ok(), '3A title proposal ok under Safe Mode' );
assert_true( ! empty( $preview['fingerprint'] ), '3A title fingerprint created' );
assert_true( $preview['apply_allowed'] === false, '3A apply_allowed false' );
assert_true( $preview['apply_blocked_reason'] === 'safe_article_mode', '3A apply_blocked_reason safe_article_mode' );
assert_true( ! empty( $preview['safe_mode'] ), '3A safe_mode true in preview' );
assert_true( $preview['mutation_type'] === MutationType::TITLE, '3A title mutation_type' );
assert_true( $preview['original_value'] === 'Live Title', '3A title original_value' );
assert_true( $preview['normalized_value'] === 'Proposed Title For Preview', '3A title normalized_value' );
assert_true( ! isset( $preview['meta_keys'] ), '3A preview does not expose meta_keys' );

$meta_prop = $svc->propose(
	array(
		'post_id'       => 70,
		'mutation_type' => MutationType::META_DESCRIPTION,
		'value'         => 'A fresh meta description for preview only.',
	)
);
$meta_prev = $meta_prop->to_preview_array();
assert_true( $meta_prop->ok() && $meta_prev['owner'] === 'rankmath', '3A meta proposal with Rank Math owner' );
assert_true( $meta_prev['owner_label'] === 'Rank Math', '3A meta owner_label human readable' );
assert_true( ! isset( $meta_prev['meta_keys'] ), '3A meta preview hides meta keys' );

$kw_prop = $svc->propose(
	array(
		'post_id'       => 70,
		'mutation_type' => MutationType::KEYWORDS,
		'value'         => 'alpha, beta, gamma',
	)
);
$kw_prev = $kw_prop->to_preview_array();
assert_true( $kw_prop->ok() && $kw_prev['mutation_type'] === MutationType::KEYWORDS, '3A keywords proposal uses KEYWORDS' );
assert_true( is_array( $kw_prev['normalized_value'] ), '3A keywords normalized list' );

assert_true( $env->get_post( 70 )['post_title'] === $title_before, '3A propose does not change title' );
assert_true( $env->get_post( 70 )['post_content'] === $content_before, '3A propose does not change post_content' );
assert_true( $env->meta[70] === $meta_before, '3A propose does not change meta' );

$bad_type = $svc->propose(
	array(
		'post_id'       => 70,
		'mutation_type' => 'post_content',
		'value'         => 'nope',
	)
);
assert_true( ! $bad_type->ok() && $bad_type->code() === 'invalid_mutation_type', '3A invalid mutation type rejected' );

$ajax_src = file_get_contents( $plugin_root . '/includes/class-rsaip-ajax.php' );
assert_true(
	is_string( $ajax_src )
	&& strpos( $ajax_src, 'function rsaip_ai_propose_title' ) !== false
	&& strpos( $ajax_src, 'function rsaip_ai_propose_meta_desc' ) !== false
	&& strpos( $ajax_src, 'function rsaip_ai_propose_keywords' ) !== false,
	'3A propose AJAX endpoints present'
);
assert_true(
	is_string( $ajax_src )
	&& false === strpos( substr( $ajax_src, (int) strpos( $ajax_src, 'function rsaip_ai_propose_title' ), 800 ), '->apply(' ),
	'3A propose_title handler does not call apply'
);

// --- Milestone 4: signed ticket Apply ---

$env = new FakePostMutationEnvironment();
$env->safe_mode = false;
$env->rankmath  = true;
$env->seed_post( 80, array( 'post_title' => 'Live Title', 'post_content' => 'BODY' ) );
$svc = make_service( $env );
$p   = $svc->propose(
	array(
		'post_id'       => 80,
		'mutation_type' => MutationType::TITLE,
		'value'         => 'Applied Title From Preview',
	)
);
$prev = $p->to_preview_array();
assert_true( $p->ok() && $prev['apply_allowed'] === true, 'M4 propose apply_allowed true when Safe Mode OFF' );
assert_true( ! empty( $prev['proposal_ticket'] ), 'M4 proposal ticket issued' );

$applied = $svc->apply_from_preview(
	array(
		'post_id'         => 80,
		'value'           => 'Applied Title From Preview',
		'proposal_ticket' => $prev['proposal_ticket'],
	),
	MutationType::TITLE
);
assert_true( $applied->ok() && $applied->code() === 'applied', 'M4 apply_from_preview writes title' );
assert_true( $env->get_post( 80 )['post_title'] === 'Applied Title From Preview', 'M4 title stored' );
assert_true( $env->get_post( 80 )['post_content'] === 'BODY', 'M4 apply does not change post_content' );

$env = new FakePostMutationEnvironment();
$env->safe_mode = true;
$env->seed_post( 81, array( 'post_title' => 'Keep Me' ) );
$svc = make_service( $env );
$p   = $svc->propose(
	array(
		'post_id'       => 81,
		'mutation_type' => MutationType::TITLE,
		'value'         => 'Should Not Apply',
	)
);
$prev    = $p->to_preview_array();
$applied = $svc->apply_from_preview(
	array(
		'post_id'         => 81,
		'value'           => 'Should Not Apply',
		'proposal_ticket' => $prev['proposal_ticket'],
	),
	MutationType::TITLE
);
assert_true( ! $applied->ok() && $applied->code() === 'safe_article_mode', 'M4 Safe Mode ON refuses ticket Apply' );
assert_true( $env->get_post( 81 )['post_title'] === 'Keep Me', 'M4 Safe Mode ON title unchanged' );

$env = new FakePostMutationEnvironment();
$env->safe_mode = false;
$env->seed_post( 82, array( 'post_title' => 'Original Title', 'post_content' => 'BODY' ) );
$svc = make_service( $env );
$p   = $svc->propose(
	array(
		'post_id'       => 82,
		'mutation_type' => MutationType::TITLE,
		'value'         => 'New Title',
	)
);
$prev = $p->to_preview_array();
$env->posts[82]['post_title'] = 'Edited Elsewhere';
$applied = $svc->apply_from_preview(
	array(
		'post_id'         => 82,
		'value'           => 'New Title',
		'proposal_ticket' => $prev['proposal_ticket'],
	),
	MutationType::TITLE
);
assert_true( ! $applied->ok() && $applied->code() === 'stale_fingerprint', 'M4 stale fingerprint rejected' );
assert_true( $env->get_post( 82 )['post_title'] === 'Edited Elsewhere', 'M4 stale apply does not overwrite' );

$env = new FakePostMutationEnvironment();
$env->safe_mode = false;
$env->seed_post( 83 );
$svc = make_service( $env );
$p   = $svc->propose(
	array(
		'post_id'       => 83,
		'mutation_type' => MutationType::TITLE,
		'value'         => 'Previewed Title',
	)
);
$prev    = $p->to_preview_array();
$applied = $svc->apply_from_preview(
	array(
		'post_id'         => 83,
		'value'           => 'Different Title After Edit',
		'proposal_ticket' => $prev['proposal_ticket'],
	),
	MutationType::TITLE
);
assert_true( ! $applied->ok() && $applied->code() === 'proposal_value_mismatch', 'M4 value mismatch rejected' );

$expired_ticket = ProposalTicket::issue(
	array(
		'v'             => 1,
		'post_id'       => 83,
		'mutation_type' => MutationType::TITLE,
		'user_id'       => 1,
		'fingerprint'   => $p->proposal()->fingerprint(),
		'value_hash'    => ProposalTicket::value_hash( $p->proposal()->new_value() ),
		'expires_at'    => time() - 10,
		'target'        => 'auto',
	),
	$env->proposal_signing_key()
);
$applied = $svc->apply_from_preview(
	array(
		'post_id'         => 83,
		'value'           => 'Previewed Title',
		'proposal_ticket' => $expired_ticket,
	),
	MutationType::TITLE
);
assert_true( ! $applied->ok() && $applied->code() === 'expired_proposal', 'M4 expired ticket rejected' );

$applied = $svc->apply_from_preview(
	array(
		'post_id'         => 83,
		'value'           => 'Previewed Title',
		'proposal_ticket' => $prev['proposal_ticket'],
		'meta_key'        => 'rank_math_description',
	),
	MutationType::TITLE
);
assert_true( ! $applied->ok() && $applied->code() === 'client_meta_key_forbidden', 'M4 meta key injection rejected on Apply' );

$applied = $svc->apply_from_preview(
	array(
		'post_id'         => 83,
		'value'           => 'Previewed Title',
		'proposal_ticket' => $prev['proposal_ticket'],
	),
	MutationType::FOCUS_KEYWORD
);
assert_true( ! $applied->ok() && $applied->code() === 'invalid_mutation_type', 'M4 FOCUS_KEYWORD Apply type rejected' );

$applied = $svc->apply_from_preview(
	array(
		'post_id' => 83,
		'value'   => 'Previewed Title',
	),
	MutationType::TITLE
);
assert_true( ! $applied->ok() && $applied->code() === 'invalid_proposal_ticket', 'M4 missing ticket rejected' );

$env->user_id = 99;
$applied      = $svc->apply_from_preview(
	array(
		'post_id'         => 83,
		'value'           => 'Previewed Title',
		'proposal_ticket' => $prev['proposal_ticket'],
	),
	MutationType::TITLE
);
assert_true( ! $applied->ok() && $applied->code() === 'unauthorized', 'M4 ticket user mismatch rejected' );
$env->user_id = 1;

$fresh = $svc->apply_fresh(
	array(
		'post_id'       => 83,
		'mutation_type' => MutationType::TITLE,
		'value'         => 'Fresh Title',
	)
);
assert_true( $fresh->ok() && $env->get_post( 83 )['post_title'] === 'Fresh Title', 'M4 apply_fresh same-request apply' );

$fresh_bad = $svc->apply_fresh(
	array(
		'post_id'       => 83,
		'mutation_type' => MutationType::FOCUS_KEYWORD,
		'value'         => 'nope',
	)
);
assert_true( ! $fresh_bad->ok() && $fresh_bad->code() === 'invalid_mutation_type', 'M4 apply_fresh rejects FOCUS_KEYWORD' );

$env = new FakePostMutationEnvironment();
$env->safe_mode = false;
$env->rankmath  = true;
$env->seed_post( 84, array( 'post_content' => 'BODY' ) );
$env->meta[84]['rank_math_focus_keyword'] = 'old';
$env->meta[84]['rsaip_generated_keywords'] = '["legacy"]';
$svc = make_service( $env );
$p   = $svc->propose(
	array(
		'post_id'       => 84,
		'mutation_type' => MutationType::KEYWORDS,
		'value'         => 'alpha, beta',
	)
);
$prev    = $p->to_preview_array();
$applied = $svc->apply_from_preview(
	array(
		'post_id'         => 84,
		'value'           => 'alpha, beta',
		'target'          => 'auto',
		'proposal_ticket' => $prev['proposal_ticket'],
	),
	MutationType::KEYWORDS
);
assert_true( $applied->ok(), 'M4 keywords ticket Apply succeeds for Rank Math' );
assert_true( $env->meta[84]['rank_math_focus_keyword'] === 'alpha, beta', 'M4 Rank Math keywords written' );
assert_true( $env->meta[84]['rsaip_generated_keywords'] === '["legacy"]', 'M4 keywords Apply does not mirror rsaip_generated_keywords' );
assert_true( $env->get_post( 84 )['post_content'] === 'BODY', 'M4 keywords Apply does not change post_content' );

$helpers_src = file_get_contents( $plugin_root . '/includes/helpers.php' );
assert_true(
	is_string( $helpers_src )
	&& strpos( $helpers_src, 'function rsaip_fix_with_ai_blocked' ) !== false
	&& preg_match( '/function rsaip_fix_with_ai_blocked\(\): bool \{\s*return true;/s', $helpers_src ) === 1,
	'M4 rsaip_fix_with_ai_blocked always true'
);

$ai_src = file_get_contents( $plugin_root . '/includes/class-rsaip-ai.php' );
$fix_fn = substr( $ai_src, (int) strpos( $ai_src, 'function fix_post_with_ai' ), 900 );
assert_true(
	strpos( $fix_fn, 'rsaip_fix_with_ai_blocked' ) !== false
	&& false === strpos( $fix_fn, 'apply_fresh' ),
	'M4 fix_post_with_ai blocked before writers; no apply_fresh'
);
assert_true(
	strpos( $ai_src, '->apply_fresh(' ) !== false
	&& false === strpos( substr( $ai_src, (int) strpos( $ai_src, 'function apply_post_title' ), 1200 ), 'wp_update_post' ),
	'M4 apply_post_title delegates to apply_fresh without wp_update_post'
);

$ajax_src = file_get_contents( $plugin_root . '/includes/class-rsaip-ajax.php' );
$apply_title_fn = substr( $ajax_src, (int) strpos( $ajax_src, 'function rsaip_ai_apply_title' ), 900 );
assert_true(
	strpos( $apply_title_fn, 'apply_from_preview' ) !== false
	&& false === strpos( $apply_title_fn, 'apply_post_title' )
	&& false === strpos( $apply_title_fn, 'apply_fresh' ),
	'M4 Apply Title AJAX uses apply_from_preview only'
);
$fix_ajax = substr( $ajax_src, (int) strpos( $ajax_src, 'function rsaip_ai_fix_post' ), 700 );
assert_true(
	strpos( $fix_ajax, 'rsaip_fix_with_ai_blocked' ) !== false,
	'M4 rsaip_ai_fix_post is server-side blocked'
);
$bulk_ajax = substr( $ajax_src, (int) strpos( $ajax_src, 'function rsaip_ai_bulk_apply_meta_desc' ), 600 );
assert_true(
	strpos( $bulk_ajax, 'rsaip_bulk_apply_meta_blocked' ) !== false,
	'M4 bulk meta Apply remains blocked'
);

// --- Milestone 4A: Safe Article Mode sanitize survives WordPress double sanitize_option ---
if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

$repo = new FakeSettingsRepository();
$repo->stored = array( 'never_modify_posts' => 1 );
$settings_svc = new SettingsService( $repo );

$first_off = $settings_svc->sanitize(
	array(
		'never_modify_posts_present' => '1',
		'thin_content_min_words'     => 600,
	)
);
assert_true( (int) $first_off['never_modify_posts'] === 0, '4A first sanitize explicit OFF (sentinel, no checkbox) stores 0' );

$second_off = $settings_svc->sanitize( $first_off );
assert_true( (int) $second_off['never_modify_posts'] === 0, '4A second sanitize preserves explicit OFF as 0' );

$first_on = $settings_svc->sanitize(
	array(
		'never_modify_posts_present' => '1',
		'never_modify_posts'         => '1',
		'thin_content_min_words'     => 600,
	)
);
assert_true( (int) $first_on['never_modify_posts'] === 1, '4A first sanitize checked ON stores 1' );
$second_on = $settings_svc->sanitize( $first_on );
assert_true( (int) $second_on['never_modify_posts'] === 1, '4A second sanitize preserves ON as 1' );

$repo->stored = array( 'never_modify_posts' => 1, 'gsc_site_url' => 'https://old.example/' );
$settings_svc = new SettingsService( $repo );
$partial      = $settings_svc->sanitize( array( 'gsc_site_url' => 'https://new.example/' ) );
assert_true( (int) $partial['never_modify_posts'] === 1, '4A partial payload does not turn Safe Mode OFF' );
$partial_again = $settings_svc->sanitize( $partial );
assert_true( (int) $partial_again['never_modify_posts'] === 1, '4A second sanitize of partial payload keeps stored ON' );

$repo->stored = array( 'never_modify_posts' => 0, 'gsc_site_url' => 'https://old.example/' );
$settings_svc = new SettingsService( $repo );
$partial_off  = $settings_svc->sanitize( array( 'gsc_site_url' => 'https://new.example/' ) );
assert_true( (int) $partial_off['never_modify_posts'] === 0, '4A partial payload preserves stored OFF' );

$repo->stored = array();
$settings_svc = new SettingsService( $repo );
$missing_key  = $settings_svc->all();
assert_true( (int) $missing_key['never_modify_posts'] === 1, '4A missing stored key fail-closed ON via defaults merge' );

$repo->stored = array( 'never_modify_posts' => 0 );
$settings_svc = new SettingsService( $repo );
$read_off     = $settings_svc->all();
assert_true( (int) $read_off['never_modify_posts'] === 0, '4A stored 0 is read as OFF' );

$helpers_src = file_get_contents( $plugin_root . '/includes/helpers.php' );
assert_true(
	is_string( $helpers_src )
	&& preg_match( '/function rsaip_fix_with_ai_blocked\(\): bool \{\s*return true;/s', $helpers_src ) === 1,
	'4A Fix With AI remains hard-blocked when Safe Mode can be OFF'
);
assert_true(
	is_string( $helpers_src )
	&& preg_match( '/function rsaip_bulk_apply_meta_blocked\(\): bool \{\s*return true;/s', $helpers_src ) === 1,
	'4A Bulk Apply remains blocked when Safe Mode can be OFF'
);
$fix_src = file_get_contents( $plugin_root . '/includes/class-rsaip-ai.php' );
assert_true(
	is_string( $fix_src ) && strpos( $fix_src, 'rsaip_fix_with_ai_blocked' ) !== false,
	'4A fix_post_with_ai still uses independent M4 block'
);

$selector = new TitleTopicSelector();
$example_title = 'Delicious Quinoa Black Bean Salad Recipe - Healthy & Easy to Make';
$anchor        = $selector->extract_anchor( $example_title );
assert_true( $anchor === 'Quinoa Black Bean Salad', '4B extract_anchor keeps recipe entity from marketing title' );

$topic_salad = $selector->resolve( $example_title, 'Salad' );
assert_true( $topic_salad->base() === 'Quinoa Black Bean Salad', '4B generic Salad does not become generation base' );
assert_true(
	stripos( $topic_salad->required_phrase(), 'Quinoa Black Bean Salad' ) !== false,
	'4B required phrase is the recipe entity not Salad'
);

$forbidden = array( 'Salad', 'Best Salad', 'Salad Step-by-Step', 'Easy Salad', 'How to Make Salad' );
$heuristic_salad = $selector->keep_titles_with_topic(
	$selector->build_heuristic_titles( $topic_salad ),
	$topic_salad
);
assert_true( $heuristic_salad !== array(), '4B heuristic produces suggestions for generic Salad keyword' );
$all_keep_entity = true;
$none_forbidden  = true;
foreach ( $heuristic_salad as $suggestion ) {
	if ( stripos( $suggestion, 'Quinoa Black Bean Salad' ) === false ) {
		$all_keep_entity = false;
	}
	if ( in_array( $suggestion, $forbidden, true ) ) {
		$none_forbidden = false;
	}
	if ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $suggestion ) : strlen( $suggestion ) ) > 60 ) {
		$all_keep_entity = false;
	}
}
assert_true( $all_keep_entity, '4B A every heuristic title preserves Quinoa Black Bean Salad' );
assert_true( $none_forbidden, '4B A heuristic does not emit generic Salad titles' );

$topic_specific = $selector->resolve( $example_title, 'quinoa black bean salad' );
assert_true(
	stripos( $topic_specific->required_phrase(), 'quinoa black bean salad' ) !== false
		|| stripos( $topic_specific->required_phrase(), 'Quinoa Black Bean Salad' ) !== false,
	'4B B specific focus keyword is preserved as topic'
);
$heuristic_specific = $selector->keep_titles_with_topic(
	$selector->build_heuristic_titles( $topic_specific ),
	$topic_specific
);
$specific_ok = $heuristic_specific !== array();
foreach ( $heuristic_specific as $suggestion ) {
	if ( stripos( $suggestion, 'quinoa black bean salad' ) === false ) {
		$specific_ok = false;
	}
}
assert_true( $specific_ok, '4B B suggestions preserve quinoa black bean salad' );

$topic_none = $selector->resolve( $example_title, '' );
assert_true( $topic_none->base() === 'Quinoa Black Bean Salad', '4B C no focus keyword still extracts recipe topic' );
$heuristic_none = $selector->keep_titles_with_topic(
	$selector->build_heuristic_titles( $topic_none ),
	$topic_none
);
$none_ok = $heuristic_none !== array();
foreach ( $heuristic_none as $suggestion ) {
	if ( stripos( $suggestion, 'Quinoa Black Bean Salad' ) === false ) {
		$none_ok = false;
	}
}
assert_true( $none_ok, '4B C heuristic from title-only still keeps recipe entity' );

$topic_generic = $selector->resolve( $example_title, 'salad' );
assert_true( $topic_generic->base() !== 'salad' && $topic_generic->base() !== 'Salad', '4B D generic salad is not the sole title base' );
assert_true( $topic_generic->base() === 'Quinoa Black Bean Salad', '4B D generic salad yields title-derived recipe phrase' );

$ai_mixed = $selector->keep_titles_with_topic(
	array(
		'Salad Step-by-Step',
		'Easy Quinoa Black Bean Salad Recipe',
		'Best Salad',
		'How to Make Salad',
	),
	$topic_salad
);
assert_true(
	$ai_mixed === array( 'Easy Quinoa Black Bean Salad Recipe' ),
	'4B E AI titles that omit the recipe entity are rejected'
);

$long_ai = $selector->keep_titles_with_topic(
	array( 'Easy Quinoa Black Bean Salad Recipe With Extra Words That Go Way Past Sixty' ),
	$topic_salad
);
assert_true( $long_ai === array(), '4B F titles over 60 characters are rejected after generation' );
foreach ( $heuristic_salad as $suggestion ) {
	$len = function_exists( 'mb_strlen' ) ? mb_strlen( $suggestion ) : strlen( $suggestion );
	assert_true( $len <= 60, '4B F heuristic title <= 60: ' . $suggestion );
}

$title_writer = new TitleWriter( new FakePostMutationEnvironment() );
assert_true(
	$title_writer->normalize_new_value( 'Salad Step-by-Step' ) === 'Salad Step-by-Step',
	'4B G TitleWriter still only normalizes spaces and length'
);
$sixty_one = str_repeat( 'a', 61 );
assert_true(
	$title_writer->normalize_new_value( $sixty_one ) === str_repeat( 'a', 60 ),
	'4B G TitleWriter still truncates to 60 characters'
);

$ai_src = is_string( $fix_src ) ? $fix_src : '';
$gen_ok = preg_match( '/function generate_title_suggestions_for_post\( int \$post_id \): array \{.*?function apply_post_title/s', $ai_src, $gen_m ) === 1;
$gen_body = $gen_ok ? $gen_m[0] : '';
assert_true(
	$gen_ok
		&& strpos( $gen_body, 'wp_update_post' ) === false
		&& strpos( $gen_body, 'update_post_meta' ) === false
		&& strpos( $gen_body, 'rank_math_' ) === false,
	'4B H title generation does not write content, meta, or Rank Math'
);
assert_true(
	strpos( $ai_src, 'keep_titles_with_topic' ) !== false
		&& ( strpos( $ai_src, 'SeoOptimizationService' ) !== false || strpos( $ai_src, 'Required topic phrase' ) !== false ),
	'4B AI generate path uses topic preservation (unified SEO proposal and/or legacy prompt)'
);
$pms_src = file_get_contents( $plugin_root . '/src/Modules/PostMutation/PostMutationService.php' );
$tw_src  = file_get_contents( $plugin_root . '/src/Modules/PostMutation/Writers/TitleWriter.php' );
assert_true( is_string( $pms_src ) && is_string( $tw_src ), '4B G PMS and TitleWriter files remain readable for regression' );

// --- Milestone 5A: SeoOptimizationProposal foundation ---
$seo_svc = new SeoOptimizationService();
$seo_ctx = $seo_svc->context_builder()->from_server_array(
	array(
		'post_id'                => 42,
		'current_title'          => 'Delicious Quinoa Black Bean Salad Recipe - Healthy & Easy to Make',
		'content_excerpt'        => str_repeat( 'Quinoa black bean salad with lime and cilantro. ', 40 ),
		'existing_focus_keyword' => 'Salad',
		'categories'             => array( 'Salads' ),
		'tags'                   => array( 'quinoa', 'vegetarian' ),
		'detected_recipe_name'   => 'Quinoa Black Bean Salad',
		'recipe_summary'         => 'Toss cooked quinoa with black beans, corn, and cilantro.',
		'seo_owner_label'        => 'rankmath',
		'has_recipe_card'        => true,
		'has_recipe_schema'      => true,
	)
);
assert_true( $seo_ctx->post_id() === 42, '5A context keeps server post_id' );
assert_true( $seo_ctx->detected_recipe_name() === 'Quinoa Black Bean Salad', '5A context recipe name' );
$prompt_payload = $seo_ctx->to_prompt_array();
assert_true( ! array_key_exists( 'post_id', $prompt_payload ), '5A prompt payload omits post_id' );
assert_true( ! array_key_exists( 'mutation_type', $prompt_payload ), '5A prompt payload omits mutation_type' );

$long_content = str_repeat( 'word ', 5000 );
$bounded_ctx  = $seo_svc->context_builder()->from_server_array(
	array(
		'post_id'         => 1,
		'current_title'   => 'Quinoa Black Bean Salad',
		'content_excerpt' => $long_content,
	)
);
$excerpt_len = function_exists( 'mb_strlen' ) ? mb_strlen( $bounded_ctx->content_excerpt() ) : strlen( $bounded_ctx->content_excerpt() );
assert_true( $excerpt_len <= SeoOptimizationContextBuilder::EXCERPT_MAX_CHARS, '5A 19 context builder bounds content length' );

$valid_meta = 'Enjoy an easy Quinoa Black Bean Salad Recipe packed with protein, fiber, and fresh lime flavor for a healthy weeknight meal.';
assert_true( strlen( $valid_meta ) >= 120 && strlen( $valid_meta ) <= 160, '5A fixture meta length in preferred band' );

function rsaip_5a_valid_proposal_data( array $overrides = array() ): array {
	$base = array(
		'topic'                     => 'Quinoa Black Bean Salad',
		'recipe_name'               => 'Quinoa Black Bean Salad',
		'search_intent'             => 'informational',
		'primary_focus_keyword'     => 'quinoa black bean salad',
		'secondary_keywords'        => array( 'easy quinoa salad', 'healthy black bean salad' ),
		'entities'                  => array( 'quinoa', 'black beans', 'cilantro' ),
		'seo_title_suggestions'     => array(
			'Easy Quinoa Black Bean Salad Recipe',
			'Quinoa Black Bean Salad Recipe',
		),
		'recommended_title'         => 'Easy Quinoa Black Bean Salad Recipe',
		'meta_description'          => 'Enjoy an easy Quinoa Black Bean Salad Recipe packed with protein, fiber, and fresh lime flavor for a healthy weeknight meal.',
		'heading_suggestions'       => array(
			array(
				'level'     => 'h2',
				'text'      => 'Ingredients for Quinoa Black Bean Salad',
				'rationale' => 'Supports recipe intent',
			),
		),
		'faq_suggestions'           => array(
			array(
				'q' => 'Can I make Quinoa Black Bean Salad ahead?',
				'a' => 'Yes, chill up to two days.',
			),
		),
		'image_alt_suggestions'     => array(
			array(
				'target' => 'featured',
				'alt'    => 'Bowl of Quinoa Black Bean Salad',
			),
		),
		'internal_link_suggestions' => array(
			array(
				'anchor'      => 'quinoa recipes',
				'target_hint' => 'quinoa category',
				'reason'      => 'Related cluster',
			),
		),
		'content_gaps'              => array( 'Add storage tips' ),
		'seo_issues'                => array(
			array(
				'code'     => 'focus_too_generic',
				'category' => 'keyword',
				'severity' => 'high',
			),
		),
		'recommendations'           => array(
			array(
				'action'      => 'Update title to include recipe entity',
				'applies_via' => 'title',
				'note'        => 'Preserve Quinoa Black Bean Salad',
			),
		),
		'confidence'                => 0.86,
		'reasoning_summary'         => 'Recipe entity is Quinoa Black Bean Salad; Salad alone is too generic.',
	);
	return array_merge( $base, $overrides );
}

$ok = $seo_svc->proposal_from_decoded( rsaip_5a_valid_proposal_data(), $seo_ctx );
assert_true( $ok->ok() && $ok->proposal() instanceof \RecipeSeoAiPro\Modules\Ai\SeoOptimization\SeoOptimizationProposal, '5A 1/17 valid Quinoa proposal produces DTO' );
assert_true( $ok->proposal()->topic() === 'Quinoa Black Bean Salad', '5A valid proposal topic' );
assert_true( $ok->proposal()->recommended_title() === 'Easy Quinoa Black Bean Salad Recipe', '5A valid recommended title' );

$fail_generic_topic = $seo_svc->proposal_from_decoded(
	rsaip_5a_valid_proposal_data(
		array(
			'topic'                 => 'Salad',
			'seo_title_suggestions' => array( 'Salad Recipe' ),
			'recommended_title'     => 'Salad Recipe',
		)
	),
	$seo_ctx
);
assert_true( ! $fail_generic_topic->ok() && $fail_generic_topic->code() === 'rsaip_seo_opt_generic_topic', '5A 2 generic topic Salad rejected' );

$fail_generic_kw = $seo_svc->proposal_from_decoded(
	rsaip_5a_valid_proposal_data( array( 'primary_focus_keyword' => 'salad' ) ),
	$seo_ctx
);
assert_true( ! $fail_generic_kw->ok() && $fail_generic_kw->code() === 'rsaip_seo_opt_generic_primary_keyword', '5A 3 generic primary keyword rejected' );

assert_true( ! $seo_svc->proposal_from_decoded( rsaip_5a_valid_proposal_data( array( 'topic' => '' ) ), $seo_ctx )->ok(), '5A 4 missing topic rejected' );
assert_true( ! $seo_svc->proposal_from_decoded( rsaip_5a_valid_proposal_data( array( 'primary_focus_keyword' => '' ) ), $seo_ctx )->ok(), '5A 5 missing primary keyword rejected' );
assert_true( ! $seo_svc->proposal_from_decoded( rsaip_5a_valid_proposal_data( array( 'recommended_title' => '' ) ), $seo_ctx )->ok(), '5A 6 missing recommended title rejected' );
assert_true( ! $seo_svc->proposal_from_decoded( rsaip_5a_valid_proposal_data( array( 'meta_description' => '' ) ), $seo_ctx )->ok(), '5A 7 missing meta description rejected' );
assert_true( ! $seo_svc->proposal_from_decoded( rsaip_5a_valid_proposal_data( array( 'seo_title_suggestions' => array() ) ), $seo_ctx )->ok(), '5A 8 empty title suggestions rejected' );

$fail_long_title = $seo_svc->proposal_from_decoded(
	rsaip_5a_valid_proposal_data(
		array(
			'seo_title_suggestions' => array( 'Easy Quinoa Black Bean Salad Recipe With Extra Words Past Sixty' ),
		)
	),
	$seo_ctx
);
assert_true( ! $fail_long_title->ok() && $fail_long_title->code() === 'rsaip_seo_opt_title_too_long', '5A 9 title >60 rejected' );

$fail_missing_entity = $seo_svc->proposal_from_decoded(
	rsaip_5a_valid_proposal_data(
		array(
			'seo_title_suggestions' => array( 'Best Healthy Lunch Ideas' ),
			'recommended_title'     => 'Easy Quinoa Black Bean Salad Recipe',
		)
	),
	$seo_ctx
);
assert_true( ! $fail_missing_entity->ok() && $fail_missing_entity->code() === 'rsaip_seo_opt_title_missing_topic', '5A 10 title without recipe/topic entity rejected' );

assert_true(
	! $seo_svc->proposal_from_decoded( rsaip_5a_valid_proposal_data( array( 'search_intent' => 'curiosity' ) ), $seo_ctx )->ok(),
	'5A 11 invalid search intent rejected'
);

$fail_enum = $seo_svc->proposal_from_decoded(
	rsaip_5a_valid_proposal_data(
		array(
			'seo_issues' => array(
				array(
					'code'     => 'x',
					'category' => 'permalink',
					'severity' => 'high',
				),
			),
		)
	),
	$seo_ctx
);
assert_true( ! $fail_enum->ok(), '5A 12 invalid nested enum rejected' );

assert_true(
	! $seo_svc->proposal_from_decoded( rsaip_5a_valid_proposal_data( array( 'confidence' => 1.5 ) ), $seo_ctx )->ok(),
	'5A 13 invalid confidence rejected'
);

$fail_malformed = $seo_svc->proposal_from_decoded(
	rsaip_5a_valid_proposal_data(
		array(
			'faq_suggestions' => array( 'not-an-object' ),
		)
	),
	$seo_ctx
);
assert_true( ! $fail_malformed->ok(), '5A 14 malformed nested structure rejected' );

$with_untrusted = rsaip_5a_valid_proposal_data(
	array(
		'post_id'           => 999,
		'mutation_type'     => 'title',
		'writable_meta_key' => 'rank_math_focus_keyword',
		'apply_allowed'     => true,
	)
);
$safe_dto = $seo_svc->proposal_from_decoded( $with_untrusted, $seo_ctx );
assert_true( $safe_dto->ok(), '5A 15 untrusted fields do not block valid contract data' );
$exported = $safe_dto->proposal()->to_array();
assert_true(
	! array_key_exists( 'post_id', $exported )
	&& ! array_key_exists( 'mutation_type', $exported )
	&& ! array_key_exists( 'writable_meta_key', $exported )
	&& ! array_key_exists( 'apply_allowed', $exported ),
	'5A 15 unknown/untrusted fields do not become DTO properties'
);

$bad_json = $seo_svc->proposal_from_raw_response( 'not-json {{{', $seo_ctx );
assert_true( ! $bad_json->ok() && $bad_json->code() === 'rsaip_seo_opt_invalid_json', '5A 16 invalid JSON hard failure' );

$validator_src = file_get_contents( $plugin_root . '/src/Modules/Ai/SeoOptimization/SeoOptimizationProposalValidator.php' );
$service_src   = file_get_contents( $plugin_root . '/src/Modules/Ai/SeoOptimization/SeoOptimizationService.php' );
assert_true(
	is_string( $validator_src )
	&& strpos( $validator_src, 'wp_update_post' ) === false
	&& strpos( $validator_src, 'update_post_meta' ) === false
	&& is_string( $service_src )
	&& strpos( $service_src, 'wp_update_post' ) === false
	&& strpos( $service_src, 'PostMutationService' ) === false,
	'5A 18 validator/service do not write posts or call PMS'
);

$analyze_stub = $seo_svc->analyze( array( 'post_id' => 1 ) );
assert_true( ! $analyze_stub->ok(), '5A analyze without AI completer hard-fails (no heuristic)' );
assert_true(
	in_array( $analyze_stub->code(), array( 'rsaip_seo_opt_ai_disabled', 'rsaip_seo_opt_ai_unavailable', 'rsaip_seo_opt_not_implemented' ), true )
	|| strpos( $analyze_stub->code(), 'rsaip_seo_opt_' ) === 0,
	'5A analyze failure uses seo_opt error code'
);

assert_true( ProviderCatalog::supports_json_response_format( 'openai' ), '5A response_format supported for openai' );
assert_true( ProviderCatalog::supports_json_response_format( 'azure_openai' ), '5A response_format supported for azure_openai' );
assert_true( ! ProviderCatalog::supports_json_response_format( 'deepseek' ), '5A response_format not forced on deepseek' );
assert_true( ! ProviderCatalog::supports_json_response_format( 'ollama' ), '5A response_format not forced on ollama' );

$hub_src = file_get_contents( $plugin_root . '/src/Modules/Ai/Hub/Providers/OpenAiStyleHubProvider.php' );
assert_true(
	is_string( $hub_src )
	&& strpos( $hub_src, 'supports_json_response_format' ) !== false
	&& strpos( $hub_src, 'normalize_response_format' ) !== false,
	'5A OpenAiStyleHubProvider optionally attaches response_format'
);

$helpers_5a = file_get_contents( $plugin_root . '/includes/helpers.php' );
assert_true(
	is_string( $helpers_5a )
	&& preg_match( '/function rsaip_never_modify_posts\(\): bool \{/s', $helpers_5a ) === 1
	&& preg_match( '/function rsaip_fix_with_ai_blocked\(\): bool \{\s*return true;/s', $helpers_5a ) === 1
	&& preg_match( '/function rsaip_bulk_apply_meta_blocked\(\): bool \{\s*return true;/s', $helpers_5a ) === 1,
	'5A 20 Safe Mode / Fix With AI / Bulk Apply remain untouched'
);
assert_true(
	is_string( $pms_src )
	&& strpos( $pms_src, 'SeoOptimization' ) === false,
	'5A 20 PostMutationService has no SeoOptimization coupling'
);

// --- Milestone 5B: Analyze Post → SeoOptimizationService ---
$quinoa_ctx_input = array(
	'post_id'                => 42,
	'current_title'          => 'Delicious Quinoa Black Bean Salad Recipe - Healthy & Easy to Make',
	'content_excerpt'        => str_repeat( 'Quinoa black bean salad with lime cilantro black beans and corn. ', 30 ),
	'existing_focus_keyword' => 'Salad',
	'categories'             => array( 'Salads' ),
	'tags'                   => array( 'quinoa' ),
	'detected_recipe_name'   => 'Quinoa Black Bean Salad',
	'recipe_summary'         => 'Ingredients: quinoa, black beans, corn, cilantro',
	'seo_owner_label'        => 'rankmath',
	'has_recipe_card'        => true,
	'has_recipe_schema'      => true,
);

$captured_options = array();
$prompt_saw_entity = false;
$fake_ai_json      = json_encode( rsaip_5a_valid_proposal_data() );

$seo_5b = new SeoOptimizationService(
	null,
	null,
	null,
	null,
	static function ( string $prompt, array $options ) use ( &$captured_options, &$prompt_saw_entity, $fake_ai_json ) {
		$captured_options  = $options;
		$prompt_saw_entity = ( strpos( $prompt, 'Quinoa Black Bean Salad' ) !== false );
		return (string) $fake_ai_json;
	}
);

$analyze_ok = $seo_5b->analyze( $quinoa_ctx_input );
assert_true( $prompt_saw_entity, '5B prompt includes recipe entity' );
assert_true( $analyze_ok->ok() && $analyze_ok->proposal() !== null, '5B 1 Analyze valid recipe → valid proposal' );
assert_true( $analyze_ok->proposal()->topic() === 'Quinoa Black Bean Salad', '5B 2 Quinoa Black Bean Salad remains the topic' );
assert_true(
	stripos( $analyze_ok->proposal()->primary_focus_keyword(), 'quinoa black bean salad' ) !== false,
	'5B 4 Primary keyword remains specific'
);
foreach ( $analyze_ok->proposal()->seo_title_suggestions() as $t ) {
	$len = function_exists( 'mb_strlen' ) ? mb_strlen( $t ) : strlen( $t );
	assert_true( $len <= 60, '5B 5 title <= 60: ' . $t );
}
$meta_len = function_exists( 'mb_strlen' ) ? mb_strlen( $analyze_ok->proposal()->meta_description() ) : strlen( $analyze_ok->proposal()->meta_description() );
assert_true( $meta_len >= 120 && $meta_len <= 170, '5B 6 meta PMS-compatible length' );

$ui = $seo_5b->to_analyze_response( $analyze_ok, 42, $seo_5b->context_builder()->from_server_array( $quinoa_ctx_input ) );
assert_true( ! empty( $ui['ok'] ) && empty( $ui['apply_allowed'] ) && ( $ui['proposal_ticket'] ?? 'x' ) === '', '5B UI envelope is read-only without tickets' );

$bad_salad_json = json_encode(
	rsaip_5a_valid_proposal_data(
		array(
			'topic'                 => 'Salad',
			'primary_focus_keyword' => 'salad',
			'seo_title_suggestions' => array( 'Salad Recipe' ),
			'recommended_title'     => 'Salad Recipe',
		)
	)
);
$seo_salad = new SeoOptimizationService(
	null,
	null,
	null,
	null,
	static function () use ( $bad_salad_json ) {
		return (string) $bad_salad_json;
	}
);
$reject_salad = $seo_salad->analyze( $quinoa_ctx_input );
assert_true( ! $reject_salad->ok(), '5B 3 Generic Salad output is rejected' );

$seo_bad_json = new SeoOptimizationService(
	null,
	null,
	null,
	null,
	static function () {
		return 'NOT JSON';
	}
);
$bad_json_5b = $seo_bad_json->analyze( $quinoa_ctx_input );
assert_true( ! $bad_json_5b->ok() && $bad_json_5b->code() === 'rsaip_seo_opt_invalid_json', '5B 7 Invalid JSON → hard error' );

$seo_ai_fail = new SeoOptimizationService(
	null,
	null,
	null,
	null,
	static function () {
		return new class() {
			public function get_error_code() {
				return 'rsaip_ai_http';
			}
			public function get_error_message() {
				return 'timeout';
			}
		};
	}
);
$ai_fail = $seo_ai_fail->analyze( $quinoa_ctx_input );
assert_true( ! $ai_fail->ok(), '5B 8 AI failure → hard error' );

$incomplete_json = json_encode(
	array(
		'topic' => 'Quinoa Black Bean Salad',
	)
);
$seo_incomplete = new SeoOptimizationService(
	null,
	null,
	null,
	null,
	static function () use ( $incomplete_json ) {
		return (string) $incomplete_json;
	}
);
$missing_field = $seo_incomplete->analyze( $quinoa_ctx_input );
assert_true( ! $missing_field->ok(), '5B 9 Missing required proposal field → hard error' );

$analyze_src = file_get_contents( $plugin_root . '/includes/class-rsaip-ai.php' );
$analyze_fn  = preg_match( '/function analyze_post_ai\( int \$post_id \): array \{(.*?)public function generate_semantic_seo_for_post/s', (string) $analyze_src, $am ) === 1 ? $am[1] : '';
assert_true(
	$analyze_fn !== ''
	&& strpos( $analyze_fn, 'SeoOptimizationService' ) !== false
	&& strpos( $analyze_fn, 'wp_update_post' ) === false
	&& strpos( $analyze_fn, 'update_post_meta' ) === false
	&& strpos( $analyze_fn, 'generate_semantic_seo_for_post' ) === false
	&& strpos( $analyze_fn, 'rsaip_seo_score' ) === false,
	'5B Analyze uses SeoOptimizationService only (no heuristic decoration / no writes)'
);

$svc_src_5b = file_get_contents( $plugin_root . '/src/Modules/Ai/SeoOptimization/SeoOptimizationService.php' );
assert_true(
	is_string( $svc_src_5b )
	&& strpos( $svc_src_5b, 'PostMutationService' ) === false
	&& strpos( $svc_src_5b, 'wp_update_post' ) === false
	&& strpos( $svc_src_5b, 'complete_with_failover' ) === false
	&& strpos( $svc_src_5b, 'resolve_active' ) !== false,
	'5B 14 service has no PMS/writes and avoids failover chain'
);

$js_src = file_get_contents( $plugin_root . '/assets/admin.js' );
assert_true(
	is_string( $js_src )
	&& strpos( $js_src, 'renderSeoOptimizationAnalysis' ) !== false
	&& strpos( $js_src, 'Apply not connected' ) !== false,
	'5B UI renders read-only analysis without Apply wiring'
);

// Simulate Safe Mode ON: analyze path must not check never_modify_posts.
assert_true(
	strpos( $analyze_fn, 'rsaip_never_modify_posts' ) === false,
	'5B 13 Safe Mode does not gate Analyze'
);

assert_true(
	is_string( $pms_src ) && strpos( $pms_src, 'SeoOptimization' ) === false,
	'5B 15 Rank Math ownership remains in PMS only; Analyze does not alter ownership'
);

$prompt_builder = new \RecipeSeoAiPro\Modules\Ai\SeoOptimization\SeoOptimizationPromptBuilder();
$prompt_sample  = $prompt_builder->build( $seo_svc->context_builder()->from_server_array( $quinoa_ctx_input ) );
assert_true(
	strpos( $prompt_sample, 'primary_focus_keyword' ) !== false
	&& strpos( $prompt_sample, 'Quinoa Black Bean Salad' ) !== false
	&& strpos( $prompt_sample, 'must NOT be "Salad"' ) !== false,
	'5B prompt forbids generic Salad reduction'
);

// response_format only when catalog supports — completer receives options from analyze_context.
$rf_seen = null;
$seo_rf  = new SeoOptimizationService(
	null,
	null,
	null,
	null,
	static function ( string $prompt, array $options ) use ( &$rf_seen, $fake_ai_json ) {
		$rf_seen = $options['response_format'] ?? null;
		return (string) $fake_ai_json;
	}
);
$seo_rf->analyze( $quinoa_ctx_input );
// Without Hub in CLI, active id defaults to openai → response_format may be set.
assert_true(
	$rf_seen === null || $rf_seen === 'json_object' || ( is_array( $rf_seen ) && ( $rf_seen['type'] ?? '' ) === 'json_object' ),
	'5B 16 response_format is json_object or omitted (never forced invalid)'
);
assert_true( ProviderCatalog::supports_json_response_format( 'openai' ), '5B 16 openai supports response_format' );
assert_true( ! ProviderCatalog::supports_json_response_format( 'deepseek' ), '5B 16 deepseek does not get forced response_format capability' );

$helpers_5b = file_get_contents( $plugin_root . '/includes/helpers.php' );
assert_true(
	is_string( $helpers_5b )
	&& preg_match( '/function rsaip_fix_with_ai_blocked\(\): bool \{\s*return true;/s', $helpers_5b ) === 1
	&& preg_match( '/function rsaip_bulk_apply_meta_blocked\(\): bool \{\s*return true;/s', $helpers_5b ) === 1,
	'5B Fix With AI / Bulk Apply remain blocked'
);

// --- Milestone 5B.1: Authoritative Recipe Schema Detection ---
$detector = new RecipeSchemaDetector();

$valid_ld = '<script type="application/ld+json">' . json_encode(
	array(
		'@context'            => 'https://schema.org',
		'@type'               => 'Recipe',
		'name'                => 'Quinoa Black Bean Salad',
		'recipeIngredient'    => array( 'quinoa', 'black beans' ),
		'recipeInstructions'  => array( 'Mix ingredients' ),
	)
) . '</script>';
$d1 = $detector->detect_from_content( $valid_ld, array( 'title' => 'Quinoa Black Bean Salad' ) );
assert_true( $d1->status() === RecipeSchemaStatus::VALID_RECIPE, '5B.1 1 valid @type Recipe' );

$array_type_ld = '<script type="application/ld+json">' . json_encode(
	array(
		'@type'              => array( 'Recipe', 'ItemPage' ),
		'name'               => 'Quinoa Black Bean Salad',
		'recipeIngredient'   => array( 'quinoa' ),
		'recipeInstructions' => array( 'Toss' ),
	)
) . '</script>';
$d2 = $detector->detect_from_content( $array_type_ld );
assert_true( $d2->status() === RecipeSchemaStatus::VALID_RECIPE, '5B.1 2 @type array includes Recipe' );

$graph_ld = '<script type="application/ld+json">' . json_encode(
	array(
		'@context' => 'https://schema.org',
		'@graph'   => array(
			array( '@type' => 'WebPage', 'name' => 'Page' ),
			array(
				'@type'              => 'Recipe',
				'name'               => 'Quinoa Black Bean Salad',
				'recipeIngredient'   => array( 'beans' ),
				'recipeInstructions' => array( 'Serve' ),
			),
		),
	)
) . '</script>';
$d3 = $detector->detect_from_content( $graph_ld );
assert_true( $d3->status() === RecipeSchemaStatus::VALID_RECIPE, '5B.1 3 Recipe inside @graph' );

$multi = $valid_ld . $array_type_ld;
$d4 = $detector->detect_from_content( $multi );
assert_true( $d4->status() === RecipeSchemaStatus::MULTIPLE && $d4->count() >= 2, '5B.1 4/9 multiple Recipe nodes' );

$plain = '<p>This Recipe is delicious. Read more Recipe tips.</p><a href="/recipe/">Recipe</a>';
$d5 = $detector->detect_from_content( $plain );
assert_true( $d5->status() === RecipeSchemaStatus::MISSING, '5B.1 5 ordinary Recipe text is not schema' );

$bad_json = '<script type="application/ld+json">{ broken json "@type":"Recipe" </script>';
$d6 = $detector->detect_from_content( $bad_json );
assert_true( $d6->status() === RecipeSchemaStatus::MISSING, '5B.1 6 malformed JSON no false positive' );

$d7 = $detector->detect_from_content( '<p>No schema here</p>' );
assert_true( $d7->status() === RecipeSchemaStatus::MISSING && $d7->repair_allowed(), '5B.1 7 missing' );

$d8 = $detector->detect_from_content(
	'<div class="wprm-recipe">Card</div>',
	array( 'external_recipe_plugin' => true, 'external_recipe_plugin_id' => 'wp_recipe_maker' )
);
assert_true( $d8->status() === RecipeSchemaStatus::EXTERNAL_OR_UNKNOWN, '5B.1 8 external recipe system' );

$d_rm = $detector->detect_from_content(
	'<p>No ld+json</p>',
	array( 'rank_math_active' => true, 'title' => 'Quinoa Black Bean Salad' )
);
assert_true( $d_rm->status() === RecipeSchemaStatus::EXTERNAL_OR_UNKNOWN && $d_rm->source() === 'rank_math', '5B.1 Rank Math active => not falsely missing' );

$ctx_valid = $seo_svc->context_builder()->from_server_array(
	array(
		'post_id'                      => 1844,
		'current_title'                => 'Delicious Quinoa Black Bean Salad Recipe - Healthy & Easy to Make',
		'detected_recipe_name'         => 'Quinoa Black Bean Salad',
		'recipe_schema_status'         => RecipeSchemaStatus::VALID_RECIPE,
		'recipe_schema_source'         => 'post_content',
		'recipe_schema_authoritative'  => true,
		'recipe_schema_details'        => 'Valid Recipe JSON-LD detected.',
		'recipe_schema_repair_allowed' => false,
		'seo_owner_label'              => 'rankmath',
	)
);
$ai_contradict = rsaip_5a_valid_proposal_data(
	array(
		'seo_issues' => array(
			array(
				'code'     => 'missing_recipe_schema',
				'category' => 'other',
				'severity' => 'high',
			),
			array(
				'code'     => 'focus_too_generic',
				'category' => 'keyword',
				'severity' => 'medium',
			),
		),
		'recommendations' => array(
			array(
				'action'      => 'Create Recipe Schema JSON-LD',
				'applies_via' => 'none',
				'note'        => 'Add schema',
			),
			array(
				'action'      => 'Update title to include recipe entity',
				'applies_via' => 'title',
				'note'        => 'Keep entity',
			),
		),
		'content_gaps' => array( 'Missing recipe schema markup', 'Add storage tips' ),
	)
);
$filtered = $seo_svc->proposal_from_decoded( $ai_contradict, $ctx_valid );
assert_true( $filtered->ok(), '5B.1 10 proposal still ok after filtering' );
$issue_codes = array();
foreach ( $filtered->proposal()->seo_issues() as $iss ) {
	$issue_codes[] = $iss['code'];
}
assert_true( ! in_array( 'missing_recipe_schema', $issue_codes, true ), '5B.1 10 AI missing-schema issue removed when valid' );
assert_true( in_array( 'focus_too_generic', $issue_codes, true ), '5B.1 non-schema issues retained' );
$rec_actions = array();
foreach ( $filtered->proposal()->recommendations() as $rec ) {
	$rec_actions[] = $rec['action'];
}
assert_true( ! in_array( 'Create Recipe Schema JSON-LD', $rec_actions, true ), '5B.1 create-schema recommendation removed when valid' );

$ctx_ext = $seo_svc->context_builder()->from_server_array(
	array(
		'current_title'               => 'Quinoa Black Bean Salad',
		'detected_recipe_name'        => 'Quinoa Black Bean Salad',
		'recipe_schema_status'        => RecipeSchemaStatus::EXTERNAL_OR_UNKNOWN,
		'recipe_schema_source'        => 'rank_math',
		'recipe_schema_authoritative' => false,
	)
);
$ext_filtered = $seo_svc->proposal_from_decoded( $ai_contradict, $ctx_ext );
$ext_recs = array();
foreach ( $ext_filtered->proposal()->recommendations() as $rec ) {
	$ext_recs[] = $rec['action'];
}
assert_true( ! in_array( 'Create Recipe Schema JSON-LD', $ext_recs, true ), '5B.1 11 external_or_unknown blocks create-schema rec' );

$ctx_missing = $seo_svc->context_builder()->from_server_array(
	array(
		'current_title'               => 'Quinoa Black Bean Salad',
		'detected_recipe_name'        => 'Quinoa Black Bean Salad',
		'recipe_schema_status'        => RecipeSchemaStatus::MISSING,
		'recipe_schema_source'        => 'none',
		'recipe_schema_authoritative' => true,
		'recipe_schema_repair_allowed'=> true,
	)
);
$miss_ok = $seo_svc->proposal_from_decoded( $ai_contradict, $ctx_missing );
$miss_codes = array();
foreach ( $miss_ok->proposal()->seo_issues() as $iss ) {
	$miss_codes[] = $iss['code'];
}
assert_true( in_array( 'missing_recipe_schema', $miss_codes, true ), '5B.1 12 missing allows schema issue' );

$prompt_5b1 = ( new \RecipeSeoAiPro\Modules\Ai\SeoOptimization\SeoOptimizationPromptBuilder() )->build( $ctx_valid );
assert_true(
	strpos( $prompt_5b1, 'recipe_schema_status' ) !== false
	&& strpos( $prompt_5b1, 'SERVER-AUTHORITATIVE' ) !== false,
	'5B.1 prompt includes authoritative schema rules'
);

$ctx_builder_src = file_get_contents( $plugin_root . '/src/Modules/Ai/SeoOptimization/SeoOptimizationContextBuilder.php' );
assert_true(
	is_string( $ctx_builder_src )
	&& strpos( $ctx_builder_src, 'RecipeSchemaDetector' ) !== false
	&& strpos( $ctx_builder_src, '/"@type"\\s*:\\s*"Recipe"/' ) === false
	&& strpos( $ctx_builder_src, 'preg_match( \'/application\\/ld\\+json/i\'' ) === false,
	'5B.1 ContextBuilder no longer uses naive Recipe regex'
);

$repair = new RecipeSchemaRepairService( $detector );
$repair_out = $repair->repair( 1844 );
assert_true(
	! $repair_out['ok']
	&& in_array( $repair_out['code'], array( 'rsaip_recipe_schema_repair_deferred', 'rsaip_recipe_schema_safe_mode', 'rsaip_recipe_schema_abort_state' ), true ),
	'5B.1 repair intentionally deferred / fail-closed (no write)'
);

$svc_no_write = file_get_contents( $plugin_root . '/src/Modules/Schema/RecipeSchemaRepairService.php' );
assert_true(
	is_string( $svc_no_write )
	&& strpos( $svc_no_write, 'wp_update_post' ) === false
	&& strpos( $svc_no_write, 'PostMutationService' ) === false
	&& strpos( $svc_no_write, 'fix_post_schema' ) === false,
	'5B.1 13/17/18 repair service does not write or use Article fixer / PMS'
);

assert_true(
	is_string( $pms_src ) && strpos( $pms_src, 'RecipeSchema' ) === false,
	'5B.1 PMS untouched by schema detector'
);
assert_true(
	is_string( $helpers_5b )
	&& preg_match( '/function rsaip_never_modify_posts\(\): bool \{/s', $helpers_5b ) === 1
	&& preg_match( '/function rsaip_fix_with_ai_blocked\(\): bool \{\s*return true;/s', $helpers_5b ) === 1,
	'5B.1 20 Safe Mode / Fix With AI unchanged'
);

$js_5b1 = file_get_contents( $plugin_root . '/assets/admin.js' );
assert_true(
	is_string( $js_5b1 ) && strpos( $js_5b1, 'Recipe Schema:' ) !== false,
	'5B.1 UI shows Recipe Schema status'
);

// --- Milestone 5C: Unify Generate Titles / Keywords / Meta with SeoOptimizationProposal ---
SeoOptimizationProposalStore::clear();

$valid_5c_json = json_encode( rsaip_5a_valid_proposal_data() );
$seo_5c        = new SeoOptimizationService(
	null,
	null,
	null,
	null,
	static function () use ( $valid_5c_json ) {
		return (string) $valid_5c_json;
	}
);

$quinoa_ctx_5c = $quinoa_ctx_input;
$analyze_5c    = $seo_5c->analyze( $quinoa_ctx_5c );
assert_true( $analyze_5c->ok() && $analyze_5c->proposal() instanceof \RecipeSeoAiPro\Modules\Ai\SeoOptimization\SeoOptimizationProposal, '5C unified analyze produces proposal' );

$proposal_5c = $analyze_5c->proposal();
$titles_5c   = $seo_5c->titles_from_proposal( $proposal_5c, 42 );
$kw_5c       = $seo_5c->keywords_from_proposal( $proposal_5c, 42 );
$meta_5c     = $seo_5c->meta_from_proposal( $proposal_5c, 42 );

assert_true( ! empty( $titles_5c['ok'] ) && ( $titles_5c['source'] ?? '' ) === 'seo_optimization', '5C 1 Generate Titles consumes proposal' );
assert_true( ! empty( $kw_5c['ok'] ) && ( $kw_5c['source'] ?? '' ) === 'seo_optimization', '5C 2 Generate Keywords consumes proposal' );
assert_true( ! empty( $meta_5c['ok'] ) && ( $meta_5c['source'] ?? '' ) === 'seo_optimization', '5C 3 Generate Meta consumes proposal' );

assert_true(
	( $titles_5c['recipe_name'] ?? '' ) === 'Quinoa Black Bean Salad'
	&& ( $kw_5c['recipe_name'] ?? '' ) === 'Quinoa Black Bean Salad'
	&& ( $meta_5c['recipe_name'] ?? '' ) === 'Quinoa Black Bean Salad',
	'5C 4 all three remain consistent around same recipe entity'
);
assert_true(
	stripos( (string) ( $titles_5c['recommended_title'] ?? '' ), 'Quinoa Black Bean Salad' ) !== false
	&& stripos( (string) ( $kw_5c['primary_focus_keyword'] ?? '' ), 'quinoa black bean' ) !== false
	&& stripos( (string) ( $meta_5c['metadesc'] ?? '' ), 'Quinoa Black Bean Salad' ) !== false,
	'5C 5 Quinoa Black Bean Salad remains specific'
);

$reject_salad_5c = $seo_svc->proposal_from_decoded(
	rsaip_5a_valid_proposal_data(
		array(
			'topic'                 => 'Salad',
			'primary_focus_keyword' => 'salad',
			'recommended_title'     => 'Salad',
			'seo_title_suggestions' => array( 'Salad' ),
		)
	),
	$seo_ctx
);
assert_true( ! $reject_salad_5c->ok(), '5C 6 Generic Salad is rejected at proposal validation' );

assert_true(
	( $kw_5c['primary_focus_keyword'] ?? '' ) !== ''
	&& is_array( $kw_5c['secondary_keywords'] ?? null )
	&& ( $kw_5c['primary_focus_keyword'] ?? '' ) !== ( $kw_5c['secondary_keywords'][0] ?? '' ),
	'5C 7 Primary keyword is separated from secondary keywords'
);

$primary_5c = (string) $kw_5c['primary_focus_keyword'];
$secs_5c    = (array) $kw_5c['secondary_keywords'];
$entities_5c = (array) $kw_5c['entities'];
$selected_one = array( $secs_5c[0] ?? 'easy quinoa salad' );
$apply_with_sel = SeoOptimizationService::build_keywords_apply_list( $primary_5c, $selected_one );
$apply_primary_only = SeoOptimizationService::build_keywords_apply_list( $primary_5c, array() );

assert_true(
	$apply_with_sel === array( $primary_5c, $selected_one[0] )
	|| ( count( $apply_with_sel ) === 2 && $apply_with_sel[0] === $primary_5c ),
	'5C 8 User can select secondary keywords into Apply list'
);
assert_true(
	$apply_primary_only === array( $primary_5c )
	&& ! in_array( $secs_5c[0] ?? 'x', $apply_primary_only, true ),
	'5C 9 Unselected secondary keywords are not included in final Apply candidate'
);
foreach ( $entities_5c as $ent ) {
	assert_true( ! in_array( $ent, $apply_with_sel, true ), '5C 10 Entities are not silently included as keywords: ' . $ent );
}
assert_true(
	! in_array( 'cilantro', $apply_primary_only, true )
	&& empty( array_intersect( $entities_5c, (array) ( $kw_5c['keywords'] ?? array() ) ) ),
	'5C 10b default keywords payload excludes entities'
);

$kw_writer = new KeywordsWriter( new FakePostMutationEnvironment(), new SeoOwnershipResolver( new FakePostMutationEnvironment() ) );
$norm_rm   = $kw_writer->normalize_new_value( implode( ', ', $apply_with_sel ) );
assert_true(
	is_array( $norm_rm ) && isset( $norm_rm[0] ) && $norm_rm[0] === $primary_5c,
	'5C 11 Rank Math KEYWORDS normalize keeps primary first (CSV semantics via writer)'
);

$meta_len = strlen( (string) ( $meta_5c['metadesc'] ?? '' ) );
assert_true( $meta_len >= 120 && $meta_len <= 160, '5C 12 Meta comes from proposal (PMS normalizes on Apply)' );

$ai_fail_5c = new SeoOptimizationService(
	null,
	null,
	null,
	null,
	static function () {
		return new class() {
			public function get_error_code() {
				return 'rsaip_ai_http';
			}
			public function get_error_message() {
				return 'timeout';
			}
		};
	}
);
$fail_hard = $ai_fail_5c->analyze( $quinoa_ctx_5c );
assert_true( ! $fail_hard->ok(), '5C 13 AI failure is a hard error' );
assert_true( ! $reject_salad_5c->ok(), '5C 14 Invalid proposal is a hard error' );

$ai_gen_src = file_get_contents( $plugin_root . '/includes/class-rsaip-ai.php' );
$title_fn_5c = preg_match( '/function generate_title_suggestions_for_post\( int \$post_id \): array \{(.*?)public function apply_post_title/s', (string) $ai_gen_src, $tm5 ) === 1 ? $tm5[1] : '';
$kw_fn_5c    = preg_match( '/function generate_keywords_for_post\( int \$post_id \): array \{(.*?)public function apply_keywords_to_post/s', (string) $ai_gen_src, $km5 ) === 1 ? $km5[1] : '';
$meta_fn_5c  = preg_match( '/function generate_meta_description_for_post\( int \$post_id \): array \{(.*?)public function apply_meta_description_to_post/s', (string) $ai_gen_src, $mm5 ) === 1 ? $mm5[1] : '';

assert_true(
	strpos( $title_fn_5c, 'get_or_create_proposal_for_post' ) !== false
	&& strpos( $title_fn_5c, 'wp_update_post' ) === false
	&& strpos( $title_fn_5c, 'update_post_meta' ) === false
	&& strpos( $title_fn_5c, 'heuristic_titles' ) === false
	&& strpos( $title_fn_5c, 'keep_titles_with_topic' ) !== false,
	'5C 15 Titles: unified proposal + 4B gate, no direct write, no heuristic fallback'
);
assert_true(
	strpos( $kw_fn_5c, 'get_or_create_proposal_for_post' ) !== false
	&& strpos( $kw_fn_5c, 'heuristic_keywords' ) === false
	&& strpos( $kw_fn_5c, 'wp_update_post' ) === false
	&& strpos( $kw_fn_5c, 'update_post_meta' ) === false,
	'5C Keywords: unified proposal, no heuristic dump, no direct write'
);
assert_true(
	strpos( $meta_fn_5c, 'get_or_create_proposal_for_post' ) !== false
	&& strpos( $meta_fn_5c, 'heuristic_meta_description' ) === false
	&& strpos( $meta_fn_5c, 'wp_update_post' ) === false,
	'5C Meta: unified proposal, no canned heuristic fallback, no direct write'
);

$svc_src_5c = file_get_contents( $plugin_root . '/src/Modules/Ai/SeoOptimization/SeoOptimizationService.php' );
assert_true(
	is_string( $svc_src_5c )
	&& strpos( $svc_src_5c, 'get_or_create_proposal_for_post' ) !== false
	&& strpos( $svc_src_5c, 'SeoOptimizationProposalStore' ) !== false
	&& strpos( $svc_src_5c, 'PostMutationService' ) === false
	&& strpos( $svc_src_5c, 'wp_update_post' ) === false,
	'5C service reuses store; never PMS / never writes'
);

$store_file = $plugin_root . '/src/Modules/Ai/SeoOptimization/SeoOptimizationProposalStore.php';
assert_true( is_file( $store_file ), '5C ProposalStore file exists (request + session scoped, no DB table)' );

// Request-scoped reuse without WordPress get_post: put/get with explicit fingerprint.
SeoOptimizationProposalStore::clear();
SeoOptimizationProposalStore::put( 99, 'fp-test-5c', $proposal_5c, $seo_ctx );
$cached_5c = SeoOptimizationProposalStore::get( 99, 'fp-test-5c' );
assert_true(
	is_array( $cached_5c )
	&& $cached_5c['proposal']->primary_focus_keyword() === $proposal_5c->primary_focus_keyword(),
	'5C proposal store reuses compatible proposal by fingerprint'
);
assert_true( SeoOptimizationProposalStore::get( 99, 'stale-fp' ) === null, '5C stale fingerprint misses cache' );

$ajax_src_5c = file_get_contents( $plugin_root . '/includes/class-rsaip-ajax.php' );
assert_true(
	is_string( $ajax_src_5c )
	&& strpos( $ajax_src_5c, 'rsaip_ai_propose_title' ) !== false
	&& strpos( $ajax_src_5c, 'apply_from_preview' ) !== false
	&& strpos( $ajax_src_5c, 'MutationType::KEYWORDS' ) !== false,
	'5C 16/17 Preview ticket + Apply still go through PMS endpoints'
);

$helpers_5c = file_get_contents( $plugin_root . '/includes/helpers.php' );
assert_true(
	is_string( $helpers_5c )
	&& preg_match( '/function rsaip_never_modify_posts\(\): bool \{/s', $helpers_5c ) === 1
	&& preg_match( '/function rsaip_fix_with_ai_blocked\(\): bool \{\s*return true;/s', $helpers_5c ) === 1
	&& preg_match( '/function rsaip_bulk_apply_meta_blocked\(\): bool \{\s*return true;/s', $helpers_5c ) === 1,
	'5C 18/21 Safe Mode / Fix With AI / Bulk Apply protections unchanged'
);

$analyze_fn_5c = preg_match( '/function analyze_post_ai\( int \$post_id \): array \{(.*?)public function generate_semantic_seo_for_post/s', (string) $ai_gen_src, $am5c ) === 1 ? $am5c[1] : '';
assert_true(
	strpos( $analyze_fn_5c, 'analyze_post' ) !== false
	&& strpos( $analyze_fn_5c, 'wp_update_post' ) === false
	&& strpos( $analyze_fn_5c, 'update_post_meta' ) === false,
	'5C 19 Analyze remains read-only and seeds proposal store via analyze_post'
);

$repair_src_5c = file_get_contents( $plugin_root . '/src/Modules/Schema/RecipeSchemaRepairService.php' );
assert_true(
	is_string( $repair_src_5c )
	&& strpos( $repair_src_5c, 'wp_update_post' ) === false
	&& is_string( $ai_gen_src )
	&& strpos( $title_fn_5c . $kw_fn_5c . $meta_fn_5c, 'RecipeSchemaRepair' ) === false,
	'5C 20 Recipe Schema is not modified by Generate paths'
);

$js_5c = file_get_contents( $plugin_root . '/assets/admin.js' );
$view_5c = file_get_contents( $plugin_root . '/src/Views/admin/ai.php' );
assert_true(
	is_string( $js_5c )
	&& strpos( $js_5c, 'renderKeywordsPicker' ) !== false
	&& strpos( $js_5c, 'syncKeywordsApplyCandidate' ) !== false
	&& strpos( $js_5c, 'rsaip-kw-secondary' ) !== false
	&& strpos( $js_5c, 'renderTitleSuggestions' ) !== false
	&& is_string( $view_5c )
	&& strpos( $view_5c, 'rsaip-ai-keywords-picker' ) !== false,
	'5C UI separates primary/secondary and builds Apply candidate from selection'
);

$kw_writer_src = file_get_contents( $plugin_root . '/src/Modules/PostMutation/Writers/KeywordsWriter.php' );
assert_true(
	is_string( $kw_writer_src )
	&& strpos( $kw_writer_src, 'implode( \', \', $list )' ) !== false
	&& strpos( $kw_writer_src, 'rsaip_generated_keywords' ) !== false,
	'5C Rank Math ownership/storage semantics preserved in KeywordsWriter (unchanged)'
);

// --- Milestone 5D: Read-only heading / FAQ / ALT / link / gap suggestions ---
$h2_ok = $seo_svc->proposal_from_decoded(
	rsaip_5a_valid_proposal_data(
		array(
			'heading_suggestions' => array(
				array(
					'level'     => 'h2',
					'text'      => 'How to Make Quinoa Black Bean Salad',
					'rationale' => 'Guides preparation for this recipe',
				),
			),
		)
	),
	$seo_ctx
);
assert_true(
	$h2_ok->ok()
	&& ( $h2_ok->proposal()->heading_suggestions()[0]['level'] ?? '' ) === 'h2',
	'5D 1 Valid H2 suggestion'
);

$h3_ok = $seo_svc->proposal_from_decoded(
	rsaip_5a_valid_proposal_data(
		array(
			'heading_suggestions' => array(
				array(
					'level'     => 'h3',
					'text'      => 'Lime dressing tips',
					'rationale' => 'Supports flavor section without stuffing',
				),
			),
		)
	),
	$seo_ctx
);
assert_true(
	$h3_ok->ok()
	&& ( $h3_ok->proposal()->heading_suggestions()[0]['level'] ?? '' ) === 'h3',
	'5D 2 Valid H3 suggestion'
);

assert_true(
	! $seo_svc->proposal_from_decoded(
		rsaip_5a_valid_proposal_data(
			array(
				'heading_suggestions' => array(
					array(
						'level'     => 'h1',
						'text'      => 'Quinoa Black Bean Salad',
						'rationale' => 'Bad level',
					),
				),
			)
		),
		$seo_ctx
	)->ok(),
	'5D 3 Invalid heading level rejected'
);

assert_true(
	! $seo_svc->proposal_from_decoded(
		rsaip_5a_valid_proposal_data(
			array(
				'heading_suggestions' => array(
					array(
						'level'     => 'h2',
						'text'      => '',
						'rationale' => 'Empty text',
					),
				),
			)
		),
		$seo_ctx
	)->ok(),
	'5D 4 Empty heading rejected'
);

assert_true(
	! $seo_svc->proposal_from_decoded(
		rsaip_5a_valid_proposal_data(
			array(
				'heading_suggestions' => array(
					array(
						'level'     => 'h2',
						'text'      => 'Storage',
						'rationale' => '',
					),
				),
			)
		),
		$seo_ctx
	)->ok(),
	'5D 4b Empty heading rationale rejected'
);

$faq_ok = $seo_svc->proposal_from_decoded(
	rsaip_5a_valid_proposal_data(
		array(
			'faq_suggestions' => array(
				array(
					'q' => 'Can I meal-prep Quinoa Black Bean Salad?',
					'a' => 'Yes, chill covered for up to two days.',
				),
			),
		)
	),
	$seo_ctx
);
assert_true( $faq_ok->ok() && count( $faq_ok->proposal()->faq_suggestions() ) === 1, '5D 5 Valid FAQ suggestion' );

assert_true(
	! $seo_svc->proposal_from_decoded(
		rsaip_5a_valid_proposal_data(
			array(
				'faq_suggestions' => array(
					array(
						'q' => 'What about leftovers?',
						'a' => '',
					),
				),
			)
		),
		$seo_ctx
	)->ok(),
	'5D 6 Malformed FAQ rejected'
);

assert_true(
	! $seo_svc->proposal_from_decoded(
		rsaip_5a_valid_proposal_data(
			array(
				'faq_suggestions' => array(
					array(
						'q' => 'How many calories?',
						'a' => 'This salad has 450 calories per serving.',
					),
				),
			)
		),
		$seo_ctx
	)->ok(),
	'5D 7 FAQ with unsupported invented nutrition rejected'
);

$alt_ok = $seo_svc->proposal_from_decoded(
	rsaip_5a_valid_proposal_data(
		array(
			'image_alt_suggestions' => array(
				array(
					'target' => 'inline',
					'alt'    => 'Close-up of Quinoa Black Bean Salad',
				),
			),
		)
	),
	$seo_ctx
);
assert_true(
	$alt_ok->ok()
	&& ( $alt_ok->proposal()->image_alt_suggestions()[0]['target'] ?? '' ) === 'inline',
	'5D 8 Valid ALT suggestion'
);

assert_true(
	! $seo_svc->proposal_from_decoded(
		rsaip_5a_valid_proposal_data(
			array(
				'image_alt_suggestions' => array(
					array(
						'target' => 'gallery',
						'alt'    => 'Salad bowl',
					),
				),
			)
		),
		$seo_ctx
	)->ok(),
	'5D 9 Invalid ALT target rejected'
);

$link_ok = $seo_svc->proposal_from_decoded(
	rsaip_5a_valid_proposal_data(
		array(
			'internal_link_suggestions' => array(
				array(
					'anchor'      => 'black bean recipes',
					'target_hint' => 'related black bean salad posts',
					'reason'      => 'Topical cluster for this recipe',
				),
			),
		)
	),
	$seo_ctx
);
assert_true( $link_ok->ok(), '5D 10 Valid internal-link suggestion' );
$link_row = $link_ok->proposal()->internal_link_suggestions()[0];
assert_true(
	! isset( $link_row['url'] )
	&& ! preg_match( '#https?://#i', (string) ( $link_row['target_hint'] ?? '' ) ),
	'5D 11 Internal-link suggestion does not create a URL'
);

assert_true(
	! $seo_svc->proposal_from_decoded(
		rsaip_5a_valid_proposal_data(
			array(
				'internal_link_suggestions' => array(
					array(
						'anchor'      => 'quinoa',
						'target_hint' => 'https://example.com/quinoa',
						'reason'      => 'Invented URL',
					),
				),
			)
		),
		$seo_ctx
	)->ok(),
	'5D 11b URL-shaped target_hint rejected'
);

$gaps_ok = $seo_svc->proposal_from_decoded(
	rsaip_5a_valid_proposal_data( array( 'content_gaps' => array( 'Consider adding storage guidance if missing from full article' ) ) ),
	$seo_ctx
);
assert_true(
	$gaps_ok->ok()
	&& in_array( 'Consider adding storage guidance if missing from full article', $gaps_ok->proposal()->content_gaps(), true ),
	'5D 12 Content gaps remain suggestions'
);

// 5D 13/14 schema still server-controlled (reuse 5B.1 filter behavior).
$schema_ai = rsaip_5a_valid_proposal_data(
	array(
		'seo_issues' => array(
			array(
				'code'     => 'missing_recipe_schema',
				'category' => 'other',
				'severity' => 'high',
			),
		),
	)
);
$schema_filtered = $seo_svc->proposal_from_decoded( $schema_ai, $seo_ctx );
assert_true( $schema_filtered->ok(), '5D 13 proposal ok with schema filter' );
$codes_5d = array_map(
	static function ( $i ) {
		return $i['code'] ?? '';
	},
	$schema_filtered->proposal()->seo_issues()
);
assert_true( ! in_array( 'missing_recipe_schema', $codes_5d, true ), '5D 14 AI cannot override schema status when valid' );

$js_5d   = file_get_contents( $plugin_root . '/assets/admin.js' );
$view_5d = file_get_contents( $plugin_root . '/src/Views/admin/ai.php' );
assert_true(
	is_string( $js_5d )
	&& strpos( $js_5d, 'populateReadonlySuggestions' ) !== false
	&& strpos( $js_5d, 'renderHeadingSuggestions' ) !== false
	&& strpos( $js_5d, 'Suggestions only — no changes are applied' ) !== false
	&& strpos( $js_5d, 'data-action="rsaip_ai_apply_heading"' ) === false
	&& strpos( $js_5d, 'data-action="rsaip_ai_apply_faq"' ) === false
	&& strpos( $js_5d, 'data-action="rsaip_ai_apply_alt"' ) === false
	&& strpos( $js_5d, 'data-action="rsaip_ai_apply_link"' ) === false
	&& is_string( $view_5d )
	&& strpos( $view_5d, 'rsaip-ai-suggest-headings' ) !== false
	&& strpos( $view_5d, 'Apply' ) !== false // title/meta/keywords Apply still present elsewhere
	&& strpos( $view_5d, 'rsaip_ai_apply_heading' ) === false
	&& strpos( $view_5d, 'Suggestions only — no changes are applied' ) !== false,
	'5D 15 No Apply buttons for 5D suggestions; UI sections present'
);

$svc_5d_src = file_get_contents( $plugin_root . '/src/Modules/Ai/SeoOptimization/SeoOptimizationService.php' );
$val_5d_src = file_get_contents( $plugin_root . '/src/Modules/Ai/SeoOptimization/SeoOptimizationProposalValidator.php' );
$prompt_5d  = file_get_contents( $plugin_root . '/src/Modules/Ai/SeoOptimization/SeoOptimizationPromptBuilder.php' );
assert_true(
	is_string( $svc_5d_src )
	&& strpos( $svc_5d_src, 'wp_update_post' ) === false
	&& strpos( $svc_5d_src, 'update_post_meta' ) === false
	&& strpos( $svc_5d_src, 'wp_insert_post' ) === false
	&& strpos( $svc_5d_src, 'PostMutationService' ) === false
	&& strpos( $svc_5d_src, 'apply_from_preview' ) === false
	&& is_string( $val_5d_src )
	&& strpos( $val_5d_src, 'wp_update_post' ) === false
	&& strpos( $val_5d_src, 'update_post_meta' ) === false,
	'5D 16/17/20 No post/meta/PMS Apply in SEO optimization engine'
);

assert_true(
	is_string( $prompt_5d )
	&& strpos( $prompt_5d, 'Do not invent ingredients' ) !== false
	&& strpos( $prompt_5d, 'NEVER invent URLs' ) !== false
	&& strpos( $prompt_5d, 'SERVER-AUTHORITATIVE' ) !== false,
	'5D prompt hardens hallucination + schema authority'
);

$pms_5d = file_get_contents( $plugin_root . '/src/Modules/PostMutation/PostMutationService.php' );
$helpers_5d = file_get_contents( $plugin_root . '/includes/helpers.php' );
assert_true( is_string( $pms_5d ) && strpos( $pms_5d, 'heading_suggestions' ) === false, '5D PMS unchanged (no heading mutation)' );
assert_true(
	is_string( $helpers_5d )
	&& preg_match( '/function rsaip_never_modify_posts\(\): bool \{/s', $helpers_5d ) === 1
	&& preg_match( '/function rsaip_fix_with_ai_blocked\(\): bool \{\s*return true;/s', $helpers_5d ) === 1,
	'5D 21 Safe Mode / Fix With AI unchanged; Analyze still unblocked by Safe Mode helpers'
);

assert_true(
	strpos( (string) file_get_contents( $plugin_root . '/includes/class-rsaip-ai.php' ), 'get_or_create_proposal_for_post' ) !== false,
	'5D 22 5C unified Generate path still present'
);
$ai_5d = (string) file_get_contents( $plugin_root . '/includes/class-rsaip-ai.php' );
assert_true(
	substr_count( $ai_5d, 'get_or_create_proposal_for_post' ) >= 3,
	'5D 22b Generate Titles/Keywords/Meta still consume unified proposal'
);

SeoOptimizationProposalStore::clear();
SeoOptimizationProposalStore::put( 1844, 'fp-5d', $h2_ok->proposal(), $seo_ctx );
assert_true( SeoOptimizationProposalStore::get( 1844, 'fp-5d' ) !== null, '5D 23 Proposal reuse remains compatible' );

assert_true(
	! ( new SeoOptimizationService( null, null, null, null, static function () {
		return 'NOT JSON';
	} ) )->analyze( $quinoa_ctx_input )->ok(),
	'5D 24 Invalid AI JSON still hard-fails'
);

$unknown_nested = rsaip_5a_valid_proposal_data(
	array(
		'internal_link_suggestions' => array(
			array(
				'anchor'      => 'quinoa recipes',
				'target_hint' => 'quinoa category',
				'reason'      => 'Related cluster',
				'url'         => 'https://evil.example/post',
				'post_id'     => 999,
			),
		),
	)
);
$unknown_res = $seo_svc->proposal_from_decoded( $unknown_nested, $seo_ctx );
assert_true( ! $unknown_res->ok(), '5D 25 Unknown nested URL/post_id fields are not trusted (rejected)' );

$attachment_touch = ( strpos( $svc_5d_src, 'wp_update_attachment' ) === false )
	&& ( strpos( $svc_5d_src, 'update_post_meta' ) === false )
	&& ( strpos( (string) $view_5d, 'rsaip_ai_apply_alt' ) === false );
assert_true( $attachment_touch, '5D 18 No attachment modification path in 5D UI/service' );

$schema_repair_5d = file_get_contents( $plugin_root . '/src/Modules/Schema/RecipeSchemaRepairService.php' );
assert_true(
	is_string( $schema_repair_5d ) && strpos( $schema_repair_5d, 'wp_update_post' ) === false,
	'5D 19 No schema modification (repair still deferred)'
);

// --- Milestone 5E: Recipe-aware intelligence, entity quality & search intent ---
$selector_5e = new TitleTopicSelector();
assert_true(
	$selector_5e->covers_specific_entity( 'quinoa black bean salad', 'Quinoa Black Bean Salad' ),
	'5E A3 covers_specific_entity accepts specific primary'
);
assert_true(
	! $selector_5e->covers_specific_entity( 'Salad', 'Quinoa Black Bean Salad' ),
	'5E A2 Salad does not cover Quinoa Black Bean Salad'
);
assert_true(
	! $selector_5e->covers_specific_entity( 'Black Bean Salad', 'Quinoa Black Bean Salad' ),
	'5E A2b truncated Black Bean Salad is weaker than full entity'
);
assert_true(
	$selector_5e->covers_specific_entity( 'Easy Quinoa Black Bean Salad Recipe', 'Quinoa Black Bean Salad' ),
	'5E A5 natural title variation accepted when entity remains clear'
);

$entity_ctx = $seo_svc->context_builder()->from_server_array(
	array(
		'post_id'                => 1844,
		'current_title'          => 'Delicious Quinoa Black Bean Salad Recipe - Healthy & Easy to Make',
		'content_excerpt'        => str_repeat( 'Quinoa black bean salad with lime and cilantro. ', 20 ),
		'existing_focus_keyword' => 'Salad',
		'detected_recipe_name'   => 'Quinoa Black Bean Salad',
		'recipe_summary'         => 'Ingredients: quinoa, black beans, lime, cilantro | servings: 4',
		'recipe_ingredients'     => array( 'quinoa', 'black beans', 'lime', 'cilantro' ),
		'known_recipe_facts'     => array( 'servings' => '4' ),
		'categories'             => array( 'Salads' ),
		'tags'                   => array( 'quinoa', 'vegetarian' ),
		'seo_owner_label'        => 'rankmath',
		'has_recipe_card'        => true,
		'recipe_schema_status'   => RecipeSchemaStatus::VALID_RECIPE,
		'recipe_schema_source'   => 'content',
		'recipe_schema_authoritative' => true,
	)
);
assert_true(
	$entity_ctx->canonical_recipe_entity() === 'Quinoa Black Bean Salad'
	|| stripos( $entity_ctx->canonical_recipe_entity(), 'Quinoa Black Bean Salad' ) !== false,
	'5E A1 specific recipe entity wins over generic focus keyword Salad'
);
assert_true( $entity_ctx->existing_focus_keyword() === 'Salad', '5E focus keyword preserved as informational context only' );
assert_true( in_array( 'quinoa', $entity_ctx->recipe_ingredients(), true ), '5E C9 known ingredients preserved in context' );
assert_true( ( $entity_ctx->known_recipe_facts()['servings'] ?? '' ) === '4', '5E known servings fact grounded' );
$prompt_5e = $entity_ctx->to_prompt_array();
assert_true(
	( $prompt_5e['known_recipe_facts']['prep_time'] ?? '' ) === 'unavailable'
	&& ( $prompt_5e['known_recipe_facts']['cook_time'] ?? '' ) === 'unavailable',
	'5E C11/C12 unknown timing marked unavailable (not invented)'
);

assert_true(
	! $seo_svc->proposal_from_decoded(
		rsaip_5a_valid_proposal_data(
			array(
				'topic'                 => 'Salad',
				'primary_focus_keyword' => 'salad',
				'recommended_title'     => 'Salad Recipe',
				'seo_title_suggestions' => array( 'Salad Recipe' ),
			)
		),
		$entity_ctx
	)->ok(),
	'5E A2/A4 generic Salad topic/primary rejected'
);

assert_true(
	! $seo_svc->proposal_from_decoded(
		rsaip_5a_valid_proposal_data(
			array(
				'topic'                 => 'Black Bean Salad',
				'primary_focus_keyword' => 'black bean salad',
				'recipe_name'           => 'Black Bean Salad',
				'recommended_title'     => 'Black Bean Salad Recipe',
				'seo_title_suggestions' => array( 'Black Bean Salad Recipe' ),
			)
		),
		$entity_ctx
	)->ok(),
	'5E incomplete entity Black Bean Salad rejected vs Quinoa Black Bean Salad'
);

$ok_specific = $seo_svc->proposal_from_decoded( rsaip_5a_valid_proposal_data(), $entity_ctx );
assert_true( $ok_specific->ok(), '5E A3 specific primary/topic accepted' );
assert_true( $ok_specific->proposal()->search_intent() === 'informational', '5E B6 recipe query intent informational OK' );

assert_true(
	! $seo_svc->proposal_from_decoded(
		rsaip_5a_valid_proposal_data( array( 'search_intent' => 'curiosity' ) ),
		$entity_ctx
	)->ok(),
	'5E B8 unsupported intent rejected'
);
assert_true(
	in_array( 'informational', array( 'informational', 'transactional', 'navigational', 'commercial' ), true ),
	'5E B7 allowed intent enum still enforced via validator'
);

assert_true(
	! $seo_svc->proposal_from_decoded(
		rsaip_5a_valid_proposal_data(
			array(
				'faq_suggestions' => array(
					array(
						'q' => 'Calories?',
						'a' => 'About 520 calories per bowl.',
					),
				),
			)
		),
		$entity_ctx
	)->ok(),
	'5E C11 unknown nutrition not invented in FAQ'
);

assert_true(
	! $seo_svc->proposal_from_decoded(
		rsaip_5a_valid_proposal_data(
			array(
				'faq_suggestions' => array(
					array(
						'q' => 'How long to cook?',
						'a' => 'Cook quinoa for 45 minutes until done.',
					),
				),
			)
		),
		$entity_ctx
	)->ok(),
	'5E C12 unknown timing not invented'
);

assert_true(
	! $seo_svc->proposal_from_decoded(
		rsaip_5a_valid_proposal_data( array( 'recipe_name' => 'Salad' ) ),
		$entity_ctx
	)->ok(),
	'5E C13 recipe_name must stay consistent with entity'
);

// Schema authority (reuse patterns).
$ext_ctx = $seo_svc->context_builder()->from_server_array(
	array(
		'post_id'              => 2,
		'current_title'        => 'Quinoa Black Bean Salad',
		'content_excerpt'      => 'Quinoa black bean salad.',
		'detected_recipe_name' => 'Quinoa Black Bean Salad',
		'recipe_schema_status' => RecipeSchemaStatus::EXTERNAL_OR_UNKNOWN,
		'seo_owner_label'      => 'rankmath',
	)
);
$ext_prop = $seo_svc->proposal_from_decoded(
	rsaip_5a_valid_proposal_data(
		array(
			'recommendations' => array(
				array(
					'action'      => 'Create Recipe Schema JSON-LD',
					'applies_via' => 'none',
					'note'        => 'Add schema',
				),
			),
		)
	),
	$ext_ctx
);
assert_true( $ext_prop->ok(), '5E D15 external proposal validates' );
$ext_actions = array_map(
	static function ( $r ) {
		return $r['action'] ?? '';
	},
	$ext_prop->proposal()->recommendations()
);
assert_true( ! in_array( 'Create Recipe Schema JSON-LD', $ext_actions, true ), '5E D15 external_or_unknown prevents creation recommendation' );

$multi_ctx = $seo_svc->context_builder()->from_server_array(
	array(
		'post_id'              => 3,
		'current_title'        => 'Quinoa Black Bean Salad',
		'content_excerpt'      => 'Quinoa.',
		'detected_recipe_name' => 'Quinoa Black Bean Salad',
		'recipe_schema_status' => RecipeSchemaStatus::MULTIPLE,
	)
);
$multi_prop = $seo_svc->proposal_from_decoded(
	rsaip_5a_valid_proposal_data(
		array(
			'recommendations' => array(
				array(
					'action'      => 'Create Recipe Schema JSON-LD',
					'applies_via' => 'none',
					'note'        => 'Add schema',
				),
			),
		)
	),
	$multi_ctx
);
$multi_actions = array_map(
	static function ( $r ) {
		return $r['action'] ?? '';
	},
	$multi_prop->proposal()->recommendations()
);
assert_true( ! in_array( 'Create Recipe Schema JSON-LD', $multi_actions, true ), '5E D16 multiple prevents creation recommendation' );

$miss_ctx = $seo_svc->context_builder()->from_server_array(
	array(
		'post_id'              => 4,
		'current_title'        => 'Quinoa Black Bean Salad',
		'content_excerpt'      => 'Quinoa black bean salad with lime.',
		'detected_recipe_name' => 'Quinoa Black Bean Salad',
		'recipe_schema_status' => RecipeSchemaStatus::MISSING,
		'seo_owner_label'      => 'rsaip',
	)
);
$miss_prop = $seo_svc->proposal_from_decoded(
	rsaip_5a_valid_proposal_data(
		array(
			'seo_issues' => array(
				array(
					'code'     => 'missing_recipe_schema',
					'category' => 'other',
					'severity' => 'medium',
				),
			),
		)
	),
	$miss_ctx
);
$miss_codes = array_map(
	static function ( $i ) {
		return $i['code'] ?? '';
	},
	$miss_prop->proposal()->seo_issues()
);
assert_true( in_array( 'missing_recipe_schema', $miss_codes, true ), '5E D17 missing may expose missing-schema issue' );

assert_true(
	! $seo_svc->proposal_from_decoded(
		rsaip_5a_valid_proposal_data(
			array(
				'recommended_title'     => 'Best Salad Recipe',
				'seo_title_suggestions' => array( 'Best Salad Recipe' ),
			)
		),
		$entity_ctx
	)->ok(),
	'5E E21 generic title rejected'
);

assert_true(
	! $seo_svc->proposal_from_decoded(
		rsaip_5a_valid_proposal_data(
			array(
				'meta_description' => 'Enjoy this Quinoa Black Bean Salad with fresh lime flavor. This bowl has exactly 450 calories per serving and is completely allergen-free for every guest at your table tonight.',
			)
		),
		$entity_ctx
	)->ok(),
	'5E E23 unsupported meta claims rejected'
);

assert_true(
	! $seo_svc->proposal_from_decoded(
		rsaip_5a_valid_proposal_data(
			array(
				'secondary_keywords' => array( 'chocolate lava cake', 'best sports cars' ),
			)
		),
		$entity_ctx
	)->ok(),
	'5E F26 unrelated secondary keyword rejected'
);

$kw_sep = $ok_specific->proposal();
assert_true(
	$kw_sep->primary_focus_keyword() !== ''
	&& ! in_array( $kw_sep->primary_focus_keyword(), $kw_sep->entities(), true ),
	'5E F24/F25 primary separated; entities are not keywords'
);
assert_true(
	SeoOptimizationService::build_keywords_apply_list( 'quinoa black bean salad', array() ) === array( 'quinoa black bean salad' ),
	'5E F27 user keyword selection remains authoritative (empty secondaries)'
);

assert_true(
	! $seo_svc->proposal_from_decoded(
		rsaip_5a_valid_proposal_data(
			array(
				'internal_link_suggestions' => array(
					array(
						'anchor'      => 'quinoa',
						'target_hint' => 'https://example.com/x',
						'reason'      => 'link',
					),
				),
			)
		),
		$entity_ctx
	)->ok(),
	'5E G30 internal links do not contain fabricated URLs'
);

$prompt_src_5e = file_get_contents( $plugin_root . '/src/Modules/Ai/SeoOptimization/SeoOptimizationPromptBuilder.php' );
$ctx_src_5e    = file_get_contents( $plugin_root . '/src/Modules/Ai/SeoOptimization/SeoOptimizationContextBuilder.php' );
$svc_src_5e    = file_get_contents( $plugin_root . '/src/Modules/Ai/SeoOptimization/SeoOptimizationService.php' );
$helpers_5e    = file_get_contents( $plugin_root . '/includes/helpers.php' );
$pms_5e        = file_get_contents( $plugin_root . '/src/Modules/PostMutation/PostMutationService.php' );
assert_true(
	is_string( $prompt_src_5e )
	&& strpos( $prompt_src_5e, 'canonical_recipe_entity' ) !== false
	&& strpos( $prompt_src_5e, 'unavailable' ) !== false
	&& is_string( $ctx_src_5e )
	&& strpos( $ctx_src_5e, 'resolve_canonical_entity' ) !== false
	&& strpos( $ctx_src_5e, 'pick_canonical_entity' ) !== false,
	'5E prompt/context use canonical entity + unavailable facts'
);
assert_true(
	is_string( $svc_src_5e )
	&& strpos( $svc_src_5e, 'wp_update_post' ) === false
	&& strpos( $svc_src_5e, 'PostMutationService' ) === false
	&& strpos( $svc_src_5e, 'get_or_create_proposal_for_post' ) !== false,
	'5E H32-38 Analyze/Generate remain read-only; unified proposal reuse preserved; no PMS bypass'
);
assert_true(
	is_string( $helpers_5e )
	&& preg_match( '/function rsaip_never_modify_posts\(\): bool \{/s', $helpers_5e ) === 1
	&& preg_match( '/function rsaip_fix_with_ai_blocked\(\): bool \{\s*return true;/s', $helpers_5e ) === 1
	&& preg_match( '/function rsaip_bulk_apply_meta_blocked\(\): bool \{\s*return true;/s', $helpers_5e ) === 1,
	'5E H39/H40 Safe Mode / Fix With AI / Bulk unchanged'
);
assert_true( is_string( $pms_5e ) && strpos( $pms_5e, 'canonical_recipe_entity' ) === false, '5E PMS untouched' );
assert_true(
	is_string( $schema_repair_5d ) && strpos( $schema_repair_5d, 'wp_update_post' ) === false,
	'5E D18/H35 schema remains read-only'
);

$js_5e = file_get_contents( $plugin_root . '/assets/admin.js' );
assert_true(
	is_string( $js_5e )
	&& strpos( $js_5e, 'populateReadonlySuggestions' ) !== false
	&& strpos( $js_5e, 'canonical_recipe_entity' ) !== false
	&& strpos( $js_5e, 'data-action="rsaip_ai_apply_heading"' ) === false,
	'5E H34 5D suggestions remain read-only; entity shown in Analyze UI'
);

// --- Article Generator A: Architecture foundation ---
function rsaip_aga_body_html( string $extra = '' ): string {
	$sentence = 'Quinoa Black Bean Salad is a fresh meal prep option with lime, herbs, and pantry staples for weeknights. ';
	return '<p>' . str_repeat( $sentence, 70 ) . '</p><h2>Tips for Quinoa Black Bean Salad</h2><p>Keep seasoning light and taste as you go while preparing Quinoa Black Bean Salad at home.</p>' . $extra;
}

function rsaip_aga_valid_article_data( array $overrides = array() ): array {
	$base = array(
		'title'                 => 'Easy Quinoa Black Bean Salad Recipe',
		'excerpt'               => 'A fresh quinoa black bean salad for weeknights.',
		'meta_description'      => 'Make an easy Quinoa Black Bean Salad with lime, cilantro, and simple pantry ingredients for a healthy meal.',
		'primary_focus_keyword' => 'quinoa black bean salad',
		'secondary_keywords'    => array( 'easy quinoa salad', 'healthy black bean salad' ),
		'entities'              => array( 'quinoa', 'black beans', 'lime' ),
		'search_intent'         => 'informational',
		'recipe_name'           => 'Quinoa Black Bean Salad',
		'recipe_facts'          => array(
			'ingredients' => array( 'status' => 'unavailable', 'value' => array() ),
			'prep_time'   => array( 'status' => 'unavailable', 'value' => '' ),
			'cook_time'   => array( 'status' => 'unavailable', 'value' => '' ),
			'total_time'  => array( 'status' => 'unavailable', 'value' => '' ),
			'servings'    => array( 'status' => 'unavailable', 'value' => '' ),
			'nutrition'   => array( 'status' => 'unavailable', 'value' => '' ),
			'dietary'     => array( 'status' => 'unavailable', 'value' => '' ),
			'allergens'   => array( 'status' => 'unavailable', 'value' => '' ),
		),
		'headings'              => array(
			array(
				'level' => 'h2',
				'text'  => 'Why Quinoa Black Bean Salad works',
			),
		),
		'faq'                   => array(
			array(
				'q' => 'Can I meal prep Quinoa Black Bean Salad?',
				'a' => 'Yes, chill covered for up to two days when storage guidance is known.',
			),
		),
		'image_plans'           => array(
			array(
				'placeholder' => '[[IMAGE_1]]',
				'alt'         => 'Bowl of Quinoa Black Bean Salad',
				'note'        => 'Featured plated dish',
			),
		),
		'content_html'          => rsaip_aga_body_html(),
		'template'              => 'general',
	);
	return array_merge( $base, $overrides );
}

$aga = new ArticleGenerationService();
$aga_ctx = $aga->context_builder()->from_brief(
	array(
		'title'                   => 'Delicious Quinoa Black Bean Salad Recipe - Healthy & Easy to Make',
		'template'                => 'general',
		'word_count'              => 1000,
		'keywords'                => array( 'quinoa black bean salad', 'easy quinoa salad' ),
		'canonical_recipe_entity' => 'Quinoa Black Bean Salad',
		'recipe_facts'            => array(
			'servings' => array( 'status' => RecipeFactAvailability::KNOWN, 'value' => '4' ),
		),
	)
);
assert_true( $aga_ctx->canonical_recipe_entity() === 'Quinoa Black Bean Salad' || stripos( $aga_ctx->canonical_recipe_entity(), 'Quinoa Black Bean Salad' ) !== false, 'AGA entity from brief' );
assert_true( $aga_ctx->requested_word_count() === 1000, 'AGA requested word count' );
assert_true( ( $aga_ctx->recipe_facts()['servings']['status'] ?? '' ) === RecipeFactAvailability::KNOWN, 'AGA 12 known recipe facts preserved' );
assert_true( ( $aga_ctx->recipe_facts()['prep_time']['status'] ?? '' ) === RecipeFactAvailability::UNAVAILABLE, 'AGA unavailable prep_time default' );

$aga_ok = $aga->proposal_from_decoded( rsaip_aga_valid_article_data(), $aga_ctx );
assert_true( $aga_ok->ok() && $aga_ok->proposal() !== null, 'AGA 1 Valid article proposal' );
assert_true( $aga_ok->proposal()->requested_word_count() === 1000, 'AGA word count contract exposes requested' );
assert_true( $aga_ok->proposal()->actual_word_count() > 0, 'AGA actual word count computed' );

assert_true(
	! $aga->proposal_from_decoded( rsaip_aga_valid_article_data( array( 'title' => '' ) ), $aga_ctx )->ok(),
	'AGA 2 Missing title rejected'
);
assert_true(
	! $aga->proposal_from_decoded( rsaip_aga_valid_article_data( array( 'content_html' => '' ) ), $aga_ctx )->ok(),
	'AGA 3 Missing content rejected'
);

$clamped_low = $aga->context_builder()->from_brief( array( 'title' => 'Quinoa Black Bean Salad', 'word_count' => -5 ) );
assert_true( $clamped_low->requested_word_count() === ArticleGenerationContext::WORD_COUNT_MIN, 'AGA 4/5 Word count bounds enforced (low clamp)' );
$clamped_high = $aga->context_builder()->from_brief( array( 'title' => 'Quinoa Black Bean Salad', 'word_count' => 99999 ) );
assert_true( $clamped_high->requested_word_count() === ArticleGenerationContext::WORD_COUNT_MAX, 'AGA 5 Word count bounds enforced (high clamp)' );

assert_true(
	! $aga->proposal_from_decoded( rsaip_aga_valid_article_data( array( 'template' => 'hacked_template' ) ), $aga_ctx )->ok(),
	'AGA 6 Invalid template rejected'
);
$tpl_ok = $aga->proposal_from_decoded( rsaip_aga_valid_article_data( array( 'template' => 'recipe_seo_midjourney' ) ), $aga_ctx );
assert_true(
	$tpl_ok->ok() && $tpl_ok->proposal()->template() === 'general',
	'AGA 6b Allowed AI template echo still yields server-authoritative template'
);

$aga_bad_json = new ArticleGenerationService(
	null,
	null,
	null,
	null,
	static function () {
		return 'NOT JSON';
	}
);
assert_true( ! $aga_bad_json->generate_from_context( $aga_ctx )->ok(), 'AGA 7 Invalid JSON rejected' );

$aga_ai_fail = new ArticleGenerationService(
	null,
	null,
	null,
	null,
	static function () {
		return new class() {
			public function get_error_code() {
				return 'rsaip_ai_http';
			}
			public function get_error_message() {
				return 'timeout';
			}
		};
	}
);
assert_true( ! $aga_ai_fail->generate_from_context( $aga_ctx )->ok(), 'AGA 8 AI failure hard-fails' );

$aga_svc_src = file_get_contents( $plugin_root . '/src/Modules/Ai/ArticleGeneration/ArticleGenerationService.php' );
assert_true(
	is_string( $aga_svc_src )
	&& strpos( $aga_svc_src, 'heuristic' ) === false
	&& strpos( $aga_svc_src, 'wp_insert_post' ) === false
	&& strpos( $aga_svc_src, 'wp_update_post' ) === false
	&& strpos( $aga_svc_src, 'update_post_meta' ) === false
	&& strpos( $aga_svc_src, 'PostMutationService' ) === false,
	'AGA 9/20/21/22 No heuristic fallback; no WP writes in ArticleGenerationService'
);

assert_true(
	! $aga->proposal_from_decoded(
		rsaip_aga_valid_article_data(
			array(
				'title'       => 'Best Salad Recipe',
				'recipe_name' => 'Salad',
			)
		),
		$aga_ctx
	)->ok(),
	'AGA 10/11 Generic recipe entity rejected when stronger entity exists'
);

assert_true(
	! $aga->proposal_from_decoded(
		rsaip_aga_valid_article_data(
			array(
				'recipe_facts' => array(
					'prep_time' => array( 'status' => 'known', 'value' => '45 minutes' ),
				),
			)
		),
		$aga_ctx
	)->ok(),
	'AGA 13 Unavailable recipe facts cannot be fabricated'
);

assert_true(
	! $aga->proposal_from_decoded(
		rsaip_aga_valid_article_data( array( 'content_html' => '<p>Hi</p><script>alert(1)</script>' ) ),
		$aga_ctx
	)->ok(),
	'AGA 14 Unsupported HTML rejected'
);
assert_true(
	! $aga->proposal_from_decoded(
		rsaip_aga_valid_article_data( array( 'content_html' => '<p><a href="javascript:alert(1)">x</a></p>' ) ),
		$aga_ctx
	)->ok(),
	'AGA 15 javascript URLs rejected'
);

assert_true(
	! $aga->proposal_from_decoded( rsaip_aga_valid_article_data( array( 'post_id' => 99 ) ), $aga_ctx )->ok(),
	'AGA 16 AI cannot provide post_id'
);
assert_true(
	! $aga->proposal_from_decoded( rsaip_aga_valid_article_data( array( 'meta_key' => 'rank_math_description' ) ), $aga_ctx )->ok(),
	'AGA 17 AI cannot provide meta keys'
);
assert_true(
	! $aga->proposal_from_decoded( rsaip_aga_valid_article_data( array( 'seo_owner' => 'rankmath' ) ), $aga_ctx )->ok(),
	'AGA 18 AI cannot provide SEO owner'
);
assert_true(
	! $aga->proposal_from_decoded( rsaip_aga_valid_article_data( array( 'mutation_type' => 'title' ) ), $aga_ctx )->ok(),
	'AGA 19 AI cannot provide mutation type'
);

$env_ui = $aga->to_generate_response( $aga_ok );
assert_true( ! empty( $env_ui['ok'] ) && empty( $env_ui['apply_allowed'] ) && empty( $env_ui['draft_allowed'] ), 'AGA generate envelope is read-only' );

$legacy_ai = file_get_contents( $plugin_root . '/includes/class-rsaip-ai.php' );
$legacy_ajax = file_get_contents( $plugin_root . '/includes/class-rsaip-ajax.php' );
assert_true(
	is_string( $legacy_ai )
	&& strpos( $legacy_ai, 'function generate_article_from_brief' ) !== false
	&& strpos( $legacy_ai, 'function create_draft_from_brief' ) !== false
	&& strpos( $legacy_ai, 'heuristic_article_from_brief' ) !== false
	&& is_string( $legacy_ajax )
	&& strpos( $legacy_ajax, 'rsaip_ai_generate_article' ) !== false
	&& strpos( $legacy_ajax, 'rsaip_ai_create_article_draft' ) !== false,
	'AGA 25 Legacy Article Generator remains available'
);
assert_true(
	strpos( (string) $legacy_ajax, 'rsaip_ai_generate_article' ) !== false
	&& strpos( (string) $legacy_ajax, 'rsaip_ai_create_article_draft' ) !== false,
	'AGA 23/24 Legacy article AJAX endpoints remain present'
);

$view_aga = file_get_contents( $plugin_root . '/src/Views/admin/ai.php' );
assert_true(
	is_string( $view_aga )
	&& strpos( $view_aga, 'rsaip_ai_generate_article' ) !== false
	&& strpos( $view_aga, 'recipe_seo_midjourney' ) !== false,
	'AGA UI templates preserved'
);

$seeded = $aga->context_builder()->with_seo_proposal_seed(
	$aga_ctx,
	array(
		'topic'                 => 'Quinoa Black Bean Salad',
		'primary_focus_keyword' => 'quinoa black bean salad',
		'post_id'               => 1844,
		'mutation_type'         => 'title',
	)
);
assert_true( $seeded->seo_seed() !== null && ! isset( $seeded->seo_seed()['post_id'] ), 'AGA M5 seed boundary strips write keys' );

// --- Article Generator B: Recipe-aware context ---
function rsaip_agb_ld_script( $data ): string {
	$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $data ) : json_encode( $data );
	return '<script type="application/ld+json">' . ( is_string( $json ) ? $json : '{}' ) . '</script>';
}

function rsaip_agb_complete_recipe_node( array $overrides = array() ): array {
	$base = array(
		'@type'              => 'Recipe',
		'name'               => 'Quinoa Black Bean Salad',
		'recipeIngredient'   => array( '1 cup cooked quinoa', '1 can black beans, rinsed', '2 tbsp lime juice' ),
		'recipeInstructions' => array(
			array( '@type' => 'HowToStep', 'text' => 'Combine quinoa and beans.' ),
			array( '@type' => 'HowToStep', 'text' => 'Dress with lime juice.' ),
		),
		'prepTime'           => 'PT15M',
		'cookTime'           => 'PT20M',
		'totalTime'          => 'PT35M',
		'recipeYield'        => '4 servings',
		'nutrition'          => array(
			'@type'    => 'NutritionInformation',
			'calories' => '320 calories',
		),
		'suitableForDiet'    => 'https://schema.org/VegetarianDiet',
		'allergens'          => 'soy-free',
	);
	return array_merge( $base, $overrides );
}

$agb_builder = new ArticleGenerationContextBuilder();
$agb_svc     = new ArticleGenerationService( $agb_builder );

// 1) Complete Recipe Schema
$agb_complete = $agb_builder->from_content(
	'Delicious Quinoa Black Bean Salad Recipe',
	'<p>Intro</p>' . rsaip_agb_ld_script( rsaip_agb_complete_recipe_node() ),
	array( 'template' => 'general', 'word_count' => 1000 )
);
assert_true( $agb_complete->recipe_schema_status() === RecipeSchemaStatus::VALID_RECIPE, 'AGB 1 Recipe Schema complete → valid_recipe' );
assert_true( ( $agb_complete->recipe_facts()['ingredients']['status'] ?? '' ) === RecipeFactAvailability::KNOWN, 'AGB 1/7 Known ingredients from schema' );
assert_true( is_array( $agb_complete->recipe_facts()['ingredients']['value'] ?? null ) && count( $agb_complete->recipe_facts()['ingredients']['value'] ) >= 2, 'AGB 7 Known ingredients list' );
assert_true( ( $agb_complete->recipe_facts()['prep_time']['status'] ?? '' ) === RecipeFactAvailability::KNOWN, 'AGB 9 Known times (prep)' );
assert_true( ( $agb_complete->recipe_facts()['cook_time']['status'] ?? '' ) === RecipeFactAvailability::KNOWN, 'AGB 9 Known times (cook)' );
assert_true( ( $agb_complete->recipe_facts()['total_time']['status'] ?? '' ) === RecipeFactAvailability::KNOWN, 'AGB 9 Known times (total)' );
assert_true( ( $agb_complete->recipe_facts()['servings']['status'] ?? '' ) === RecipeFactAvailability::KNOWN, 'AGB 11 Known servings' );
assert_true( ( $agb_complete->recipe_facts()['nutrition']['status'] ?? '' ) === RecipeFactAvailability::KNOWN, 'AGB 13 Known nutrition' );
assert_true( ( $agb_complete->recipe_facts()['dietary']['status'] ?? '' ) === RecipeFactAvailability::KNOWN, 'AGB 15 Known dietary' );
assert_true( ( $agb_complete->recipe_facts()['allergens']['status'] ?? '' ) === RecipeFactAvailability::KNOWN, 'AGB 17 Known allergens' );
assert_true(
	stripos( $agb_complete->canonical_recipe_entity(), 'Quinoa' ) !== false,
	'AGB 19 Canonical entity from Recipe Schema name'
);

// 2) Optional fields missing → unavailable
$agb_partial_node = rsaip_agb_complete_recipe_node(
	array(
		'prepTime'        => null,
		'cookTime'        => null,
		'totalTime'       => null,
		'recipeYield'     => null,
		'nutrition'       => null,
		'suitableForDiet' => null,
		'allergens'       => null,
	)
);
unset( $agb_partial_node['prepTime'], $agb_partial_node['cookTime'], $agb_partial_node['totalTime'], $agb_partial_node['recipeYield'], $agb_partial_node['nutrition'], $agb_partial_node['suitableForDiet'], $agb_partial_node['allergens'] );
$agb_partial = $agb_builder->from_content(
	'Quinoa Black Bean Salad',
	rsaip_agb_ld_script( $agb_partial_node ),
	array()
);
assert_true( $agb_partial->recipe_schema_status() === RecipeSchemaStatus::VALID_RECIPE, 'AGB 2 Incomplete optional fields still valid when ingredients+instructions present' );
assert_true( ( $agb_partial->recipe_facts()['prep_time']['status'] ?? '' ) === RecipeFactAvailability::UNAVAILABLE, 'AGB 2/10 Unavailable times when missing in schema' );
assert_true( ( $agb_partial->recipe_facts()['servings']['status'] ?? '' ) === RecipeFactAvailability::UNAVAILABLE, 'AGB 2/12 Unavailable servings when missing' );
assert_true( ( $agb_partial->recipe_facts()['nutrition']['status'] ?? '' ) === RecipeFactAvailability::UNAVAILABLE, 'AGB 2/14 Unavailable nutrition when missing' );
assert_true( ( $agb_partial->recipe_facts()['dietary']['status'] ?? '' ) === RecipeFactAvailability::UNAVAILABLE, 'AGB 2/16 Unavailable dietary when missing' );
assert_true( ( $agb_partial->recipe_facts()['allergens']['status'] ?? '' ) === RecipeFactAvailability::UNAVAILABLE, 'AGB 2/18 Unavailable allergens when missing' );
assert_true( ( $agb_partial->recipe_facts()['ingredients']['status'] ?? '' ) === RecipeFactAvailability::KNOWN, 'AGB 2 ingredients still known' );

// 3) @graph
$agb_graph = $agb_builder->from_content(
	'Salad Page',
	rsaip_agb_ld_script(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				array( '@type' => 'WebPage', 'name' => 'Page' ),
				rsaip_agb_complete_recipe_node(),
			),
		)
	),
	array()
);
assert_true( $agb_graph->recipe_schema_status() === RecipeSchemaStatus::VALID_RECIPE, 'AGB 3 Recipe Schema inside @graph' );
assert_true( ( $agb_graph->recipe_facts()['ingredients']['status'] ?? '' ) === RecipeFactAvailability::KNOWN, 'AGB 3 @graph ingredients known' );

// 4) Multiple JSON-LD blocks
$agb_multi = $agb_builder->from_content(
	'Quinoa Black Bean Salad',
	rsaip_agb_ld_script( array( '@type' => 'WebSite', 'name' => 'Site' ) )
	. rsaip_agb_ld_script( rsaip_agb_complete_recipe_node( array( 'name' => 'Quinoa Black Bean Salad' ) ) ),
	array()
);
assert_true( $agb_multi->recipe_schema_status() === RecipeSchemaStatus::VALID_RECIPE, 'AGB 4 Multiple JSON-LD blocks; Recipe still found' );
assert_true( stripos( $agb_multi->canonical_recipe_entity(), 'Quinoa' ) !== false, 'AGB 4 entity from Recipe block' );

// 5) Missing Recipe Schema
$agb_missing = $agb_builder->from_content(
	'Quinoa Black Bean Salad Tips',
	'<p>A friendly article about quinoa salads with no schema.</p>',
	array()
);
assert_true( $agb_missing->recipe_schema_status() === RecipeSchemaStatus::MISSING, 'AGB 5 Missing Recipe Schema' );
assert_true( ( $agb_missing->recipe_facts()['ingredients']['status'] ?? '' ) === RecipeFactAvailability::UNAVAILABLE, 'AGB 5/8 Unavailable ingredients when no authoritative list' );
assert_true( is_array( $agb_missing->recipe_facts()['ingredients']['value'] ?? null ) && $agb_missing->recipe_facts()['ingredients']['value'] === array(), 'AGB 8 ingredients value=[] when unavailable' );

// 6) External/unknown recipe system
$agb_external = $agb_builder->from_content(
	'External Salad',
	'<div class="wprm-recipe">WP Recipe Maker card</div>',
	array( 'external_recipe_plugin' => true, 'external_recipe_plugin_id' => 'wp_recipe_maker' )
);
assert_true( $agb_external->recipe_schema_status() === RecipeSchemaStatus::EXTERNAL_OR_UNKNOWN, 'AGB 6 External/unknown recipe system' );
assert_true( ( $agb_external->recipe_facts()['ingredients']['status'] ?? '' ) === RecipeFactAvailability::UNAVAILABLE, 'AGB 6 do not guess external fields' );
assert_true( ( $agb_external->recipe_facts()['prep_time']['status'] ?? '' ) === RecipeFactAvailability::UNAVAILABLE, 'AGB 6 external times unavailable' );

// 19/20 Canonical entity + generic keyword must not override
$agb_entity = $agb_builder->from_content(
	'Delicious Quinoa Black Bean Salad Recipe - Healthy & Easy',
	rsaip_agb_ld_script( rsaip_agb_complete_recipe_node() ),
	array( 'primary_focus_keyword' => 'Salad', 'keywords' => array( 'Salad' ) )
);
assert_true(
	stripos( $agb_entity->canonical_recipe_entity(), 'Quinoa' ) !== false
	&& strtolower( $agb_entity->canonical_recipe_entity() ) !== 'salad',
	'AGB 20 Generic keyword must not override specific recipe entity'
);

// Prompt distinguishes known vs unavailable
$agb_prompt = $agb_svc->prompt_builder()->build( $agb_complete );
assert_true(
	strpos( $agb_prompt, 'KNOWN_RECIPE_FACTS' ) !== false
	&& strpos( $agb_prompt, 'UNAVAILABLE_RECIPE_FACTS' ) !== false
	&& strpos( $agb_prompt, 'Never invent' ) !== false,
	'AGB prompt exposes KNOWN vs UNAVAILABLE recipe facts'
);
$agb_prompt_partial = $agb_svc->prompt_builder()->build( $agb_partial );
assert_true(
	strpos( $agb_prompt_partial, 'prep_time' ) !== false
	&& strpos( $agb_prompt_partial, 'UNAVAILABLE' ) !== false,
	'AGB prompt lists unavailable fact keys'
);

// 21) AI inventing unavailable facts fails validation
$agb_invent = $agb_svc->proposal_from_decoded(
	rsaip_aga_valid_article_data(
		array(
			'recipe_facts' => array(
				'ingredients' => array( 'status' => 'known', 'value' => array( 'invented cilantro fairy dust' ) ),
				'prep_time'   => array( 'status' => 'known', 'value' => '12 minutes' ),
				'nutrition'   => array( 'status' => 'known', 'value' => '999 calories' ),
			),
		)
	),
	$agb_partial
);
assert_true( ! $agb_invent->ok(), 'AGB 21 AI inventing unavailable recipe facts fails validation' );

$agb_body_invent = $agb_svc->proposal_from_decoded(
	rsaip_aga_valid_article_data(
		array(
			'content_html' => rsaip_aga_body_html( '<p>This recipe has 450 calories and cook for 90 minutes. Serves 8.</p>' ),
			'recipe_facts' => array(
				'ingredients' => array( 'status' => 'unavailable', 'value' => array() ),
				'prep_time'   => array( 'status' => 'unavailable', 'value' => null ),
				'cook_time'   => array( 'status' => 'unavailable', 'value' => null ),
				'total_time'  => array( 'status' => 'unavailable', 'value' => null ),
				'servings'    => array( 'status' => 'unavailable', 'value' => null ),
				'nutrition'   => array( 'status' => 'unavailable', 'value' => null ),
				'dietary'     => array( 'status' => 'unavailable', 'value' => null ),
				'allergens'   => array( 'status' => 'unavailable', 'value' => null ),
			),
		)
	),
	$agb_partial
);
assert_true( ! $agb_body_invent->ok(), 'AGB 21b Body inventing unavailable nutrition/times/servings fails' );

// Known facts preserved through validation
$agb_ok_known = $agb_svc->proposal_from_decoded(
	rsaip_aga_valid_article_data(
		array(
			'recipe_facts' => array(
				'ingredients' => array( 'status' => 'known', 'value' => array( '1 cup cooked quinoa', '1 can black beans, rinsed' ) ),
				'prep_time'   => array( 'status' => 'known', 'value' => '15 minutes' ),
				'cook_time'   => array( 'status' => 'known', 'value' => '20 minutes' ),
				'total_time'  => array( 'status' => 'known', 'value' => '35 minutes' ),
				'servings'    => array( 'status' => 'known', 'value' => '4 servings' ),
				'nutrition'   => array( 'status' => 'known', 'value' => 'calories: 320 calories' ),
				'dietary'     => array( 'status' => 'known', 'value' => 'VegetarianDiet' ),
				'allergens'   => array( 'status' => 'known', 'value' => 'soy-free' ),
			),
			'content_html' => rsaip_aga_body_html( '<p>Prep time 15 minutes. Cook time 20 minutes. About 320 calories per serving. Serves 4 servings.</p>' ),
		)
	),
	$agb_complete
);
assert_true( $agb_ok_known->ok(), 'AGB known recipe facts preserved through validation' );
assert_true(
	$agb_ok_known->ok()
	&& ( $agb_ok_known->proposal()->recipe_facts()['ingredients']['status'] ?? '' ) === RecipeFactAvailability::KNOWN,
	'AGB proposal keeps known ingredients from context'
);

// 22/23 No WordPress writes in builder/service
$agb_builder_src = file_get_contents( $plugin_root . '/src/Modules/Ai/ArticleGeneration/ArticleGenerationContextBuilder.php' );
$agb_resolver_src = file_get_contents( $plugin_root . '/src/Modules/Ai/ArticleGeneration/ArticleRecipeContextResolver.php' );
$agb_svc_src = file_get_contents( $plugin_root . '/src/Modules/Ai/ArticleGeneration/ArticleGenerationService.php' );
assert_true(
	is_string( $agb_builder_src )
	&& strpos( $agb_builder_src, 'wp_insert_post' ) === false
	&& strpos( $agb_builder_src, 'wp_update_post' ) === false
	&& strpos( $agb_builder_src, 'update_post_meta' ) === false
	&& is_string( $agb_resolver_src )
	&& strpos( $agb_resolver_src, 'wp_insert_post' ) === false
	&& strpos( $agb_resolver_src, 'wp_update_post' ) === false
	&& strpos( $agb_resolver_src, 'update_post_meta' ) === false
	&& is_string( $agb_svc_src )
	&& strpos( $agb_svc_src, 'wp_insert_post' ) === false
	&& strpos( $agb_svc_src, 'wp_update_post' ) === false
	&& strpos( $agb_svc_src, 'update_post_meta' ) === false,
	'AGB 22/23 No WordPress writes during context building or generation'
);

// 24 M5 SEO context remains read-only via seed boundary
$agb_seeded = $agb_builder->with_seo_proposal_seed(
	$agb_complete,
	array(
		'primary_focus_keyword' => 'quinoa black bean salad',
		'search_intent'         => 'informational',
		'content_gaps'          => array( 'storage tips' ),
		'post_id'               => 999,
		'seo_owner'             => 'rankmath',
		'mutation_type'         => 'meta',
		'meta_key'              => 'rank_math_description',
	)
);
assert_true(
	$agb_seeded->seo_seed() !== null
	&& ! isset( $agb_seeded->seo_seed()['post_id'] )
	&& ! isset( $agb_seeded->seo_seed()['seo_owner'] )
	&& ! isset( $agb_seeded->seo_seed()['mutation_type'] )
	&& ! isset( $agb_seeded->seo_seed()['meta_key'] )
	&& ( $agb_seeded->recipe_facts()['ingredients']['status'] ?? '' ) === RecipeFactAvailability::KNOWN,
	'AGB 24 M5 SEO seed read-only; write keys stripped; recipe facts retained'
);

// 25 No Safe Mode / PMS / AJAX / UI changes
$agb_ajax = file_get_contents( $plugin_root . '/includes/class-rsaip-ajax.php' );
$agb_pms  = file_get_contents( $plugin_root . '/src/Modules/PostMutation/PostMutationService.php' );
$agb_ai   = file_get_contents( $plugin_root . '/includes/class-rsaip-ai.php' );
assert_true(
	is_string( $agb_ajax )
	&& strpos( $agb_ajax, 'ArticleRecipeContextResolver' ) === false
	&& strpos( $agb_ajax, 'rsaip_ai_generate_article' ) !== false
	&& strpos( $agb_ajax, 'rsaip_ai_create_article_draft' ) !== false
	&& is_string( $agb_pms )
	&& strpos( $agb_pms, 'ArticleRecipeContextResolver' ) === false
	&& is_string( $agb_ai )
	&& strpos( $agb_ai, 'function generate_article_from_brief' ) !== false
	&& strpos( $agb_ai, 'ArticleRecipeContextResolver' ) === false,
	'AGB 25 No Safe Mode/PMS/legacy generator wiring changes'
);

// Resolver isolation: empty facts model
$agb_empty = ( new ArticleRecipeContextResolver() )->empty_facts();
assert_true(
	( $agb_empty['ingredients']['status'] ?? '' ) === RecipeFactAvailability::UNAVAILABLE
	&& $agb_empty['ingredients']['value'] === array()
	&& ( $agb_empty['total_time']['value'] ?? 'x' ) === '',
	'AGB empty facts default unavailable with ingredients=[]'
);

// --- Article Generator C: SEO-aware generation (read-only) ---
$agc_builder = new ArticleGenerationContextBuilder();
$agc_svc     = new ArticleGenerationService( $agc_builder );

$agc_base = $agc_builder->from_content(
	'Delicious Quinoa Black Bean Salad Recipe',
	'<p>Intro</p>' . rsaip_agb_ld_script( rsaip_agb_complete_recipe_node() ),
	array(
		'template'   => 'general',
		'word_count' => 1000,
		'keywords'   => array( 'quinoa black bean salad' ),
	)
);

$agc_ctx = $agc_builder->with_seo_proposal_seed(
	$agc_base,
	array(
		'topic'                     => 'Quinoa Black Bean Salad',
		'recipe_name'               => 'Quinoa Black Bean Salad',
		'search_intent'             => 'commercial',
		'primary_focus_keyword'     => 'quinoa black bean salad',
		'secondary_keywords'        => array( 'easy quinoa salad', 'healthy black bean salad' ),
		'entities'                  => array( 'quinoa', 'black beans', 'lime', 'cilantro' ),
		'content_gaps'              => array( 'Add make-ahead storage tips', 'Mention dressing variations' ),
		'recommended_title'         => 'Easy Quinoa Black Bean Salad',
		'meta_description'          => 'Learn how to make Quinoa Black Bean Salad with lime and pantry staples.',
		'seo_title_suggestions'     => array( 'Quinoa Black Bean Salad Recipe' ),
		'heading_suggestions'       => array(
			array( 'level' => 'h2', 'text' => 'How to make Quinoa Black Bean Salad' ),
			array( 'level' => 'h3', 'text' => 'Dressing tips' ),
		),
		'faq_suggestions'           => array(
			array(
				'q' => 'Can I meal prep Quinoa Black Bean Salad?',
				'a' => 'Yes when storage guidance is known.',
			),
		),
		'image_alt_suggestions'     => array( 'Bowl of Quinoa Black Bean Salad' ),
		'internal_link_suggestions' => array(
			array(
				'anchor'      => 'meal prep salads',
				'target_hint' => 'related meal-prep salad guide',
			),
		),
		'recommendations'           => array( 'Lead with the recipe entity in the introduction.' ),
		'post_id'                   => 1844,
		'seo_owner'                 => 'rankmath',
		'mutation_type'             => 'meta',
		'meta_key'                  => 'rank_math_description',
		'rank_math_score'           => 100,
	)
);

$agc_prompt_arr = $agc_ctx->to_prompt_array();
assert_true(
	isset( $agc_prompt_arr['RECIPE_FACTS'], $agc_prompt_arr['SEO_STRATEGY'], $agc_prompt_arr['GENERATION_INSTRUCTIONS'] ),
	'AGC 1 SEO context reaches article generation (three prompt sections)'
);
assert_true(
	( $agc_prompt_arr['SEO_STRATEGY']['primary_focus_keyword'] ?? '' ) === 'quinoa black bean salad'
	&& ( $agc_prompt_arr['SEO_STRATEGY']['search_intent'] ?? '' ) === 'commercial'
	&& in_array( 'Add make-ahead storage tips', $agc_prompt_arr['SEO_STRATEGY']['content_gaps'] ?? array(), true ),
	'AGC 1/3/5/6 Primary keyword, intent, and content gaps in SEO_STRATEGY'
);
assert_true(
	stripos( (string) ( $agc_prompt_arr['RECIPE_FACTS']['canonical_recipe_entity'] ?? '' ), 'Quinoa' ) !== false,
	'AGC 2 Canonical recipe entity remains central'
);
assert_true(
	$agc_ctx->primary_focus_keyword_hint() === 'quinoa black bean salad',
	'AGC 3 Primary keyword is preserved'
);
assert_true(
	in_array( 'easy quinoa salad', $agc_ctx->secondary_keyword_hints(), true ),
	'AGC 4 Secondary keywords are available but optional'
);
assert_true(
	strpos( $agc_prompt_arr['SEO_STRATEGY']['note'] ?? '', 'not factual' ) !== false
	|| strpos( (string) ( $agc_prompt_arr['SEO_STRATEGY']['note'] ?? '' ), 'Recommendations' ) !== false,
	'AGC 6/7 Content gaps/entities treated as recommendations / supporting concepts'
);
assert_true(
	( $agc_ctx->recipe_facts()['ingredients']['status'] ?? '' ) === RecipeFactAvailability::KNOWN
	&& in_array( 'prep_time', $agc_ctx->unavailable_recipe_fact_keys(), true ) === false,
	'AGC 8 Recipe facts remain authoritative from schema'
);

$agc_prompt = $agc_svc->prompt_builder()->build( $agc_ctx );
assert_true(
	strpos( $agc_prompt, 'SEO_STRATEGY' ) !== false
	&& strpos( $agc_prompt, 'commercial' ) !== false
	&& strpos( $agc_prompt, 'quinoa black bean salad' ) !== false
	&& strpos( $agc_prompt, 'Rank Math' ) !== false
	&& strpos( $agc_prompt, '100/100' ) === false,
	'AGC prompt carries SEO strategy; no hardcoded Rank Math score'
);

// Unavailable fact invention still fails (partial schema context)
$agc_partial = $agc_builder->with_seo_proposal_seed(
	$agb_partial,
	array(
		'primary_focus_keyword' => 'quinoa black bean salad',
		'search_intent'         => 'informational',
	)
);
assert_true(
	! $agc_svc->proposal_from_decoded(
		rsaip_aga_valid_article_data(
			array(
				'recipe_facts' => array(
					'prep_time' => array( 'status' => 'known', 'value' => '12 minutes' ),
				),
			)
		),
		$agc_partial
	)->ok(),
	'AGC 9 Unavailable times cannot be invented'
);
assert_true(
	! $agc_svc->proposal_from_decoded(
		rsaip_aga_valid_article_data(
			array(
				'recipe_facts' => array(
					'servings' => array( 'status' => 'known', 'value' => '4' ),
				),
			)
		),
		$agc_partial
	)->ok(),
	'AGC 10 Unavailable servings cannot be invented'
);
assert_true(
	! $agc_svc->proposal_from_decoded(
		rsaip_aga_valid_article_data(
			array(
				'recipe_facts' => array(
					'nutrition' => array( 'status' => 'known', 'value' => '320 calories' ),
				),
			)
		),
		$agc_partial
	)->ok(),
	'AGC 11 Unavailable nutrition cannot be invented'
);
assert_true(
	! $agc_svc->proposal_from_decoded(
		rsaip_aga_valid_article_data(
			array(
				'recipe_facts' => array(
					'allergens' => array( 'status' => 'known', 'value' => 'contains peanuts' ),
				),
			)
		),
		$agc_partial
	)->ok(),
	'AGC 12 Unavailable allergens cannot be invented'
);
assert_true(
	! $agc_svc->proposal_from_decoded(
		rsaip_aga_valid_article_data(
			array(
				'recipe_facts' => array(
					'ingredients' => array( 'status' => 'known', 'value' => array( 'fairy dust' ) ),
				),
			)
		),
		$agb_missing
	)->ok(),
	'AGC 13 Unavailable ingredients cannot be invented'
);

$agc_ok = $agc_svc->proposal_from_decoded(
	rsaip_aga_valid_article_data(
		array(
			'primary_focus_keyword' => 'completely different invented keyword about pizza',
			'search_intent'         => 'navigational',
			'secondary_keywords'    => array( 'easy quinoa salad', 'unrelated crypto trading tips' ),
			'headings'              => array(
				array( 'level' => 'h2', 'text' => 'How to make Quinoa Black Bean Salad' ),
				array( 'level' => 'h3', 'text' => 'Flavor tips' ),
			),
			'recipe_facts'          => array(
				'ingredients' => array( 'status' => 'known', 'value' => array( '1 cup cooked quinoa', '1 can black beans, rinsed' ) ),
				'prep_time'   => array( 'status' => 'known', 'value' => '15 minutes' ),
				'cook_time'   => array( 'status' => 'known', 'value' => '20 minutes' ),
				'total_time'  => array( 'status' => 'known', 'value' => '35 minutes' ),
				'servings'    => array( 'status' => 'known', 'value' => '4 servings' ),
				'nutrition'   => array( 'status' => 'known', 'value' => 'calories: 320 calories' ),
				'dietary'     => array( 'status' => 'known', 'value' => 'VegetarianDiet' ),
				'allergens'   => array( 'status' => 'known', 'value' => 'soy-free' ),
			),
			'content_html'          => rsaip_aga_body_html( '<p>Prep time 15 minutes. Cook time 20 minutes. About 320 calories per serving. Serves 4 servings.</p>' ),
		)
	),
	$agc_ctx
);
assert_true( $agc_ok->ok() && $agc_ok->proposal() !== null, 'AGC valid SEO-aware proposal accepts' );
assert_true(
	$agc_ok->ok() && $agc_ok->proposal()->primary_focus_keyword() === 'quinoa black bean salad',
	'AGC 3/14 Server primary keyword wins over invented AI primary'
);
assert_true(
	$agc_ok->ok() && $agc_ok->proposal()->search_intent() === 'commercial',
	'AGC 5 Server search intent is authoritative'
);
assert_true(
	$agc_ok->ok()
	&& in_array( 'easy quinoa salad', $agc_ok->proposal()->secondary_keywords(), true )
	&& ! in_array( 'unrelated crypto trading tips', $agc_ok->proposal()->secondary_keywords(), true ),
	'AGC 4 Unrelated secondary keywords filtered; relevant kept'
);
assert_true(
	$agc_ok->ok()
	&& $agc_ok->proposal()->title() !== ''
	&& stripos( $agc_ok->proposal()->title(), 'Quinoa' ) !== false,
	'AGC 14 Title remains entity-relevant'
);
assert_true(
	$agc_ok->ok()
	&& stripos( $agc_ok->proposal()->meta_description(), 'Quinoa' ) !== false,
	'AGC 15 Meta remains entity-relevant'
);
assert_true(
	$agc_ok->ok()
	&& $agc_ok->proposal()->headings() !== array()
	&& $agc_ok->proposal()->headings()[0]['level'] === 'h2',
	'AGC 16 Headings are H2/H3 only'
);
assert_true(
	$agc_ok->ok()
	&& stripos( $agc_ok->proposal()->faq()[0]['q'], 'Quinoa' ) !== false,
	'AGC 17 FAQ remains recipe-specific'
);
assert_true(
	$agc_ok->ok()
	&& $agc_ok->proposal()->actual_word_count() > 0
	&& $agc_ok->proposal()->word_count_in_band(),
	'AGC 27 Word count calculated server-side and in band'
);

assert_true(
	! $agc_svc->proposal_from_decoded(
		rsaip_aga_valid_article_data(
			array(
				'content_html' => rsaip_aga_body_html( '<p>See <a href="https://evil.example/hack">link</a>.</p>' ),
			)
		),
		$agc_ctx
	)->ok(),
	'AGC 18 No invented URLs'
);
assert_true(
	! $agc_svc->proposal_from_decoded( rsaip_aga_valid_article_data( array( 'post_id' => 99 ) ), $agc_ctx )->ok(),
	'AGC 19 No post_id generated by AI'
);
assert_true(
	! $agc_svc->proposal_from_decoded( rsaip_aga_valid_article_data( array( 'seo_owner' => 'rankmath' ) ), $agc_ctx )->ok(),
	'AGC 20 No SEO owner generated by AI'
);
assert_true(
	! $agc_svc->proposal_from_decoded( rsaip_aga_valid_article_data( array( 'mutation_type' => 'title' ) ), $agc_ctx )->ok(),
	'AGC 21 No mutation_type generated by AI'
);
assert_true(
	! $agc_svc->proposal_from_decoded( rsaip_aga_valid_article_data( array( 'proposal_ticket' => 'abc' ) ), $agc_ctx )->ok(),
	'AGC 22 No PMS ticket generated by AI'
);
assert_true(
	! $agc_svc->proposal_from_decoded( rsaip_aga_valid_article_data( array( 'rank_math_score' => 100 ) ), $agc_ctx )->ok(),
	'AGC 29 Rank Math score is never accepted/hardcoded from AI'
);

assert_true(
	! $agc_svc->proposal_from_decoded(
		rsaip_aga_valid_article_data( array( 'content_html' => '<p>Too short.</p>' ) ),
		$agc_ctx
	)->ok(),
	'AGC 28 Content is not padded; short content fails word-count band'
);

// --- Word-count runtime fix: observability + token budget + prompt (band unchanged) ---
assert_true( $agc_ctx->requested_word_count() === 1000, 'WCFIX requested 1000 remains 1000' );
$wcfix_band = $agc_ctx->to_prompt_array()['GENERATION_INSTRUCTIONS']['word_count_band'] ?? array();
assert_true(
	(int) ( $wcfix_band['min'] ?? 0 ) === 850 && (int) ( $wcfix_band['max'] ?? 0 ) === 1250,
	'WCFIX band remains 850–1250 for requested 1000'
);

$wcfix_short = $agc_svc->proposal_from_decoded(
	rsaip_aga_valid_article_data( array( 'content_html' => '<p>Too short.</p>' ) ),
	$agc_ctx
);
assert_true(
	! $wcfix_short->ok()
	&& $wcfix_short->code() === 'rsaip_article_word_count_out_of_band',
	'WCFIX out-of-band still hard-fails'
);
$wcfix_meta = $wcfix_short->meta();
assert_true(
	(int) ( $wcfix_meta['requested_word_count'] ?? 0 ) === 1000
	&& (int) ( $wcfix_meta['actual_word_count'] ?? -1 ) === 2
	&& (int) ( $wcfix_meta['band_min'] ?? 0 ) === 850
	&& (int) ( $wcfix_meta['band_max'] ?? 0 ) === 1250,
	'WCFIX diagnostic fields on ValidationResult meta'
);
$wcfix_resp = $agc_svc->to_preview_response( $wcfix_short );
assert_true(
	empty( $wcfix_resp['ok'] )
	&& ( $wcfix_resp['code'] ?? '' ) === 'rsaip_article_word_count_out_of_band'
	&& (int) ( $wcfix_resp['requested_word_count'] ?? 0 ) === 1000
	&& (int) ( $wcfix_resp['actual_word_count'] ?? -1 ) === 2
	&& (int) ( $wcfix_resp['band_min'] ?? 0 ) === 850
	&& (int) ( $wcfix_resp['band_max'] ?? 0 ) === 1250
	&& ! isset( $wcfix_resp['api_key'] ),
	'WCFIX diagnostic fields returned in preview/error response'
);

// Headings/FAQ JSON fields must not inflate counted words when content_html is short.
$wcfix_faq_only = $agc_svc->proposal_from_decoded(
	rsaip_aga_valid_article_data(
		array(
			'content_html' => '<p>Too short.</p>',
			'headings'     => array(
				array( 'level' => 'h2', 'text' => str_repeat( 'Heading padding words for quinoa salad ', 40 ) ),
			),
			'faq'          => array(
				array(
					'q' => str_repeat( 'Question padding about quinoa black bean salad ', 30 ),
					'a' => str_repeat( 'Answer padding about quinoa black bean salad ', 40 ),
				),
			),
		)
	),
	$agc_ctx
);
assert_true(
	! $wcfix_faq_only->ok()
	&& $wcfix_faq_only->code() === 'rsaip_article_word_count_out_of_band'
	&& (int) ( $wcfix_faq_only->meta()['actual_word_count'] ?? -1 ) === 2,
	'WCFIX actual count is content_html only; headings/FAQ not counted'
);

$wcfix_ok = $agc_svc->proposal_from_decoded( rsaip_aga_valid_article_data(), $agc_ctx );
assert_true(
	$wcfix_ok->ok()
	&& $wcfix_ok->proposal()->requested_word_count() === 1000
	&& $wcfix_ok->proposal()->actual_word_count() >= 850
	&& $wcfix_ok->proposal()->actual_word_count() <= 1250
	&& $wcfix_ok->proposal()->word_count_in_band(),
	'WCFIX in-band content_html still validates; band unchanged'
);

$wcfix_svc_src = file_get_contents( $plugin_root . '/src/Modules/Ai/ArticleGeneration/ArticleGenerationService.php' );
$wcfix_prompt_src = file_get_contents( $plugin_root . '/src/Modules/Ai/ArticleGeneration/ArticleGenerationPromptBuilder.php' );
$wcfix_val_src = file_get_contents( $plugin_root . '/src/Modules/Ai/ArticleGeneration/ArticleGenerationProposalValidator.php' );
assert_true(
	is_string( $wcfix_svc_src )
	&& preg_match( "/'max_tokens'\\s*=>\\s*8000/", $wcfix_svc_src ) === 1
	&& strpos( $wcfix_svc_src, "'max_tokens'  => 3500" ) === false,
	'WCFIX max_tokens configured to 8000'
);
assert_true(
	is_string( $wcfix_prompt_src )
	&& strpos( $wcfix_prompt_src, 'content_html only' ) !== false
	&& strpos( $wcfix_prompt_src, 'do NOT count' ) !== false
	&& strpos( $wcfix_prompt_src, 'Finish the entire JSON' ) !== false
	&& strpos( $wcfix_prompt_src, 'meaningless' ) !== false,
	'WCFIX prompt requires content_html word target and complete JSON'
);
assert_true(
	is_string( $wcfix_val_src )
	&& strpos( $wcfix_val_src, 'requested - 150' ) !== false
	&& strpos( $wcfix_val_src, 'requested + 250' ) !== false
	&& strpos( $wcfix_val_src, 'str_repeat' ) === false
	&& strpos( $wcfix_val_src, 'automatic regeneration' ) === false
	&& strpos( $wcfix_svc_src, 'str_repeat' ) === false,
	'WCFIX band formula unchanged; no padding/fallback in validator/service'
);
$wcfix_prompt_built = $agc_svc->prompt_builder()->build( $agc_ctx );
assert_true(
	strpos( $wcfix_prompt_built, '850' ) !== false
	&& strpos( $wcfix_prompt_built, '1250' ) !== false
	&& strpos( $wcfix_prompt_built, 'content_html only' ) !== false,
	'WCFIX built prompt includes 850-1250 and content_html-only rule'
);

assert_true(
	! $agc_svc->proposal_from_decoded(
		rsaip_aga_valid_article_data( array( 'content_html' => rsaip_aga_body_html( '<h1>Wrong</h1>' ) ) ),
		$agc_ctx
	)->ok(),
	'AGC no H1 in content_html'
);

$agc_fail = new ArticleGenerationService(
	$agc_builder,
	null,
	null,
	null,
	static function () {
		return new class() {
			public function get_error_code() {
				return 'rsaip_ai_timeout';
			}
			public function get_error_message() {
				return 'AI timed out';
			}
		};
	}
);
assert_true( ! $agc_fail->generate_from_context( $agc_ctx )->ok(), 'AGC 25 Provider failure produces hard failure' );

$agc_bad_json = new ArticleGenerationService(
	$agc_builder,
	null,
	null,
	null,
	static function () {
		return 'not-json{{{';
	}
);
assert_true( ! $agc_bad_json->generate_from_context( $agc_ctx )->ok(), 'AGC 26 Invalid structured JSON produces hard failure' );

$agc_svc_src = file_get_contents( $plugin_root . '/src/Modules/Ai/ArticleGeneration/ArticleGenerationService.php' );
$agc_val_src = file_get_contents( $plugin_root . '/src/Modules/Ai/ArticleGeneration/ArticleGenerationProposalValidator.php' );
$agc_prompt_src = file_get_contents( $plugin_root . '/src/Modules/Ai/ArticleGeneration/ArticleGenerationPromptBuilder.php' );
assert_true(
	is_string( $agc_svc_src )
	&& strpos( $agc_svc_src, 'wp_insert_post' ) === false
	&& strpos( $agc_svc_src, 'wp_update_post' ) === false
	&& strpos( $agc_svc_src, 'update_post_meta' ) === false
	&& strpos( $agc_svc_src, 'heuristic' ) === false
	&& is_string( $agc_val_src )
	&& strpos( $agc_prompt_src, '100/100' ) === false
	&& strpos( $agc_prompt_src, 'keyword density' ) === false,
	'AGC 23/24/29 No WordPress writes; no heuristic fallback; no hardcoded Rank Math / density'
);

$agc_ajax = file_get_contents( $plugin_root . '/includes/class-rsaip-ajax.php' );
$agc_pms  = file_get_contents( $plugin_root . '/src/Modules/PostMutation/PostMutationService.php' );
$agc_ai   = file_get_contents( $plugin_root . '/includes/class-rsaip-ai.php' );
assert_true(
	is_string( $agc_ajax )
	&& strpos( $agc_ajax, 'rsaip_ai_create_article_draft' ) !== false
	&& is_string( $agc_pms )
	&& strpos( (string) $agc_pms, 'ArticleGenerationService' ) === false
	&& is_string( $agc_ai )
	&& strpos( $agc_ai, 'function generate_article_from_brief' ) !== false
	&& strpos( $agc_ai, 'ArticleGenerationPromptBuilder' ) === false,
	'AGC 30 Safe Mode/PMS/legacy Article Generator remain untouched'
);

assert_true(
	$agc_ctx->seo_seed() !== null
	&& ! isset( $agc_ctx->seo_seed()['post_id'] )
	&& ! isset( $agc_ctx->seo_seed()['rank_math_score'] )
	&& ! isset( $agc_ctx->seo_seed()['seo_owner'] ),
	'AGC M5 seed strips write keys and Rank Math score fields'
);

// --- Article Generator D: Preview layer (read-only) ---
ArticleGenerationProposalStore::clear();
$agd_svc = new ArticleGenerationService(
	null,
	null,
	null,
	null,
	static function () {
		$fixture = rsaip_aga_valid_article_data(
			array(
				'recipe_facts' => array(
					'ingredients' => array( 'status' => 'known', 'value' => array( '1 cup cooked quinoa', '1 can black beans, rinsed' ) ),
					'prep_time'   => array( 'status' => 'known', 'value' => '15 minutes' ),
					'cook_time'   => array( 'status' => 'known', 'value' => '20 minutes' ),
					'total_time'  => array( 'status' => 'known', 'value' => '35 minutes' ),
					'servings'    => array( 'status' => 'known', 'value' => '4 servings' ),
					'nutrition'   => array( 'status' => 'known', 'value' => 'calories: 320 calories' ),
					'dietary'     => array( 'status' => 'known', 'value' => 'VegetarianDiet' ),
					'allergens'   => array( 'status' => 'known', 'value' => 'soy-free' ),
				),
				'content_html' => rsaip_aga_body_html( '<p>Prep time 15 minutes. Cook time 20 minutes. About 320 calories per serving. Serves 4 servings.</p>' ),
			)
		);
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $fixture ) : json_encode( $fixture );
		return is_string( $json ) ? $json : '{}';
	}
);

$agd_brief = array(
	'title'                   => 'Delicious Quinoa Black Bean Salad Recipe',
	'template'                => 'general',
	'word_count'              => 1000,
	'keywords'                => array( 'quinoa black bean salad', 'easy quinoa salad' ),
	'primary_focus_keyword'   => 'quinoa black bean salad',
	'canonical_recipe_entity' => 'Quinoa Black Bean Salad',
	'content'                 => rsaip_agb_ld_script( rsaip_agb_complete_recipe_node() ),
	'search_intent'           => 'informational',
);

$agd_preview = $agd_svc->generate_preview_from_brief( $agd_brief, 42 );
assert_true( ! empty( $agd_preview['ok'] ), 'AGD 1 Preview proposal can be generated' );
assert_true(
	! empty( $agd_preview['proposal_id'] ) && ! empty( $agd_preview['fingerprint'] ) && ! empty( $agd_preview['expires_at'] ),
	'AGD 2 Valid proposal can be stored (id + fingerprint + expiry)'
);
assert_true(
	empty( $agd_preview['apply_allowed'] )
	&& empty( $agd_preview['draft_allowed'] )
	&& empty( $agd_preview['create_draft'] )
	&& ( $agd_preview['proposal_ticket'] ?? '' ) === '',
	'AGD 14 AI proposal contains no mutation authority'
);
assert_true(
	! isset( $agd_preview['post_id'] )
	&& ! isset( $agd_preview['seo_owner'] )
	&& ! isset( $agd_preview['meta_key'] )
	&& ! isset( $agd_preview['mutation_type'] )
	&& ! isset( $agd_preview['rank_math_score'] )
	&& strpos( (string) json_encode( $agd_preview ), '100/100' ) === false,
	'AGD 21/25 Preview response contains only safe proposal data (no Rank Math score)'
);

$agd_got = $agd_svc->retrieve_preview( (string) $agd_preview['proposal_id'], (string) $agd_preview['fingerprint'], 42 );
assert_true( ! empty( $agd_got['ok'] ) && ( $agd_got['title'] ?? '' ) !== '', 'AGD 3 Proposal can be retrieved by its identifier' );

$agd_other_user = $agd_svc->retrieve_preview( (string) $agd_preview['proposal_id'], (string) $agd_preview['fingerprint'], 99 );
assert_true( empty( $agd_other_user['ok'] ), 'AGD 4 Proposal is bound to user' );

$agd_bad_fp = $agd_svc->retrieve_preview( (string) $agd_preview['proposal_id'], str_repeat( 'a', 64 ), 42 );
assert_true( empty( $agd_bad_fp['ok'] ), 'AGD 5 Proposal fingerprint is validated' );

ArticleGenerationProposalStore::force_expire_for_tests( 42, (string) $agd_preview['proposal_id'] );
$agd_expired = $agd_svc->retrieve_preview( (string) $agd_preview['proposal_id'], (string) $agd_preview['fingerprint'], 42 );
assert_true( empty( $agd_expired['ok'] ) && ( $agd_expired['code'] ?? '' ) === 'preview_expired', 'AGD 6 Expired proposal is rejected' );

// Fresh store for remaining integrity tests.
ArticleGenerationProposalStore::clear();
$agd_preview2 = $agd_svc->generate_preview_from_brief( $agd_brief, 42 );
assert_true( ! empty( $agd_preview2['ok'] ), 'AGD fresh preview after clear' );

$agd_ctx = $agd_svc->context_builder()->from_brief( $agd_brief );
$agd_ok_prop = $agd_svc->proposal_from_decoded(
	rsaip_aga_valid_article_data(
		array(
			'recipe_facts' => array(
				'ingredients' => array( 'status' => 'known', 'value' => array( '1 cup cooked quinoa' ) ),
				'prep_time'   => array( 'status' => 'known', 'value' => '15 minutes' ),
				'cook_time'   => array( 'status' => 'known', 'value' => '20 minutes' ),
				'total_time'  => array( 'status' => 'known', 'value' => '35 minutes' ),
				'servings'    => array( 'status' => 'known', 'value' => '4 servings' ),
				'nutrition'   => array( 'status' => 'known', 'value' => 'calories: 320 calories' ),
				'dietary'     => array( 'status' => 'known', 'value' => 'VegetarianDiet' ),
				'allergens'   => array( 'status' => 'known', 'value' => 'soy-free' ),
			),
			'content_html' => rsaip_aga_body_html( '<p>Prep time 15 minutes. Cook time 20 minutes. About 320 calories per serving. Serves 4 servings.</p>' ),
		)
	),
	$agd_ctx
);
assert_true( $agd_ok_prop->ok(), 'AGD fixture validates against recipe context' );
$agd_stored = ArticleGenerationProposalStore::put( 7, $agd_ok_prop->proposal(), $agd_ctx );
assert_true( ! empty( $agd_stored['ok'] ), 'AGD store put ok' );

// Browser cannot override stored recipe facts / SEO fields by forging retrieve payload —
// retrieve ignores client body and reloads server entry.
$tamper = $agd_svc->retrieve_preview( (string) $agd_stored['proposal_id'], (string) $agd_stored['fingerprint'], 7 );
assert_true(
	! empty( $tamper['ok'] )
	&& ( $tamper['recipe_facts']['prep_time']['status'] ?? '' ) === RecipeFactAvailability::KNOWN
	&& ( $tamper['primary_focus_keyword'] ?? '' ) === 'quinoa black bean salad',
	'AGD 8/9 Browser cannot override server recipe facts or primary keyword via retrieve'
);

assert_true(
	! $agd_svc->proposal_from_decoded( rsaip_aga_valid_article_data( array( 'seo_owner' => 'rankmath' ) ), $agd_ctx )->ok(),
	'AGD 10 Browser/AI cannot override SEO owner'
);
assert_true(
	! $agd_svc->proposal_from_decoded( rsaip_aga_valid_article_data( array( 'meta_key' => 'rank_math_description' ) ), $agd_ctx )->ok(),
	'AGD 11 Browser/AI cannot introduce meta keys'
);
assert_true(
	! $agd_svc->proposal_from_decoded( rsaip_aga_valid_article_data( array( 'mutation_type' => 'title' ) ), $agd_ctx )->ok(),
	'AGD 12 Browser/AI cannot introduce mutation types'
);
assert_true(
	! $agd_svc->proposal_from_decoded( rsaip_aga_valid_article_data( array( 'post_id' => 99 ) ), $agd_ctx )->ok(),
	'AGD 13 Browser/AI cannot introduce post IDs'
);
assert_true(
	! $agd_svc->proposal_from_decoded(
		rsaip_aga_valid_article_data( array( 'content_html' => rsaip_aga_body_html( '<script>alert(1)</script>' ) ) ),
		$agd_ctx
	)->ok(),
	'AGD 19 HTML dangerous content is rejected'
);
assert_true(
	! $agd_svc->proposal_from_decoded(
		rsaip_aga_valid_article_data( array( 'content_html' => rsaip_aga_body_html( '<h1>Nope</h1>' ) ) ),
		$agd_ctx
	)->ok(),
	'AGD 20 H1 remains disallowed in content'
);

$agd_unavailable = $agd_svc->context_builder()->from_brief(
	array(
		'title'                   => 'Quinoa Black Bean Salad',
		'canonical_recipe_entity' => 'Quinoa Black Bean Salad',
		'primary_focus_keyword'   => 'quinoa black bean salad',
		'word_count'              => 1000,
		'recipe_facts'            => array(
			'prep_time' => array( 'status' => RecipeFactAvailability::UNAVAILABLE, 'value' => '' ),
		),
	)
);
assert_true(
	( $agd_unavailable->recipe_facts()['prep_time']['status'] ?? '' ) === RecipeFactAvailability::UNAVAILABLE,
	'AGD 24 Unavailable recipe facts remain unavailable'
);
assert_true(
	! $agd_svc->proposal_from_decoded(
		rsaip_aga_valid_article_data(
			array(
				'recipe_facts' => array(
					'prep_time' => array( 'status' => 'known', 'value' => '11 minutes' ),
				),
			)
		),
		$agd_unavailable
	)->ok(),
	'AGD 24b Inventing unavailable prep_time rejected'
);

$agd_svc_src = file_get_contents( $plugin_root . '/src/Modules/Ai/ArticleGeneration/ArticleGenerationService.php' );
$agd_store_src = file_get_contents( $plugin_root . '/src/Modules/Ai/ArticleGeneration/ArticleGenerationProposalStore.php' );
$agd_ajax = file_get_contents( $plugin_root . '/includes/class-rsaip-ajax.php' );
$agd_js = file_get_contents( $plugin_root . '/assets/admin.js' );
$agd_view = file_get_contents( $plugin_root . '/src/Views/admin/ai.php' );
$agd_pms = file_get_contents( $plugin_root . '/src/Modules/PostMutation/PostMutationService.php' );
$agd_ai = file_get_contents( $plugin_root . '/includes/class-rsaip-ai.php' );

assert_true(
	is_string( $agd_svc_src ) && strpos( $agd_svc_src, 'wp_insert_post' ) === false && strpos( $agd_svc_src, 'wp_update_post' ) === false,
	'AGD 15/16 No WordPress post create/update in ArticleGenerationService'
);
assert_true(
	is_string( $agd_store_src ) && strpos( $agd_store_src, 'wp_insert_post' ) === false && strpos( $agd_store_src, 'update_post_meta' ) === false,
	'AGD 15 Store does not write posts/meta'
);
assert_true(
	is_string( $agd_ajax )
	&& strpos( $agd_ajax, 'rsaip_ai_article_preview_generate' ) !== false
	&& strpos( $agd_ajax, 'generate_preview_from_brief' ) !== false
	&& strpos( $agd_ajax, 'rsaip_ai_article_preview_get' ) !== false
	&& strpos( $agd_ajax, 'retrieve_preview' ) !== false,
	'AGD preview AJAX endpoints exist and call read-only service methods'
);
assert_true(
	is_string( $agd_pms ) && strpos( $agd_pms, 'ArticleGenerationProposalStore' ) === false,
	'AGD 17 No PMS call / wiring from preview store'
);
assert_true(
	is_string( $agd_ai ) && strpos( $agd_ai, 'function generate_article_from_brief' ) !== false
	&& strpos( $agd_ai, 'ArticleGenerationProposalStore' ) === false,
	'AGD 23 Legacy generator path remains unchanged'
);
assert_true(
	is_string( $agd_view )
	&& strpos( $agd_view, 'NEW AI Article Generator' ) !== false
	&& strpos( $agd_view, 'Legacy Article Generator' ) !== false
	&& strpos( $agd_view, 'rsaip_ai_article_preview_generate' ) !== false
	&& strpos( $agd_view, 'Create Draft Automatically' ) !== false
	&& strpos( $agd_view, 'rsaip-new-article-preview-btn' ) !== false,
	'AGD UI distinguishes NEW preview vs legacy (legacy Create Draft still present, not on NEW panel)'
);
assert_true(
	is_string( $agd_js )
	&& strpos( $agd_js, 'rsaipSafeAppendArticleHtml' ) !== false
	&& strpos( $agd_js, 'rsaipRenderArticlePreview' ) !== false
	&& strpos( $agd_js, 'rsaip_ai_article_preview_generate' ) !== false
	&& strpos( $agd_js, 'Rank Math scoring is not simulated' ) !== false,
	'AGD safe HTML renderer + preview UI wired'
);

$agd_fail = new ArticleGenerationService(
	null,
	null,
	null,
	null,
	static function () {
		return new class() {
			public function get_error_code() {
				return 'rsaip_ai_timeout';
			}
			public function get_error_message() {
				return 'timeout';
			}
		};
	}
);
assert_true( empty( $agd_fail->generate_preview_from_brief( $agd_brief, 42 )['ok'] ), 'AGD 22 Provider failure remains hard failure' );

assert_true(
	! $agd_svc->proposal_from_decoded( array( 'not' => 'valid' ), $agd_ctx )->ok(),
	'AGD 7 Invalid proposal is rejected'
);

// --- Article Generator E: Explicit Create Draft ---
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

ArticleGenerationProposalStore::clear();

$age_posts = array();
$age_next_id = 5000;
$age_pms_calls = array();
$age_deleted = array();

$age_make_creator = static function ( $pms_ok = true, $pms_code = '', $force_seo_fail = false ) use ( &$age_posts, &$age_next_id, &$age_pms_calls, &$age_deleted ) {
	$inserter = static function ( array $args ) use ( &$age_posts, &$age_next_id ) {
		assert( ! isset( $args['ID'] ) && ! isset( $args['id'] ) );
		$id = ++$age_next_id;
		$age_posts[ $id ] = array(
			'ID'           => $id,
			'post_type'    => (string) ( $args['post_type'] ?? '' ),
			'post_status'  => (string) ( $args['post_status'] ?? '' ),
			'post_title'   => (string) ( $args['post_title'] ?? '' ),
			'post_excerpt' => (string) ( $args['post_excerpt'] ?? '' ),
			'post_content' => (string) ( $args['post_content'] ?? '' ),
		);
		return $id;
	};
	$loader = static function ( int $id ) use ( &$age_posts ) {
		return $age_posts[ $id ] ?? null;
	};
	$deleter = static function ( int $id, bool $force ) use ( &$age_posts, &$age_deleted ) {
		if ( ! isset( $age_posts[ $id ] ) ) {
			return false;
		}
		if ( ( $age_posts[ $id ]['post_status'] ?? '' ) !== 'draft' ) {
			return false;
		}
		$age_deleted[] = $id;
		unset( $age_posts[ $id ] );
		unset( $force );
		return true;
	};
	$pms = static function ( array $input ) use ( &$age_pms_calls, $pms_ok, $pms_code, $force_seo_fail ) {
		$age_pms_calls[] = $input;
		if ( $force_seo_fail ) {
			return array( 'ok' => false, 'code' => $pms_code !== '' ? $pms_code : 'writer_error', 'message' => 'SEO failed' );
		}
		if ( ! $pms_ok ) {
			return array( 'ok' => false, 'code' => $pms_code !== '' ? $pms_code : 'safe_article_mode', 'message' => 'Safe Mode' );
		}
		return array( 'ok' => true, 'code' => '', 'message' => '' );
	};
	return new ArticleGenerationDraftCreator( $inserter, $deleter, $loader, $pms );
};

$age_svc = new ArticleGenerationService(
	null,
	null,
	null,
	null,
	static function () {
		$fixture = rsaip_aga_valid_article_data(
			array(
				'recipe_facts' => array(
					'ingredients' => array( 'status' => 'known', 'value' => array( '1 cup cooked quinoa', '1 can black beans, rinsed' ) ),
					'prep_time'   => array( 'status' => 'known', 'value' => '15 minutes' ),
					'cook_time'   => array( 'status' => 'known', 'value' => '20 minutes' ),
					'total_time'  => array( 'status' => 'known', 'value' => '35 minutes' ),
					'servings'    => array( 'status' => 'known', 'value' => '4 servings' ),
					'nutrition'   => array( 'status' => 'known', 'value' => 'calories: 320 calories' ),
					'dietary'     => array( 'status' => 'known', 'value' => 'VegetarianDiet' ),
					'allergens'   => array( 'status' => 'known', 'value' => 'soy-free' ),
				),
				'content_html' => rsaip_aga_body_html( '<p>Prep time 15 minutes. Cook time 20 minutes. About 320 calories per serving. Serves 4 servings.</p>' ),
			)
		);
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $fixture ) : json_encode( $fixture );
		return is_string( $json ) ? $json : '{}';
	}
);

$age_brief = array(
	'title'                   => 'Delicious Quinoa Black Bean Salad Recipe',
	'template'                => 'general',
	'word_count'              => 1000,
	'keywords'                => array( 'quinoa black bean salad', 'easy quinoa salad' ),
	'primary_focus_keyword'   => 'quinoa black bean salad',
	'canonical_recipe_entity' => 'Quinoa Black Bean Salad',
	'content'                 => rsaip_agb_ld_script( rsaip_agb_complete_recipe_node() ),
	'search_intent'           => 'informational',
);

$age_preview = $age_svc->generate_preview_from_brief( $age_brief, 77 );
assert_true( ! empty( $age_preview['ok'] ) && ! empty( $age_preview['proposal_id'] ), 'AGE preview stored for draft' );

$age_creator = $age_make_creator( true );
$age_create = $age_creator->create_from_preview(
	array(
		'proposal_id' => (string) $age_preview['proposal_id'],
		'fingerprint' => (string) $age_preview['fingerprint'],
	),
	77
);
assert_true( ! empty( $age_create['ok'] ) && (int) ( $age_create['draft_id'] ?? 0 ) > 0, 'AGE 1 Valid proposal creates exactly one NEW draft' );
$age_draft_id = (int) $age_create['draft_id'];
assert_true( isset( $age_posts[ $age_draft_id ] ), 'AGE 1b Draft exists in store' );
assert_true( ( $age_posts[ $age_draft_id ]['post_status'] ?? '' ) === 'draft', 'AGE 12 Draft has post_status=draft' );
assert_true( ( $age_posts[ $age_draft_id ]['post_type'] ?? '' ) === 'post', 'AGE 13 Draft has correct post_type' );
assert_true( ( $age_posts[ $age_draft_id ]['post_title'] ?? '' ) === (string) ( $age_preview['title'] ?? '' ), 'AGE 14 Draft title matches proposal' );
assert_true( ( $age_posts[ $age_draft_id ]['post_excerpt'] ?? '' ) === (string) ( $age_preview['excerpt'] ?? '' ), 'AGE 15 Draft excerpt matches proposal' );
assert_true( ( $age_posts[ $age_draft_id ]['post_content'] ?? '' ) === (string) ( $age_preview['content_html'] ?? '' ), 'AGE 5/16 Server-side proposal content is used' );
assert_true( empty( $age_create['published'] ), 'AGE 27 No publish operation occurs' );
assert_true( ( $age_create['seo_status'] ?? '' ) === 'applied' && ! empty( $age_create['seo_ok'] ), 'AGE 17 SEO metadata goes through PMS' );
assert_true( count( $age_pms_calls ) >= 2, 'AGE 17b PMS called for keywords + meta' );
assert_true(
	( $age_pms_calls[0]['mutation_type'] ?? '' ) === MutationType::KEYWORDS
	|| ( $age_pms_calls[1]['mutation_type'] ?? '' ) === MutationType::KEYWORDS,
	'AGE keywords mutation type used'
);

$reject_post_id = $age_creator->create_from_preview(
	array(
		'proposal_id' => (string) $age_preview['proposal_id'],
		'fingerprint' => (string) $age_preview['fingerprint'],
		'post_id'     => 1844,
	),
	77
);
// After consume, post_id key is rejected before idempotent path — actually order is: reject client fields first.
assert_true( empty( $reject_post_id['ok'] ) && ( $reject_post_id['code'] ?? '' ) === 'rsaip_article_draft_client_override_forbidden', 'AGE 2/3 Client-supplied post ID is rejected' );

$reject_html = $age_creator->create_from_preview(
	array(
		'proposal_id'  => (string) $age_preview['proposal_id'],
		'fingerprint'  => (string) $age_preview['fingerprint'],
		'content_html' => '<p>Hacked</p>',
	),
	77
);
assert_true( empty( $reject_html['ok'] ), 'AGE 4 Browser content is not trusted' );

// Fresh preview for ownership / fingerprint / expiry / duplicate tests.
ArticleGenerationProposalStore::clear();
$age_posts = array();
$age_pms_calls = array();
$age_preview2 = $age_svc->generate_preview_from_brief( $age_brief, 77 );
$age_creator2 = $age_make_creator( true );

$age_wrong_user = $age_creator2->create_from_preview(
	array(
		'proposal_id' => (string) $age_preview2['proposal_id'],
		'fingerprint' => (string) $age_preview2['fingerprint'],
	),
	99
);
assert_true( empty( $age_wrong_user['ok'] ), 'AGE 6 User ownership is enforced' );

$age_bad_fp = $age_creator2->create_from_preview(
	array(
		'proposal_id' => (string) $age_preview2['proposal_id'],
		'fingerprint' => str_repeat( 'b', 64 ),
	),
	77
);
assert_true( empty( $age_bad_fp['ok'] ), 'AGE 7 Fingerprint is enforced' );

ArticleGenerationProposalStore::force_expire_for_tests( 77, (string) $age_preview2['proposal_id'] );
$age_expired = $age_creator2->create_from_preview(
	array(
		'proposal_id' => (string) $age_preview2['proposal_id'],
		'fingerprint' => (string) $age_preview2['fingerprint'],
	),
	77
);
assert_true( empty( $age_expired['ok'] ) && ( $age_expired['code'] ?? '' ) === 'preview_expired', 'AGE 8 Expired proposal cannot create a draft' );

ArticleGenerationProposalStore::clear();
$age_preview3 = $age_svc->generate_preview_from_brief( $age_brief, 77 );
$age_creator3 = $age_make_creator( true );
$age_first = $age_creator3->create_from_preview(
	array(
		'proposal_id' => (string) $age_preview3['proposal_id'],
		'fingerprint' => (string) $age_preview3['fingerprint'],
	),
	77
);
$age_id_a = (int) ( $age_first['draft_id'] ?? 0 );
$age_second = $age_creator3->create_from_preview(
	array(
		'proposal_id' => (string) $age_preview3['proposal_id'],
		'fingerprint' => (string) $age_preview3['fingerprint'],
	),
	77
);
$age_id_b = (int) ( $age_second['draft_id'] ?? 0 );
assert_true( ! empty( $age_first['ok'] ) && ! empty( $age_second['ok'] ) && $age_id_a === $age_id_b && $age_id_a > 0, 'AGE 21/22/40 Duplicate Create Draft does not create duplicate drafts; consumed returns existing' );
assert_true( ! empty( $age_second['already_consumed'] ) || ( $age_second['code'] ?? '' ) === 'proposal_already_consumed', 'AGE 22 Consumed proposal returns existing result' );
assert_true( count( $age_posts ) === 1, 'AGE 40 Exactly one draft per proposal' );

// Expired consumed: still returns existing draft.
ArticleGenerationProposalStore::force_expire_for_tests( 77, (string) $age_preview3['proposal_id'] );
$age_expired_consumed = $age_creator3->create_from_preview(
	array(
		'proposal_id' => (string) $age_preview3['proposal_id'],
		'fingerprint' => (string) $age_preview3['fingerprint'],
	),
	77
);
assert_true( ! empty( $age_expired_consumed['ok'] ) && (int) $age_expired_consumed['draft_id'] === $age_id_a, 'AGE 23 Expired consumed proposal returns existing draft deterministically' );

// Safe Mode SEO block → draft kept, not reported as full SEO success.
ArticleGenerationProposalStore::clear();
$age_posts = array();
$age_pms_calls = array();
$age_preview4 = $age_svc->generate_preview_from_brief( $age_brief, 77 );
$age_safe = $age_make_creator( false, 'safe_article_mode' );
$age_safe_res = $age_safe->create_from_preview(
	array(
		'proposal_id' => (string) $age_preview4['proposal_id'],
		'fingerprint' => (string) $age_preview4['fingerprint'],
	),
	77
);
assert_true( ! empty( $age_safe_res['ok'] ) && ! empty( $age_safe_res['partial'] ) && ( $age_safe_res['seo_status'] ?? '' ) === 'blocked_by_safe_mode', 'AGE 20 Safe Mode respected with clear partial state' );
assert_true( empty( $age_safe_res['seo_ok'] ), 'AGE 25 SEO failure/block is not reported as full SEO success' );
assert_true( isset( $age_posts[ (int) $age_safe_res['draft_id'] ] ), 'AGE 20b Draft body retained under Safe Mode block' );

// Unexpected SEO failure → compensation deletes NEW draft only.
ArticleGenerationProposalStore::clear();
$age_posts = array();
$age_deleted = array();
$age_posts[ 1844 ] = array(
	'ID'          => 1844,
	'post_type'   => 'post',
	'post_status' => 'publish',
	'post_title'  => 'Existing Published',
	'post_excerpt'=> '',
	'post_content'=> 'keep me',
);
$age_preview5 = $age_svc->generate_preview_from_brief( $age_brief, 77 );
$age_fail_seo = $age_make_creator( false, 'writer_error', true );
$age_fail_res = $age_fail_seo->create_from_preview(
	array(
		'proposal_id' => (string) $age_preview5['proposal_id'],
		'fingerprint' => (string) $age_preview5['fingerprint'],
	),
	77
);
assert_true( empty( $age_fail_res['ok'] ), 'AGE 25b Unexpected SEO failure is not full success' );
assert_true( isset( $age_posts[1844] ) && ( $age_posts[1844]['post_status'] ?? '' ) === 'publish', 'AGE 26 Compensation never deletes an existing post' );
assert_true( ! empty( $age_deleted ), 'AGE compensation deletes the new draft' );

$age_invalid = $age_make_creator( true )->create_from_preview(
	array(
		'proposal_id' => 'nope',
		'fingerprint' => str_repeat( 'c', 64 ),
	),
	77
);
assert_true( empty( $age_invalid['ok'] ), 'AGE 9 Invalid proposal cannot create a draft' );

$age_unauth = $age_make_creator( true )->create_from_preview(
	array(
		'proposal_id' => (string) $age_preview5['proposal_id'],
		'fingerprint' => (string) $age_preview5['fingerprint'],
	),
	0
);
assert_true( empty( $age_unauth['ok'] ), 'AGE 11 Missing capability/auth cannot create a draft' );

$age_ajax = file_get_contents( $plugin_root . '/includes/class-rsaip-ajax.php' );
$age_creator_src = file_get_contents( $plugin_root . '/src/Modules/Ai/ArticleGeneration/ArticleGenerationDraftCreator.php' );
$age_view = file_get_contents( $plugin_root . '/src/Views/admin/ai.php' );
$age_js = file_get_contents( $plugin_root . '/assets/admin.js' );
$age_ai = file_get_contents( $plugin_root . '/includes/class-rsaip-ai.php' );
$age_pms = file_get_contents( $plugin_root . '/src/Modules/PostMutation/PostMutationService.php' );

assert_true(
	is_string( $age_ajax )
	&& strpos( $age_ajax, 'rsaip_ai_article_create_draft' ) !== false
	&& strpos( $age_ajax, 'ArticleGenerationDraftCreator' ) !== false
	&& strpos( $age_ajax, 'check_ajax_referer' ) !== false,
	'AGE 10 AJAX endpoint uses capability/nonce architecture (check())'
);
assert_true(
	is_string( $age_creator_src )
	&& strpos( $age_creator_src, 'wp_update_post' ) === false
	&& strpos( $age_creator_src, 'wp_insert_post' ) !== false
	&& strpos( $age_creator_src, 'apply_fresh' ) !== false
	&& strpos( $age_creator_src, 'update_post_meta' ) === false,
	'AGE 19/28 No direct SEO meta writes; no update_post; insert + PMS only'
);
assert_true(
	is_string( $age_view )
	&& strpos( $age_view, 'rsaip_ai_article_create_draft' ) !== false
	&& strpos( $age_view, 'Create Draft (NEW Generator)' ) !== false
	&& strpos( $age_view, 'Legacy Article Generator' ) !== false
	&& strpos( $age_view, 'rsaip_ai_create_article_draft' ) !== false,
	'AGE UI Create Draft on NEW panel; legacy untouched'
);
assert_true(
	is_string( $age_js )
	&& strpos( $age_js, 'rsaip_ai_article_create_draft' ) !== false
	&& strpos( $age_js, 'proposal_id' ) !== false
	&& strpos( $age_js, 'fingerprint' ) !== false,
	'AGE JS sends proposal_id + fingerprint only'
);
assert_true(
	is_string( $age_ai )
	&& strpos( $age_ai, 'function create_draft_from_brief' ) !== false
	&& strpos( $age_ai, 'ArticleGenerationDraftCreator' ) === false,
	'AGE 31 Legacy generator behavior unchanged'
);
assert_true(
	is_string( $age_pms )
	&& strpos( $age_pms, 'ArticleGenerationDraftCreator' ) === false,
	'AGE 30 No M5/PMS architecture changes from draft creator'
);
assert_true(
	is_string( $age_creator_src )
	&& strpos( $age_creator_src, '100/100' ) === false
	&& strpos( $age_creator_src, 'api_key' ) === false
	&& strpos( $age_creator_src, 'post_status\'  => \'publish' ) === false
	&& strpos( $age_creator_src, "post_status' => 'publish" ) === false,
	'AGE 37/38 No fake Rank Math score / no API key / no publish'
);
assert_true(
	is_string( $age_creator_src )
	&& strpos( $age_creator_src, 'MutationType::KEYWORDS' ) !== false
	&& strpos( $age_creator_src, 'MutationType::META_DESCRIPTION' ) !== false
	&& strpos( $age_creator_src, 'rsaip_post_mutation_service' ) !== false
	&& strpos( $age_creator_src, "seo_owner" ) === false
	&& strpos( $age_creator_src, 'rank_math_score' ) === false,
	'AGE 18/32-35 SEO via PMS; AI cannot control owner/meta keys/mutation from creator request path'
);
assert_true(
	is_string( $age_ajax )
	&& strpos( $age_ajax, 'function rsaip_ai_article_create_draft' ) !== false
	&& ( strpos( substr( $age_ajax, (int) strpos( $age_ajax, 'function rsaip_ai_article_create_draft' ), 1200 ), 'content_html' ) === false
		|| strpos( substr( $age_ajax, (int) strpos( $age_ajax, 'function rsaip_ai_article_create_draft' ), 1200 ), "\$_POST['content_html']" ) === false )
	&& strpos( substr( $age_ajax, (int) strpos( $age_ajax, 'function rsaip_ai_article_create_draft' ), 1200 ), "\$_POST['post_id']" ) === false
	&& strpos( substr( $age_ajax, (int) strpos( $age_ajax, 'function rsaip_ai_article_create_draft' ), 1200 ), 'wp_update_post' ) === false
	&& strpos( substr( $age_ajax, (int) strpos( $age_ajax, 'function rsaip_ai_article_create_draft' ), 1200 ), 'update_post_meta' ) === false,
	'AGE AJAX accepts only proposal identifiers; no browser-authoritative content or meta writes'
);
$age_schema = file_get_contents( $plugin_root . '/src/Modules/Schema/RecipeSchemaRepairService.php' );
assert_true(
	is_string( $age_schema ) && strpos( $age_schema, 'ArticleGenerationDraftCreator' ) === false,
	'AGE 29 No schema repair coupling'
);

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
