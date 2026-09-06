<?php
declare(strict_types=1);

/**
 * View model for AI Content Optimizer (Phase 4.1).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentOptimizer;

use RecipeSeoAiPro\Contracts\SettingsServiceInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ContentOptimizerViewModel
 */
final class ContentOptimizerViewModel {

	private SettingsServiceInterface $settings;

	public function __construct( SettingsServiceInterface $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function for_admin_page( int $post_id = 0 ): array {
		$all      = $this->settings->all();
		$provider = isset( $all['ai_provider'] ) ? sanitize_key( (string) $all['ai_provider'] ) : '';
		$endpoint = isset( $all['ai_endpoint'] ) ? trim( (string) $all['ai_endpoint'] ) : '';
		$model    = isset( $all['ai_model'] ) ? trim( (string) $all['ai_model'] ) : '';
		$has_key  = function_exists( 'rsaip_setting_has_secret' )
			? rsaip_setting_has_secret( $all, 'ai_api_key' )
			: ( isset( $all['ai_api_key'] ) && trim( (string) $all['ai_api_key'] ) !== '' );
		$ai_ready = ( $provider === 'openai_compatible' ) && $endpoint !== '' && $model !== '' && $has_key;

		$post_title = '';
		if ( $post_id > 0 ) {
			$post_title = (string) get_the_title( $post_id );
		}

		return array(
			'title'      => __( 'AI Content Optimizer', 'recipe-seo-ai-pro' ),
			'ai_ready'   => $ai_ready,
			'ai_provider'=> $provider,
			'post_id'    => $post_id,
			'post_title' => $post_title,
			'workflows'  => $this->workflow_labels(),
			'scopes'     => $this->scope_labels(),
			'note'       => __( 'Analyze and optimize content with isolated workflows. Apply always creates a backup first.', 'recipe-seo-ai-pro' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function workflow_labels(): array {
		return array(
			'seo_recovery'           => __( 'SEO Recovery', 'recipe-seo-ai-pro' ),
			'human_rewrite'          => __( 'Human Rewrite', 'recipe-seo-ai-pro' ),
			'recipe_optimization'    => __( 'Recipe Optimization', 'recipe-seo-ai-pro' ),
			'eeat_optimization'      => __( 'EEAT Optimization', 'recipe-seo-ai-pro' ),
			'content_expansion'      => __( 'Content Expansion', 'recipe-seo-ai-pro' ),
			'content_simplification' => __( 'Content Simplification', 'recipe-seo-ai-pro' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function scope_labels(): array {
		return array(
			'full_article'       => __( 'Full Article', 'recipe-seo-ai-pro' ),
			'selected_paragraph' => __( 'Selected Paragraph', 'recipe-seo-ai-pro' ),
			'introduction'       => __( 'Introduction', 'recipe-seo-ai-pro' ),
			'conclusion'         => __( 'Conclusion', 'recipe-seo-ai-pro' ),
			'heading'            => __( 'Heading', 'recipe-seo-ai-pro' ),
			'meta_description'   => __( 'Meta Description', 'recipe-seo-ai-pro' ),
			'recipe_card'        => __( 'Recipe Card only', 'recipe-seo-ai-pro' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function present( ContentOptimizerDTO $dto ): array {
		return array(
			'optimization' => $dto->to_array(),
			'workflows'    => $this->workflow_labels(),
			'scopes'       => $this->scope_labels(),
		);
	}
}
