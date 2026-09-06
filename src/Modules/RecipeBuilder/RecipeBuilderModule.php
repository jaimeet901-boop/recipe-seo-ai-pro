<?php
declare(strict_types=1);

/**
 * Recipe Builder 2.0 module (Phase 5.1).
 *
 * Isolated from legacy Recipe Engine / recipe card rendering.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\RecipeBuilder;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Contracts\ModuleInterface;
use RecipeSeoAiPro\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeBuilderModule
 */
final class RecipeBuilderModule implements ModuleInterface {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'recipe_builder';
	}

	/**
	 * @inheritDoc
	 */
	public function register( Container $container ): void {
		$container->set(
			RecipeBuilderRepository::class,
			static function (): RecipeBuilderRepository {
				return new RecipeBuilderRepository();
			}
		);

		$container->set(
			RecipeSectionManager::class,
			static function ( Container $c ): RecipeSectionManager {
				return new RecipeSectionManager( $c->get( RecipeBuilderRepository::class ) );
			}
		);

		$container->set(
			RecipeIngredientManager::class,
			static function ( Container $c ): RecipeIngredientManager {
				return new RecipeIngredientManager( $c->get( RecipeBuilderRepository::class ) );
			}
		);

		$container->set(
			RecipeStepManager::class,
			static function ( Container $c ): RecipeStepManager {
				return new RecipeStepManager( $c->get( RecipeBuilderRepository::class ) );
			}
		);

		$container->set(
			RecipeBuilderService::class,
			static function ( Container $c ): RecipeBuilderService {
				return new RecipeBuilderService(
					$c->get( RecipeBuilderRepository::class ),
					$c->get( RecipeSectionManager::class ),
					$c->get( RecipeIngredientManager::class ),
					$c->get( RecipeStepManager::class ),
					$c->get( CacheInterface::class ),
					$c->get( LoggerInterface::class ),
					$c->get( EventDispatcherInterface::class )
				);
			}
		);

		$container->set(
			RecipeBuilderViewModel::class,
			static function ( Container $c ): RecipeBuilderViewModel {
				return new RecipeBuilderViewModel( $c->get( RecipeBuilderService::class ) );
			}
		);

		$container->set(
			RecipeBuilderController::class,
			static function ( Container $c ): RecipeBuilderController {
				return new RecipeBuilderController(
					$c->get( RecipeBuilderService::class ),
					$c->get( RecipeBuilderViewModel::class ),
					$c->get( RecipeIngredientManager::class )
				);
			}
		);
	}

	/**
	 * @inheritDoc
	 */
	public function boot( Container $container ): void {
		/** @var RecipeBuilderController $controller */
		$controller = $container->get( RecipeBuilderController::class );
		$controller->register_hooks();
	}
}
