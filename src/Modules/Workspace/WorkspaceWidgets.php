<?php
declare(strict_types=1);

/**
 * Read-only workspace widgets (Phase 4.2).
 *
 * Aggregates counts from existing tables/repos. No writes. No AJAX changes.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Workspace;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Modules\Projects\ProjectRepository;
use RecipeSeoAiPro\Modules\Projects\ProjectService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WorkspaceWidgets
 */
final class WorkspaceWidgets {

	private WorkspaceNavigation $navigation;

	private ?ProjectRepository $projects;

	private CacheInterface $cache;

	private LoggerInterface $logger;

	public function __construct(
		WorkspaceNavigation $navigation,
		CacheInterface $cache,
		LoggerInterface $logger,
		?ProjectRepository $projects = null
	) {
		$this->navigation = $navigation;
		$this->cache      = $cache;
		$this->logger     = $logger;
		$this->projects   = $projects;
	}

	/**
	 * Widget cards for a project (or global when project_id = 0).
	 *
	 * @return list<array<string, mixed>>
	 */
	public function for_project( int $project_id ): array {
		$key = 'rsaip_ws_widgets_' . $project_id;
		$cached = $this->cache->get( $key, null );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$counts = $this->collect_counts( $project_id );
		$cards  = array();
		foreach ( $this->navigation->modules_with_urls( $project_id ) as $mod ) {
			$id = (string) $mod['id'];
			$cards[] = array(
				'id'          => $id,
				'label'       => $mod['label'],
				'description' => $mod['description'],
				'url'         => $mod['url'],
				'count'       => (int) ( $counts[ $id ] ?? 0 ),
				'count_label' => $this->count_label( $id ),
			);
		}

		$this->cache->set( $key, $cards, 30 );
		$this->logger->debug( 'workspace.widgets', array( 'project_id' => $project_id ) );
		return $cards;
	}

	/**
	 * @return array<string, int>
	 */
	private function collect_counts( int $project_id ): array {
		$counts = array(
			'keywords'  => 0,
			'briefs'    => 0,
			'serp'      => 0,
			'calendar'  => 0,
			'articles'  => 0,
			'optimizer' => 0,
			'recipe'    => 0,
			'reports'   => 0,
			'analytics' => 0,
		);

		if ( $this->projects && $project_id > 0 ) {
			$counts['keywords'] = $this->projects->count_assets( $project_id, ProjectService::ASSET_KEYWORD );
			$counts['briefs']   = $this->projects->count_assets( $project_id, ProjectService::ASSET_BRIEF );
			$counts['articles'] = $this->projects->count_assets( $project_id, ProjectService::ASSET_ARTICLE );
		} else {
			$counts['keywords'] = $this->count_table( 'table_keywords', $project_id );
			$counts['briefs']   = $this->count_briefs( $project_id );
			$counts['articles'] = $this->count_posts();
		}

		$counts['serp']      = $this->count_table( 'table_serp_analyses', $project_id );
		$counts['calendar']  = $this->count_table( 'table_calendar_events', $project_id );
		$counts['optimizer'] = $this->count_optimizer();
		$counts['recipe']    = $this->count_recipe_posts();
		$counts['reports']   = 0; // Navigation hub — no dedicated count.
		$counts['analytics'] = 0;

		return $counts;
	}

	private function count_table( string $method, int $project_id ): int {
		if ( ! class_exists( 'RSAIP_DB' ) || ! method_exists( 'RSAIP_DB', $method ) ) {
			return 0;
		}
		global $wpdb;
		$table = (string) \RSAIP_DB::{$method}();
		if ( ! $this->table_exists( $table ) ) {
			return 0;
		}
		if ( $project_id > 0 ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE project_id = %d", $project_id )
			);
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	private function count_briefs( int $project_id ): int {
		if ( $project_id > 0 && $this->projects ) {
			return $this->projects->count_assets( $project_id, ProjectService::ASSET_BRIEF );
		}
		if ( ! class_exists( 'RSAIP_DB' ) ) {
			return 0;
		}
		global $wpdb;
		$table = \RSAIP_DB::table_content_briefs();
		if ( ! $this->table_exists( $table ) ) {
			return 0;
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	private function count_optimizer(): int {
		if ( ! class_exists( 'RSAIP_DB' ) || ! method_exists( 'RSAIP_DB', 'table_content_optimizations' ) ) {
			return 0;
		}
		global $wpdb;
		$table = \RSAIP_DB::table_content_optimizations();
		if ( ! $this->table_exists( $table ) ) {
			return 0;
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	private function count_posts(): int {
		$counts = wp_count_posts( 'post' );
		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	private function count_recipe_posts(): int {
		global $wpdb;
		// Heuristic only — does not alter recipe engine.
		$like = '%' . $wpdb->esc_like( 'rsaip-recipe' ) . '%';
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('post','page') AND post_content LIKE %s",
				$like
			)
		);
	}

	private function table_exists( string $table ): bool {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		return $found === $table;
	}

	private function count_label( string $id ): string {
		$map = array(
			'keywords'  => __( 'keywords', 'recipe-seo-ai-pro' ),
			'briefs'    => __( 'briefs', 'recipe-seo-ai-pro' ),
			'serp'      => __( 'analyses', 'recipe-seo-ai-pro' ),
			'calendar'  => __( 'events', 'recipe-seo-ai-pro' ),
			'articles'  => __( 'articles', 'recipe-seo-ai-pro' ),
			'optimizer' => __( 'runs', 'recipe-seo-ai-pro' ),
			'recipe'    => __( 'recipe posts', 'recipe-seo-ai-pro' ),
			'reports'   => __( 'open', 'recipe-seo-ai-pro' ),
			'analytics' => __( 'open', 'recipe-seo-ai-pro' ),
		);
		return $map[ $id ] ?? '';
	}
}
