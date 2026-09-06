<?php
declare(strict_types=1);

/**
 * AI Recipe Assistant module (Phase 5.2).
 *
 * Isolated from Recipe Builder source, legacy Recipe Engine, Optimizer, Brief, etc.
 * Reads/writes Builder recipes only through RecipeBuilderService DI.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\RecipeAI;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Contracts\ModuleInterface;
use RecipeSeoAiPro\Contracts\SettingsServiceInterface;
use RecipeSeoAiPro\Core\Container;
use RecipeSeoAiPro\Modules\Ai\AiProviderRegistry;
use RecipeSeoAiPro\Modules\RecipeBuilder\RecipeBuilderService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeAiModule
 */
final class RecipeAiModule implements ModuleInterface {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'recipe_ai';
	}

	/**
	 * @inheritDoc
	 */
	public function register( Container $container ): void {
		$container->set(
			AiJsonDecoder::class,
			static function (): AiJsonDecoder {
				return new AiJsonDecoder();
			}
		);

		$container->set(
			RecipePromptBuilder::class,
			static function (): RecipePromptBuilder {
				return new RecipePromptBuilder();
			}
		);

		$container->set(
			RecipeAiRepository::class,
			static function (): RecipeAiRepository {
				return new RecipeAiRepository();
			}
		);

		$assistant = static function ( string $class ) {
			return static function ( Container $c ) use ( $class ) {
				return new $class(
					$c->get( AiProviderRegistry::class ),
					$c->get( SettingsServiceInterface::class ),
					$c->get( LoggerInterface::class ),
					$c->get( RecipePromptBuilder::class ),
					$c->get( AiJsonDecoder::class )
				);
			};
		};

		$container->set( IngredientAssistant::class, $assistant( IngredientAssistant::class ) );
		$container->set( InstructionAssistant::class, $assistant( InstructionAssistant::class ) );
		$container->set( NutritionSuggestionEngine::class, $assistant( NutritionSuggestionEngine::class ) );
		$container->set( RecipeRewriteEngine::class, $assistant( RecipeRewriteEngine::class ) );
		$container->set( RecipeVariationEngine::class, $assistant( RecipeVariationEngine::class ) );

		$container->set(
			PostRecipeSource::class,
			static function (): PostRecipeSource {
				return new PostRecipeSource();
			}
		);

		$container->set(
			PostPickerService::class,
			static function (): PostPickerService {
				return new PostPickerService();
			}
		);

		$container->set(
			RecipeAiService::class,
			static function ( Container $c ): RecipeAiService {
				$builder = null;
				if ( $c->has( RecipeBuilderService::class ) ) {
					$builder = $c->get( RecipeBuilderService::class );
				}
				return new RecipeAiService(
					$c->get( RecipeAiRepository::class ),
					$c->get( IngredientAssistant::class ),
					$c->get( InstructionAssistant::class ),
					$c->get( NutritionSuggestionEngine::class ),
					$c->get( RecipeRewriteEngine::class ),
					$c->get( RecipeVariationEngine::class ),
					$c->get( RecipePromptBuilder::class ),
					$c->get( AiJsonDecoder::class ),
					$c->get( AiProviderRegistry::class ),
					$c->get( SettingsServiceInterface::class ),
					$c->get( CacheInterface::class ),
					$c->get( LoggerInterface::class ),
					$c->get( EventDispatcherInterface::class ),
					$builder,
					$c->get( PostRecipeSource::class )
				);
			}
		);

		$container->set(
			RecipeAiViewModel::class,
			static function ( Container $c ): RecipeAiViewModel {
				return new RecipeAiViewModel(
					$c->get( SettingsServiceInterface::class ),
					$c->get( RecipeAiService::class )
				);
			}
		);

		$container->set(
			RecipeAiController::class,
			static function ( Container $c ): RecipeAiController {
				return new RecipeAiController(
					$c->get( RecipeAiService::class ),
					$c->get( RecipeAiViewModel::class ),
					$c->get( PostPickerService::class )
				);
			}
		);
	}

	/**
	 * @inheritDoc
	 */
	public function boot( Container $container ): void {
		/** @var RecipeAiController $controller */
		$controller = $container->get( RecipeAiController::class );
		$controller->register_hooks();
	}
}
