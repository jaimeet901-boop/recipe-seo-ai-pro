<?php
declare(strict_types=1);

/**
 * Project reads, statistics refresh, and dashboard assembly (Phase 3.3).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Projects;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProjectService
 */
final class ProjectService {

	public const ASSET_BRIEF   = 'brief';
	public const ASSET_KEYWORD = 'keyword';
	public const ASSET_ARTICLE = 'article';

	private ProjectRepository $repository;

	private CacheInterface $cache;

	private LoggerInterface $logger;

	public function __construct(
		ProjectRepository $repository,
		CacheInterface $cache,
		LoggerInterface $logger
	) {
		$this->repository = $repository;
		$this->cache      = $cache;
		$this->logger     = $logger;
	}

	public function ensure_tables(): void {
		if ( class_exists( 'RSAIP_DB' ) ) {
			\RSAIP_DB::migrate_projects_tables();
		}
	}

	/**
	 * @return ProjectDTO|\WP_Error
	 */
	public function get( int $id, bool $with_dashboard = true ) {
		$this->ensure_tables();
		$row = $this->repository->find_project( $id );
		if ( ! $row ) {
			return new \WP_Error( 'rsaip_project_missing', 'Project not found.', array( 'status' => 404 ) );
		}

		$dto = $this->dto_from_row( $row );
		if ( $with_dashboard ) {
			$dto->statistics      = $this->get_statistics( $id, true );
			$dto->members         = $this->present_members( $id );
			$dto->upcoming_tasks  = $this->repository->upcoming_tasks( $id, 8 );
			$dto->recent_activity = $this->repository->recent_activity( $id, 12 );
		}

		return $dto;
	}

	/**
	 * @param array<string, mixed> $args Search args.
	 * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
	 */
	public function list_projects( array $args ): array {
		$this->ensure_tables();
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
		$args['page']     = $page;
		$args['per_page'] = $per_page;

		$cache_key = 'rsaip_projects_list_' . md5( (string) wp_json_encode( $args ) );
		if ( empty( $args['bypass_cache'] ) ) {
			$cached = $this->cache->get( $cache_key, null );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$result = $this->repository->search_projects( $args );
		$items  = array();
		foreach ( $result['items'] as $row ) {
			$items[] = array(
				'id'              => (int) $row['id'],
				'name'            => (string) $row['name'],
				'description'     => (string) ( $row['description'] ?? '' ),
				'target_country'  => (string) ( $row['target_country'] ?? '' ),
				'language'        => (string) ( $row['language'] ?? '' ),
				'niche'           => (string) ( $row['niche'] ?? '' ),
				'status'          => (string) ( $row['status'] ?? 'active' ),
				'owner_user_id'   => (int) ( $row['owner_user_id'] ?? 0 ),
				'created_at'      => (string) ( $row['created_at'] ?? '' ),
				'updated_at'      => (string) ( $row['updated_at'] ?? '' ),
				'briefs_count'    => (int) ( $row['briefs_count'] ?? 0 ),
				'keywords_count'  => (int) ( $row['keywords_count'] ?? 0 ),
				'articles_count'  => (int) ( $row['articles_count'] ?? 0 ),
				'completion_pct'  => (int) ( $row['completion_pct'] ?? 0 ),
			);
		}

		$payload = array(
			'items'    => $items,
			'total'    => (int) $result['total'],
			'page'     => $page,
			'per_page' => $per_page,
		);
		$this->cache->set( $cache_key, $payload, 30 );
		return $payload;
	}

	/**
	 * Recompute and persist project statistics.
	 *
	 * Keywords / articles remain 0 until those modules attach assets.
	 *
	 * @return array<string, mixed>
	 */
	public function refresh_statistics( int $project_id ): array {
		$this->ensure_tables();

		$briefs   = $this->repository->count_assets( $project_id, self::ASSET_BRIEF );
		$keywords = $this->repository->count_assets( $project_id, self::ASSET_KEYWORD );
		$articles = $this->repository->count_assets( $project_id, self::ASSET_ARTICLE );

		// Lightweight planning score until calendar/publishing exist.
		$completion = 0;
		if ( $briefs > 0 ) {
			$completion += 40;
		}
		if ( $keywords > 0 ) {
			$completion += 30;
		}
		if ( $articles > 0 ) {
			$completion += 30;
		}
		$completion = max( 0, min( 100, $completion ) );

		$meta = array(
			'future_modules' => array(
				'keyword_research',
				'topic_clusters',
				'content_calendar',
				'competitor_analysis',
				'publishing',
				'analytics',
			),
		);

		$row = array(
			'project_id'      => $project_id,
			'briefs_count'    => $briefs,
			'keywords_count'  => $keywords,
			'articles_count'  => $articles,
			'completion_pct'  => $completion,
			'meta'            => (string) wp_json_encode( $meta ),
			'updated_at'      => \RSAIP_DB::now_gmt_sql(),
		);
		$this->repository->upsert_statistics( $row );
		$this->logger->info( 'projects.stats.refresh', array( 'project_id' => $project_id ) );

		return $this->normalize_stats( $row );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_statistics( int $project_id, bool $refresh_if_missing = false ): array {
		$stats = $this->repository->get_statistics( $project_id );
		if ( ! $stats && $refresh_if_missing ) {
			return $this->refresh_statistics( $project_id );
		}
		if ( ! $stats ) {
			return $this->normalize_stats(
				array(
					'project_id'     => $project_id,
					'briefs_count'   => 0,
					'keywords_count' => 0,
					'articles_count' => 0,
					'completion_pct' => 0,
				)
			);
		}
		return $this->normalize_stats( $stats );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function present_members( int $project_id ): array {
		$out = array();
		foreach ( $this->repository->list_members( $project_id ) as $row ) {
			$user_id = (int) ( $row['user_id'] ?? 0 );
			$user    = $user_id > 0 ? get_userdata( $user_id ) : false;
			$out[]   = array(
				'user_id'      => $user_id,
				'role'         => (string) ( $row['role'] ?? 'member' ),
				'display_name' => $user ? (string) $user->display_name : '',
				'user_email'   => $user ? (string) $user->user_email : '',
				'created_at'   => (string) ( $row['created_at'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $row DB project row.
	 */
	public function dto_from_row( array $row ): ProjectDTO {
		$dto                 = new ProjectDTO();
		$dto->id             = (int) ( $row['id'] ?? 0 );
		$dto->name           = (string) ( $row['name'] ?? '' );
		$dto->description    = (string) ( $row['description'] ?? '' );
		$dto->target_country = (string) ( $row['target_country'] ?? '' );
		$dto->language       = (string) ( $row['language'] ?? '' );
		$dto->niche          = (string) ( $row['niche'] ?? '' );
		$dto->status         = (string) ( $row['status'] ?? 'active' );
		$dto->owner_user_id  = (int) ( $row['owner_user_id'] ?? 0 );
		$dto->created_at     = (string) ( $row['created_at'] ?? '' );
		$dto->updated_at     = (string) ( $row['updated_at'] ?? '' );
		return $dto;
	}

	/**
	 * @param array<string, mixed> $stats Raw stats.
	 * @return array<string, mixed>
	 */
	private function normalize_stats( array $stats ): array {
		return array(
			'briefs_count'   => (int) ( $stats['briefs_count'] ?? 0 ),
			'keywords_count' => (int) ( $stats['keywords_count'] ?? 0 ),
			'articles_count' => (int) ( $stats['articles_count'] ?? 0 ),
			'completion_pct' => (int) ( $stats['completion_pct'] ?? 0 ),
			'updated_at'     => (string) ( $stats['updated_at'] ?? '' ),
		);
	}
}
