<?php
declare(strict_types=1);

/**
 * Request + PHP-session reuse of a validated SeoOptimizationProposal (Milestone 5C).
 *
 * No DB/transient/options persistence. Fingerprint invalidates on title/content/mtime change.
 * Session allows Analyze and Generate AJAX calls in the same admin browser session to share
 * one OpenAI proposal without a second AI round-trip.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\SeoOptimization;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SeoOptimizationProposalStore
 */
final class SeoOptimizationProposalStore {

	private const SESSION_KEY = 'rsaip_seo_opt_proposals';

	/**
	 * @var array<int, array{fingerprint: string, proposal: SeoOptimizationProposal, context: SeoOptimizationContext}>
	 */
	private static array $entries = array();

	public static function clear(): void {
		self::$entries = array();
		self::ensure_session();
		if ( isset( $_SESSION[ self::SESSION_KEY ] ) ) {
			unset( $_SESSION[ self::SESSION_KEY ] );
		}
	}

	public static function fingerprint_for_post( int $post_id ): string {
		$post_id = max( 0, $post_id );
		if ( $post_id <= 0 || ! function_exists( 'get_post' ) ) {
			return '';
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}
		$title   = function_exists( 'get_the_title' ) ? (string) get_the_title( $post_id ) : (string) $post->post_title;
		$content = (string) $post->post_content;
		$mod     = (string) $post->post_modified_gmt;
		$focus   = '';
		if ( function_exists( 'rsaip_get_focus_keywords' ) ) {
			$kw = rsaip_get_focus_keywords( $post_id );
			if ( is_array( $kw ) && isset( $kw[0] ) ) {
				$focus = (string) $kw[0];
			}
		}
		$slice = function_exists( 'mb_substr' ) ? (string) mb_substr( $content, 0, 4000, 'UTF-8' ) : substr( $content, 0, 4000 );
		return hash( 'sha256', $post_id . '|' . $mod . '|' . $title . '|' . $focus . '|' . $slice );
	}

	public static function get( int $post_id, string $fingerprint ): ?array {
		if ( $fingerprint === '' ) {
			return null;
		}
		if ( isset( self::$entries[ $post_id ] ) ) {
			$entry = self::$entries[ $post_id ];
			if ( self::entry_matches( $entry, $fingerprint ) ) {
				return $entry;
			}
		}

		$from_session = self::get_from_session( $post_id, $fingerprint );
		if ( is_array( $from_session ) ) {
			self::$entries[ $post_id ] = $from_session;
			return $from_session;
		}
		return null;
	}

	public static function put( int $post_id, string $fingerprint, SeoOptimizationProposal $proposal, SeoOptimizationContext $context ): void {
		if ( $post_id <= 0 || $fingerprint === '' ) {
			return;
		}
		$entry = array(
			'fingerprint' => $fingerprint,
			'proposal'    => $proposal,
			'context'     => $context,
		);
		self::$entries[ $post_id ] = $entry;
		self::put_session( $post_id, $fingerprint, $proposal, $context );
	}

	/**
	 * @param array<string, mixed> $entry Entry.
	 */
	private static function entry_matches( array $entry, string $fingerprint ): bool {
		if ( (string) ( $entry['fingerprint'] ?? '' ) !== $fingerprint ) {
			return false;
		}
		return ( $entry['proposal'] instanceof SeoOptimizationProposal )
			&& ( $entry['context'] instanceof SeoOptimizationContext );
	}

	private static function ensure_session(): void {
		if ( PHP_SESSION_ACTIVE === session_status() ) {
			return;
		}
		if ( headers_sent() ) {
			return;
		}
		// Admin AJAX: quiet session for proposal reuse only.
		@session_start();
	}

	private static function get_from_session( int $post_id, string $fingerprint ): ?array {
		self::ensure_session();
		if ( PHP_SESSION_ACTIVE !== session_status() ) {
			return null;
		}
		$bag = $_SESSION[ self::SESSION_KEY ] ?? null;
		if ( ! is_array( $bag ) || ! isset( $bag[ $post_id ] ) || ! is_array( $bag[ $post_id ] ) ) {
			return null;
		}
		$row = $bag[ $post_id ];
		if ( (string) ( $row['fingerprint'] ?? '' ) !== $fingerprint ) {
			return null;
		}
		$proposal_arr = $row['proposal'] ?? null;
		$context_arr  = $row['context'] ?? null;
		if ( ! is_array( $proposal_arr ) || ! is_array( $context_arr ) ) {
			return null;
		}
		try {
			$builder = new SeoOptimizationContextBuilder();
			$context = $builder->from_server_array( $context_arr );
			if ( ! $context instanceof SeoOptimizationContext ) {
				return null;
			}
			// Re-validate before trust (candidates only; Apply still requires Preview ticket).
			$validator = new SeoOptimizationProposalValidator();
			$check     = $validator->validate( $proposal_arr, $context );
			if ( ! $check->ok() || ! $check->proposal() instanceof SeoOptimizationProposal ) {
				return null;
			}
			return array(
				'fingerprint' => $fingerprint,
				'proposal'    => $check->proposal(),
				'context'     => $context,
			);
		} catch ( \Throwable $e ) {
			unset( $e );
			return null;
		}
	}

	private static function put_session( int $post_id, string $fingerprint, SeoOptimizationProposal $proposal, SeoOptimizationContext $context ): void {
		self::ensure_session();
		if ( PHP_SESSION_ACTIVE !== session_status() ) {
			return;
		}
		if ( ! isset( $_SESSION[ self::SESSION_KEY ] ) || ! is_array( $_SESSION[ self::SESSION_KEY ] ) ) {
			$_SESSION[ self::SESSION_KEY ] = array();
		}
		$_SESSION[ self::SESSION_KEY ][ $post_id ] = array(
			'fingerprint' => $fingerprint,
			'proposal'    => $proposal->to_array(),
			'context'     => $context->to_server_array(),
		);
	}
}
