<?php
declare(strict_types=1);

/**
 * AI Content Brief Engine orchestrator (Phase 3.1).
 *
 * Isolated from RSAIP_AI feature methods and legacy prompts.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentBrief;

use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\HttpClientInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Contracts\SettingsServiceInterface;
use RecipeSeoAiPro\Modules\Ai\AiProviderRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ContentBriefService
 */
final class ContentBriefService {

	private AiProviderRegistry $providers;

	private SettingsServiceInterface $settings;

	/** Reserved for v2 enrichment (scraping/URL fetches). Injected per architecture. */
	private HttpClientInterface $http;

	private LoggerInterface $logger;

	private EventDispatcherInterface $events;

	private PromptBuilder $prompt_builder;

	private ContentBriefBuilder $brief_builder;

	public function __construct(
		AiProviderRegistry $providers,
		SettingsServiceInterface $settings,
		HttpClientInterface $http,
		LoggerInterface $logger,
		EventDispatcherInterface $events,
		PromptBuilder $prompt_builder,
		ContentBriefBuilder $brief_builder
	) {
		$this->providers      = $providers;
		$this->settings       = $settings;
		$this->http           = $http;
		$this->logger         = $logger;
		$this->events         = $events;
		$this->prompt_builder = $prompt_builder;
		$this->brief_builder  = $brief_builder;
	}

	/**
	 * Generate a content brief from a topic (AI-only v1).
	 *
	 * @param string               $topic   Topic or seed keyword.
	 * @param array<string, mixed> $options audience, locale, notes.
	 * @return BriefDTO|\WP_Error
	 */
	public function generate( string $topic, array $options = array() ) {
		$topic = trim( $topic );
		if ( $topic === '' ) {
			return new \WP_Error( 'rsaip_brief_topic', 'Topic is required.' );
		}
		if ( mb_strlen( $topic ) > 300 ) {
			return new \WP_Error( 'rsaip_brief_topic', 'Topic is too long (max 300 characters).' );
		}

		$all = $this->settings->all();
		$provider_id = isset( $all['ai_provider'] ) ? (string) $all['ai_provider'] : '';
		if ( $provider_id !== 'openai_compatible' ) {
			return new \WP_Error( 'rsaip_brief_ai_disabled', 'Enable an OpenAI-compatible AI provider in Settings to generate briefs.' );
		}

		$prompt = $this->prompt_builder->build( $topic, $options );
		$provider = $this->providers->get( 'openai_compatible' );

		$this->logger->info(
			'content_brief.generate.start',
			array(
				'topic_len'   => mb_strlen( $topic ),
				'http_bound'  => $this->http instanceof HttpClientInterface,
			)
		);

		$result = $provider->complete(
			$prompt,
			array(
				'max_tokens' => 1200,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'content_brief.generate.failed',
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				)
			);
			return $result;
		}

		$dto = $this->brief_builder->from_ai_response( $topic, (string) $result );
		if ( is_wp_error( $dto ) ) {
			$this->logger->error(
				'content_brief.parse.failed',
				array(
					'code' => $dto->get_error_code(),
				)
			);
			return $dto;
		}

		$this->events->dispatch(
			'rsaip.content_brief.generated',
			array(
				'topic'           => $dto->topic,
				'primary_keyword' => $dto->primary_keyword,
				'word_count'      => $dto->recommended_word_count,
			)
		);

		$this->logger->info(
			'content_brief.generate.done',
			array(
				'primary_keyword' => $dto->primary_keyword,
			)
		);

		return $dto;
	}
}
