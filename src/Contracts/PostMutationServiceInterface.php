<?php
declare(strict_types=1);

/**
 * Contract for the centralized existing-post mutation service (Milestone 2).
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Contracts;

use RecipeSeoAiPro\Modules\PostMutation\MutationProposal;
use RecipeSeoAiPro\Modules\PostMutation\MutationResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface PostMutationServiceInterface
 */
interface PostMutationServiceInterface {

	/**
	 * Build a proposal (fingerprint + ownership) without writing.
	 *
	 * @param array<string, mixed> $input
	 */
	public function propose( array $input ): MutationResult;

	/**
	 * Apply a previously built proposal. Fail-closed; never mutates post_content.
	 */
	public function apply( MutationProposal $proposal ): MutationResult;

	/**
	 * Apply from a Preview ticket + live rebuild. Mutation type must be supplied by the caller (AJAX), not the client.
	 *
	 * @param array<string, mixed> $input Input.
	 */
	public function apply_from_preview( array $input, string $expected_type ): MutationResult;

	/**
	 * Same-request propose + apply. Not for UI Apply AJAX. Never used by Fix With AI.
	 *
	 * @param array<string, mixed> $input Input.
	 */
	public function apply_fresh( array $input ): MutationResult;

	/**
	 * Verify that the live field matches the intended value.
	 */
	public function verify( MutationProposal $proposal ): MutationResult;

	/**
	 * Attempt undo from a snapshot id. Foundation stub may fail safely.
	 */
	public function undo( int $snapshot_id ): MutationResult;
}
