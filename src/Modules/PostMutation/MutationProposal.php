<?php
declare(strict_types=1);

/**
 * Immutable mutation proposal DTO.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MutationProposal
 */
final class MutationProposal {

	private int $post_id;
	private string $mutation_type;
	private string $owner;
	/** @var mixed */
	private $new_value;
	private string $fingerprint;
	private int $user_id;
	/** @var list<string> */
	private array $meta_keys;
	private string $client_target;

	/**
	 * @param mixed        $new_value New value.
	 * @param list<string> $meta_keys Server-resolved meta keys only.
	 */
	public function __construct(
		int $post_id,
		string $mutation_type,
		string $owner,
		$new_value,
		string $fingerprint,
		int $user_id = 0,
		array $meta_keys = array(),
		string $client_target = 'auto'
	) {
		$this->post_id         = $post_id;
		$this->mutation_type   = $mutation_type;
		$this->owner           = $owner;
		$this->new_value       = $new_value;
		$this->fingerprint     = $fingerprint;
		$this->user_id         = $user_id;
		$this->meta_keys       = array_values( $meta_keys );
		$this->client_target   = $client_target;
	}

	public function post_id(): int {
		return $this->post_id;
	}

	public function mutation_type(): string {
		return $this->mutation_type;
	}

	public function owner(): string {
		return $this->owner;
	}

	/**
	 * @return mixed
	 */
	public function new_value() {
		return $this->new_value;
	}

	public function fingerprint(): string {
		return $this->fingerprint;
	}

	public function user_id(): int {
		return $this->user_id;
	}

	/**
	 * @return list<string>
	 */
	public function meta_keys(): array {
		return $this->meta_keys;
	}

	public function client_target(): string {
		return $this->client_target;
	}

	/**
	 * Reject any attempt to smuggle client meta keys into a proposal payload.
	 *
	 * @param array<string, mixed> $input Raw input.
	 */
	public static function rejects_client_meta_key( array $input ): bool {
		foreach ( array( 'meta_key', 'meta_keys', 'key', 'keys' ) as $banned ) {
			if ( array_key_exists( $banned, $input ) ) {
				return true;
			}
		}
		return false;
	}
}
