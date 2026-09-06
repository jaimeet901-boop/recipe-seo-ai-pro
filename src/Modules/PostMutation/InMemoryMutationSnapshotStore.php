<?php
declare(strict_types=1);

/**
 * In-memory snapshot store for tests / non-durable foundation use.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class InMemoryMutationSnapshotStore
 */
final class InMemoryMutationSnapshotStore implements MutationSnapshotStore {

	/** @var array<int, MutationSnapshot> */
	private array $rows = array();

	private int $seq = 0;

	public function save( MutationSnapshot $snapshot ): MutationSnapshot {
		if ( $snapshot->id <= 0 ) {
			$this->seq++;
			$snapshot->id = $this->seq;
		}
		if ( $snapshot->created_at === '' ) {
			$snapshot->created_at = gmdate( 'Y-m-d H:i:s' );
		}
		$this->rows[ $snapshot->id ] = $snapshot;
		return $snapshot;
	}

	public function find( int $id ): ?MutationSnapshot {
		return $this->rows[ $id ] ?? null;
	}

	public function update( MutationSnapshot $snapshot ): bool {
		if ( $snapshot->id <= 0 || ! isset( $this->rows[ $snapshot->id ] ) ) {
			return false;
		}
		$this->rows[ $snapshot->id ] = $snapshot;
		return true;
	}

	public function supports_durable_undo(): bool {
		return false;
	}

	/**
	 * @return list<MutationSnapshot>
	 */
	public function all(): array {
		return array_values( $this->rows );
	}
}
