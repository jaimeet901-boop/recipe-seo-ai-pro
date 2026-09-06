<?php
declare(strict_types=1);

/**
 * WordPress transient-backed CacheInterface implementation.
 *
 * Keys are passed through unchanged (callers own namespacing, e.g.
 * rsaip_dashboard_stats). Not wired into legacy modules in Phase 2E.
 *
 * Note: get_transient() returns false for both "missing" and stored false;
 * missing keys therefore resolve to $default (WP transient semantics).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Support\Cache;

use RecipeSeoAiPro\Contracts\CacheInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TransientCache
 */
final class TransientCache implements CacheInterface {

	/**
	 * @inheritDoc
	 */
	public function get( string $key, $default = null ) {
		$value = get_transient( $key );
		if ( false === $value ) {
			return $default;
		}
		return $value;
	}

	/**
	 * @inheritDoc
	 */
	public function set( string $key, $value, int $ttl = 0 ): bool {
		return (bool) set_transient( $key, $value, $ttl );
	}

	/**
	 * @inheritDoc
	 */
	public function delete( string $key ): bool {
		return (bool) delete_transient( $key );
	}
}
