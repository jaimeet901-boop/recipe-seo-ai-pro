<?php
declare(strict_types=1);

/**
 * Lightweight dependency injection container.
 *
 * Phase 1: bindings and shared instances only. No auto-wiring, no reflection.
 * Legacy RSAIP_* classes are not resolved here yet; they remain manually
 * constructed in includes/class-rsaip-plugin.php.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Container
 *
 * Simple service locator / factory registry used by Module::register().
 */
final class Container {

	/** @var array<string, callable(self): mixed> */
	private array $factories = array();

	/** @var array<string, mixed> */
	private array $instances = array();

	/**
	 * Register a factory for an abstract id (class or interface name).
	 *
	 * @param string                $id      Binding id.
	 * @param callable(self): mixed $factory Factory receiving the container.
	 */
	public function set( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Bind a shared (singleton) instance directly.
	 *
	 * @param string $id       Binding id.
	 * @param mixed  $instance Concrete instance.
	 */
	public function instance( string $id, $instance ): void {
		$this->instances[ $id ] = $instance;
	}

	/**
	 * Whether a binding or shared instance exists.
	 */
	public function has( string $id ): bool {
		return array_key_exists( $id, $this->instances ) || array_key_exists( $id, $this->factories );
	}

	/**
	 * Resolve a binding. Factories are memoized as shared instances.
	 *
	 * @param string $id Binding id.
	 * @return mixed
	 *
	 * @throws \RuntimeException If the id is not bound.
	 */
	public function get( string $id ) {
		if ( array_key_exists( $id, $this->instances ) ) {
			return $this->instances[ $id ];
		}

		if ( ! array_key_exists( $id, $this->factories ) ) {
			throw new \RuntimeException( sprintf( 'RecipeSeoAiPro container: unknown binding "%s".', $id ) );
		}

		$instance                   = ( $this->factories[ $id ] )( $this );
		$this->instances[ $id ] = $instance;

		return $instance;
	}
}
