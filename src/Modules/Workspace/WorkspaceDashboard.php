<?php
declare(strict_types=1);

/**
 * Assembles the Unified SEO Workspace dashboard payload (Phase 4.2).
 *
 * Read-only orchestration of navigation + widgets. No mutations.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Workspace;

use RecipeSeoAiPro\Modules\Projects\ProjectRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WorkspaceDashboard
 */
final class WorkspaceDashboard {

	private WorkspaceNavigation $navigation;

	private WorkspaceWidgets $widgets;

	private ?ProjectRepository $projects;

	public function __construct(
		WorkspaceNavigation $navigation,
		WorkspaceWidgets $widgets,
		?ProjectRepository $projects = null
	) {
		$this->navigation = $navigation;
		$this->widgets    = $widgets;
		$this->projects   = $projects;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function build( int $project_id = 0 ): array {
		$project = null;
		if ( $project_id > 0 && $this->projects ) {
			$project = $this->projects->find_project( $project_id );
			if ( ! $project ) {
				$project_id = 0;
			}
		}

		$projects = array();
		if ( $this->projects ) {
			$result = $this->projects->search_projects(
				array(
					'page'     => 1,
					'per_page' => 100,
					'sort'     => 'name',
					'order'    => 'ASC',
				)
			);
			foreach ( $result['items'] as $row ) {
				$projects[] = array(
					'id'   => (int) ( $row['id'] ?? 0 ),
					'name' => (string) ( $row['name'] ?? '' ),
				);
			}
		}

		return array(
			'project_id'   => $project_id,
			'project'      => $project,
			'projects'     => $projects,
			'workflow'     => $this->navigation->workflow_with_urls( $project_id ),
			'modules'      => $this->navigation->modules_with_urls( $project_id ),
			'widgets'      => $this->widgets->for_project( $project_id ),
			'quick_links'  => $this->quick_links( $project_id ),
			'projects_url' => $this->navigation->url_for( 'rsaip-seo-projects' ),
			'research_url' => $this->navigation->url_for( 'rsaip-keyword-research', $project_id ),
			'brief_new'    => $this->navigation->url_for( 'rsaip-content-brief' ),
		);
	}

	/**
	 * Next-step shortcuts for the current workflow position.
	 *
	 * @return list<array{label: string, url: string, hint: string}>
	 */
	private function quick_links( int $project_id ): array {
		return array(
			array(
				'label' => __( 'Start: Keyword Research', 'recipe-seo-ai-pro' ),
				'url'   => $this->navigation->url_for( 'rsaip-keyword-research', $project_id ),
				'hint'  => __( 'Generate keywords, then manage them in the workspace', 'recipe-seo-ai-pro' ),
			),
			array(
				'label' => __( 'Next: SERP Intelligence', 'recipe-seo-ai-pro' ),
				'url'   => $this->navigation->url_for( 'rsaip-serp-intelligence', $project_id ),
				'hint'  => __( 'Analyze SERP intent before writing', 'recipe-seo-ai-pro' ),
			),
			array(
				'label' => __( 'Then: Content Brief', 'recipe-seo-ai-pro' ),
				'url'   => $this->navigation->url_for( 'rsaip-content-brief' ),
				'hint'  => __( 'Create a brief from your keyword', 'recipe-seo-ai-pro' ),
			),
			array(
				'label' => __( 'Then: Write Article', 'recipe-seo-ai-pro' ),
				'url'   => $this->navigation->url_for( 'rsaip-ai' ),
				'hint'  => __( 'Generate or draft the article', 'recipe-seo-ai-pro' ),
			),
			array(
				'label' => __( 'Then: Optimize', 'recipe-seo-ai-pro' ),
				'url'   => $this->navigation->url_for( 'rsaip-content-optimizer' ),
				'hint'  => __( 'Run SEO / human / recipe workflows', 'recipe-seo-ai-pro' ),
			),
			array(
				'label' => __( 'Schedule & Publish', 'recipe-seo-ai-pro' ),
				'url'   => $this->navigation->url_for( 'rsaip-content-calendar', $project_id ),
				'hint'  => __( 'Plan publish dates and queue', 'recipe-seo-ai-pro' ),
			),
			array(
				'label' => __( 'Measure: Analytics', 'recipe-seo-ai-pro' ),
				'url'   => $this->navigation->url_for( 'rsaip-gsc' ),
				'hint'  => __( 'Review GSC & performance', 'recipe-seo-ai-pro' ),
			),
		);
	}
}
