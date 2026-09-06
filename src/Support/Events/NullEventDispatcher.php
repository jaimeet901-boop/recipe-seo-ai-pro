<?php
declare(strict_types=1);

/**
 * No-op event dispatcher (available as an alternate DI binding).
 *
 * Phase 2E binds EventDispatcher as the default EventDispatcherInterface.
 * Resolve NullEventDispatcher::class explicitly when a silent bus is needed.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Support\Events;

use RecipeSeoAiPro\Contracts\EventDispatcherInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NullEventDispatcher
 */
final class NullEventDispatcher implements EventDispatcherInterface {

	/** @inheritDoc */
	public function dispatch( string $event_name, array $payload = array() ): void {}

	/** @inheritDoc */
	public function listen( string $event_name, callable $listener ): void {}
}
