<?php
declare(strict_types=1);

/**
 * Unified SEO Workspace module (Phase 4.2).
 *
 * Navigation & workflow hub only. Does not modify business logic, DB, AJAX, or AI engines.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Workspace;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Contracts\ModuleInterface;
use RecipeSeoAiPro\Core\Container;
use RecipeSeoAiPro\Modules\Projects\ProjectRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WorkspaceModule
 */
final class WorkspaceModule implements ModuleInterface {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'seo_workspace';
	}

	/**
	 * @inheritDoc
	 */
	public function register( Container $container ): void {
		$container->set(
			WorkspaceNavigation::class,
			static function (): WorkspaceNavigation {
				return new WorkspaceNavigation();
			}
		);

		$container->set(
			WorkspaceWidgets::class,
			static function ( Container $c ): WorkspaceWidgets {
				$projects = $c->has( ProjectRepository::class ) ? $c->get( ProjectRepository::class ) : null;
				return new WorkspaceWidgets(
					$c->get( WorkspaceNavigation::class ),
					$c->get( CacheInterface::class ),
					$c->get( LoggerInterface::class ),
					$projects instanceof ProjectRepository ? $projects : null
				);
			}
		);

		$container->set(
			WorkspaceDashboard::class,
			static function ( Container $c ): WorkspaceDashboard {
				$projects = $c->has( ProjectRepository::class ) ? $c->get( ProjectRepository::class ) : null;
				return new WorkspaceDashboard(
					$c->get( WorkspaceNavigation::class ),
					$c->get( WorkspaceWidgets::class ),
					$projects instanceof ProjectRepository ? $projects : null
				);
			}
		);

		$container->set(
			WorkspaceViewModel::class,
			static function ( Container $c ): WorkspaceViewModel {
				return new WorkspaceViewModel( $c->get( WorkspaceDashboard::class ) );
			}
		);

		$container->set(
			WorkspaceController::class,
			static function ( Container $c ): WorkspaceController {
				return new WorkspaceController( $c->get( WorkspaceViewModel::class ) );
			}
		);
	}

	/**
	 * @inheritDoc
	 */
	public function boot( Container $container ): void {
		/** @var WorkspaceController $controller */
		$controller = $container->get( WorkspaceController::class );
		$controller->register_hooks();
	}
}
