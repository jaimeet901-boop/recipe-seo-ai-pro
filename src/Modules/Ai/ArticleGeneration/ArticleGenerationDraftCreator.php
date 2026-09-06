<?php
declare(strict_types=1);

/**
 * Explicit Create Draft from a stored ArticleGeneration preview (Milestone E).
 *
 * ONLY allowed write: wp_insert_post() for a NEW draft.
 * SEO via PostMutationService. Never updates existing posts. Never publishes.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\ArticleGeneration;

use RecipeSeoAiPro\Modules\PostMutation\MutationType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ArticleGenerationDraftCreator
 */
final class ArticleGenerationDraftCreator {

	/** @var callable|null function(array $args): int|\WP_Error|object */
	private $inserter;

	/** @var callable|null function(int $post_id, bool $force): bool|mixed */
	private $deleter;

	/** @var callable|null function(int $post_id): object|array|null */
	private $post_loader;

	/** @var callable|null function(array $input): object MutationResult-like */
	private $pms_applier;

	/**
	 * @param callable|null $inserter    Test double for wp_insert_post.
	 * @param callable|null $deleter     Test double for wp_delete_post.
	 * @param callable|null $post_loader Test double for get_post.
	 * @param callable|null $pms_applier Test double for PMS apply_fresh.
	 */
	public function __construct( $inserter = null, $deleter = null, $post_loader = null, $pms_applier = null ) {
		$this->inserter    = is_callable( $inserter ) ? $inserter : null;
		$this->deleter     = is_callable( $deleter ) ? $deleter : null;
		$this->post_loader = is_callable( $post_loader ) ? $post_loader : null;
		$this->pms_applier = is_callable( $pms_applier ) ? $pms_applier : null;
	}

	/**
	 * Create a NEW draft from a server-stored preview. Browser may only supply ids.
	 *
	 * @param array<string, mixed> $request May include proposal_id, fingerprint; post_id/content ignored.
	 * @return array<string, mixed>
	 */
	public function create_from_preview( array $request, int $user_id ): array {
		$user_id = max( 0, $user_id );
		if ( $user_id <= 0 ) {
			return $this->fail( 'rsaip_article_draft_unauthorized', 'You must be logged in to create a draft.' );
		}

		// Reject authoritative browser mutation / content fields.
		foreach ( array( 'post_id', 'ID', 'content_html', 'title', 'excerpt', 'meta_key', 'seo_owner', 'owner', 'mutation_type', 'proposal_ticket' ) as $forbidden ) {
			if ( array_key_exists( $forbidden, $request ) && $request[ $forbidden ] !== '' && $request[ $forbidden ] !== null ) {
				return $this->fail(
					'rsaip_article_draft_client_override_forbidden',
					'Client cannot supply ' . $forbidden . ' for Create Draft.'
				);
			}
		}

		$proposal_id = isset( $request['proposal_id'] ) ? sanitize_text_field( (string) $request['proposal_id'] ) : '';
		$fingerprint = isset( $request['fingerprint'] ) ? sanitize_text_field( (string) $request['fingerprint'] ) : '';
		if ( $proposal_id === '' || $fingerprint === '' ) {
			return $this->fail( 'rsaip_article_draft_invalid_request', 'proposal_id and fingerprint are required.' );
		}

		$claim = ArticleGenerationProposalStore::claim_for_draft( $user_id, $proposal_id, $fingerprint );
		if ( empty( $claim['ok'] ) ) {
			return $this->fail(
				(string) ( $claim['code'] ?? 'rsaip_article_draft_claim_failed' ),
				(string) ( $claim['message'] ?? 'Could not claim preview proposal.' )
			);
		}

		// Idempotent: already consumed → return existing draft result (no second insert).
		if ( (string) ( $claim['status'] ?? '' ) === ArticleGenerationProposalStore::STATUS_CONSUMED
			|| (string) ( $claim['code'] ?? '' ) === 'proposal_already_consumed' ) {
			$draft_id = (int) ( $claim['draft_id'] ?? 0 );
			if ( $draft_id <= 0 ) {
				return $this->fail( 'rsaip_article_draft_consumed_missing', 'Proposal was consumed but draft ID is missing.' );
			}
			$verified = $this->verify_draft( $draft_id, $claim['proposal'] ?? null );
			if ( empty( $verified['ok'] ) ) {
				return $this->fail(
					(string) ( $verified['code'] ?? 'rsaip_article_draft_verify_failed' ),
					(string) ( $verified['message'] ?? 'Previously created draft failed verification.' )
				);
			}
			return $this->success_payload(
				$draft_id,
				$claim['proposal'],
				(string) ( $claim['seo_status'] ?? 'unknown' ),
				true,
				$proposal_id,
				$fingerprint
			);
		}

		$proposal = $claim['proposal'] ?? null;
		$context  = $claim['context'] ?? null;
		if ( ! $proposal instanceof ArticleGenerationProposal || ! $context instanceof ArticleGenerationContext ) {
			ArticleGenerationProposalStore::release_claim( $user_id, $proposal_id );
			return $this->fail( 'rsaip_article_draft_invalid_proposal', 'Stored proposal is invalid.' );
		}

		// Re-validate immediately before write.
		$recheck = ( new ArticleGenerationProposalValidator() )->validate( $proposal->to_array(), $context );
		if ( ! $recheck->ok() || ! $recheck->proposal() instanceof ArticleGenerationProposal ) {
			ArticleGenerationProposalStore::release_claim( $user_id, $proposal_id );
			return $this->fail(
				$recheck->code() !== '' ? $recheck->code() : 'rsaip_article_draft_revalidation_failed',
				$recheck->message() !== '' ? $recheck->message() : 'Proposal failed re-validation before draft creation.'
			);
		}
		$proposal = $recheck->proposal();

		if ( ! $proposal->word_count_in_band() ) {
			ArticleGenerationProposalStore::release_claim( $user_id, $proposal_id );
			return $this->fail( 'rsaip_article_word_count_out_of_band', 'Proposal word count is out of band.' );
		}

		$insert_args = array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => $proposal->title(),
			'post_excerpt' => $proposal->excerpt(),
			'post_content' => $proposal->content_html(),
			// Never set ID — NEW post only.
		);

		$insert_id = $this->insert_draft( $insert_args );
		if ( is_object( $insert_id ) && method_exists( $insert_id, 'get_error_message' ) ) {
			ArticleGenerationProposalStore::release_claim( $user_id, $proposal_id );
			$msg = (string) $insert_id->get_error_message();
			return $this->fail( 'rsaip_article_draft_insert_failed', $msg !== '' ? $msg : 'wp_insert_post failed.' );
		}
		$draft_id = (int) $insert_id;
		if ( $draft_id <= 0 ) {
			ArticleGenerationProposalStore::release_claim( $user_id, $proposal_id );
			return $this->fail( 'rsaip_article_draft_insert_failed', 'wp_insert_post did not return a valid ID.' );
		}

		$verified = $this->verify_draft( $draft_id, $proposal );
		if ( empty( $verified['ok'] ) ) {
			$this->compensate_delete_new_draft( $draft_id );
			ArticleGenerationProposalStore::release_claim( $user_id, $proposal_id );
			return $this->fail(
				(string) ( $verified['code'] ?? 'rsaip_article_draft_verify_failed' ),
				(string) ( $verified['message'] ?? 'Created draft failed verification.' )
			);
		}

		$seo = $this->apply_seo_via_pms( $draft_id, $proposal );
		$seo_status = (string) ( $seo['status'] ?? 'failed' );

		if ( ! empty( $seo['ok'] ) ) {
			ArticleGenerationProposalStore::mark_consumed( $user_id, $proposal_id, $draft_id, $seo_status );
			return $this->success_payload( $draft_id, $proposal, $seo_status, false, $proposal_id, $fingerprint );
		}

		// Safe Mode: draft body is valid; SEO intentionally blocked — clear partial success, keep draft.
		if ( (string) ( $seo['code'] ?? '' ) === 'safe_article_mode' ) {
			ArticleGenerationProposalStore::mark_consumed( $user_id, $proposal_id, $draft_id, 'blocked_by_safe_mode' );
			$out = $this->success_payload( $draft_id, $proposal, 'blocked_by_safe_mode', false, $proposal_id, $fingerprint );
			$out['ok']               = true;
			$out['partial']          = true;
			$out['seo_ok']           = false;
			$out['seo_code']         = 'safe_article_mode';
			$out['seo_message']      = (string) ( $seo['message'] ?? 'SEO application blocked by Safe Article Mode.' );
			$out['message']          = 'Draft created. SEO metadata was not applied because Safe Article Mode is enabled.';
			return $out;
		}

		// Unexpected SEO failure: compensate by deleting ONLY this new draft.
		$deleted = $this->compensate_delete_new_draft( $draft_id );
		ArticleGenerationProposalStore::release_claim( $user_id, $proposal_id );
		if ( $deleted ) {
			return $this->fail(
				'rsaip_article_draft_seo_failed',
				'SEO application failed after draft creation; the new draft was removed. ' . (string) ( $seo['message'] ?? '' )
			);
		}

		// Compensation failed — report partial with draft ID (never delete anything else).
		ArticleGenerationProposalStore::mark_consumed( $user_id, $proposal_id, $draft_id, 'seo_failed_compensation_failed' );
		return array(
			'ok'                 => false,
			'partial'            => true,
			'code'               => 'rsaip_article_draft_partial_seo_failure',
			'message'            => 'Draft was created but SEO application failed and the draft could not be safely removed.',
			'draft_id'           => $draft_id,
			'status'             => 'draft',
			'seo_status'         => 'failed',
			'seo_ok'             => false,
			'seo_code'           => (string) ( $seo['code'] ?? 'seo_failed' ),
			'seo_message'        => (string) ( $seo['message'] ?? '' ),
			'proposal_consumed'  => true,
			'proposal_id'        => $proposal_id,
			'fingerprint'        => $fingerprint,
			'source'             => 'article_generation_draft',
			'apply_allowed'      => false,
			'create_draft'       => false,
		);
	}

	/**
	 * @param array<string, mixed> $args Insert args (no ID).
	 * @return int|\WP_Error|object
	 */
	private function insert_draft( array $args ) {
		unset( $args['ID'], $args['id'], $args['post_id'] );
		$args['post_type']   = 'post';
		$args['post_status'] = 'draft';

		if ( is_callable( $this->inserter ) ) {
			return call_user_func( $this->inserter, $args );
		}

		if ( ! function_exists( 'wp_insert_post' ) ) {
			return new \WP_Error( 'rsaip_article_draft_wp_missing', 'wp_insert_post is unavailable.' );
		}

		$result = wp_insert_post( function_exists( 'wp_slash' ) ? wp_slash( $args ) : $args, true );
		return $result;
	}

	/**
	 * @return array{ok: bool, code?: string, message?: string}
	 */
	private function verify_draft( int $draft_id, $proposal ): array {
		if ( $draft_id <= 0 ) {
			return array( 'ok' => false, 'code' => 'rsaip_article_draft_invalid_id', 'message' => 'Invalid draft ID.' );
		}
		$post = $this->load_post( $draft_id );
		if ( $post === null ) {
			return array( 'ok' => false, 'code' => 'rsaip_article_draft_missing', 'message' => 'Draft post not found after insert.' );
		}

		$type   = (string) ( is_object( $post ) ? ( $post->post_type ?? '' ) : ( $post['post_type'] ?? '' ) );
		$status = (string) ( is_object( $post ) ? ( $post->post_status ?? '' ) : ( $post['post_status'] ?? '' ) );
		$title  = (string) ( is_object( $post ) ? ( $post->post_title ?? '' ) : ( $post['post_title'] ?? '' ) );
		$excerpt = (string) ( is_object( $post ) ? ( $post->post_excerpt ?? '' ) : ( $post['post_excerpt'] ?? '' ) );
		$content = (string) ( is_object( $post ) ? ( $post->post_content ?? '' ) : ( $post['post_content'] ?? '' ) );

		if ( $type !== 'post' ) {
			return array( 'ok' => false, 'code' => 'rsaip_article_draft_bad_type', 'message' => 'Created post has unexpected post_type.' );
		}
		if ( $status !== 'draft' ) {
			return array( 'ok' => false, 'code' => 'rsaip_article_draft_not_draft', 'message' => 'Created post is not a draft.' );
		}
		if ( $proposal instanceof ArticleGenerationProposal ) {
			if ( $title !== $proposal->title() ) {
				return array( 'ok' => false, 'code' => 'rsaip_article_draft_title_mismatch', 'message' => 'Draft title does not match proposal.' );
			}
			if ( $excerpt !== $proposal->excerpt() ) {
				return array( 'ok' => false, 'code' => 'rsaip_article_draft_excerpt_mismatch', 'message' => 'Draft excerpt does not match proposal.' );
			}
			if ( $content !== $proposal->content_html() ) {
				return array( 'ok' => false, 'code' => 'rsaip_article_draft_content_mismatch', 'message' => 'Draft content does not match proposal.' );
			}
		}
		return array( 'ok' => true );
	}

	/**
	 * @return array{ok: bool, status: string, code?: string, message?: string}
	 */
	private function apply_seo_via_pms( int $draft_id, ArticleGenerationProposal $proposal ): array {
		$keywords = array_values(
			array_filter(
				array_merge(
					array( $proposal->primary_focus_keyword() ),
					$proposal->secondary_keywords()
				)
			)
		);
		$meta = $proposal->meta_description();

		$kw = $this->pms_apply(
			array(
				'post_id'       => $draft_id,
				'mutation_type' => MutationType::KEYWORDS,
				'value'         => $keywords,
				'target'        => 'auto',
			)
		);
		if ( empty( $kw['ok'] ) ) {
			return array(
				'ok'      => false,
				'status'  => 'keywords_failed',
				'code'    => (string) ( $kw['code'] ?? 'keywords_failed' ),
				'message' => (string) ( $kw['message'] ?? 'Keywords apply failed.' ),
			);
		}

		$md = $this->pms_apply(
			array(
				'post_id'       => $draft_id,
				'mutation_type' => MutationType::META_DESCRIPTION,
				'value'         => $meta,
				'target'        => 'auto',
			)
		);
		if ( empty( $md['ok'] ) ) {
			return array(
				'ok'      => false,
				'status'  => 'meta_failed',
				'code'    => (string) ( $md['code'] ?? 'meta_failed' ),
				'message' => (string) ( $md['message'] ?? 'Meta description apply failed.' ),
			);
		}

		return array(
			'ok'     => true,
			'status' => 'applied',
		);
	}

	/**
	 * @param array<string, mixed> $input PMS input.
	 * @return array{ok: bool, code?: string, message?: string}
	 */
	private function pms_apply( array $input ): array {
		if ( is_callable( $this->pms_applier ) ) {
			$result = call_user_func( $this->pms_applier, $input );
			if ( is_object( $result ) && method_exists( $result, 'ok' ) ) {
				return array(
					'ok'      => (bool) $result->ok(),
					'code'    => method_exists( $result, 'code' ) ? (string) $result->code() : '',
					'message' => method_exists( $result, 'message' ) ? (string) $result->message() : '',
				);
			}
			if ( is_array( $result ) ) {
				return array(
					'ok'      => ! empty( $result['ok'] ),
					'code'    => (string) ( $result['code'] ?? '' ),
					'message' => (string) ( $result['message'] ?? '' ),
				);
			}
			return array( 'ok' => false, 'code' => 'pms_invalid', 'message' => 'Invalid PMS test double response.' );
		}

		if ( ! function_exists( 'rsaip_post_mutation_service' ) ) {
			return array( 'ok' => false, 'code' => 'writer_missing', 'message' => 'Post mutation service is unavailable.' );
		}

		$result = rsaip_post_mutation_service()->apply_fresh( $input );
		return array(
			'ok'      => $result->ok(),
			'code'    => $result->code(),
			'message' => $result->message(),
		);
	}

	/**
	 * Delete ONLY the draft created in this request. Never deletes published/non-draft posts.
	 */
	private function compensate_delete_new_draft( int $draft_id ): bool {
		if ( $draft_id <= 0 ) {
			return false;
		}
		$post = $this->load_post( $draft_id );
		if ( $post === null ) {
			return false;
		}
		$status = (string) ( is_object( $post ) ? ( $post->post_status ?? '' ) : ( $post['post_status'] ?? '' ) );
		$type   = (string) ( is_object( $post ) ? ( $post->post_type ?? '' ) : ( $post['post_type'] ?? '' ) );
		if ( $status !== 'draft' || $type !== 'post' ) {
			// Safety: never delete non-draft / unexpected types.
			return false;
		}

		if ( is_callable( $this->deleter ) ) {
			return (bool) call_user_func( $this->deleter, $draft_id, true );
		}
		if ( ! function_exists( 'wp_delete_post' ) ) {
			return false;
		}
		$deleted = wp_delete_post( $draft_id, true );
		return (bool) $deleted;
	}

	/**
	 * @return object|array|null
	 */
	private function load_post( int $post_id ) {
		if ( is_callable( $this->post_loader ) ) {
			return call_user_func( $this->post_loader, $post_id );
		}
		if ( ! function_exists( 'get_post' ) ) {
			return null;
		}
		$post = get_post( $post_id );
		return $post ?: null;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function success_payload(
		int $draft_id,
		$proposal,
		string $seo_status,
		bool $already_consumed,
		string $proposal_id,
		string $fingerprint
	): array {
		$title = $proposal instanceof ArticleGenerationProposal ? $proposal->title() : '';
		$edit  = '';
		if ( function_exists( 'get_edit_post_link' ) ) {
			$link = get_edit_post_link( $draft_id, 'raw' );
			$edit = is_string( $link ) ? $link : '';
		}

		return array(
			'ok'                => true,
			'partial'           => ( $seo_status === 'blocked_by_safe_mode' ),
			'code'              => $already_consumed ? 'proposal_already_consumed' : '',
			'message'           => $already_consumed
				? 'Draft was already created for this preview.'
				: 'Draft created successfully.',
			'draft_id'          => $draft_id,
			'post_id'           => $draft_id, // alias for UI; always the NEW draft from this proposal.
			'status'            => 'draft',
			'post_type'         => 'post',
			'title'             => $title,
			'seo_status'        => $seo_status,
			'seo_ok'            => ( $seo_status === 'applied' ),
			'proposal_consumed' => true,
			'already_consumed'  => $already_consumed,
			'proposal_id'       => $proposal_id,
			'fingerprint'       => $fingerprint,
			'edit_url'          => $edit,
			'source'            => 'article_generation_draft',
			'apply_allowed'     => false,
			'create_draft'      => false,
			'published'         => false,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function fail( string $code, string $message ): array {
		return array(
			'ok'                => false,
			'code'              => $code,
			'message'           => $message,
			'source'            => 'article_generation_draft',
			'apply_allowed'     => false,
			'create_draft'      => false,
			'proposal_consumed' => false,
			'published'         => false,
		);
	}
}
