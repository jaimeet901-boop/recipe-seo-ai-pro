<?php
declare(strict_types=1);

/**
 * View model for AI Keyword Research admin UI (Phase 3.5).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Keywords\Research;

use RecipeSeoAiPro\Contracts\SettingsServiceInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KeywordResearchViewModel
 */
final class KeywordResearchViewModel {

	private SettingsServiceInterface $settings;

	private KeywordResearchService $service;

	public function __construct( SettingsServiceInterface $settings, KeywordResearchService $service ) {
		$this->settings = $settings;
		$this->service  = $service;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function for_admin_page( int $project_id = 0 ): array {
		$all      = $this->settings->all();
		$provider = isset( $all['ai_provider'] ) ? sanitize_key( (string) $all['ai_provider'] ) : '';
		$endpoint = isset( $all['ai_endpoint'] ) ? trim( (string) $all['ai_endpoint'] ) : '';
		$model    = isset( $all['ai_model'] ) ? trim( (string) $all['ai_model'] ) : '';
		$has_key  = function_exists( 'rsaip_setting_has_secret' )
			? rsaip_setting_has_secret( $all, 'ai_api_key' )
			: ( isset( $all['ai_api_key'] ) && trim( (string) $all['ai_api_key'] ) !== '' );

		$ai_ready = ( $provider === 'openai_compatible' ) && $endpoint !== '' && $model !== '' && $has_key;

		return array(
			'title'      => __( 'AI Keyword Research', 'recipe-seo-ai-pro' ),
			'ai_ready'   => $ai_ready,
			'ai_provider'=> $provider,
			'project_id' => $project_id,
			'projects'   => $this->service->list_projects_for_select(),
			'locale'     => function_exists( 'get_locale' ) ? (string) get_locale() : 'en_US',
			'categories' => $this->category_labels(),
			'note'       => __( 'AI-only research (no Google scraping). Preview results, select keywords, then save into Keyword Workspace.', 'recipe-seo-ai-pro' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function category_labels(): array {
		return array(
			'primary'        => __( 'Primary', 'recipe-seo-ai-pro' ),
			'secondary'      => __( 'Secondary', 'recipe-seo-ai-pro' ),
			'long_tail'      => __( 'Long Tail', 'recipe-seo-ai-pro' ),
			'question'       => __( 'Questions', 'recipe-seo-ai-pro' ),
			'comparison'     => __( 'Comparison', 'recipe-seo-ai-pro' ),
			'commercial'     => __( 'Commercial', 'recipe-seo-ai-pro' ),
			'informational'  => __( 'Informational', 'recipe-seo-ai-pro' ),
			'transactional'  => __( 'Transactional', 'recipe-seo-ai-pro' ),
			'local'          => __( 'Local', 'recipe-seo-ai-pro' ),
			'seasonal'       => __( 'Seasonal', 'recipe-seo-ai-pro' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function present( KeywordResearchDTO $dto ): array {
		return array(
			'research'     => $dto->to_array(),
			'categories'   => $this->category_labels(),
			'generated_at' => gmdate( 'c' ),
		);
	}
}
