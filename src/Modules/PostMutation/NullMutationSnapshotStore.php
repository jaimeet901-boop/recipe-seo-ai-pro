<?php
declare(strict_types=1);

/**
 * Null snapshot store — records nothing durable; undo always fails safely.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NullMutationSnapshotStore
 */
final class NullMutationSnapshotStore implements MutationSnapshotStore {

	public function save( MutationSnapshot $snapshot ): MutationSnapshot {
		$snapshot->id = 0;
		return $snapshot;
	}

	public function find( int $id ): ?MutationSnapshot {
		unset( $id );
		return null;
	}

	public function update( MutationSnapshot $snapshot ): bool {
		unset( $snapshot );
		return false;
	}

	public function supports_durable_undo(): bool {
		return false;
	}
}
