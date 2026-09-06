<?php
declare(strict_types=1);

/**
 * AI SEO Projects module (Phase 3.3).
 *
 * Organizes briefs and future SEO assets. Isolated from AI generation features.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Projects;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Contracts\ModuleInterface;
use RecipeSeoAiPro\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProjectsModule
 */
final class ProjectsModule implements ModuleInterface {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'seo_projects';
	}

	/**
	 * @inheritDoc
	 */
	public function register( Container $container ): void {
		$container->set(
			ProjectRepository::class,
			static function (): ProjectRepository {
				return new ProjectRepository();
			}
		);

		$container->set(
			ProjectViewModel::class,
			static function (): ProjectViewModel {
				return new ProjectViewModel();
			}
		);

		$container->set(
			ProjectService::class,
			static function ( Container $c ): ProjectService {
				return new ProjectService(
					$c->get( ProjectRepository::class ),
					$c->get( CacheInterface::class ),
					$c->get( LoggerInterface::class )
				);
			}
		);

		$container->set(
			ProjectManager::class,
			static function ( Container $c ): ProjectManager {
				return new ProjectManager(
					$c->get( ProjectRepository::class ),
					$c->get( ProjectService::class ),
					$c->get( LoggerInterface::class ),
					$c->get( EventDispatcherInterface::class )
				);
			}
		);

		$container->set(
			ProjectController::class,
			static function ( Container $c ): ProjectController {
				return new ProjectController(
					$c->get( ProjectService::class ),
					$c->get( ProjectManager::class ),
					$c->get( ProjectViewModel::class )
				);
			}
		);
	}

	/**
	 * @inheritDoc
	 */
	public function boot( Container $container ): void {
		/** @var ProjectController $controller */
		$controller = $container->get( ProjectController::class );
		$controller->register_hooks();
	}
}
