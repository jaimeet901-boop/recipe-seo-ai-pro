<?php
declare(strict_types=1);

/**
 * View model for the AI Content Brief admin page.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentBrief;

use RecipeSeoAiPro\Contracts\SettingsServiceInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BriefViewModel
 */
final class BriefViewModel {

	private SettingsServiceInterface $settings;

	public function __construct( SettingsServiceInterface $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Data for the admin template (no secrets).
	 *
	 * @return array<string, mixed>
	 */
	public function for_admin_page(): array {
		$all      = $this->settings->all();
		$provider = isset( $all['ai_provider'] ) ? sanitize_key( (string) $all['ai_provider'] ) : '';
		$endpoint = isset( $all['ai_endpoint'] ) ? trim( (string) $all['ai_endpoint'] ) : '';
		$model    = isset( $all['ai_model'] ) ? trim( (string) $all['ai_model'] ) : '';
		$has_key  = function_exists( 'rsaip_setting_has_secret' )
			? rsaip_setting_has_secret( $all, 'ai_api_key' )
			: ( isset( $all['ai_api_key'] ) && trim( (string) $all['ai_api_key'] ) !== '' );

		$ai_ready = ( $provider === 'openai_compatible' ) && $endpoint !== '' && $model !== '' && $has_key;

		return array(
			'title'       => __( 'AI Content Brief', 'recipe-seo-ai-pro' ),
			'ai_ready'    => $ai_ready,
			'ai_provider' => $provider,
			'locale'      => function_exists( 'get_locale' ) ? (string) get_locale() : 'en',
			'sections'    => $this->section_labels(),
		);
	}

	/**
	 * Labels for rendering brief sections in the UI.
	 *
	 * @return array<string, string>
	 */
	public function section_labels(): array {
		return array(
			'search_intent'                  => __( 'Search Intent', 'recipe-seo-ai-pro' ),
			'primary_keyword'                => __( 'Primary Keyword', 'recipe-seo-ai-pro' ),
			'secondary_keywords'             => __( 'Secondary Keywords', 'recipe-seo-ai-pro' ),
			'long_tail_keywords'             => __( 'Long Tail Keywords', 'recipe-seo-ai-pro' ),
			'semantic_keywords'              => __( 'Semantic Keywords', 'recipe-seo-ai-pro' ),
			'entities'                       => __( 'Entities', 'recipe-seo-ai-pro' ),
			'faq_ideas'                      => __( 'FAQ Ideas', 'recipe-seo-ai-pro' ),
			'h1'                             => __( 'H1', 'recipe-seo-ai-pro' ),
			'h2_structure'                   => __( 'H2 Structure', 'recipe-seo-ai-pro' ),
			'h3_suggestions'                 => __( 'H3 Suggestions', 'recipe-seo-ai-pro' ),
			'meta_description'               => __( 'Meta Description', 'recipe-seo-ai-pro' ),
			'suggested_slug'                 => __( 'Suggested Slug', 'recipe-seo-ai-pro' ),
			'internal_linking_opportunities' => __( 'Internal Linking Opportunities', 'recipe-seo-ai-pro' ),
			'external_authority_suggestions' => __( 'External Authority Suggestions', 'recipe-seo-ai-pro' ),
			'schema_recommendation'          => __( 'Schema Recommendation', 'recipe-seo-ai-pro' ),
			'eeat_recommendations'           => __( 'EEAT Recommendations', 'recipe-seo-ai-pro' ),
			'recommended_word_count'         => __( 'Recommended Word Count', 'recipe-seo-ai-pro' ),
		);
	}

	/**
	 * Shape a BriefDTO for JSON / front-end rendering.
	 *
	 * @return array<string, mixed>
	 */
	public function present( BriefDTO $brief ): array {
		return array(
			'brief'    => $brief->to_array(),
			'labels'   => $this->section_labels(),
			'generated_at' => gmdate( 'c' ),
		);
	}
}
