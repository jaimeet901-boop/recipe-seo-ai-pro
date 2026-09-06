<?php
declare(strict_types=1);

/**
 * View model for Unified SEO Workspace (Phase 4.2).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Workspace;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WorkspaceViewModel
 */
final class WorkspaceViewModel {

	private WorkspaceDashboard $dashboard;

	public function __construct( WorkspaceDashboard $dashboard ) {
		$this->dashboard = $dashboard;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function for_admin_page( int $project_id = 0 ): array {
		$data = $this->dashboard->build( $project_id );
		$project_name = '';
		if ( ! empty( $data['project']['name'] ) ) {
			$project_name = (string) $data['project']['name'];
		}

		return array_merge(
			$data,
			array(
				'title'        => __( 'Unified SEO Workspace', 'recipe-seo-ai-pro' ),
				'project_name' => $project_name,
				'note'         => __( 'Central hub for your SEO workflow. Links open existing modules — no data is changed here.', 'recipe-seo-ai-pro' ),
			)
		);
	}
}
