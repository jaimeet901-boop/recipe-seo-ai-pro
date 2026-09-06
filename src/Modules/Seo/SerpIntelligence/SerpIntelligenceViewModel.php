<?php
declare(strict_types=1);

/**
 * View model for SERP Intelligence admin UI (Phase 3.6).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Seo\SerpIntelligence;

use RecipeSeoAiPro\Contracts\SettingsServiceInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SerpIntelligenceViewModel
 */
final class SerpIntelligenceViewModel {

	private SettingsServiceInterface $settings;

	private SerpIntelligenceService $service;

	public function __construct( SettingsServiceInterface $settings, SerpIntelligenceService $service ) {
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
			'title'       => __( 'SERP Intelligence', 'recipe-seo-ai-pro' ),
			'ai_ready'    => $ai_ready,
			'ai_provider' => $provider,
			'project_id'  => $project_id,
			'projects'    => $this->service->list_projects_for_select(),
			'locale'      => function_exists( 'get_locale' ) ? (string) get_locale() : 'en_US',
			'sections'    => $this->section_labels(),
			'note'        => __( 'AI-powered SERP intelligence (no Google scraping). Analyze a query, then save and attach to a project, keyword, or content brief.', 'recipe-seo-ai-pro' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function section_labels(): array {
		return array(
			'search_intent'                 => __( 'Search Intent', 'recipe-seo-ai-pro' ),
			'expected_serp_features'        => __( 'Expected SERP Features', 'recipe-seo-ai-pro' ),
			'recommended_article_type'      => __( 'Recommended Article Type', 'recipe-seo-ai-pro' ),
			'recommended_heading_structure' => __( 'Recommended Heading Structure', 'recipe-seo-ai-pro' ),
			'missing_topics'                => __( 'Missing Topics', 'recipe-seo-ai-pro' ),
			'related_entities'              => __( 'Related Entities', 'recipe-seo-ai-pro' ),
			'recommended_word_count'        => __( 'Recommended Word Count', 'recipe-seo-ai-pro' ),
			'recommended_media'             => __( 'Recommended Media', 'recipe-seo-ai-pro' ),
			'suggested_faq'                 => __( 'Suggested FAQ', 'recipe-seo-ai-pro' ),
			'eeat_recommendations'          => __( 'EEAT Recommendations', 'recipe-seo-ai-pro' ),
			'common_mistakes'               => __( 'Common Mistakes', 'recipe-seo-ai-pro' ),
			'opportunities'                 => __( 'Opportunities', 'recipe-seo-ai-pro' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function present( SerpIntelligenceDTO $dto ): array {
		return array(
			'analysis'     => $dto->to_array(),
			'labels'       => $this->section_labels(),
			'generated_at' => gmdate( 'c' ),
		);
	}
}
