<?php
declare(strict_types=1);

/**
 * Keyword Workspace module (Phase 3.4).
 *
 * Management only — does not research or generate keywords.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Keywords;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Contracts\ModuleInterface;
use RecipeSeoAiPro\Core\Container;
use RecipeSeoAiPro\Modules\Projects\ProjectManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KeywordsModule
 */
final class KeywordsModule implements ModuleInterface {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'keyword_workspace';
	}

	/**
	 * @inheritDoc
	 */
	public function register( Container $container ): void {
		$container->set(
			KeywordRepository::class,
			static function (): KeywordRepository {
				return new KeywordRepository();
			}
		);

		$container->set(
			KeywordViewModel::class,
			static function (): KeywordViewModel {
				return new KeywordViewModel();
			}
		);

		$container->set(
			KeywordService::class,
			static function ( Container $c ): KeywordService {
				return new KeywordService(
					$c->get( KeywordRepository::class ),
					$c->get( CacheInterface::class ),
					$c->get( LoggerInterface::class )
				);
			}
		);

		$container->set(
			KeywordManager::class,
			static function ( Container $c ): KeywordManager {
				$projects = $c->has( ProjectManager::class ) ? $c->get( ProjectManager::class ) : null;
				return new KeywordManager(
					$c->get( KeywordRepository::class ),
					$c->get( KeywordService::class ),
					$c->get( LoggerInterface::class ),
					$c->get( EventDispatcherInterface::class ),
					$projects instanceof ProjectManager ? $projects : null
				);
			}
		);

		$container->set(
			KeywordController::class,
			static function ( Container $c ): KeywordController {
				return new KeywordController(
					$c->get( KeywordService::class ),
					$c->get( KeywordManager::class ),
					$c->get( KeywordViewModel::class )
				);
			}
		);
	}

	/**
	 * @inheritDoc
	 */
	public function boot( Container $container ): void {
		/** @var KeywordController $controller */
		$controller = $container->get( KeywordController::class );
		$controller->register_hooks();
	}
}
