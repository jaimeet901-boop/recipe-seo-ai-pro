<?php
declare(strict_types=1);

/**
 * Unified SEO Workspace navigation + workflow steps (Phase 4.2).
 *
 * Navigation only — no business logic.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Workspace;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WorkspaceNavigation
 */
final class WorkspaceNavigation {

	/**
	 * Canonical workflow chain.
	 *
	 * @return list<array{id: string, label: string, page: string, next: string}>
	 */
	public function workflow_steps(): array {
		return array(
			array(
				'id'    => 'keyword',
				'label' => __( 'Keyword', 'recipe-seo-ai-pro' ),
				'page'  => 'rsaip-keyword-workspace',
				'next'  => 'serp',
			),
			array(
				'id'    => 'serp',
				'label' => __( 'SERP', 'recipe-seo-ai-pro' ),
				'page'  => 'rsaip-serp-intelligence',
				'next'  => 'brief',
			),
			array(
				'id'    => 'brief',
				'label' => __( 'Brief', 'recipe-seo-ai-pro' ),
				'page'  => 'rsaip-content-brief',
				'next'  => 'article',
			),
			array(
				'id'    => 'article',
				'label' => __( 'Article', 'recipe-seo-ai-pro' ),
				'page'  => 'rsaip-ai',
				'next'  => 'optimizer',
			),
			array(
				'id'    => 'optimizer',
				'label' => __( 'Optimizer', 'recipe-seo-ai-pro' ),
				'page'  => 'rsaip-content-optimizer',
				'next'  => 'calendar',
			),
			array(
				'id'    => 'calendar',
				'label' => __( 'Calendar', 'recipe-seo-ai-pro' ),
				'page'  => 'rsaip-content-calendar',
				'next'  => 'publishing',
			),
			array(
				'id'    => 'publishing',
				'label' => __( 'Publishing', 'recipe-seo-ai-pro' ),
				'page'  => 'rsaip-content-calendar',
				'next'  => 'analytics',
			),
			array(
				'id'    => 'analytics',
				'label' => __( 'Analytics', 'recipe-seo-ai-pro' ),
				'page'  => 'rsaip-gsc',
				'next'  => '',
			),
		);
	}

	/**
	 * Module hub links for the project dashboard.
	 *
	 * @return list<array{id: string, label: string, page: string, description: string}>
	 */
	public function module_links(): array {
		return array(
			array(
				'id'          => 'keywords',
				'label'       => __( 'Keywords', 'recipe-seo-ai-pro' ),
				'page'        => 'rsaip-keyword-workspace',
				'description' => __( 'Manage project keywords', 'recipe-seo-ai-pro' ),
			),
			array(
				'id'          => 'briefs',
				'label'       => __( 'Briefs', 'recipe-seo-ai-pro' ),
				'page'        => 'rsaip-content-brief-library',
				'description' => __( 'Content brief library', 'recipe-seo-ai-pro' ),
			),
			array(
				'id'          => 'serp',
				'label'       => __( 'SERP', 'recipe-seo-ai-pro' ),
				'page'        => 'rsaip-serp-intelligence',
				'description' => __( 'SERP intelligence analyses', 'recipe-seo-ai-pro' ),
			),
			array(
				'id'          => 'calendar',
				'label'       => __( 'Calendar', 'recipe-seo-ai-pro' ),
				'page'        => 'rsaip-content-calendar',
				'description' => __( 'Content calendar & queue', 'recipe-seo-ai-pro' ),
			),
			array(
				'id'          => 'articles',
				'label'       => __( 'Articles', 'recipe-seo-ai-pro' ),
				'page'        => 'rsaip-ai',
				'description' => __( 'AI article generator', 'recipe-seo-ai-pro' ),
			),
			array(
				'id'          => 'optimizer',
				'label'       => __( 'Optimizer', 'recipe-seo-ai-pro' ),
				'page'        => 'rsaip-content-optimizer',
				'description' => __( 'AI content optimizer', 'recipe-seo-ai-pro' ),
			),
			array(
				'id'          => 'recipe',
				'label'       => __( 'Recipe Builder', 'recipe-seo-ai-pro' ),
				'page'        => 'rsaip-recipe-optimizer',
				'description' => __( 'Recipe SEO optimizer', 'recipe-seo-ai-pro' ),
			),
			array(
				'id'          => 'reports',
				'label'       => __( 'Reports', 'recipe-seo-ai-pro' ),
				'page'        => 'rsaip-reports',
				'description' => __( 'SEO reports & exports', 'recipe-seo-ai-pro' ),
			),
			array(
				'id'          => 'analytics',
				'label'       => __( 'Analytics', 'recipe-seo-ai-pro' ),
				'page'        => 'rsaip-gsc',
				'description' => __( 'Search Console & performance', 'recipe-seo-ai-pro' ),
			),
		);
	}

	/**
	 * Build admin URL for a module page, optionally scoped to a project.
	 *
	 * @param array<string, scalar> $extra Extra query args.
	 */
	public function url_for( string $page, int $project_id = 0, array $extra = array() ): string {
		$args = array_merge( array( 'page' => $page ), $extra );
		if ( $project_id > 0 && $this->page_accepts_project( $page ) ) {
			$args['project_id'] = $project_id;
		}
		return admin_url( 'admin.php?' . http_build_query( $args ) );
	}

	/**
	 * Workflow steps with resolved URLs + next-step URL.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function workflow_with_urls( int $project_id = 0 ): array {
		$steps = $this->workflow_steps();
		$by_id = array();
		foreach ( $steps as $step ) {
			$by_id[ $step['id'] ] = $step;
		}
		$out = array();
		foreach ( $steps as $step ) {
			$extra = array();
			if ( $step['id'] === 'publishing' ) {
				$extra['view'] = 'agenda';
			}
			$item              = $step;
			$item['url']       = $this->url_for( $step['page'], $project_id, $extra );
			$item['next_url']  = '';
			$item['next_label'] = '';
			if ( $step['next'] !== '' && isset( $by_id[ $step['next'] ] ) ) {
				$next               = $by_id[ $step['next'] ];
				$item['next_url']   = $this->url_for( $next['page'], $project_id );
				$item['next_label'] = $next['label'];
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * Module links with URLs.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function modules_with_urls( int $project_id = 0 ): array {
		$out = array();
		foreach ( $this->module_links() as $link ) {
			$link['url'] = $this->url_for( $link['page'], $project_id );
			$out[]       = $link;
		}
		return $out;
	}

	private function page_accepts_project( string $page ): bool {
		return in_array(
			$page,
			array(
				'rsaip-keyword-workspace',
				'rsaip-keyword-research',
				'rsaip-serp-intelligence',
				'rsaip-content-calendar',
				'rsaip-seo-projects',
				'rsaip-seo-workspace',
			),
			true
		);
	}
}
