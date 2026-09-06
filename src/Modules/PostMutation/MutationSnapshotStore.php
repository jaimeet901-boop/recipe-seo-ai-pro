<?php
declare(strict_types=1);

/**
 * Snapshot persistence contract (no DB migration in Milestone 2).
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface MutationSnapshotStore
 */
interface MutationSnapshotStore {

	public function save( MutationSnapshot $snapshot ): MutationSnapshot;

	public function find( int $id ): ?MutationSnapshot;

	public function update( MutationSnapshot $snapshot ): bool;

	/**
	 * Whether this store can support durable undo across requests.
	 */
	public function supports_durable_undo(): bool;
}
