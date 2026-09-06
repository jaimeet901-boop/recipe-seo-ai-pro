<?php
declare(strict_types=1);

/**
 * Keyword Workspace view model (Phase 3.4).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Keywords;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KeywordViewModel
 */
final class KeywordViewModel {

	/**
	 * @return array<string, mixed>
	 */
	public function for_admin_page( int $project_id = 0 ): array {
		$projects = array();
		if ( class_exists( 'RSAIP_DB' ) ) {
			global $wpdb;
			$table = \RSAIP_DB::table_projects();
			$rows  = $wpdb->get_results( "SELECT id, name FROM {$table} ORDER BY name ASC", ARRAY_A );
			if ( is_array( $rows ) ) {
				$projects = $rows;
			}
		}

		return array(
			'title'      => __( 'Keyword Workspace', 'recipe-seo-ai-pro' ),
			'project_id' => $project_id,
			'projects'   => $projects,
			'statuses'   => array(
				'all'         => __( 'All statuses', 'recipe-seo-ai-pro' ),
				'idea'        => __( 'Idea', 'recipe-seo-ai-pro' ),
				'queued'      => __( 'Queued', 'recipe-seo-ai-pro' ),
				'in_progress' => __( 'In progress', 'recipe-seo-ai-pro' ),
				'briefed'     => __( 'Briefed', 'recipe-seo-ai-pro' ),
				'writing'     => __( 'Writing', 'recipe-seo-ai-pro' ),
				'published'   => __( 'Published', 'recipe-seo-ai-pro' ),
				'archived'    => __( 'Archived', 'recipe-seo-ai-pro' ),
			),
			'intents'    => array(
				'all'            => __( 'All intents', 'recipe-seo-ai-pro' ),
				'informational'  => __( 'Informational', 'recipe-seo-ai-pro' ),
				'navigational'   => __( 'Navigational', 'recipe-seo-ai-pro' ),
				'commercial'     => __( 'Commercial', 'recipe-seo-ai-pro' ),
				'transactional'  => __( 'Transactional', 'recipe-seo-ai-pro' ),
			),
			'phases'     => array(
				'now'     => __( 'Now', 'recipe-seo-ai-pro' ),
				'next'    => __( 'Next', 'recipe-seo-ai-pro' ),
				'later'   => __( 'Later', 'recipe-seo-ai-pro' ),
				'backlog' => __( 'Backlog', 'recipe-seo-ai-pro' ),
			),
			'note'       => __( 'Management workspace only — keywords are entered manually. Research generation is not included in this phase.', 'recipe-seo-ai-pro' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function present( KeywordDTO $dto ): array {
		$data = $dto->to_array();
		return array(
			'keyword' => $data,
			'notes'   => $dto->notes,
			'history' => $dto->history,
		);
	}
}
