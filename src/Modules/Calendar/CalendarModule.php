<?php
declare(strict_types=1);

/**
 * AI Content Calendar module (Phase 3.7).
 *
 * Isolated from Projects, Keyword Workspace, SERP, Content Brief, Article Generator.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Calendar;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Contracts\ModuleInterface;
use RecipeSeoAiPro\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CalendarModule
 */
final class CalendarModule implements ModuleInterface {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'content_calendar';
	}

	/**
	 * @inheritDoc
	 */
	public function register( Container $container ): void {
		$container->set(
			CalendarRepository::class,
			static function (): CalendarRepository {
				return new CalendarRepository();
			}
		);

		$container->set(
			CalendarService::class,
			static function ( Container $c ): CalendarService {
				return new CalendarService(
					$c->get( CalendarRepository::class ),
					$c->get( CacheInterface::class ),
					$c->get( LoggerInterface::class )
				);
			}
		);

		$container->set(
			CalendarManager::class,
			static function ( Container $c ): CalendarManager {
				return new CalendarManager(
					$c->get( CalendarRepository::class ),
					$c->get( CalendarService::class ),
					$c->get( LoggerInterface::class ),
					$c->get( EventDispatcherInterface::class )
				);
			}
		);

		$container->set(
			CalendarViewModel::class,
			static function ( Container $c ): CalendarViewModel {
				return new CalendarViewModel( $c->get( CalendarService::class ) );
			}
		);

		$container->set(
			CalendarController::class,
			static function ( Container $c ): CalendarController {
				return new CalendarController(
					$c->get( CalendarService::class ),
					$c->get( CalendarManager::class ),
					$c->get( CalendarViewModel::class )
				);
			}
		);
	}

	/**
	 * @inheritDoc
	 */
	public function boot( Container $container ): void {
		/** @var CalendarController $controller */
		$controller = $container->get( CalendarController::class );
		$controller->register_hooks();
	}
}
