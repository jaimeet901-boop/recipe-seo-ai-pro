<?php
declare(strict_types=1);

/**
 * Snapshot DTO for a single-field mutation.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MutationSnapshot
 */
final class MutationSnapshot {

	public int $id = 0;
	public int $post_id = 0;
	public string $mutation_type = '';
	public string $owner = '';
	/** @var list<string> */
	public array $meta_keys = array();
	/** @var mixed */
	public $old_value = null;
	/** @var mixed */
	public $new_value = null;
	public string $fingerprint_before = '';
	public string $fingerprint_after = '';
	public string $post_modified_gmt = '';
	public string $content_hash = '';
	public int $user_id = 0;
	public string $status = 'proposed';
	public string $error_code = '';
	public string $error_message = '';
	public string $created_at = '';
	public string $applied_at = '';
	public string $verified_at = '';

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'                  => $this->id,
			'post_id'             => $this->post_id,
			'mutation_type'       => $this->mutation_type,
			'owner'               => $this->owner,
			'meta_keys'           => $this->meta_keys,
			'old_value'           => $this->old_value,
			'new_value'           => $this->new_value,
			'fingerprint_before'  => $this->fingerprint_before,
			'fingerprint_after'   => $this->fingerprint_after,
			'post_modified_gmt'   => $this->post_modified_gmt,
			'content_hash'        => $this->content_hash,
			'user_id'             => $this->user_id,
			'status'              => $this->status,
			'error_code'          => $this->error_code,
			'error_message'       => $this->error_message,
			'created_at'          => $this->created_at,
			'applied_at'          => $this->applied_at,
			'verified_at'         => $this->verified_at,
		);
	}
}
