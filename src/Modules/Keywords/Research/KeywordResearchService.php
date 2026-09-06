<?php
declare(strict_types=1);

/**
 * AI Keyword Research Engine orchestrator (Phase 3.5).
 *
 * Isolated from Keyword Workspace UI/controllers. Saves via repositories/managers only.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Keywords\Research;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Contracts\SettingsServiceInterface;
use RecipeSeoAiPro\Modules\Ai\AiProviderRegistry;
use RecipeSeoAiPro\Modules\Keywords\KeywordManager;
use RecipeSeoAiPro\Modules\Keywords\KeywordRepository;
use RecipeSeoAiPro\Modules\Projects\ProjectRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KeywordResearchService
 */
final class KeywordResearchService {

	private AiProviderRegistry $providers;

	private SettingsServiceInterface $settings;

	private LoggerInterface $logger;

	private CacheInterface $cache;

	private EventDispatcherInterface $events;

	private KeywordResearchPromptBuilder $prompt_builder;

	private KeywordResearchBuilder $builder;

	private KeywordRepository $keywords;

	private ProjectRepository $projects;

	private ?KeywordManager $keyword_manager;

	public function __construct(
		AiProviderRegistry $providers,
		SettingsServiceInterface $settings,
		LoggerInterface $logger,
		CacheInterface $cache,
		EventDispatcherInterface $events,
		KeywordResearchPromptBuilder $prompt_builder,
		KeywordResearchBuilder $builder,
		KeywordRepository $keywords,
		ProjectRepository $projects,
		?KeywordManager $keyword_manager = null
	) {
		$this->providers       = $providers;
		$this->settings        = $settings;
		$this->logger          = $logger;
		$this->cache           = $cache;
		$this->events          = $events;
		$this->prompt_builder  = $prompt_builder;
		$this->builder         = $builder;
		$this->keywords        = $keywords;
		$this->projects        = $projects;
		$this->keyword_manager = $keyword_manager;
	}

	/**
	 * @param string               $seed    Seed topic / keyword.
	 * @param array<string, mixed> $options language, country, audience, niche, notes.
	 * @return KeywordResearchDTO|\WP_Error
	 */
	public function generate( string $seed, array $options = array() ) {
		$seed = trim( $seed );
		if ( $seed === '' ) {
			return new \WP_Error( 'rsaip_kw_research_seed', 'Seed topic is required.' );
		}
		if ( mb_strlen( $seed ) > 300 ) {
			return new \WP_Error( 'rsaip_kw_research_seed', 'Seed is too long (max 300 characters).' );
		}

		$all         = $this->settings->all();
		$provider_id = isset( $all['ai_provider'] ) ? (string) $all['ai_provider'] : '';
		if ( $provider_id !== 'openai_compatible' ) {
			return new \WP_Error( 'rsaip_kw_research_ai', 'Enable an OpenAI-compatible AI provider in Settings to run keyword research.' );
		}

		$prompt   = $this->prompt_builder->build( $seed, $options );
		$provider = $this->providers->get( 'openai_compatible' );

		$this->logger->info(
			'keyword_research.generate.start',
			array(
				'seed_len' => mb_strlen( $seed ),
			)
		);

		$result = $provider->complete(
			$prompt,
			array(
				'max_tokens' => 2800,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'keyword_research.generate.failed',
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				)
			);
			return $result;
		}

		$dto = $this->builder->from_ai_response( $seed, (string) $result, $options );
		if ( is_wp_error( $dto ) ) {
			$this->logger->error(
				'keyword_research.parse.failed',
				array(
					'code' => $dto->get_error_code(),
				)
			);
			return $dto;
		}

		$cache_key = 'rsaip_kw_research_' . md5( $seed . '|' . (string) wp_json_encode( $options ) );
		$this->cache->set( $cache_key, $dto->to_array(), 300 );

		$this->events->dispatch(
			'rsaip.keyword_research.generated',
			array(
				'seed'  => $dto->seed,
				'count' => count( $dto->keywords ),
			)
		);

		$this->logger->info(
			'keyword_research.generate.done',
			array(
				'count' => count( $dto->keywords ),
			)
		);

		return $dto;
	}

	/**
	 * Save selected research keywords into Keyword Workspace tables.
	 *
	 * @param int                  $project_id Project ID.
	 * @param list<array<string, mixed>> $selected Selected keyword rows.
	 * @param int                  $user_id    Actor.
	 * @return array{saved: int, clusters_created: int, keyword_ids: list<int>}|\WP_Error
	 */
	public function save_selected( int $project_id, array $selected, int $user_id = 0 ) {
		if ( $project_id <= 0 ) {
			return new \WP_Error( 'rsaip_kw_research_project', 'Select a project before saving keywords.' );
		}
		if ( ! $this->projects->find_project( $project_id ) ) {
			return new \WP_Error( 'rsaip_kw_research_project', 'Project not found.', array( 'status' => 404 ) );
		}
		if ( ! $selected ) {
			return new \WP_Error( 'rsaip_kw_research_select', 'Select at least one keyword to save.' );
		}

		if ( class_exists( 'RSAIP_DB' ) ) {
			\RSAIP_DB::migrate_keywords_tables();
		}

		$cluster_map       = $this->cluster_name_map( $project_id );
		$clusters_created  = 0;
		$saved             = 0;
		$keyword_ids       = array();
		$seen              = array();

		foreach ( $selected as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$keyword = sanitize_text_field( (string) ( $row['keyword'] ?? '' ) );
			if ( $keyword === '' ) {
				continue;
			}
			$dedupe = mb_strtolower( $keyword );
			if ( isset( $seen[ $dedupe ] ) ) {
				continue;
			}
			$seen[ $dedupe ] = true;

			$cluster_name = sanitize_text_field( (string) ( $row['suggested_cluster'] ?? '' ) );
			$cluster_id   = 0;
			if ( $cluster_name !== '' ) {
				$ck = mb_strtolower( $cluster_name );
				if ( ! isset( $cluster_map[ $ck ] ) ) {
					$new_id = $this->create_cluster( $project_id, $cluster_name );
					if ( $new_id > 0 ) {
						$cluster_map[ $ck ] = $new_id;
						++$clusters_created;
					}
				}
				$cluster_id = (int) ( $cluster_map[ $ck ] ?? 0 );
			}

			$id = $this->persist_keyword(
				array(
					'primary_keyword' => $keyword,
					'intent'          => sanitize_text_field( (string) ( $row['intent'] ?? 'informational' ) ),
					'difficulty'      => absint( $row['difficulty'] ?? 0 ),
					'priority'        => absint( $row['priority'] ?? 50 ),
					'status'          => 'idea',
					'project_id'      => $project_id,
					'cluster_id'      => $cluster_id,
					'language'        => sanitize_text_field( (string) ( $row['language'] ?? '' ) ),
					'country'         => sanitize_text_field( (string) ( $row['country'] ?? '' ) ),
					'roadmap_phase'   => 'backlog',
				),
				$user_id
			);

			if ( $id > 0 ) {
				++$saved;
				$keyword_ids[] = $id;
			}
		}

		if ( $saved <= 0 ) {
			return new \WP_Error( 'rsaip_kw_research_save', 'No keywords were saved.' );
		}

		$this->events->dispatch(
			'rsaip.keyword_research.saved',
			array(
				'project_id'       => $project_id,
				'saved'            => $saved,
				'clusters_created' => $clusters_created,
			)
		);

		$this->logger->info(
			'keyword_research.saved',
			array(
				'project_id' => $project_id,
				'saved'      => $saved,
			)
		);

		return array(
			'saved'            => $saved,
			'clusters_created' => $clusters_created,
			'keyword_ids'      => $keyword_ids,
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

	/**
	 * @return array<string, int> lowercase name => id
	 */
	private function cluster_name_map( int $project_id ): array {
		$map = array();
		foreach ( $this->keywords->list_clusters( $project_id ) as $cluster ) {
			$name = (string) ( $cluster['name'] ?? '' );
			if ( $name === '' ) {
				continue;
			}
			$map[ mb_strtolower( $name ) ] = (int) ( $cluster['id'] ?? 0 );
		}
		return $map;
	}

	private function create_cluster( int $project_id, string $name ): int {
		if ( $this->keyword_manager ) {
			$result = $this->keyword_manager->create_cluster(
				array(
					'project_id' => $project_id,
					'name'       => $name,
				)
			);
			if ( ! is_wp_error( $result ) && isset( $result['id'] ) ) {
				return (int) $result['id'];
			}
		}

		$now = \RSAIP_DB::now_gmt_sql();
		return $this->keywords->insert_cluster(
			array(
				'project_id'  => $project_id,
				'name'        => mb_substr( $name, 0, 255 ),
				'description' => '',
				'color'       => '',
				'created_at'  => $now,
				'updated_at'  => $now,
			)
		);
	}

	/**
	 * @param array<string, mixed> $input Keyword fields.
	 */
	private function persist_keyword( array $input, int $user_id ): int {
		if ( $this->keyword_manager ) {
			$result = $this->keyword_manager->create( $input, $user_id );
			if ( ! is_wp_error( $result ) ) {
				return (int) $result->id;
			}
			return 0;
		}

		$now = \RSAIP_DB::now_gmt_sql();
		$row = array(
			'primary_keyword'   => mb_substr( (string) $input['primary_keyword'], 0, 255 ),
			'intent'            => (string) ( $input['intent'] ?? '' ),
			'difficulty'        => absint( $input['difficulty'] ?? 0 ),
			'priority'          => absint( $input['priority'] ?? 50 ),
			'status'            => (string) ( $input['status'] ?? 'idea' ),
			'project_id'        => absint( $input['project_id'] ?? 0 ),
			'brief_id'          => 0,
			'article_id'        => 0,
			'target_url'        => '',
			'cluster_id'        => absint( $input['cluster_id'] ?? 0 ),
			'parent_keyword_id' => 0,
			'language'          => (string) ( $input['language'] ?? '' ),
			'country'           => (string) ( $input['country'] ?? '' ),
			'roadmap_phase'     => (string) ( $input['roadmap_phase'] ?? 'backlog' ),
			'roadmap_order'     => 0,
			'user_id'           => max( 0, $user_id ),
			'created_at'        => $now,
			'updated_at'        => $now,
		);
		return $this->keywords->insert( $row );
	}
}
