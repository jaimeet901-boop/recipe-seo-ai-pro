<?php
declare(strict_types=1);

/**
 * Modern application bootstrap.
 *
 * Boots the DI container and modules alongside the legacy RSAIP_Plugin singleton.
 * Must not replace, wrap, or alter legacy behavior until an approved migration.
 *
 * Connection model:
 * - Legacy: recipe-seo-ai-pro.php → includes/* → RSAIP_Plugin::init()
 * - Modern: recipe-seo-ai-pro.php → Autoloader → Plugin::boot()
 * - Phase 2E: infrastructure services bound in DI only (not consumed by legacy).
 * - Phase 2F: REST foundation (rsaip/v1) + read-only /system/status.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\HttpClientInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Contracts\ModuleInterface;
use RecipeSeoAiPro\Core\Container;
use RecipeSeoAiPro\Http\Rest\RestBootstrap;
use RecipeSeoAiPro\Database\Repositories\BulkQueueRepository;
use RecipeSeoAiPro\Database\Repositories\LinkGraphRepository;
use RecipeSeoAiPro\Database\Repositories\LinkSuggestionRepository;
use RecipeSeoAiPro\Database\Repositories\PostMetricsRepository;
use RecipeSeoAiPro\Database\Repositories\RecipeVoteRepository;
use RecipeSeoAiPro\Modules\Ai\AiModule;
use RecipeSeoAiPro\Modules\Ai\ContentBrief\ContentBriefModule;
use RecipeSeoAiPro\Modules\Ai\ContentOptimizer\ContentOptimizerModule;
use RecipeSeoAiPro\Modules\Keywords\KeywordsModule;
use RecipeSeoAiPro\Modules\Keywords\Research\KeywordResearchModule;
use RecipeSeoAiPro\Modules\Projects\ProjectsModule;
use RecipeSeoAiPro\Modules\Seo\SerpIntelligence\SerpIntelligenceModule;
use RecipeSeoAiPro\Modules\Calendar\CalendarModule;
use RecipeSeoAiPro\Modules\Workspace\WorkspaceModule;
use RecipeSeoAiPro\Modules\RecipeBuilder\RecipeBuilderModule;
use RecipeSeoAiPro\Modules\RecipeAI\RecipeAiModule;
use RecipeSeoAiPro\Modules\Audit\AuditModule;
use RecipeSeoAiPro\Modules\Bulk\BulkModule;
use RecipeSeoAiPro\Modules\Dashboard\DashboardModule;
use RecipeSeoAiPro\Modules\Gsc\GscModule;
use RecipeSeoAiPro\Modules\ImageSeo\ImageSeoModule;
use RecipeSeoAiPro\Modules\InternalLinking\InternalLinkingModule;
use RecipeSeoAiPro\Modules\LinkGraph\LinkGraphModule;
use RecipeSeoAiPro\Modules\Performance\PerformanceModule;
use RecipeSeoAiPro\Modules\PostMutation\PostMutationModule;
use RecipeSeoAiPro\Modules\Queue\QueueModule;
use RecipeSeoAiPro\Modules\Recipe\RecipeModule;
use RecipeSeoAiPro\Modules\Reports\ReportsModule;
use RecipeSeoAiPro\Modules\Schema\SchemaModule;
use RecipeSeoAiPro\Modules\Settings\SettingsModule;
use RecipeSeoAiPro\Modules\Sitemap\SitemapModule;
use RecipeSeoAiPro\Modules\Templates\TemplatesModule;
use RecipeSeoAiPro\Modules\Workflow\WorkflowModule;
use RecipeSeoAiPro\Support\Cache\NullCache;
use RecipeSeoAiPro\Support\Cache\TransientCache;
use RecipeSeoAiPro\Support\Events\EventDispatcher;
use RecipeSeoAiPro\Support\Events\NullEventDispatcher;
use RecipeSeoAiPro\Support\Http\WpHttpClient;
use RecipeSeoAiPro\Support\Logging\NullLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * Entry point for the modern architecture skeleton.
 */
final class Plugin {

	private static ?self $instance = null;

	private Container $container;

	/** @var ModuleInterface[] */
	private array $modules = array();

	private bool $booted = false;

	private function __construct() {
		$this->container = new Container();
		$this->container->instance( Container::class, $this->container );
		$this->container->instance( self::class, $this );

		// Phase 2E: shared infrastructure (DI only — not wired into legacy modules).
		$this->register_infrastructure_services();

		// Phase 2B: table repositories (SQL only). Bound as shared instances.
		$this->container->instance( LinkGraphRepository::class, new LinkGraphRepository() );
		$this->container->instance( PostMetricsRepository::class, new PostMetricsRepository() );
		$this->container->instance( LinkSuggestionRepository::class, new LinkSuggestionRepository() );
		$this->container->instance( BulkQueueRepository::class, new BulkQueueRepository() );
		$this->container->instance( RecipeVoteRepository::class, new RecipeVoteRepository() );
	}

	/**
	 * Bind Event / Logger / Cache / HttpClient for future module use.
	 *
	 * Defaults are live implementations where safe (TransientCache, EventDispatcher,
	 * WpHttpClient) and NullLogger (no I/O). Null* alternates remain resolvable
	 * by concrete class. Legacy RSAIP_* code does not consume these yet.
	 */
	private function register_infrastructure_services(): void {
		$dispatcher = new EventDispatcher();
		$this->container->instance( EventDispatcherInterface::class, $dispatcher );
		$this->container->instance( EventDispatcher::class, $dispatcher );
		$this->container->instance( NullEventDispatcher::class, new NullEventDispatcher() );

		$logger = new NullLogger();
		$this->container->instance( LoggerInterface::class, $logger );
		$this->container->instance( NullLogger::class, $logger );

		$cache = new TransientCache();
		$this->container->instance( CacheInterface::class, $cache );
		$this->container->instance( TransientCache::class, $cache );
		$this->container->instance( NullCache::class, new NullCache() );

		$http = new WpHttpClient();
		$this->container->instance( HttpClientInterface::class, $http );
		$this->container->instance( WpHttpClient::class, $http );
	}

	/**
	 * Singleton accessor (does not conflict with RSAIP_Plugin::instance()).
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Expose the DI container for future facades and tests.
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Register modules and Phase 2F REST foundation. Idempotent.
	 *
	 * Legacy RSAIP_Plugin continues to own AJAX, menus, cron, and feature logic.
	 * REST adds only rsaip/v1 read-only foundation routes (no write ops).
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		foreach ( $this->module_classes() as $class ) {
			try {
				if ( ! class_exists( $class ) ) {
					continue;
				}
				/** @var ModuleInterface $module */
				$module = new $class();
				$module->register( $this->container );
				$this->modules[ $module::id() ] = $module;
			} catch ( \Throwable $e ) {
				// Skip broken module registration; do not abort remaining modules.
				continue;
			}
		}

		foreach ( $this->modules as $module ) {
			try {
				$module->boot( $this->container );
			} catch ( \Throwable $e ) {
				// Skip broken module boot; keep admin reachable.
				continue;
			}
		}

		try {
			$this->boot_rest_api();
		} catch ( \Throwable $e ) {
			// REST foundation is optional relative to admin uptime.
		}
	}

	/**
	 * Bind and hook RestBootstrap (namespace rsaip/v1).
	 */
	private function boot_rest_api(): void {
		$rest = new RestBootstrap( $this );
		$this->container->instance( RestBootstrap::class, $rest );
		$rest->register();
	}

	/**
	 * Whether the modern skeleton has completed boot.
	 */
	public function is_booted(): bool {
		return $this->booted;
	}

	/**
	 * @return array<string, ModuleInterface>
	 */
	public function modules(): array {
		return $this->modules;
	}

	/**
	 * Ordered list of module class names for Phase 1 registration.
	 *
	 * @return list<class-string<ModuleInterface>>
	 */
	private function module_classes(): array {
		return array(
			DashboardModule::class,
			LinkGraphModule::class,
			InternalLinkingModule::class,
			AuditModule::class,
			ImageSeoModule::class,
			GscModule::class,
			RecipeModule::class,
			SchemaModule::class,
			SitemapModule::class,
			PerformanceModule::class,
			AiModule::class,
			BulkModule::class,
			ReportsModule::class,
			SettingsModule::class,
			ContentBriefModule::class,
			ContentOptimizerModule::class,
			ProjectsModule::class,
			KeywordsModule::class,
			KeywordResearchModule::class,
			SerpIntelligenceModule::class,
			CalendarModule::class,
			WorkspaceModule::class,
			RecipeBuilderModule::class,
			RecipeAiModule::class,
			WorkflowModule::class,
			QueueModule::class,
			TemplatesModule::class,
			PostMutationModule::class,
		);
	}
}
