<?php
declare(strict_types=1);

/**
 * Centralized existing-post mutation service.
 *
 * Milestone 4: UI Apply uses apply_from_preview() (signed ticket).
 * apply_fresh() is same-request only and is never used by Fix With AI.
 * Never mutates post_content.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation;

use RecipeSeoAiPro\Contracts\PostMutationServiceInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PostMutationService
 */
final class PostMutationService implements PostMutationServiceInterface {

	private PostMutationEnvironment $env;
	private SeoOwnershipResolver $ownership;
	private FieldFingerprint $fingerprint;
	private MutationSnapshotStore $snapshots;

	/** @var array<string, MutationWriter> */
	private array $writers = array();

	/**
	 * @param list<MutationWriter> $writers Writers.
	 */
	public function __construct(
		PostMutationEnvironment $env,
		SeoOwnershipResolver $ownership,
		FieldFingerprint $fingerprint,
		MutationSnapshotStore $snapshots,
		array $writers
	) {
		$this->env         = $env;
		$this->ownership   = $ownership;
		$this->fingerprint = $fingerprint;
		$this->snapshots   = $snapshots;
		foreach ( $writers as $writer ) {
			if ( $writer instanceof MutationWriter ) {
				$this->writers[ $writer->mutation_type() ] = $writer;
			}
		}
	}

	/**
	 * Soft proposal TTL for Preview UX (seconds). Fingerprint remains the hard validity check.
	 */
	public const PROPOSAL_TTL_SECONDS = 3600;

	/**
	 * @param array<string, mixed> $input Input.
	 */
	public function propose( array $input ): MutationResult {
		if ( MutationProposal::rejects_client_meta_key( $input ) ) {
			return MutationResult::failure( 'client_meta_key_forbidden', 'Client meta keys are not allowed.' );
		}

		if ( ! $this->env->security_helpers_available() ) {
			return MutationResult::failure( 'security_helpers_missing', 'Security helpers are unavailable.' );
		}
		if ( ! $this->env->can_manage_plugin() ) {
			return MutationResult::failure( 'unauthorized', 'User is not authorized to mutate this post.' );
		}

		$type = MutationType::sanitize( (string) ( $input['mutation_type'] ?? $input['type'] ?? '' ) );
		if ( $type === '' ) {
			return MutationResult::failure( 'invalid_mutation_type', 'Mutation type is not in the allowlist.' );
		}

		$writer = $this->writers[ $type ] ?? null;
		if ( ! $writer instanceof MutationWriter ) {
			return MutationResult::failure( 'writer_missing', 'No writer registered for mutation type.' );
		}

		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		if ( $post_id < 0 ) {
			$post_id = 0;
		}
		$post = $this->env->get_post( $post_id );
		if ( null === $post ) {
			return MutationResult::failure( 'invalid_post', 'Post not found.' );
		}
		if ( ! in_array( (string) $post['post_type'], $this->env->supported_post_types(), true ) ) {
			return MutationResult::failure( 'invalid_post_type', 'Post type is not supported.' );
		}
		if ( ! $this->env->can_edit_post( $post_id ) ) {
			return MutationResult::failure( 'unauthorized', 'User is not authorized to mutate this post.' );
		}

		$client_target = isset( $input['target'] ) ? (string) $input['target'] : 'auto';
		$owner         = $this->ownership->resolve( $client_target );
		if ( $owner === '' ) {
			return MutationResult::failure( 'ownership_override_forbidden', 'Client target cannot override server SEO ownership.' );
		}

		$raw_value = $input['value'] ?? $input['new_value'] ?? null;
		$new_value = $writer->normalize_new_value( $raw_value );
		if ( null === $new_value ) {
			return MutationResult::failure( 'invalid_value', 'Mutation value is invalid.' );
		}

		$current      = $writer->read_current( $post_id, $owner );
		$content_hash = $this->fingerprint->content_hash( (string) $post['post_content'] );
		$fp           = $this->fingerprint->build(
			$post_id,
			$type,
			$owner,
			$current,
			(string) $post['post_modified_gmt'],
			$content_hash
		);

		$proposal = new MutationProposal(
			$post_id,
			$type,
			$owner,
			$new_value,
			$fp,
			$this->env->current_user_id(),
			$this->ownership->allowed_meta_keys( $owner, $type ),
			$client_target
		);

		$snapshot                     = new MutationSnapshot();
		$snapshot->post_id            = $post_id;
		$snapshot->mutation_type      = $type;
		$snapshot->owner              = $owner;
		$snapshot->meta_keys          = $proposal->meta_keys();
		$snapshot->old_value          = $current;
		$snapshot->new_value          = $new_value;
		$snapshot->fingerprint_before = $fp;
		$snapshot->post_modified_gmt  = (string) $post['post_modified_gmt'];
		$snapshot->content_hash       = $content_hash;
		$snapshot->user_id            = $proposal->user_id();
		$snapshot->status             = 'proposed';
		$snapshot                     = $this->snapshots->save( $snapshot );

		$proposed_at    = gmdate( 'Y-m-d H:i:s' );
		$expires_unix   = time() + self::PROPOSAL_TTL_SECONDS;
		$expires_at     = gmdate( 'Y-m-d H:i:s', $expires_unix );
		$safe_mode      = $this->env->never_modify_posts();
		$apply_allowed  = ! $safe_mode;
		$blocked_reason = $safe_mode ? 'safe_article_mode' : '';

		$ticket   = '';
		$sign_key = $this->env->proposal_signing_key();
		if ( $sign_key !== '' ) {
			$ticket = ProposalTicket::issue(
				array(
					'v'             => 1,
					'post_id'       => $post_id,
					'mutation_type' => $type,
					'user_id'       => $proposal->user_id(),
					'fingerprint'   => $fp,
					'value_hash'    => ProposalTicket::value_hash( $new_value ),
					'expires_at'    => $expires_unix,
					'target'        => $client_target,
				),
				$sign_key
			);
		}
		if ( $ticket === '' ) {
			$apply_allowed  = false;
			$blocked_reason = $blocked_reason !== '' ? $blocked_reason : 'proposal_ticket_unavailable';
		}

		// Read-only path: writers are never invoked for write; snapshot store may be null/no-op.
		return MutationResult::success(
			'proposed',
			'Mutation proposal created.',
			$current,
			$new_value,
			false,
			$snapshot->id,
			$proposal
		)->with_preview(
			array(
				'original_value'       => $current,
				'proposed_value'       => $raw_value,
				'normalized_value'     => $new_value,
				'post_modified_gmt'    => (string) $post['post_modified_gmt'],
				'proposed_at'          => $proposed_at,
				'expires_at'           => $expires_at,
				'safe_mode'            => $safe_mode,
				'apply_allowed'        => $apply_allowed,
				'apply_blocked_reason' => $blocked_reason,
				'mutation_type'        => $type,
				'owner'                => $owner,
				'post_id'              => $post_id,
				'fingerprint'          => $fp,
				'proposal_ticket'      => $ticket,
			)
		);
	}

	/**
	 * @param array<string, mixed> $input Input from Apply AJAX (ticket + value). Type is $expected_type only.
	 */
	public function apply_from_preview( array $input, string $expected_type ): MutationResult {
		if ( MutationProposal::rejects_client_meta_key( $input ) ) {
			return MutationResult::failure( 'client_meta_key_forbidden', 'Client meta keys are not allowed.' );
		}

		$type = MutationType::sanitize( $expected_type );
		if ( $type === '' || ! $this->is_ui_apply_type( $type ) ) {
			return MutationResult::failure( 'invalid_mutation_type', 'Mutation type is not in the allowlist.' );
		}

		$ticket_raw = isset( $input['proposal_ticket'] ) ? (string) $input['proposal_ticket'] : '';
		$claims     = ProposalTicket::verify( $ticket_raw, $this->env->proposal_signing_key() );
		if ( null === $claims ) {
			return MutationResult::failure( 'invalid_proposal_ticket', 'Proposal ticket is missing or invalid.' );
		}

		if ( (int) ( $claims['user_id'] ?? 0 ) !== $this->env->current_user_id() ) {
			return MutationResult::failure( 'unauthorized', 'User is not authorized to mutate this post.' );
		}

		$claim_type = MutationType::sanitize( (string) ( $claims['mutation_type'] ?? '' ) );
		if ( $claim_type === '' || $claim_type !== $type ) {
			return MutationResult::failure( 'invalid_mutation_type', 'Mutation type is not in the allowlist.' );
		}

		$expires_at = (int) ( $claims['expires_at'] ?? 0 );
		if ( $expires_at <= 0 || time() > $expires_at ) {
			return MutationResult::failure( 'expired_proposal', 'Proposal has expired. Preview again before Apply.' );
		}

		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		if ( $post_id !== (int) ( $claims['post_id'] ?? 0 ) ) {
			return MutationResult::failure( 'invalid_post', 'Proposal is not bound to this post.' );
		}

		$rebuild = $this->propose(
			array(
				'post_id'       => $post_id,
				'mutation_type' => $type,
				'value'         => $input['value'] ?? $input['new_value'] ?? null,
				'target'        => isset( $input['target'] ) ? (string) $input['target'] : (string) ( $claims['target'] ?? 'auto' ),
			)
		);
		if ( ! $rebuild->ok() || ! $rebuild->proposal() instanceof MutationProposal ) {
			return $rebuild;
		}

		$proposal  = $rebuild->proposal();
		$ticket_fp = (string) ( $claims['fingerprint'] ?? '' );
		if ( ! $this->fingerprint->matches( $ticket_fp, $proposal->fingerprint() ) ) {
			return MutationResult::failure( 'stale_fingerprint', 'Proposal fingerprint does not match the current post state.' );
		}

		$claim_hash = (string) ( $claims['value_hash'] ?? '' );
		$live_hash  = ProposalTicket::value_hash( $proposal->new_value() );
		if ( $claim_hash === '' || ! hash_equals( $claim_hash, $live_hash ) ) {
			return MutationResult::failure( 'proposal_value_mismatch', 'Proposed value no longer matches the Preview ticket. Preview again before Apply.' );
		}

		return $this->apply( $proposal );
	}

	/**
	 * @param array<string, mixed> $input Input.
	 */
	public function apply_fresh( array $input ): MutationResult {
		if ( MutationProposal::rejects_client_meta_key( $input ) ) {
			return MutationResult::failure( 'client_meta_key_forbidden', 'Client meta keys are not allowed.' );
		}

		$type = MutationType::sanitize( (string) ( $input['mutation_type'] ?? $input['type'] ?? '' ) );
		if ( $type === '' || ! $this->is_ui_apply_type( $type ) ) {
			return MutationResult::failure( 'invalid_mutation_type', 'Mutation type is not in the allowlist.' );
		}

		$input['mutation_type'] = $type;
		$rebuild                = $this->propose( $input );
		if ( ! $rebuild->ok() || ! $rebuild->proposal() instanceof MutationProposal ) {
			return $rebuild;
		}

		return $this->apply( $rebuild->proposal() );
	}

	public function apply( MutationProposal $proposal ): MutationResult {
		if ( ! $this->env->security_helpers_available() ) {
			return MutationResult::failure( 'security_helpers_missing', 'Security helpers are unavailable.' );
		}
		if ( $this->env->never_modify_posts() ) {
			$message = function_exists( 'rsaip_existing_post_mutation_frozen_message' )
				? rsaip_existing_post_mutation_frozen_message()
				: 'This existing-post mutation is temporarily disabled while Safe Article Mode is enabled.';
			return MutationResult::failure( 'safe_article_mode', $message );
		}
		if ( ! $this->env->can_manage_plugin() || ! $this->env->can_edit_post( $proposal->post_id() ) ) {
			return MutationResult::failure( 'unauthorized', 'User is not authorized to mutate this post.' );
		}

		$type = MutationType::sanitize( $proposal->mutation_type() );
		if ( $type === '' || $type !== $proposal->mutation_type() ) {
			return MutationResult::failure( 'invalid_mutation_type', 'Mutation type is not in the allowlist.' );
		}

		$writer = $this->writers[ $type ] ?? null;
		if ( ! $writer instanceof MutationWriter ) {
			return MutationResult::failure( 'writer_missing', 'No writer registered for mutation type.' );
		}

		$post = $this->env->get_post( $proposal->post_id() );
		if ( null === $post ) {
			return MutationResult::failure( 'invalid_post', 'Post not found.' );
		}
		if ( ! in_array( (string) $post['post_type'], $this->env->supported_post_types(), true ) ) {
			return MutationResult::failure( 'invalid_post_type', 'Post type is not supported.' );
		}

		$owner = $this->ownership->resolve( $proposal->client_target() );
		if ( $owner === '' || $owner !== $proposal->owner() ) {
			return MutationResult::failure( 'ownership_override_forbidden', 'Client target cannot override server SEO ownership.' );
		}

		// Server meta keys only — ignore any keys on the proposal that are not ownership-approved.
		$allowed_keys = $this->ownership->allowed_meta_keys( $owner, $type );
		foreach ( $proposal->meta_keys() as $key ) {
			if ( ! in_array( $key, $allowed_keys, true ) ) {
				return MutationResult::failure( 'client_meta_key_forbidden', 'Client meta keys are not allowed.' );
			}
		}

		$current      = $writer->read_current( $proposal->post_id(), $owner );
		$content_hash = $this->fingerprint->content_hash( (string) $post['post_content'] );
		$live_fp      = $this->fingerprint->build(
			$proposal->post_id(),
			$type,
			$owner,
			$current,
			(string) $post['post_modified_gmt'],
			$content_hash
		);

		if ( ! $this->fingerprint->matches( $proposal->fingerprint(), $live_fp ) ) {
			return MutationResult::failure( 'stale_fingerprint', 'Proposal fingerprint does not match the current post state.' );
		}

		$new_value = $writer->normalize_new_value( $proposal->new_value() );
		if ( null === $new_value ) {
			return MutationResult::failure( 'invalid_value', 'Mutation value is invalid.' );
		}

		$snapshot                     = new MutationSnapshot();
		$snapshot->post_id            = $proposal->post_id();
		$snapshot->mutation_type      = $type;
		$snapshot->owner              = $owner;
		$snapshot->meta_keys          = $allowed_keys;
		$snapshot->old_value          = $current;
		$snapshot->new_value          = $new_value;
		$snapshot->fingerprint_before = $live_fp;
		$snapshot->post_modified_gmt  = (string) $post['post_modified_gmt'];
		$snapshot->content_hash       = $content_hash;
		$snapshot->user_id            = $this->env->current_user_id();
		$snapshot->status             = 'applying';
		$snapshot                     = $this->snapshots->save( $snapshot );

		$wrote = $writer->write( $proposal->post_id(), $owner, $new_value );
		if ( ! $wrote ) {
			$snapshot->status        = 'failed';
			$snapshot->error_code    = 'write_failed';
			$snapshot->error_message = 'Write operation failed.';
			$this->snapshots->update( $snapshot );
			return MutationResult::failure( 'write_failed', 'Write operation failed.' );
		}

		$snapshot->applied_at = gmdate( 'Y-m-d H:i:s' );
		$snapshot->status     = 'applied';
		$this->snapshots->update( $snapshot );

		$verify = $this->verify_after_write( $writer, $proposal->post_id(), $owner, $new_value );
		if ( ! $verify['ok'] ) {
			// Best-effort rollback of the single field; still report failure (never success).
			$writer->write( $proposal->post_id(), $owner, $current );
			$snapshot->status        = 'failed';
			$snapshot->error_code    = 'verification_mismatch';
			$snapshot->error_message = 'Read-back value did not match the intended mutation.';
			$this->snapshots->update( $snapshot );
			return MutationResult::failure( 'verification_mismatch', 'Read-back value did not match the intended mutation.' );
		}

		$post_after     = $this->env->get_post( $proposal->post_id() );
		$after_modified = $post_after ? (string) $post_after['post_modified_gmt'] : (string) $post['post_modified_gmt'];
		$after_hash     = $post_after
			? $this->fingerprint->content_hash( (string) $post_after['post_content'] )
			: $content_hash;

		$snapshot->fingerprint_after = $this->fingerprint->build(
			$proposal->post_id(),
			$type,
			$owner,
			$verify['actual'],
			$after_modified,
			$after_hash
		);
		$snapshot->verified_at = gmdate( 'Y-m-d H:i:s' );
		$snapshot->status      = 'verified';
		$this->snapshots->update( $snapshot );

		return MutationResult::success(
			'applied',
			'Mutation applied and verified.',
			$current,
			$new_value,
			true,
			$snapshot->id,
			$proposal
		);
	}

	public function verify( MutationProposal $proposal ): MutationResult {
		$type   = MutationType::sanitize( $proposal->mutation_type() );
		$writer = $this->writers[ $type ] ?? null;
		if ( ! $writer instanceof MutationWriter ) {
			return MutationResult::failure( 'writer_missing', 'No writer registered for mutation type.' );
		}
		$check = $this->verify_after_write( $writer, $proposal->post_id(), $proposal->owner(), $proposal->new_value() );
		if ( ! $check['ok'] ) {
			return MutationResult::failure( 'verification_mismatch', 'Read-back value did not match the intended mutation.' );
		}
		return MutationResult::success(
			'verified',
			'Value matches the intended mutation.',
			null,
			$check['actual'],
			true,
			0,
			$proposal
		);
	}

	public function undo( int $snapshot_id ): MutationResult {
		if ( ! $this->snapshots->supports_durable_undo() ) {
			return MutationResult::failure(
				'undo_unavailable',
				'Durable undo is not available until a persistent snapshot store is implemented.'
			);
		}
		$snapshot = $this->snapshots->find( $snapshot_id );
		if ( null === $snapshot || $snapshot->status !== 'verified' ) {
			return MutationResult::failure( 'undo_unavailable', 'No verified durable snapshot is available to undo.' );
		}
		// Durable undo path reserved for a later milestone with a persistent store.
		return MutationResult::failure(
			'undo_unavailable',
			'Durable undo is not available until a persistent snapshot store is implemented.'
		);
	}

	/**
	 * @param array<string, mixed> $input Input.
	 */
	private function authorize_read( array $input ): ?MutationResult {
		unset( $input );
		if ( ! $this->env->security_helpers_available() ) {
			return MutationResult::failure( 'security_helpers_missing', 'Security helpers are unavailable.' );
		}
		if ( ! $this->env->can_manage_plugin() ) {
			return MutationResult::failure( 'unauthorized', 'User is not authorized to mutate this post.' );
		}
		return null;
	}

	/**
	 * @param mixed $expected Expected.
	 * @return array{ok:bool,actual:mixed}
	 */
	private function verify_after_write( MutationWriter $writer, int $post_id, string $owner, $expected ): array {
		$actual = $writer->read_current( $post_id, $owner );
		return array(
			'ok'     => $writer->values_match( $expected, $actual ),
			'actual' => $actual,
		);
	}

	private function is_ui_apply_type( string $type ): bool {
		return in_array(
			$type,
			array( MutationType::TITLE, MutationType::META_DESCRIPTION, MutationType::KEYWORDS ),
			true
		);
	}
}
