<?php
declare(strict_types=1);

/**
 * Content Brief Library search / filter / sort (Phase 3.2).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentBrief;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BriefSearchService
 */
final class BriefSearchService {

	private BriefRepository $repository;

	private BriefStorageService $storage;

	private CacheInterface $cache;

	private LoggerInterface $logger;

	public function __construct(
		BriefRepository $repository,
		BriefStorageService $storage,
		CacheInterface $cache,
		LoggerInterface $logger
	) {
		$this->repository = $repository;
		$this->storage    = $storage;
		$this->cache      = $cache;
		$this->logger     = $logger;
	}

	/**
	 * @param array<string, mixed> $args q, status, sort, order, page, per_page, user_id, bypass_cache.
	 * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
	 */
	public function search( array $args ): array {
		$this->storage->ensure_table();

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
		$args['page']     = $page;
		$args['per_page'] = $per_page;

		$bypass = ! empty( $args['bypass_cache'] );
		$key    = 'rsaip_brief_lib_' . md5( (string) wp_json_encode( $args ) );

		if ( ! $bypass ) {
			$cached = $this->cache->get( $key, null );
			if ( is_array( $cached ) && isset( $cached['items'], $cached['total'] ) ) {
				return $cached;
			}
		}

		$result = $this->repository->search( $args );
		$items  = array();
		foreach ( $result['items'] as $row ) {
			$items[] = array(
				'id'              => (int) ( $row['id'] ?? 0 ),
				'title'           => (string) ( $row['title'] ?? '' ),
				'topic'           => (string) ( $row['topic'] ?? '' ),
				'primary_keyword' => (string) ( $row['primary_keyword'] ?? '' ),
				'search_intent'   => (string) ( $row['search_intent'] ?? '' ),
				'status'          => (string) ( $row['status'] ?? '' ),
				'word_count'      => (int) ( $row['word_count'] ?? 0 ),
				'user_id'         => (int) ( $row['user_id'] ?? 0 ),
				'slug'            => (string) ( $row['slug'] ?? '' ),
				'created_at'      => (string) ( $row['created_at'] ?? '' ),
				'updated_at'      => (string) ( $row['updated_at'] ?? '' ),
			);
		}

		$payload = array(
			'items'    => $items,
			'total'    => (int) $result['total'],
			'page'     => $page,
			'per_page' => $per_page,
		);

		$this->cache->set( $key, $payload, 30 );
		$this->logger->debug(
			'content_brief.library.search',
			array(
				'total' => $payload['total'],
				'page'  => $page,
			)
		);

		return $payload;
	}

	/**
	 * Bust short-lived list cache after mutations (best-effort; keys are hashed).
	 */
	public function bust_cache_hint(): void {
		// TransientCache has no tag flush; short TTL (30s) is acceptable for v1.
		$this->logger->debug( 'content_brief.library.cache_hint', array() );
	}
}
