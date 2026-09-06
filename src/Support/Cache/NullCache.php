<?php
declare(strict_types=1);

/**
 * No-op cache implementation (available as an alternate DI binding).
 *
 * Phase 2E binds TransientCache as the default CacheInterface.
 * Resolve NullCache::class explicitly for tests or silent mode.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Support\Cache;

use RecipeSeoAiPro\Contracts\CacheInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NullCache
 */
final class NullCache implements CacheInterface {

	/** @inheritDoc */
	public function get( string $key, $default = null ) {
		return $default;
	}

	/** @inheritDoc */
	public function set( string $key, $value, int $ttl = 0 ): bool {
		return true;
	}

	/** @inheritDoc */
	public function delete( string $key ): bool {
		return true;
	}
}
