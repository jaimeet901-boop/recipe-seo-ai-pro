<?php
declare(strict_types=1);

/**
 * View model for AI SEO Projects admin UI (Phase 3.3).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Projects;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProjectViewModel
 */
final class ProjectViewModel {

	/**
	 * @return array<string, mixed>
	 */
	public function for_admin_page(): array {
		return array(
			'title'    => __( 'AI SEO Projects', 'recipe-seo-ai-pro' ),
			'statuses' => array(
				'all'      => __( 'All statuses', 'recipe-seo-ai-pro' ),
				'active'   => __( 'Active', 'recipe-seo-ai-pro' ),
				'paused'   => __( 'Paused', 'recipe-seo-ai-pro' ),
				'archived' => __( 'Archived', 'recipe-seo-ai-pro' ),
			),
			'future_modules' => array(
				__( 'Keyword Research (not yet)', 'recipe-seo-ai-pro' ),
				__( 'Content Calendar', 'recipe-seo-ai-pro' ),
				__( 'Competitor Analysis', 'recipe-seo-ai-pro' ),
				__( 'Publishing', 'recipe-seo-ai-pro' ),
				__( 'Analytics', 'recipe-seo-ai-pro' ),
			),
			'locale' => function_exists( 'get_locale' ) ? (string) get_locale() : 'en_US',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function present( ProjectDTO $dto ): array {
		return $dto->to_array();
	}
}
