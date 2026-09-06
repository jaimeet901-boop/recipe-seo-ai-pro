<?php
declare(strict_types=1);

/**
 * No-op logger (default LoggerInterface binding in Phase 2E).
 *
 * Safe default: no I/O, no debug.log writes. Legacy modules are not wired
 * to LoggerInterface yet — zero behavior change until an approved migration.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Support\Logging;

use RecipeSeoAiPro\Contracts\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NullLogger
 */
final class NullLogger implements LoggerInterface {

	/** @inheritDoc */
	public function debug( string $message, array $context = array() ): void {}

	/** @inheritDoc */
	public function info( string $message, array $context = array() ): void {}

	/** @inheritDoc */
	public function warning( string $message, array $context = array() ): void {}

	/** @inheritDoc */
	public function error( string $message, array $context = array() ): void {}
}
