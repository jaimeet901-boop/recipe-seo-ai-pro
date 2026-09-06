<?php
declare(strict_types=1);

/**
 * AI module — provider registry + AI Provider Hub.
 *
 * Feature modules keep using AiProviderRegistry / openai_compatible->complete().
 * Hub adapters, settings UI, logging, and failover live under Modules\Ai\Hub.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai;

use RecipeSeoAiPro\Contracts\AiProviderInterface;
use RecipeSeoAiPro\Core\Container;
use RecipeSeoAiPro\Modules\AbstractModule;
use RecipeSeoAiPro\Modules\Ai\Hub\AiRequestLogger;
use RecipeSeoAiPro\Modules\Ai\Hub\HubController;
use RecipeSeoAiPro\Modules\Ai\Hub\HubRepository;
use RecipeSeoAiPro\Modules\Ai\Hub\Http\AiHttpClient;
use RecipeSeoAiPro\Modules\Ai\Hub\ProviderManager;
use RecipeSeoAiPro\Modules\Ai\Providers\DisabledProvider;
use RecipeSeoAiPro\Modules\Ai\Providers\OpenAiCompatibleProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AiModule
 */
final class AiModule extends AbstractModule {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'ai';
	}

	/**
	 * @inheritDoc
	 */
	public function register( Container $container ): void {
		$container->set(
			AiHttpClient::class,
			static function (): AiHttpClient {
				return new AiHttpClient();
			}
		);

		$container->set(
			HubRepository::class,
			static function (): HubRepository {
				return new HubRepository();
			}
		);

		$container->set(
			AiRequestLogger::class,
			static function (): AiRequestLogger {
				return new AiRequestLogger();
			}
		);

		$container->set(
			ProviderManager::class,
			static function ( Container $c ): ProviderManager {
				$manager = new ProviderManager(
					$c->get( HubRepository::class ),
					$c->get( AiRequestLogger::class ),
					$c->get( AiHttpClient::class )
				);
				ProviderManager::set_instance( $manager );
				return $manager;
			}
		);

		$container->set(
			HubController::class,
			static function ( Container $c ): HubController {
				return new HubController( $c->get( ProviderManager::class ) );
			}
		);

		$container->set(
			OpenAiCompatibleProvider::class,
			static function ( Container $c ): OpenAiCompatibleProvider {
				return new OpenAiCompatibleProvider( $c->get( ProviderManager::class ) );
			}
		);

		$container->set(
			DisabledProvider::class,
			static function (): DisabledProvider {
				return new DisabledProvider();
			}
		);

		$container->set(
			AiProviderRegistry::class,
			static function ( Container $c ): AiProviderRegistry {
				$registry = new AiProviderRegistry();
				$registry->register( $c->get( OpenAiCompatibleProvider::class ) );
				$registry->register( $c->get( DisabledProvider::class ) );
				return $registry;
			}
		);

		$container->set(
			AiProviderInterface::class,
			static function ( Container $c ): AiProviderInterface {
				/** @var AiProviderRegistry $registry */
				$registry = $c->get( AiProviderRegistry::class );
				return $registry->resolve();
			}
		);
	}

	/**
	 * @inheritDoc
	 */
	public function boot( Container $container ): void {
		if ( class_exists( 'RSAIP_DB' ) ) {
			\RSAIP_DB::migrate_ai_logs_table();
		}

		/** @var HubController $controller */
		$controller = $container->get( HubController::class );
		$controller->register_hooks();

		// Ensure hub option is seeded from legacy settings once.
		$container->get( HubRepository::class )->all();
	}
}
