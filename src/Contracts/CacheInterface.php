<?php
declare(strict_types=1);

/**
 * Contract for application caching.
 *
 * Phase 2E: TransientCache is the default DI binding. Legacy code still uses
 * get_transient/set_transient directly until an approved migration.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface CacheInterface
 */
interface CacheInterface {

	/**
	 * @param string $key Cache key.
	 * @param mixed  $default Default when missing.
	 * @return mixed
	 */
	public function get( string $key, $default = null );

	/**
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Seconds.
	 */
	public function set( string $key, $value, int $ttl = 0 ): bool;

	/**
	 * @param string $key Cache key.
	 */
	public function delete( string $key ): bool;
}
