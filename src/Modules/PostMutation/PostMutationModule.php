<?php
declare(strict_types=1);

/**
 * PostMutation module (Milestone 2 foundation).
 *
 * Registers the service in DI. Does not wire production Apply call sites.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation;

use RecipeSeoAiPro\Contracts\PostMutationServiceInterface;
use RecipeSeoAiPro\Core\Container;
use RecipeSeoAiPro\Modules\AbstractModule;
use RecipeSeoAiPro\Modules\PostMutation\Writers\FocusKeywordWriter;
use RecipeSeoAiPro\Modules\PostMutation\Writers\KeywordsWriter;
use RecipeSeoAiPro\Modules\PostMutation\Writers\MetaDescriptionWriter;
use RecipeSeoAiPro\Modules\PostMutation\Writers\TitleWriter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PostMutationModule
 */
final class PostMutationModule extends AbstractModule {

	/**
	 * @inheritDoc
	 */
	public static function id(): string {
		return 'post_mutation';
	}

	/**
	 * @inheritDoc
	 */
	public function register( Container $container ): void {
		$container->set(
			PostMutationEnvironment::class,
			static function (): PostMutationEnvironment {
				return new WpPostMutationEnvironment();
			}
		);

		$container->set(
			SeoOwnershipResolver::class,
			static function ( Container $c ): SeoOwnershipResolver {
				return new SeoOwnershipResolver( $c->get( PostMutationEnvironment::class ) );
			}
		);

		$container->set(
			FieldFingerprint::class,
			static function (): FieldFingerprint {
				return new FieldFingerprint();
			}
		);

		$container->set(
			MutationSnapshotStore::class,
			static function (): MutationSnapshotStore {
				// No DB migration in Milestone 2 — null store (non-durable).
				return new NullMutationSnapshotStore();
			}
		);

		$container->set(
			TitleWriter::class,
			static function ( Container $c ): TitleWriter {
				return new TitleWriter( $c->get( PostMutationEnvironment::class ) );
			}
		);

		$container->set(
			MetaDescriptionWriter::class,
			static function ( Container $c ): MetaDescriptionWriter {
				return new MetaDescriptionWriter(
					$c->get( PostMutationEnvironment::class ),
					$c->get( SeoOwnershipResolver::class )
				);
			}
		);

		$container->set(
			FocusKeywordWriter::class,
			static function ( Container $c ): FocusKeywordWriter {
				return new FocusKeywordWriter(
					$c->get( PostMutationEnvironment::class ),
					$c->get( SeoOwnershipResolver::class )
				);
			}
		);

		$container->set(
			KeywordsWriter::class,
			static function ( Container $c ): KeywordsWriter {
				return new KeywordsWriter(
					$c->get( PostMutationEnvironment::class ),
					$c->get( SeoOwnershipResolver::class )
				);
			}
		);

		$container->set(
			PostMutationService::class,
			static function ( Container $c ): PostMutationService {
				return new PostMutationService(
					$c->get( PostMutationEnvironment::class ),
					$c->get( SeoOwnershipResolver::class ),
					$c->get( FieldFingerprint::class ),
					$c->get( MutationSnapshotStore::class ),
					array(
						$c->get( TitleWriter::class ),
						$c->get( MetaDescriptionWriter::class ),
						$c->get( FocusKeywordWriter::class ),
						$c->get( KeywordsWriter::class ),
					)
				);
			}
		);

		$container->set(
			PostMutationServiceInterface::class,
			static function ( Container $c ): PostMutationServiceInterface {
				return $c->get( PostMutationService::class );
			}
		);
	}

	/**
	 * @inheritDoc
	 */
	public function boot( Container $container ): void {
		// Milestone 4 wires Apply via RSAIP_AJAX → apply_from_preview(). Fix With AI stays blocked.
		unset( $container );
	}
}
