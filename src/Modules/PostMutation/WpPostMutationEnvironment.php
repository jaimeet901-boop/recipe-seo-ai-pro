<?php
declare(strict_types=1);

/**
 * WordPress-backed environment for PostMutationService.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WpPostMutationEnvironment
 */
final class WpPostMutationEnvironment implements PostMutationEnvironment {

	public function never_modify_posts(): bool {
		if ( ! function_exists( 'rsaip_never_modify_posts' ) ) {
			return true;
		}
		return (bool) rsaip_never_modify_posts();
	}

	public function security_helpers_available(): bool {
		return function_exists( 'rsaip_capability' )
			&& function_exists( 'current_user_can' )
			&& function_exists( 'rsaip_never_modify_posts' );
	}

	public function can_manage_plugin(): bool {
		if ( ! $this->security_helpers_available() ) {
			return false;
		}
		$cap = rsaip_capability();
		if ( ! is_string( $cap ) || $cap === '' ) {
			return false;
		}
		return (bool) current_user_can( $cap );
	}

	public function can_edit_post( int $post_id ): bool {
		if ( ! function_exists( 'current_user_can' ) || $post_id <= 0 ) {
			return false;
		}
		return (bool) current_user_can( 'edit_post', $post_id );
	}

	public function current_user_id(): int {
		return function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
	}

	public function get_post( int $post_id ): ?array {
		if ( ! function_exists( 'get_post' ) || $post_id <= 0 ) {
			return null;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}
		return array(
			'ID'                => (int) $post->ID,
			'post_type'         => (string) $post->post_type,
			'post_status'       => (string) $post->post_status,
			'post_title'        => (string) $post->post_title,
			'post_content'      => (string) $post->post_content,
			'post_modified_gmt' => (string) $post->post_modified_gmt,
		);
	}

	public function supported_post_types(): array {
		$settings = function_exists( 'rsaip_get_settings' ) ? rsaip_get_settings() : array();
		$types    = is_array( $settings ) ? ( $settings['auto_insert_post_types'] ?? array( 'post' ) ) : array( 'post' );
		if ( ! is_array( $types ) || $types === array() ) {
			return array( 'post' );
		}
		$out = array();
		foreach ( $types as $type ) {
			$key = is_string( $type ) ? sanitize_key( $type ) : '';
			if ( $key !== '' ) {
				$out[] = $key;
			}
		}
		return $out !== array() ? array_values( array_unique( $out ) ) : array( 'post' );
	}

	public function is_rankmath_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			if ( defined( 'ABSPATH' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
		}
		return function_exists( 'is_plugin_active' ) && is_plugin_active( 'seo-by-rank-math/rank-math.php' );
	}

	public function is_yoast_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			if ( defined( 'ABSPATH' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
		}
		return function_exists( 'is_plugin_active' ) && is_plugin_active( 'wordpress-seo/wp-seo.php' );
	}

	public function get_post_meta( int $post_id, string $meta_key ): string {
		if ( ! function_exists( 'get_post_meta' ) ) {
			return '';
		}
		$value = get_post_meta( $post_id, $meta_key, true );
		return is_string( $value ) ? $value : ( is_scalar( $value ) ? (string) $value : '' );
	}

	public function update_post_meta( int $post_id, string $meta_key, string $meta_value ): bool {
		if ( ! function_exists( 'update_post_meta' ) ) {
			return false;
		}
		$result = update_post_meta( $post_id, $meta_key, $meta_value );
		return false !== $result;
	}

	public function update_post_title( int $post_id, string $title ): bool {
		if ( ! function_exists( 'wp_update_post' ) ) {
			return false;
		}
		$result = wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $title,
			),
			true
		);
		return ! is_wp_error( $result ) && (int) $result > 0;
	}

	public function proposal_signing_key(): string {
		if ( ! function_exists( 'wp_salt' ) ) {
			return '';
		}
		return (string) wp_salt( 'auth' ) . '|rsaip-m4-proposal';
	}
}
