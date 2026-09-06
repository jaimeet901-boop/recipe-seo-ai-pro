<?php
declare(strict_types=1);

/**
 * Persistence contract for plugin settings (wp_options).
 *
 * Stores/retrieves the raw option array. Normalization and sanitization belong
 * in SettingsServiceInterface — not in the repository.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface SettingsRepositoryInterface
 */
interface SettingsRepositoryInterface {

	/**
	 * WordPress option name (must remain rsaip_settings).
	 */
	public function option_key(): string;

	/**
	 * Read the raw stored option array (may be empty or partial).
	 *
	 * @return array<string, mixed>
	 */
	public function read(): array;

	/**
	 * Persist a full settings array to the option.
	 *
	 * @param array<string, mixed> $settings Full settings payload.
	 */
	public function write( array $settings ): void;
}
