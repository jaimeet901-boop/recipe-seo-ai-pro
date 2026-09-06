<?php
declare(strict_types=1);

/**
 * WordPress options-backed settings repository.
 *
 * Option key is fixed to rsaip_settings for backward compatibility.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Settings;

use RecipeSeoAiPro\Contracts\SettingsRepositoryInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SettingsRepository
 */
final class SettingsRepository implements SettingsRepositoryInterface {

	/**
	 * @inheritDoc
	 */
	public function option_key(): string {
		return 'rsaip_settings';
	}

	/**
	 * @inheritDoc
	 */
	public function read(): array {
		$stored = get_option( $this->option_key(), array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * @inheritDoc
	 */
	public function write( array $settings ): void {
		update_option( $this->option_key(), $settings, false );
	}
}
