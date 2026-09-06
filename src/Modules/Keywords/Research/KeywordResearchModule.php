<?php
declare(strict_types=1);

/**
 * AI Keyword Research Engine module (Phase 3.5).
 *
 * Completely separate from Keyword Workspace / Projects / Content Brief modules.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Keywords\Research;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Contracts\ModuleInterface;
use RecipeSeoAiPro\Contracts\SettingsServiceInterface;
use RecipeSeoAiPro\Core\Container;
use RecipeSeoAiPro\Modules\Ai\AiProviderRegistry;
use RecipeSeoAiPro\Modules\Keywords\KeywordManager;
use RecipeSeoAiPro\Modules\Keywords\KeywordRepository;
use RecipeSeoAiPro\Modules\Projects\ProjectRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KeywordResearchModule
 */
final class KeywordResearchModule implements ModuleInterface {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'keyword_research';
	}

	/**
	 * @inheritDoc
	 */
	public function register( Container $container ): void {
		$container->set(
			KeywordResearchPromptBuilder::class,
			static function (): KeywordResearchPromptBuilder {
				return new KeywordResearchPromptBuilder();
			}
		);

		$container->set(
			KeywordResearchBuilder::class,
			static function (): KeywordResearchBuilder {
				return new KeywordResearchBuilder();
			}
		);

		$container->set(
			KeywordResearchService::class,
			static function ( Container $c ): KeywordResearchService {
				$manager = $c->has( KeywordManager::class ) ? $c->get( KeywordManager::class ) : null;
				return new KeywordResearchService(
					$c->get( AiProviderRegistry::class ),
					$c->get( SettingsServiceInterface::class ),
					$c->get( LoggerInterface::class ),
					$c->get( CacheInterface::class ),
					$c->get( EventDispatcherInterface::class ),
					$c->get( KeywordResearchPromptBuilder::class ),
					$c->get( KeywordResearchBuilder::class ),
					$c->get( KeywordRepository::class ),
					$c->get( ProjectRepository::class ),
					$manager instanceof KeywordManager ? $manager : null
				);
			}
		);

		$container->set(
			KeywordResearchViewModel::class,
			static function ( Container $c ): KeywordResearchViewModel {
				return new KeywordResearchViewModel(
					$c->get( SettingsServiceInterface::class ),
					$c->get( KeywordResearchService::class )
				);
			}
		);

		$container->set(
			KeywordResearchController::class,
			static function ( Container $c ): KeywordResearchController {
				return new KeywordResearchController(
					$c->get( KeywordResearchService::class ),
					$c->get( KeywordResearchViewModel::class )
				);
			}
		);
	}

	/**
	 * @inheritDoc
	 */
	public function boot( Container $container ): void {
		/** @var KeywordResearchController $controller */
		$controller = $container->get( KeywordResearchController::class );
		$controller->register_hooks();
	}
}
