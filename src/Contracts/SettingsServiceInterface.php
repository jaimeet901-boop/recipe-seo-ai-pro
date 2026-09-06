<?php
declare(strict_types=1);

/**
 * Application-level settings API (defaults, normalize, sanitize, secrets).
 *
 * Unit-testable without WordPress admin UI. Legacy helpers and RSAIP_Admin
 * delegate here for backward compatibility.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface SettingsServiceInterface
 */
interface SettingsServiceInterface {

	/**
	 * Default settings (same keys as historical rsaip_default_settings()).
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array;

	/**
	 * Normalized settings (defaults + stored + type coercion).
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array;

	/**
	 * Merge partial updates into current settings and persist.
	 *
	 * @param array<string, mixed> $settings Partial settings.
	 */
	public function update( array $settings ): void;

	/**
	 * Settings API sanitize callback logic (Fix 1 merge + Fix 5 secrets).
	 *
	 * @param mixed $value Submitted option value.
	 * @return array<string, mixed>
	 */
	public function sanitize( $value ): array;

	/**
	 * @return string[]
	 */
	public function secret_keys(): array;

	public function secret_placeholder(): string;

	/**
	 * @param mixed $value Submitted secret field value.
	 */
	public function is_unchanged_secret_submission( $value ): bool;

	/**
	 * @param array<string, mixed> $settings Settings map.
	 */
	public function has_secret( array $settings, string $key ): bool;
}
