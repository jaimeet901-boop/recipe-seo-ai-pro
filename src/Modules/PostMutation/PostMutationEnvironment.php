<?php
declare(strict_types=1);

/**
 * Environment port for PostMutation (WordPress or test fake).
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface PostMutationEnvironment
 */
interface PostMutationEnvironment {

	/**
	 * True when Safe Article Mode is ON or cannot be resolved (fail closed).
	 */
	public function never_modify_posts(): bool;

	/**
	 * True when plugin admin capability helper is available and current user passes it.
	 */
	public function can_manage_plugin(): bool;

	/**
	 * True when security helpers required for authorization are available.
	 */
	public function security_helpers_available(): bool;

	public function can_edit_post( int $post_id ): bool;

	public function current_user_id(): int;

	/**
	 * @return array{
	 *   ID:int,
	 *   post_type:string,
	 *   post_status:string,
	 *   post_title:string,
	 *   post_content:string,
	 *   post_modified_gmt:string
	 * }|null
	 */
	public function get_post( int $post_id ): ?array;

	/**
	 * @return list<string>
	 */
	public function supported_post_types(): array;

	public function is_rankmath_active(): bool;

	public function is_yoast_active(): bool;

	/**
	 * @return string Meta value or empty string.
	 */
	public function get_post_meta( int $post_id, string $meta_key ): string;

	public function update_post_meta( int $post_id, string $meta_key, string $meta_value ): bool;

	public function update_post_title( int $post_id, string $title ): bool;

	/**
	 * Secret used to HMAC-sign Preview→Apply tickets. Empty = tickets disabled (fail closed).
	 */
	public function proposal_signing_key(): string;
}
