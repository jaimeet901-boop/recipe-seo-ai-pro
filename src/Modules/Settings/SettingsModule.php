<?php
declare(strict_types=1);

/**
 * Settings module (Phase 2A).
 *
 * Registers SettingsRepository + SettingsService in the DI container.
 * Legacy helpers and RSAIP_Admin::sanitize_settings() delegate here.
 * Admin UI / forms remain in RSAIP_Admin (unchanged markup).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Settings;

use RecipeSeoAiPro\Contracts\SettingsRepositoryInterface;
use RecipeSeoAiPro\Contracts\SettingsServiceInterface;
use RecipeSeoAiPro\Core\Container;
use RecipeSeoAiPro\Modules\AbstractModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SettingsModule
 */
final class SettingsModule extends AbstractModule {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'settings';
	}

	/**
	 * @inheritDoc
	 */
	public function register( Container $container ): void {
		$container->set(
			SettingsRepositoryInterface::class,
			static function (): SettingsRepositoryInterface {
				return new SettingsRepository();
			}
		);

		$container->set(
			SettingsServiceInterface::class,
			static function ( Container $c ): SettingsServiceInterface {
				return new SettingsService( $c->get( SettingsRepositoryInterface::class ) );
			}
		);

		$container->set(
			SettingsService::class,
			static function ( Container $c ): SettingsService {
				/** @var SettingsService $service */
				$service = $c->get( SettingsServiceInterface::class );
				return $service;
			}
		);

		$container->set(
			SettingsRepository::class,
			static function ( Container $c ): SettingsRepository {
				/** @var SettingsRepository $repo */
				$repo = $c->get( SettingsRepositoryInterface::class );
				return $repo;
			}
		);
	}
}
