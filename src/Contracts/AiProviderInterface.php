<?php
declare(strict_types=1);

/**
 * Contract for AI chat/completion HTTP providers.
 *
 * Phase 2C: providers own remote transport only. Prompt building, heuristics,
 * and feature orchestration remain in RSAIP_AI.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface AiProviderInterface
 */
interface AiProviderInterface {

	/**
	 * Provider identifier matching settings.ai_provider (e.g. openai_compatible).
	 */
	public function id(): string;

	/**
	 * Send a completion request over HTTP (or no-op / error for disabled).
	 *
	 * @param string               $prompt  User prompt (task instructions).
	 * @param array<string, mixed> $options Options; supports max_tokens (int),
	 *                                      system (string), article_content (string).
	 * @return string|\WP_Error Assistant text on success; WP_Error on failure.
	 */
	public function complete( string $prompt, array $options = array() );
}
