<?php
declare(strict_types=1);

/**
 * Contract for the application event bus.
 *
 * Phase 2E: EventDispatcher is the default DI binding. WordPress actions/filters
 * remain the sole runtime event mechanism via legacy RSAIP_* classes until an
 * approved migration; this bus does not auto-bridge to do_action.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface EventDispatcherInterface
 */
interface EventDispatcherInterface {

	/**
	 * @param string               $event_name Event name (e.g. rsaip.audit.completed).
	 * @param array<string, mixed> $payload    Event payload.
	 */
	public function dispatch( string $event_name, array $payload = array() ): void;

	/**
	 * @param string   $event_name Event name.
	 * @param callable $listener   Listener callback.
	 */
	public function listen( string $event_name, callable $listener ): void;
}
