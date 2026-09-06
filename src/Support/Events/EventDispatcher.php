<?php
declare(strict_types=1);

/**
 * In-process application event dispatcher.
 *
 * Phase 2E: available via DI. Does not bridge to WordPress actions/filters and
 * is not listened to by legacy modules yet — zero runtime behavior change.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Support\Events;

use RecipeSeoAiPro\Contracts\EventDispatcherInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EventDispatcher
 */
final class EventDispatcher implements EventDispatcherInterface {

	/**
	 * @var array<string, list<callable>>
	 */
	private array $listeners = array();

	/**
	 * @inheritDoc
	 */
	public function listen( string $event_name, callable $listener ): void {
		if ( ! isset( $this->listeners[ $event_name ] ) ) {
			$this->listeners[ $event_name ] = array();
		}
		$this->listeners[ $event_name ][] = $listener;
	}

	/**
	 * @inheritDoc
	 *
	 * Listeners receive ( array $payload, string $event_name ).
	 * Exceptions from listeners are not swallowed — callers/tests control safety.
	 */
	public function dispatch( string $event_name, array $payload = array() ): void {
		if ( empty( $this->listeners[ $event_name ] ) ) {
			return;
		}

		foreach ( $this->listeners[ $event_name ] as $listener ) {
			$listener( $payload, $event_name );
		}
	}

	/**
	 * Whether any listener is registered for an event (introspection / tests).
	 */
	public function has_listeners( string $event_name ): bool {
		return ! empty( $this->listeners[ $event_name ] );
	}
}
