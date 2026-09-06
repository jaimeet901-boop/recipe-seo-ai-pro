<?php
declare(strict_types=1);

/**
 * Contract for structured logging.
 *
 * Phase 2E: NullLogger is the default DI binding (no I/O). Legacy code does
 * not log via DI until an approved migration.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface LoggerInterface
 */
interface LoggerInterface {

	/** @param array<string, mixed> $context */
	public function debug( string $message, array $context = array() ): void;

	/** @param array<string, mixed> $context */
	public function info( string $message, array $context = array() ): void;

	/** @param array<string, mixed> $context */
	public function warning( string $message, array $context = array() ): void;

	/** @param array<string, mixed> $context */
	public function error( string $message, array $context = array() ): void;
}
