<?php
declare(strict_types=1);

/**
 * Mutation operation result DTO.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MutationResult
 */
final class MutationResult {

	private bool $ok;
	private string $code;
	private string $message;
	/** @var mixed */
	private $old_value;
	/** @var mixed */
	private $new_value;
	private bool $verified;
	private int $snapshot_id;
	private ?MutationProposal $proposal;
	/** @var array<string, mixed> */
	private array $preview = array();

	/**
	 * @param mixed                $old_value Old value.
	 * @param mixed                $new_value New value.
	 * @param array<string, mixed> $preview   Preview payload extras.
	 */
	private function __construct(
		bool $ok,
		string $code,
		string $message,
		$old_value = null,
		$new_value = null,
		bool $verified = false,
		int $snapshot_id = 0,
		?MutationProposal $proposal = null,
		array $preview = array()
	) {
		$this->ok          = $ok;
		$this->code        = $code;
		$this->message     = $message;
		$this->old_value   = $old_value;
		$this->new_value   = $new_value;
		$this->verified    = $verified;
		$this->snapshot_id = $snapshot_id;
		$this->proposal    = $proposal;
		$this->preview     = $preview;
	}

	/**
	 * @param mixed $old_value Old.
	 * @param mixed $new_value New.
	 */
	public static function success(
		string $code,
		string $message,
		$old_value = null,
		$new_value = null,
		bool $verified = false,
		int $snapshot_id = 0,
		?MutationProposal $proposal = null
	): self {
		return new self( true, $code, $message, $old_value, $new_value, $verified, $snapshot_id, $proposal );
	}

	public static function failure( string $code, string $message ): self {
		return new self( false, $code, $message );
	}

	/**
	 * Attach Milestone 3A preview fields (no meta keys).
	 *
	 * @param array<string, mixed> $preview Preview fields.
	 */
	public function with_preview( array $preview ): self {
		$clone          = clone $this;
		$clone->preview = $preview;
		return $clone;
	}

	public function ok(): bool {
		return $this->ok;
	}

	public function code(): string {
		return $this->code;
	}

	public function message(): string {
		return $this->message;
	}

	/**
	 * @return mixed
	 */
	public function old_value() {
		return $this->old_value;
	}

	/**
	 * @return mixed
	 */
	public function new_value() {
		return $this->new_value;
	}

	public function verified(): bool {
		return $this->verified;
	}

	public function snapshot_id(): int {
		return $this->snapshot_id;
	}

	/**
	 * AJAX payload for Apply (never includes writable meta keys).
	 *
	 * @return array<string, mixed>
	 */
	public function to_apply_array(): array {
		$proposal = $this->proposal;
		$out      = array(
			'ok'            => $this->ok,
			'code'          => $this->code,
			'message'       => $this->message,
			'applied'       => $this->ok && $this->code === 'applied',
			'verified'      => $this->verified,
			'post_id'       => $proposal ? $proposal->post_id() : 0,
			'mutation_type' => $proposal ? $proposal->mutation_type() : '',
			'owner'         => $proposal ? $proposal->owner() : '',
			'owner_label'   => self::owner_label( $proposal ? $proposal->owner() : '' ),
			'old_value'     => $this->old_value,
			'new_value'     => $this->new_value,
			'apply_allowed' => false,
		);
		if ( ! $this->ok ) {
			return array(
				'ok'            => false,
				'code'          => $this->code,
				'message'       => $this->message,
				'applied'       => false,
				'apply_allowed' => false,
			);
		}
		return $out;
	}

	public function proposal(): ?MutationProposal {
		return $this->proposal;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$base = array(
			'ok'          => $this->ok,
			'code'        => $this->code,
			'message'     => $this->message,
			'old_value'   => $this->old_value,
			'new_value'   => $this->new_value,
			'verified'    => $this->verified,
			'snapshot_id' => $this->snapshot_id,
			'fingerprint' => $this->proposal ? $this->proposal->fingerprint() : '',
			'owner'       => $this->proposal ? $this->proposal->owner() : '',
			'type'        => $this->proposal ? $this->proposal->mutation_type() : '',
			'post_id'     => $this->proposal ? $this->proposal->post_id() : 0,
		);
		return array_merge( $base, $this->preview );
	}

	/**
	 * Preview/transport payload for Propose UI (never includes writable meta keys).
	 *
	 * @return array<string, mixed>
	 */
	public function to_preview_array(): array {
		$proposal = $this->proposal;
		$owner    = $proposal ? $proposal->owner() : (string) ( $this->preview['owner'] ?? '' );
		$type     = $proposal ? $proposal->mutation_type() : (string) ( $this->preview['mutation_type'] ?? '' );

		$out = array(
			'ok'                   => $this->ok,
			'code'                 => $this->code,
			'message'              => $this->message,
			'post_id'              => $proposal ? $proposal->post_id() : (int) ( $this->preview['post_id'] ?? 0 ),
			'mutation_type'        => $type,
			'owner'                => $owner,
			'owner_label'          => self::owner_label( $owner ),
			'original_value'       => $this->preview['original_value'] ?? $this->old_value,
			'proposed_value'       => $this->preview['proposed_value'] ?? $this->new_value,
			'normalized_value'     => $this->preview['normalized_value'] ?? $this->new_value,
			'fingerprint'          => $proposal ? $proposal->fingerprint() : (string) ( $this->preview['fingerprint'] ?? '' ),
			'post_modified_gmt'    => (string) ( $this->preview['post_modified_gmt'] ?? '' ),
			'proposed_at'          => (string) ( $this->preview['proposed_at'] ?? '' ),
			'expires_at'           => (string) ( $this->preview['expires_at'] ?? '' ),
			'safe_mode'            => array_key_exists( 'safe_mode', $this->preview ) ? (bool) $this->preview['safe_mode'] : true,
			'apply_allowed'        => array_key_exists( 'apply_allowed', $this->preview ) ? (bool) $this->preview['apply_allowed'] : false,
			'apply_blocked_reason' => (string) ( $this->preview['apply_blocked_reason'] ?? 'safe_article_mode' ),
			'proposal_ticket'      => (string) ( $this->preview['proposal_ticket'] ?? '' ),
		);

		if ( ! $this->ok ) {
			return array(
				'ok'                   => false,
				'code'                 => $this->code,
				'message'              => $this->message,
				'apply_allowed'        => false,
				'apply_blocked_reason' => (string) ( $this->preview['apply_blocked_reason'] ?? 'safe_article_mode' ),
				'safe_mode'            => array_key_exists( 'safe_mode', $this->preview ) ? (bool) $this->preview['safe_mode'] : true,
			);
		}

		return $out;
	}

	public static function owner_label( string $owner ): string {
		switch ( $owner ) {
			case SeoOwner::RANKMATH:
				return 'Rank Math';
			case SeoOwner::YOAST:
				return 'Yoast';
			case SeoOwner::RSAIP:
				return 'RSAIP';
			default:
				return $owner === '' ? '—' : $owner;
		}
	}
}
