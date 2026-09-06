<?php
declare(strict_types=1);

/**
 * SERP Intelligence Engine orchestrator (Phase 3.6).
 *
 * AI-only v1 — no scraping / Google parsing.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Seo\SerpIntelligence;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Contracts\SettingsServiceInterface;
use RecipeSeoAiPro\Modules\Ai\AiProviderRegistry;
use RecipeSeoAiPro\Modules\Ai\ContentBrief\BriefRepository;
use RecipeSeoAiPro\Modules\Keywords\KeywordRepository;
use RecipeSeoAiPro\Modules\Projects\ProjectManager;
use RecipeSeoAiPro\Modules\Projects\ProjectRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SerpIntelligenceService
 */
final class SerpIntelligenceService {

	public const PROJECT_ASSET_TYPE = 'serp_analysis';

	private AiProviderRegistry $providers;

	private SettingsServiceInterface $settings;

	private LoggerInterface $logger;

	private CacheInterface $cache;

	private EventDispatcherInterface $events;

	private PromptBuilder $prompt_builder;

	private SerpAnalyzer $analyzer;

	private SerpIntelligenceRepository $repository;

	private ProjectRepository $projects;

	private ?KeywordRepository $keywords;

	private ?BriefRepository $briefs;

	private ?ProjectManager $project_manager;

	public function __construct(
		AiProviderRegistry $providers,
		SettingsServiceInterface $settings,
		LoggerInterface $logger,
		CacheInterface $cache,
		EventDispatcherInterface $events,
		PromptBuilder $prompt_builder,
		SerpAnalyzer $analyzer,
		SerpIntelligenceRepository $repository,
		ProjectRepository $projects,
		?KeywordRepository $keywords = null,
		?BriefRepository $briefs = null,
		?ProjectManager $project_manager = null
	) {
		$this->providers       = $providers;
		$this->settings        = $settings;
		$this->logger          = $logger;
		$this->cache           = $cache;
		$this->events          = $events;
		$this->prompt_builder  = $prompt_builder;
		$this->analyzer        = $analyzer;
		$this->repository      = $repository;
		$this->projects        = $projects;
		$this->keywords        = $keywords;
		$this->briefs          = $briefs;
		$this->project_manager = $project_manager;
	}

	public function ensure_tables(): void {
		if ( class_exists( 'RSAIP_DB' ) ) {
			\RSAIP_DB::migrate_serp_analyses_table();
		}
	}

	/**
	 * @param string               $query   Query / keyword.
	 * @param array<string, mixed> $options language, country, audience, notes.
	 * @return SerpIntelligenceDTO|\WP_Error
	 */
	public function analyze( string $query, array $options = array() ) {
		$query = trim( $query );
		if ( $query === '' ) {
			return new \WP_Error( 'rsaip_serp_query', 'Query is required.' );
		}
		if ( mb_strlen( $query ) > 300 ) {
			return new \WP_Error( 'rsaip_serp_query', 'Query is too long (max 300 characters).' );
		}

		$all         = $this->settings->all();
		$provider_id = isset( $all['ai_provider'] ) ? (string) $all['ai_provider'] : '';
		if ( $provider_id !== 'openai_compatible' ) {
			return new \WP_Error( 'rsaip_serp_ai', 'Enable an OpenAI-compatible AI provider in Settings to run SERP intelligence.' );
		}

		$prompt   = $this->prompt_builder->build( $query, $options );
		$provider = $this->providers->get( 'openai_compatible' );

		$this->logger->info( 'serp_intelligence.analyze.start', array( 'query_len' => mb_strlen( $query ) ) );

		$result = $provider->complete(
			$prompt,
			array(
				'max_tokens' => 2000,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'serp_intelligence.analyze.failed',
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				)
			);
			return $result;
		}

		$dto = $this->analyzer->from_ai_response( $query, (string) $result, $options );
		if ( is_wp_error( $dto ) ) {
			$this->logger->error( 'serp_intelligence.parse.failed', array( 'code' => $dto->get_error_code() ) );
			return $dto;
		}

		$cache_key = 'rsaip_serp_' . md5( $query . '|' . (string) wp_json_encode( $options ) );
		$this->cache->set( $cache_key, $dto->to_array(), 300 );

		$this->events->dispatch(
			'rsaip.serp_intelligence.analyzed',
			array(
				'query' => $dto->query,
				'type'  => $dto->recommended_article_type,
			)
		);

		$this->logger->info( 'serp_intelligence.analyze.done', array( 'query' => $dto->query ) );

		return $dto;
	}

	/**
	 * Persist analysis and optional attachments.
	 *
	 * @param SerpIntelligenceDTO  $dto     Analysis.
	 * @param array<string, mixed> $attach  project_id, keyword_id, brief_id.
	 * @param int                  $user_id Actor.
	 * @return SerpIntelligenceDTO|\WP_Error
	 */
	public function save( SerpIntelligenceDTO $dto, array $attach = array(), int $user_id = 0 ) {
		$this->ensure_tables();

		$project_id = absint( $attach['project_id'] ?? $dto->project_id );
		$keyword_id = absint( $attach['keyword_id'] ?? $dto->keyword_id );
		$brief_id   = absint( $attach['brief_id'] ?? $dto->brief_id );

		if ( $project_id > 0 && ! $this->projects->find_project( $project_id ) ) {
			return new \WP_Error( 'rsaip_serp_project', 'Project not found.', array( 'status' => 404 ) );
		}
		if ( $keyword_id > 0 && $this->keywords && ! $this->keywords->find( $keyword_id ) ) {
			return new \WP_Error( 'rsaip_serp_keyword', 'Keyword not found.', array( 'status' => 404 ) );
		}
		if ( $brief_id > 0 && $this->briefs && ! $this->briefs->find( $brief_id ) ) {
			return new \WP_Error( 'rsaip_serp_brief', 'Content brief not found.', array( 'status' => 404 ) );
		}

		$now = \RSAIP_DB::now_gmt_sql();
		$row = array(
			'query_text'               => mb_substr( $dto->query, 0, 255 ),
			'language'                 => mb_substr( $dto->language, 0, 20 ),
			'country'                  => mb_substr( $dto->country, 0, 8 ),
			'search_intent'            => $dto->search_intent,
			'recommended_article_type' => mb_substr( $dto->recommended_article_type, 0, 120 ),
			'recommended_word_count'   => max( 0, $dto->recommended_word_count ),
			'project_id'               => $project_id,
			'keyword_id'               => $keyword_id,
			'brief_id'                 => $brief_id,
			'user_id'                  => max( 0, $user_id ),
			'payload'                  => (string) wp_json_encode( $dto->payload_array() ),
			'updated_at'               => $now,
		);

		if ( $dto->id > 0 ) {
			if ( ! $this->repository->update( $dto->id, $row ) ) {
				return new \WP_Error( 'rsaip_serp_save', 'Could not update SERP analysis.' );
			}
			$id = $dto->id;
		} else {
			$row['created_at'] = $now;
			$id                = $this->repository->insert( $row );
			if ( $id <= 0 ) {
				return new \WP_Error( 'rsaip_serp_save', 'Could not save SERP analysis.' );
			}
		}

		$saved = $this->get( $id );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		if ( $project_id > 0 && $this->project_manager ) {
			$this->project_manager->attach_asset(
				$project_id,
				self::PROJECT_ASSET_TYPE,
				$id,
				$saved->query,
				$user_id
			);
		}

		$this->events->dispatch(
			'rsaip.serp_intelligence.saved',
			array(
				'id'         => $id,
				'project_id' => $project_id,
				'keyword_id' => $keyword_id,
				'brief_id'   => $brief_id,
			)
		);

		$this->logger->info( 'serp_intelligence.saved', array( 'id' => $id ) );

		return $saved;
	}

	/**
	 * @return SerpIntelligenceDTO|\WP_Error
	 */
	public function attach_to_project( int $id, int $project_id, int $user_id = 0 ) {
		if ( $project_id <= 0 ) {
			return new \WP_Error( 'rsaip_serp_project', 'Select a project to attach.' );
		}
		return $this->attach( $id, array( 'project_id' => $project_id ), $user_id );
	}

	/**
	 * @return SerpIntelligenceDTO|\WP_Error
	 */
	public function attach_to_keyword( int $id, int $keyword_id, int $user_id = 0 ) {
		if ( $keyword_id <= 0 ) {
			return new \WP_Error( 'rsaip_serp_keyword', 'Enter a keyword ID to attach.' );
		}
		return $this->attach( $id, array( 'keyword_id' => $keyword_id ), $user_id );
	}

	/**
	 * @return SerpIntelligenceDTO|\WP_Error
	 */
	public function attach_to_brief( int $id, int $brief_id, int $user_id = 0 ) {
		if ( $brief_id <= 0 ) {
			return new \WP_Error( 'rsaip_serp_brief', 'Enter a content brief ID to attach.' );
		}
		return $this->attach( $id, array( 'brief_id' => $brief_id ), $user_id );
	}

	/**
	 * @param array<string, int> $fields Attachment fields.
	 * @return SerpIntelligenceDTO|\WP_Error
	 */
	public function attach( int $id, array $fields, int $user_id = 0 ) {
		$dto = $this->get( $id );
		if ( is_wp_error( $dto ) ) {
			return $dto;
		}

		$attach = array(
			'project_id' => $dto->project_id,
			'keyword_id' => $dto->keyword_id,
			'brief_id'   => $dto->brief_id,
		);
		foreach ( array( 'project_id', 'keyword_id', 'brief_id' ) as $key ) {
			if ( array_key_exists( $key, $fields ) ) {
				$attach[ $key ] = absint( $fields[ $key ] );
			}
		}

		return $this->save( $dto, $attach, $user_id );
	}

	/**
	 * @return SerpIntelligenceDTO|\WP_Error
	 */
	public function get( int $id ) {
		$this->ensure_tables();
		$row = $this->repository->find( $id );
		if ( ! $row ) {
			return new \WP_Error( 'rsaip_serp_missing', 'SERP analysis not found.', array( 'status' => 404 ) );
		}
		return $this->analyzer->from_row( $row );
	}

	/**
	 * @param array<string, mixed> $args Filters.
	 * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
	 */
	public function list_analyses( array $args ): array {
		$this->ensure_tables();
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
		$args['page']     = $page;
		$args['per_page'] = $per_page;

		$result = $this->repository->search( $args );
		return array(
			'items'    => $result['items'],
			'total'    => $result['total'],
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * @return list<array{id: int, name: string}>
	 */
	public function list_projects_for_select(): array {
		$result = $this->projects->search_projects(
			array(
				'page'     => 1,
				'per_page' => 100,
				'sort'     => 'name',
				'order'    => 'ASC',
			)
		);
		$out = array();
		foreach ( $result['items'] as $row ) {
			$out[] = array(
				'id'   => (int) ( $row['id'] ?? 0 ),
				'name' => (string) ( $row['name'] ?? '' ),
			);
		}
		return $out;
	}
}
