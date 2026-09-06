<?php
declare(strict_types=1);

/**
 * SERP Intelligence Engine module (Phase 3.6).
 *
 * Isolated from Projects, Keyword Workspace, Content Brief, Keyword Research.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Seo\SerpIntelligence;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Contracts\ModuleInterface;
use RecipeSeoAiPro\Contracts\SettingsServiceInterface;
use RecipeSeoAiPro\Core\Container;
use RecipeSeoAiPro\Modules\Ai\AiProviderRegistry;
use RecipeSeoAiPro\Modules\Ai\ContentBrief\BriefRepository;
use RecipeSeoAiPro\Modules\Keywords\KeywordRepository;
use RecipeSeoAiPro\Modules\Projects\ProjectManager;
use RecipeSeoAiPro\Modules\Projects\ProjectRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SerpIntelligenceModule
 */
final class SerpIntelligenceModule implements ModuleInterface {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'serp_intelligence';
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
			SerpAnalyzer::class,
			static function (): SerpAnalyzer {
				return new SerpAnalyzer();
			}
		);

		$container->set(
			SerpIntelligenceRepository::class,
			static function (): SerpIntelligenceRepository {
				return new SerpIntelligenceRepository();
			}
		);

		$container->set(
			SerpIntelligenceService::class,
			static function ( Container $c ): SerpIntelligenceService {
				$keywords = $c->has( KeywordRepository::class ) ? $c->get( KeywordRepository::class ) : null;
				$briefs   = $c->has( BriefRepository::class ) ? $c->get( BriefRepository::class ) : null;
				$pm       = $c->has( ProjectManager::class ) ? $c->get( ProjectManager::class ) : null;

				return new SerpIntelligenceService(
					$c->get( AiProviderRegistry::class ),
					$c->get( SettingsServiceInterface::class ),
					$c->get( LoggerInterface::class ),
					$c->get( CacheInterface::class ),
					$c->get( EventDispatcherInterface::class ),
					$c->get( PromptBuilder::class ),
					$c->get( SerpAnalyzer::class ),
					$c->get( SerpIntelligenceRepository::class ),
					$c->get( ProjectRepository::class ),
					$keywords instanceof KeywordRepository ? $keywords : null,
					$briefs instanceof BriefRepository ? $briefs : null,
					$pm instanceof ProjectManager ? $pm : null
				);
			}
		);

		$container->set(
			SerpIntelligenceViewModel::class,
			static function ( Container $c ): SerpIntelligenceViewModel {
				return new SerpIntelligenceViewModel(
					$c->get( SettingsServiceInterface::class ),
					$c->get( SerpIntelligenceService::class )
				);
			}
		);

		$container->set(
			SerpIntelligenceController::class,
			static function ( Container $c ): SerpIntelligenceController {
				return new SerpIntelligenceController(
					$c->get( SerpIntelligenceService::class ),
					$c->get( SerpIntelligenceViewModel::class ),
					$c->get( SerpAnalyzer::class )
				);
			}
		);
	}

	/**
	 * @inheritDoc
	 */
	public function boot( Container $container ): void {
		/** @var SerpIntelligenceController $controller */
		$controller = $container->get( SerpIntelligenceController::class );
		$controller->register_hooks();
	}
}
