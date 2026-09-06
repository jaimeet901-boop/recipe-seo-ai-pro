<?php
declare(strict_types=1);

/**
 * AI Content Brief Engine + Library module (Phases 3.1 / 3.2).
 *
 * Generation (3.1) and Library (3.2) are isolated from legacy RSAIP_AI.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentBrief;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\HttpClientInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Contracts\ModuleInterface;
use RecipeSeoAiPro\Contracts\SettingsServiceInterface;
use RecipeSeoAiPro\Core\Container;
use RecipeSeoAiPro\Modules\Ai\AiProviderRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ContentBriefModule
 */
final class ContentBriefModule implements ModuleInterface {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'ai_content_brief';
	}

	/**
	 * @inheritDoc
	 */
	public function register( Container $container ): void {
		$container->set(
			PromptBuilder::class,
			static function (): PromptBuilder {
				return new PromptBuilder();
			}
		);

		$container->set(
			ContentBriefBuilder::class,
			static function (): ContentBriefBuilder {
				return new ContentBriefBuilder();
			}
		);

		$container->set(
			BriefViewModel::class,
			static function ( Container $c ): BriefViewModel {
				return new BriefViewModel( $c->get( SettingsServiceInterface::class ) );
			}
		);

		$container->set(
			ContentBriefService::class,
			static function ( Container $c ): ContentBriefService {
				return new ContentBriefService(
					$c->get( AiProviderRegistry::class ),
					$c->get( SettingsServiceInterface::class ),
					$c->get( HttpClientInterface::class ),
					$c->get( LoggerInterface::class ),
					$c->get( EventDispatcherInterface::class ),
					$c->get( PromptBuilder::class ),
					$c->get( ContentBriefBuilder::class )
				);
			}
		);

		$container->set(
			BriefController::class,
			static function ( Container $c ): BriefController {
				return new BriefController(
					$c->get( ContentBriefService::class ),
					$c->get( BriefViewModel::class )
				);
			}
		);

		// Phase 3.2 — library stack (does not alter ContentBriefService).
		$container->set(
			BriefRepository::class,
			static function (): BriefRepository {
				return new BriefRepository();
			}
		);

		$container->set(
			BriefStorageService::class,
			static function (): BriefStorageService {
				return new BriefStorageService();
			}
		);

		$container->set(
			BriefManagerService::class,
			static function ( Container $c ): BriefManagerService {
				return new BriefManagerService(
					$c->get( BriefRepository::class ),
					$c->get( BriefStorageService::class ),
					$c->get( LoggerInterface::class ),
					$c->get( EventDispatcherInterface::class )
				);
			}
		);

		$container->set(
			BriefSearchService::class,
			static function ( Container $c ): BriefSearchService {
				return new BriefSearchService(
					$c->get( BriefRepository::class ),
					$c->get( BriefStorageService::class ),
					$c->get( CacheInterface::class ),
					$c->get( LoggerInterface::class )
				);
			}
		);

		$container->set(
			BriefExportService::class,
			static function ( Container $c ): BriefExportService {
				return new BriefExportService(
					$c->get( BriefManagerService::class ),
					$c->get( LoggerInterface::class )
				);
			}
		);

		$container->set(
			LibraryController::class,
			static function ( Container $c ): LibraryController {
				return new LibraryController(
					$c->get( BriefManagerService::class ),
					$c->get( BriefSearchService::class ),
					$c->get( BriefExportService::class ),
					$c->get( BriefViewModel::class )
				);
			}
		);
	}

	/**
	 * @inheritDoc
	 */
	public function boot( Container $container ): void {
		/** @var BriefController $controller */
		$controller = $container->get( BriefController::class );
		$controller->register_hooks();

		/** @var LibraryController $library */
		$library = $container->get( LibraryController::class );
		$library->register_hooks();
	}
}
