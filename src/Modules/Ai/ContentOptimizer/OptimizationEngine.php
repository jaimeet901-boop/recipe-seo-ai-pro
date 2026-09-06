<?php
declare(strict_types=1);

/**
 * Optimization workflows (Phase 4.1).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentOptimizer;

use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Contracts\SettingsServiceInterface;
use RecipeSeoAiPro\Modules\Ai\AiProviderRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OptimizationEngine
 */
final class OptimizationEngine {

	public const WORKFLOWS = array(
		'seo_recovery',
		'human_rewrite',
		'recipe_optimization',
		'eeat_optimization',
		'content_expansion',
		'content_simplification',
	);

	private AiProviderRegistry $providers;

	private SettingsServiceInterface $settings;

	private LoggerInterface $logger;

	private PromptBuilder $prompts;

	private RewriteEngine $rewrite;

	public function __construct(
		AiProviderRegistry $providers,
		SettingsServiceInterface $settings,
		LoggerInterface $logger,
		PromptBuilder $prompts,
		RewriteEngine $rewrite
	) {
		$this->providers = $providers;
		$this->settings  = $settings;
		$this->logger    = $logger;
		$this->prompts   = $prompts;
		$this->rewrite   = $rewrite;
	}

	public function normalize_workflow( string $workflow ): string {
		$workflow = sanitize_key( $workflow );
		$aliases  = array(
			'seo_optimize'   => 'seo_recovery',
			'seo'            => 'seo_recovery',
			'humanize'       => 'human_rewrite',
			'rewrite'        => 'human_rewrite',
			'recipe_optimize'=> 'recipe_optimization',
			'recipe'         => 'recipe_optimization',
			'eeat'           => 'eeat_optimization',
			'expand'         => 'content_expansion',
			'expand_content' => 'content_expansion',
			'simplify'       => 'content_simplification',
		);
		if ( isset( $aliases[ $workflow ] ) ) {
			$workflow = $aliases[ $workflow ];
		}
		return in_array( $workflow, self::WORKFLOWS, true ) ? $workflow : 'human_rewrite';
	}

	/**
	 * @param array<string, mixed> $context title, meta, keywords, notes, selected_text.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function run( string $workflow, string $scope, string $content, array $context = array() ) {
		$all = $this->settings->all();
		if ( ( $all['ai_provider'] ?? '' ) !== 'openai_compatible' ) {
			return new \WP_Error( 'rsaip_opt_ai', 'Enable an OpenAI-compatible AI provider in Settings.' );
		}

		$workflow = $this->normalize_workflow( $workflow );
		$scope    = $this->rewrite->normalize_scope( $scope );
		$segment  = $this->rewrite->extract_scope( $content, $scope, $context );
		if ( trim( wp_strip_all_tags( $segment ) ) === '' && $scope !== 'meta_description' ) {
			return new \WP_Error( 'rsaip_opt_scope', 'No content found for the selected scope.' );
		}
		if ( $scope === 'meta_description' && trim( $segment ) === '' ) {
			$segment = (string) ( $context['meta'] ?? '' );
			if ( $segment === '' ) {
				$segment = ' ';
			}
		}

		$parts    = $this->prompts->build_workflow_parts( $workflow, $scope, $segment, $context );
		$provider = $this->providers->get( 'openai_compatible' );

		$this->logger->info(
			'content_optimizer.workflow.start',
			array(
				'workflow' => $workflow,
				'scope'    => $scope,
			)
		);

		$result = $provider->complete(
			(string) ( $parts['user'] ?? '' ),
			array(
				'max_tokens'      => 3200,
				'system'          => (string) ( $parts['system'] ?? '' ),
				'article_content' => (string) ( $parts['article_content'] ?? '' ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data = $this->decode_json( (string) $result );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'rsaip_opt_parse', 'Could not parse optimizer JSON.' );
		}

		$opt_title = isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : (string) ( $context['title'] ?? '' );
		$opt_meta  = isset( $data['meta_description'] ) ? sanitize_text_field( (string) $data['meta_description'] ) : (string) ( $context['meta'] ?? '' );
		$opt_body  = isset( $data['content'] ) ? wp_kses_post( (string) $data['content'] ) : '';

		if ( $scope === 'meta_description' ) {
			$merged = $content;
			if ( $opt_meta === '' && $opt_body !== '' ) {
				$opt_meta = sanitize_text_field( wp_strip_all_tags( $opt_body ) );
			}
		} else {
			$merged_raw = $this->rewrite->merge_scope( $content, $opt_body !== '' ? $opt_body : $segment, $scope, $context );
			$merged     = wp_kses_post( $merged_raw );
		}

		return array(
			'workflow'                  => $workflow,
			'scope'                     => $scope,
			'title'                     => $opt_title,
			'meta_description'          => $opt_meta,
			'content'                   => $merged,
			'scope_content'             => $opt_body,
			'summary'                   => $this->string_list( $data, 'summary' ),
			'internal_link_suggestions' => $this->string_list( $data, 'internal_link_suggestions' ),
			'schema_recommendations'    => $this->string_list( $data, 'schema_recommendations' ),
			'warnings'                  => $this->string_list( $data, 'warnings' ),
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function decode_json( string $raw ): ?array {
		$raw = trim( $raw );
		if ( preg_match( '/^```(?:json)?\s*(.*?)\s*```$/is', $raw, $m ) ) {
			$raw = trim( $m[1] );
		}
		$data = json_decode( $raw, true );
		if ( is_array( $data ) ) {
			return $data;
		}
		$start = strpos( $raw, '{' );
		$end   = strrpos( $raw, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$data = json_decode( substr( $raw, $start, $end - $start + 1 ), true );
			return is_array( $data ) ? $data : null;
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $data Payload.
	 * @return list<string>
	 */
	private function string_list( array $data, string $key ): array {
		if ( empty( $data[ $key ] ) || ! is_array( $data[ $key ] ) ) {
			return array();
		}
		$out = array();
		foreach ( $data[ $key ] as $item ) {
			if ( is_scalar( $item ) && trim( (string) $item ) !== '' ) {
				$out[] = trim( (string) $item );
			}
		}
		return $out;
	}
}
