<?php
declare(strict_types=1);

/**
 * Short-lived session store for ArticleGeneration preview proposals (Milestone D).
 *
 * No WordPress post/meta writes. Bound to authenticated user + fingerprint + expiry.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\ArticleGeneration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ArticleGenerationProposalStore
 */
final class ArticleGenerationProposalStore {

	public const TTL_SECONDS = 3600;
	public const STATUS_AVAILABLE = 'available';
	public const STATUS_CONSUMING = 'consuming';
	public const STATUS_CONSUMED = 'consumed';

	private const SESSION_KEY = 'rsaip_article_gen_previews';

	/**
	 * @var array<string, array<string, mixed>>
	 */
	private static array $entries = array();

	public static function clear(): void {
		self::$entries = array();
		self::ensure_session();
		if ( isset( $_SESSION[ self::SESSION_KEY ] ) ) {
			unset( $_SESSION[ self::SESSION_KEY ] );
		}
	}

	/**
	 * Deterministic fingerprint for integrity checks (recomputed server-side).
	 */
	public static function fingerprint( int $user_id, ArticleGenerationContext $context, ArticleGenerationProposal $proposal, int $created_at ): string {
		$user_id = max( 0, $user_id );
		$payload = function_exists( 'wp_json_encode' )
			? wp_json_encode(
				array(
					'u' => $user_id,
					'c' => self::context_fingerprint_payload( $context ),
					'p' => $proposal->to_array(),
					't' => $created_at,
				)
			)
			: json_encode(
				array(
					'u' => $user_id,
					'c' => self::context_fingerprint_payload( $context ),
					'p' => $proposal->to_array(),
					't' => $created_at,
				)
			);
		if ( ! is_string( $payload ) || $payload === '' ) {
			$payload = (string) $user_id . '|' . $created_at;
		}
		return hash( 'sha256', $payload );
	}

	/**
	 * Context-only fingerprint (stale brief detection).
	 */
	public static function context_fingerprint( ArticleGenerationContext $context ): string {
		$payload = function_exists( 'wp_json_encode' )
			? wp_json_encode( self::context_fingerprint_payload( $context ) )
			: json_encode( self::context_fingerprint_payload( $context ) );
		return hash( 'sha256', is_string( $payload ) ? $payload : '' );
	}

	/**
	 * @return array{ok: bool, code?: string, message?: string, proposal_id?: string, fingerprint?: string, expires_at?: int, entry?: array<string, mixed>}
	 */
	public static function put(
		int $user_id,
		ArticleGenerationProposal $proposal,
		ArticleGenerationContext $context
	): array {
		$user_id = max( 0, $user_id );
		if ( $user_id <= 0 ) {
			return array(
				'ok'      => false,
				'code'    => 'rsaip_article_preview_unauthorized',
				'message' => 'Authenticated user required to store preview.',
			);
		}

		$created_at  = time();
		$expires_at  = $created_at + self::TTL_SECONDS;
		$fingerprint = self::fingerprint( $user_id, $context, $proposal, $created_at );
		$proposal_id = self::new_proposal_id();

		$entry = array(
			'proposal_id'         => $proposal_id,
			'user_id'             => $user_id,
			'fingerprint'         => $fingerprint,
			'context_fingerprint' => self::context_fingerprint( $context ),
			'created_at'          => $created_at,
			'expires_at'          => $expires_at,
			'status'              => self::STATUS_AVAILABLE,
			'draft_id'            => 0,
			'draft_created_at'    => 0,
			'seo_status'          => '',
			'proposal'            => $proposal->to_array(),
			'context'             => $context->to_server_array(),
		);

		$key = self::entry_key( $user_id, $proposal_id );
		self::$entries[ $key ] = $entry;
		self::put_session( $user_id, $proposal_id, $entry );

		return array(
			'ok'          => true,
			'proposal_id' => $proposal_id,
			'fingerprint' => $fingerprint,
			'expires_at'  => $expires_at,
			'entry'       => $entry,
		);
	}

	/**
	 * Retrieve and re-validate a stored preview. Never trusts client proposal body.
	 *
	 * @return array{ok: bool, code?: string, message?: string, proposal?: ArticleGenerationProposal, context?: ArticleGenerationContext, proposal_id?: string, fingerprint?: string, expires_at?: int}
	 */
	public static function get( int $user_id, string $proposal_id, string $fingerprint ): array {
		$user_id     = max( 0, $user_id );
		$proposal_id = preg_replace( '/[^a-zA-Z0-9_-]/', '', $proposal_id ) ?? '';
		$fingerprint = preg_replace( '/[^a-f0-9]/', '', strtolower( $fingerprint ) ) ?? '';

		if ( $user_id <= 0 || $proposal_id === '' || $fingerprint === '' ) {
			return array(
				'ok'      => false,
				'code'    => 'rsaip_article_preview_invalid',
				'message' => 'Invalid preview identifier.',
			);
		}

		$entry = self::load_entry( $user_id, $proposal_id );
		if ( ! is_array( $entry ) ) {
			return array(
				'ok'      => false,
				'code'    => 'rsaip_article_preview_not_found',
				'message' => 'Preview proposal not found.',
			);
		}

		if ( (int) ( $entry['user_id'] ?? 0 ) !== $user_id ) {
			return array(
				'ok'      => false,
				'code'    => 'rsaip_article_preview_unauthorized',
				'message' => 'Preview proposal is not available for this user.',
			);
		}

		$expires_at = (int) ( $entry['expires_at'] ?? 0 );
		$status     = (string) ( $entry['status'] ?? self::STATUS_AVAILABLE );

		// Consumed proposals remain retrievable for idempotent Create Draft (even if TTL elapsed).
		if ( $status !== self::STATUS_CONSUMED && $expires_at > 0 && time() > $expires_at ) {
			self::forget( $user_id, $proposal_id );
			return array(
				'ok'      => false,
				'code'    => 'preview_expired',
				'message' => 'Preview proposal has expired. Generate a new preview.',
			);
		}

		if ( (string) ( $entry['fingerprint'] ?? '' ) !== $fingerprint ) {
			return array(
				'ok'      => false,
				'code'    => 'rsaip_article_preview_fingerprint_mismatch',
				'message' => 'Preview proposal fingerprint is invalid.',
			);
		}

		$proposal_arr = $entry['proposal'] ?? null;
		$context_arr  = $entry['context'] ?? null;
		if ( ! is_array( $proposal_arr ) || ! is_array( $context_arr ) ) {
			return array(
				'ok'      => false,
				'code'    => 'rsaip_article_preview_invalid',
				'message' => 'Stored preview is invalid.',
			);
		}

		// Reject browser-style mutation authority if somehow present in stored arrays.
		foreach ( array( 'post_id', 'meta_key', 'seo_owner', 'owner', 'mutation_type', 'proposal_ticket' ) as $forbidden ) {
			if ( array_key_exists( $forbidden, $proposal_arr ) || array_key_exists( $forbidden, $context_arr ) ) {
				return array(
					'ok'      => false,
					'code'    => 'rsaip_article_preview_forbidden_field',
					'message' => 'Stored preview contains forbidden mutation fields.',
				);
			}
		}

		try {
			$builder = new ArticleGenerationContextBuilder();
			$context = $builder->from_brief( $context_arr );
			$validator = new ArticleGenerationProposalValidator();
			$check     = $validator->validate( $proposal_arr, $context );
			if ( ! $check->ok() || ! $check->proposal() instanceof ArticleGenerationProposal ) {
				return array(
					'ok'      => false,
					'code'    => $check->code() !== '' ? $check->code() : 'rsaip_article_preview_invalid',
					'message' => $check->message() !== '' ? $check->message() : 'Stored preview failed re-validation.',
				);
			}

			$created_at = (int) ( $entry['created_at'] ?? 0 );
			$expected   = self::fingerprint( $user_id, $context, $check->proposal(), $created_at );
			if ( ! hash_equals( $expected, $fingerprint ) ) {
				return array(
					'ok'      => false,
					'code'    => 'rsaip_article_preview_fingerprint_mismatch',
					'message' => 'Preview proposal fingerprint failed recomputation.',
				);
			}

			return array(
				'ok'          => true,
				'proposal_id' => $proposal_id,
				'fingerprint' => $fingerprint,
				'expires_at'  => $expires_at,
				'status'      => $status,
				'draft_id'    => (int) ( $entry['draft_id'] ?? 0 ),
				'seo_status'  => (string) ( $entry['seo_status'] ?? '' ),
				'proposal'    => $check->proposal(),
				'context'     => $context,
			);
		} catch ( \Throwable $e ) {
			unset( $e );
			return array(
				'ok'      => false,
				'code'    => 'rsaip_article_preview_invalid',
				'message' => 'Could not restore preview proposal.',
			);
		}
	}

	/**
	 * Atomically claim an available proposal for Create Draft.
	 *
	 * @return array{ok: bool, code?: string, message?: string, status?: string, draft_id?: int, seo_status?: string, proposal?: ArticleGenerationProposal, context?: ArticleGenerationContext, proposal_id?: string, fingerprint?: string, expires_at?: int}
	 */
	public static function claim_for_draft( int $user_id, string $proposal_id, string $fingerprint ): array {
		$got = self::get( $user_id, $proposal_id, $fingerprint );
		if ( empty( $got['ok'] ) ) {
			return $got;
		}

		$status = (string) ( $got['status'] ?? self::STATUS_AVAILABLE );
		if ( $status === self::STATUS_CONSUMED ) {
			return array(
				'ok'          => true,
				'code'        => 'proposal_already_consumed',
				'message'     => 'This preview already created a draft.',
				'status'      => self::STATUS_CONSUMED,
				'draft_id'    => (int) ( $got['draft_id'] ?? 0 ),
				'seo_status'  => (string) ( $got['seo_status'] ?? '' ),
				'proposal_id' => $proposal_id,
				'fingerprint' => $fingerprint,
				'expires_at'  => (int) ( $got['expires_at'] ?? 0 ),
				'proposal'    => $got['proposal'],
				'context'     => $got['context'],
			);
		}
		if ( $status === self::STATUS_CONSUMING ) {
			return array(
				'ok'      => false,
				'code'    => 'proposal_consuming',
				'message' => 'Create Draft is already in progress for this preview.',
				'status'  => self::STATUS_CONSUMING,
			);
		}

		$entry = self::load_entry( $user_id, $proposal_id );
		if ( ! is_array( $entry ) ) {
			return array(
				'ok'      => false,
				'code'    => 'rsaip_article_preview_not_found',
				'message' => 'Preview proposal not found.',
			);
		}
		// Re-check race.
		$live_status = (string) ( $entry['status'] ?? self::STATUS_AVAILABLE );
		if ( $live_status === self::STATUS_CONSUMED ) {
			return self::claim_for_draft( $user_id, $proposal_id, $fingerprint );
		}
		if ( $live_status === self::STATUS_CONSUMING ) {
			return array(
				'ok'      => false,
				'code'    => 'proposal_consuming',
				'message' => 'Create Draft is already in progress for this preview.',
				'status'  => self::STATUS_CONSUMING,
			);
		}

		$entry['status'] = self::STATUS_CONSUMING;
		self::save_entry( $user_id, $proposal_id, $entry );

		return array(
			'ok'          => true,
			'status'      => self::STATUS_CONSUMING,
			'proposal_id' => $proposal_id,
			'fingerprint' => $fingerprint,
			'expires_at'  => (int) ( $got['expires_at'] ?? 0 ),
			'proposal'    => $got['proposal'],
			'context'     => $got['context'],
		);
	}

	/**
	 * Mark proposal consumed after successful draft creation.
	 */
	public static function mark_consumed( int $user_id, string $proposal_id, int $draft_id, string $seo_status = 'applied' ): bool {
		$entry = self::load_entry( $user_id, $proposal_id );
		if ( ! is_array( $entry ) ) {
			return false;
		}
		$entry['status']           = self::STATUS_CONSUMED;
		$entry['draft_id']         = max( 0, $draft_id );
		$entry['draft_created_at'] = time();
		$entry['seo_status']       = $seo_status;
		self::save_entry( $user_id, $proposal_id, $entry );
		return true;
	}

	/**
	 * Release consuming lock after failure (proposal remains available if not expired).
	 */
	public static function release_claim( int $user_id, string $proposal_id ): void {
		$entry = self::load_entry( $user_id, $proposal_id );
		if ( ! is_array( $entry ) ) {
			return;
		}
		if ( (string) ( $entry['status'] ?? '' ) !== self::STATUS_CONSUMING ) {
			return;
		}
		$entry['status'] = self::STATUS_AVAILABLE;
		self::save_entry( $user_id, $proposal_id, $entry );
	}

	/**
	 * @param array<string, mixed> $entry Entry.
	 */
	private static function save_entry( int $user_id, string $proposal_id, array $entry ): void {
		$key = self::entry_key( $user_id, $proposal_id );
		self::$entries[ $key ] = $entry;
		self::put_session( $user_id, $proposal_id, $entry );
	}

	public static function forget( int $user_id, string $proposal_id ): void {
		$key = self::entry_key( $user_id, $proposal_id );
		unset( self::$entries[ $key ] );
		self::ensure_session();
		if ( PHP_SESSION_ACTIVE === session_status()
			&& isset( $_SESSION[ self::SESSION_KEY ][ $user_id ][ $proposal_id ] ) ) {
			unset( $_SESSION[ self::SESSION_KEY ][ $user_id ][ $proposal_id ] );
		}
	}

	/**
	 * Test helper: force-expire a stored preview without WordPress writes.
	 */
	public static function force_expire_for_tests( int $user_id, string $proposal_id ): void {
		$key   = self::entry_key( $user_id, $proposal_id );
		$entry = self::load_entry( $user_id, $proposal_id );
		if ( ! is_array( $entry ) ) {
			return;
		}
		$entry['expires_at'] = time() - 5;
		self::$entries[ $key ] = $entry;
		self::put_session( $user_id, $proposal_id, $entry );
	}

	/**
	 * @param array<string, mixed> $entry Entry.
	 */
	private static function put_session( int $user_id, string $proposal_id, array $entry ): void {
		self::ensure_session();
		if ( PHP_SESSION_ACTIVE !== session_status() ) {
			return;
		}
		if ( ! isset( $_SESSION[ self::SESSION_KEY ] ) || ! is_array( $_SESSION[ self::SESSION_KEY ] ) ) {
			$_SESSION[ self::SESSION_KEY ] = array();
		}
		if ( ! isset( $_SESSION[ self::SESSION_KEY ][ $user_id ] ) || ! is_array( $_SESSION[ self::SESSION_KEY ][ $user_id ] ) ) {
			$_SESSION[ self::SESSION_KEY ][ $user_id ] = array();
		}
		$_SESSION[ self::SESSION_KEY ][ $user_id ][ $proposal_id ] = $entry;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function load_entry( int $user_id, string $proposal_id ): ?array {
		$key = self::entry_key( $user_id, $proposal_id );
		if ( isset( self::$entries[ $key ] ) && is_array( self::$entries[ $key ] ) ) {
			return self::$entries[ $key ];
		}
		self::ensure_session();
		if ( PHP_SESSION_ACTIVE !== session_status() ) {
			return null;
		}
		$row = $_SESSION[ self::SESSION_KEY ][ $user_id ][ $proposal_id ] ?? null;
		if ( ! is_array( $row ) ) {
			return null;
		}
		self::$entries[ $key ] = $row;
		return $row;
	}

	private static function entry_key( int $user_id, string $proposal_id ): string {
		return $user_id . ':' . $proposal_id;
	}

	private static function new_proposal_id(): string {
		try {
			return bin2hex( random_bytes( 16 ) );
		} catch ( \Throwable $e ) {
			unset( $e );
			return hash( 'sha256', uniqid( 'rsaip_art_', true ) );
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function context_fingerprint_payload( ArticleGenerationContext $context ): array {
		$data = $context->to_server_array();
		// Stable subset — exclude volatile seo_seed blob noise where possible by using prompt-ish fields.
		return array(
			'title'                   => $data['title'] ?? '',
			'template'                => $data['template'] ?? '',
			'requested_word_count'    => $data['requested_word_count'] ?? 0,
			'canonical_recipe_entity' => $data['canonical_recipe_entity'] ?? '',
			'primary_focus_keyword'   => $data['primary_focus_keyword_hint'] ?? '',
			'search_intent'           => $data['search_intent_hint'] ?? '',
			'secondary_keywords'      => $data['secondary_keyword_hints'] ?? array(),
			'recipe_facts'            => $data['recipe_facts'] ?? array(),
			'content_gaps'            => $data['content_gap_hints'] ?? array(),
		);
	}

	private static function ensure_session(): void {
		if ( PHP_SESSION_ACTIVE === session_status() ) {
			return;
		}
		if ( headers_sent() ) {
			return;
		}
		@session_start();
	}
}
