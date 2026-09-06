<?php
declare(strict_types=1);

/**
 * AI Content Optimizer Engine module (Phase 4.1).
 *
 * Completely isolated from Article Generator, Content Brief, Keyword Workspace,
 * SERP Intelligence, Content Calendar, Recipe Engine, and legacy RSAIP_AI.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentOptimizer;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Contracts\ModuleInterface;
use RecipeSeoAiPro\Contracts\SettingsServiceInterface;
use RecipeSeoAiPro\Core\Container;
use RecipeSeoAiPro\Modules\Ai\AiProviderRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ContentOptimizerModule
 */
final class ContentOptimizerModule implements ModuleInterface {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'content_optimizer';
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
			RewriteEngine::class,
			static function (): RewriteEngine {
				return new RewriteEngine();
			}
		);

		$container->set(
			ContentOptimizerRepository::class,
			static function (): ContentOptimizerRepository {
				return new ContentOptimizerRepository();
			}
		);

		$container->set(
			ContentAnalyzer::class,
			static function ( Container $c ): ContentAnalyzer {
				return new ContentAnalyzer(
					$c->get( AiProviderRegistry::class ),
					$c->get( SettingsServiceInterface::class ),
					$c->get( LoggerInterface::class ),
					$c->get( PromptBuilder::class )
				);
			}
		);

		$container->set(
			OptimizationEngine::class,
			static function ( Container $c ): OptimizationEngine {
				return new OptimizationEngine(
					$c->get( AiProviderRegistry::class ),
					$c->get( SettingsServiceInterface::class ),
					$c->get( LoggerInterface::class ),
					$c->get( PromptBuilder::class ),
					$c->get( RewriteEngine::class )
				);
			}
		);

		$container->set(
			ContentOptimizerService::class,
			static function ( Container $c ): ContentOptimizerService {
				return new ContentOptimizerService(
					$c->get( ContentAnalyzer::class ),
					$c->get( OptimizationEngine::class ),
					$c->get( RewriteEngine::class ),
					$c->get( ContentOptimizerRepository::class ),
					$c->get( CacheInterface::class ),
					$c->get( LoggerInterface::class ),
					$c->get( EventDispatcherInterface::class )
				);
			}
		);

		$container->set(
			ContentOptimizerViewModel::class,
			static function ( Container $c ): ContentOptimizerViewModel {
				return new ContentOptimizerViewModel( $c->get( SettingsServiceInterface::class ) );
			}
		);

		$container->set(
			ContentOptimizerController::class,
			static function ( Container $c ): ContentOptimizerController {
				return new ContentOptimizerController(
					$c->get( ContentOptimizerService::class ),
					$c->get( ContentOptimizerViewModel::class )
				);
			}
		);
	}

	/**
	 * @inheritDoc
	 */
	public function boot( Container $container ): void {
		/** @var ContentOptimizerController $controller */
		$controller = $container->get( ContentOptimizerController::class );
		$controller->register_hooks();
	}
}
