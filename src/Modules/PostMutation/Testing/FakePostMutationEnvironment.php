<?php
declare(strict_types=1);

/**
 * Test fake for PostMutationEnvironment.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation\Testing;

use RecipeSeoAiPro\Modules\PostMutation\PostMutationEnvironment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FakePostMutationEnvironment
 */
final class FakePostMutationEnvironment implements PostMutationEnvironment {

	public bool $safe_mode = true;
	public bool $security_helpers = true;
	public bool $can_manage = true;
	public bool $can_edit = true;
	public bool $rankmath = false;
	public bool $yoast = false;
	public int $user_id = 1;
	/** @var list<string> */
	public array $post_types = array( 'post' );

	/** @var array<int, array<string, mixed>> */
	public array $posts = array();

	/** @var array<int, array<string, string>> */
	public array $meta = array();

	/** Force next title/meta read to diverge after write (verification tests). */
	public bool $corrupt_reads = false;

	/** When true, reads become corrupt only after the first successful write. */
	public bool $corrupt_reads_after_write = false;

	private bool $wrote_once = false;

	public function never_modify_posts(): bool {
		return $this->safe_mode;
	}

	public function security_helpers_available(): bool {
		return $this->security_helpers;
	}

	public function can_manage_plugin(): bool {
		return $this->security_helpers && $this->can_manage;
	}

	public function can_edit_post( int $post_id ): bool {
		return $this->security_helpers && $this->can_edit && isset( $this->posts[ $post_id ] );
	}

	public function current_user_id(): int {
		return $this->user_id;
	}

	public function get_post( int $post_id ): ?array {
		if ( ! isset( $this->posts[ $post_id ] ) ) {
			return null;
		}
		$row = $this->posts[ $post_id ];
		return array(
			'ID'                => $post_id,
			'post_type'         => (string) ( $row['post_type'] ?? 'post' ),
			'post_status'       => (string) ( $row['post_status'] ?? 'publish' ),
			'post_title'        => (string) ( $row['post_title'] ?? '' ),
			'post_content'      => (string) ( $row['post_content'] ?? '' ),
			'post_modified_gmt' => (string) ( $row['post_modified_gmt'] ?? '2026-01-01 00:00:00' ),
		);
	}

	public function supported_post_types(): array {
		return $this->post_types;
	}

	public function is_rankmath_active(): bool {
		return $this->rankmath;
	}

	public function is_yoast_active(): bool {
		return $this->yoast;
	}

	public function get_post_meta( int $post_id, string $meta_key ): string {
		if ( $this->should_corrupt_reads() ) {
			return '__corrupt__';
		}
		return (string) ( $this->meta[ $post_id ][ $meta_key ] ?? '' );
	}

	public function update_post_meta( int $post_id, string $meta_key, string $meta_value ): bool {
		if ( ! isset( $this->meta[ $post_id ] ) ) {
			$this->meta[ $post_id ] = array();
		}
		$this->meta[ $post_id ][ $meta_key ] = $meta_value;
		if ( isset( $this->posts[ $post_id ] ) ) {
			$this->posts[ $post_id ]['post_modified_gmt'] = '2026-01-02 00:00:00';
		}
		$this->wrote_once = true;
		return true;
	}

	public function proposal_signing_key(): string {
		return 'rsaip-test-proposal-key';
	}

	public function update_post_title( int $post_id, string $title ): bool {
		if ( ! isset( $this->posts[ $post_id ] ) ) {
			return false;
		}
		$this->posts[ $post_id ]['post_title']        = $title;
		$this->posts[ $post_id ]['post_modified_gmt'] = '2026-01-02 00:00:00';
		$this->wrote_once                             = true;
		return true;
	}

	private function should_corrupt_reads(): bool {
		if ( $this->corrupt_reads ) {
			return true;
		}
		return $this->corrupt_reads_after_write && $this->wrote_once;
	}

	/**
	 * @param array<string, mixed> $post Post row.
	 */
	public function seed_post( int $post_id, array $post = array() ): void {
		$this->posts[ $post_id ] = array_merge(
			array(
				'post_type'         => 'post',
				'post_status'       => 'publish',
				'post_title'        => 'Original Title',
				'post_content'      => 'Hello content',
				'post_modified_gmt' => '2026-01-01 00:00:00',
			),
			$post
		);
	}
}
