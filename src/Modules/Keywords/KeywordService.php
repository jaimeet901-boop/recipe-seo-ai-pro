<?php
declare(strict_types=1);

/**
 * Keyword Workspace reads / views (Phase 3.4).
 *
 * Does not generate or research keywords.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Keywords;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KeywordService
 */
final class KeywordService {

	private KeywordRepository $repository;

	private CacheInterface $cache;

	private LoggerInterface $logger;

	public function __construct(
		KeywordRepository $repository,
		CacheInterface $cache,
		LoggerInterface $logger
	) {
		$this->repository = $repository;
		$this->cache      = $cache;
		$this->logger     = $logger;
	}

	public function ensure_tables(): void {
		if ( class_exists( 'RSAIP_DB' ) ) {
			\RSAIP_DB::migrate_keywords_tables();
		}
	}

	/**
	 * @return KeywordDTO|\WP_Error
	 */
	public function get( int $id, bool $with_details = true ) {
		$this->ensure_tables();
		$row = $this->repository->find( $id );
		if ( ! $row ) {
			return new \WP_Error( 'rsaip_keyword_missing', 'Keyword not found.', array( 'status' => 404 ) );
		}
		$dto = $this->dto_from_row( $row );
		if ( $with_details ) {
			$dto->notes   = $this->repository->list_notes( $id, 30 );
			$dto->history = $this->repository->timeline( 0, $id, 40 );
		}
		return $dto;
	}

	/**
	 * @param array<string, mixed> $args Search args.
	 * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
	 */
	public function search( array $args ): array {
		$this->ensure_tables();
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 25 ) ) );
		$args['page']     = $page;
		$args['per_page'] = $per_page;

		$key = 'rsaip_kw_list_' . md5( (string) wp_json_encode( $args ) );
		if ( empty( $args['bypass_cache'] ) ) {
			$cached = $this->cache->get( $key, null );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$result = $this->repository->search( $args );
		$items  = array();
		foreach ( $result['items'] as $row ) {
			$items[] = $this->dto_from_row( $row )->to_array();
		}

		$payload = array(
			'items'    => $items,
			'total'    => (int) $result['total'],
			'page'     => $page,
			'per_page' => $per_page,
		);
		$this->cache->set( $key, $payload, 20 );
		$this->logger->debug( 'keywords.search', array( 'total' => $payload['total'] ) );
		return $payload;
	}

	/**
	 * @return array{clusters: list<array<string, mixed>>, groups: array<string, list<array<string, mixed>>>}
	 */
	public function cluster_view( int $project_id ): array {
		$this->ensure_tables();
		$rows   = $this->repository->cluster_view( $project_id );
		$groups = array();
		foreach ( $rows as $row ) {
			$name = (string) ( $row['cluster_name'] ?? 'Unclustered' );
			if ( ! isset( $groups[ $name ] ) ) {
				$groups[ $name ] = array();
			}
			$groups[ $name ][] = $row;
		}
		return array(
			'clusters' => $this->repository->list_clusters( $project_id ),
			'groups'   => $groups,
		);
	}

	/**
	 * @return array{phases: array<string, list<array<string, mixed>>>}
	 */
	public function roadmap_view( int $project_id ): array {
		$this->ensure_tables();
		$rows   = $this->repository->roadmap_view( $project_id );
		$phases = array(
			'now'     => array(),
			'next'    => array(),
			'later'   => array(),
			'backlog' => array(),
		);
		foreach ( $rows as $row ) {
			$phase = (string) ( $row['roadmap_phase'] ?? '' );
			if ( ! isset( $phases[ $phase ] ) ) {
				$phase = 'backlog';
			}
			$phases[ $phase ][] = $row;
		}
		return array( 'phases' => $phases );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function timeline( int $project_id = 0, int $keyword_id = 0 ): array {
		$this->ensure_tables();
		return $this->repository->timeline( $project_id, $keyword_id, 50 );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_clusters( int $project_id = 0 ): array {
		$this->ensure_tables();
		return $this->repository->list_clusters( $project_id );
	}

	/**
	 * @param array<string, mixed> $row DB row.
	 */
	public function dto_from_row( array $row ): KeywordDTO {
		$dto                     = new KeywordDTO();
		$dto->id                 = (int) ( $row['id'] ?? 0 );
		$dto->primary_keyword    = (string) ( $row['primary_keyword'] ?? '' );
		$dto->intent             = (string) ( $row['intent'] ?? '' );
		$dto->difficulty         = (int) ( $row['difficulty'] ?? 0 );
		$dto->priority           = (int) ( $row['priority'] ?? 50 );
		$dto->status             = (string) ( $row['status'] ?? 'idea' );
		$dto->project_id         = (int) ( $row['project_id'] ?? 0 );
		$dto->brief_id           = (int) ( $row['brief_id'] ?? 0 );
		$dto->article_id         = (int) ( $row['article_id'] ?? 0 );
		$dto->target_url         = (string) ( $row['target_url'] ?? '' );
		$dto->cluster_id         = (int) ( $row['cluster_id'] ?? 0 );
		$dto->parent_keyword_id  = (int) ( $row['parent_keyword_id'] ?? 0 );
		$dto->language           = (string) ( $row['language'] ?? '' );
		$dto->country            = (string) ( $row['country'] ?? '' );
		$dto->roadmap_phase      = (string) ( $row['roadmap_phase'] ?? '' );
		$dto->roadmap_order      = (int) ( $row['roadmap_order'] ?? 0 );
		$dto->user_id            = (int) ( $row['user_id'] ?? 0 );
		$dto->created_at         = (string) ( $row['created_at'] ?? '' );
		$dto->updated_at         = (string) ( $row['updated_at'] ?? '' );
		$dto->cluster_name       = (string) ( $row['cluster_name'] ?? '' );
		$dto->project_name       = (string) ( $row['project_name'] ?? '' );
		return $dto;
	}
}
