<?php
declare(strict_types=1);

/**
 * Rich AI Hub provider contract.
 *
 * Extends the legacy AiProviderInterface so feature modules keep calling
 * complete() only. Hub adapters implement the full surface; selection and
 * failover stay inside ProviderManager.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface AiHubProviderInterface
 */
interface AiHubProviderInterface extends AiProviderInterface {

	/**
	 * Human-readable provider name.
	 */
	public function label(): string;

	/**
	 * Apply / validate connection settings for this provider instance.
	 *
	 * @param array<string, mixed> $config Provider config.
	 * @return true|\WP_Error
	 */
	public function connect( array $config = array() );

	/**
	 * Chat with explicit messages.
	 *
	 * @param list<array{role: string, content: string}> $messages Messages.
	 * @param array<string, mixed>                       $options  Options.
	 * @return string|\WP_Error
	 */
	public function chat( array $messages, array $options = array() );

	/**
	 * Stream a chat response when supported; otherwise fall back to chat().
	 * Invokes $options['on_chunk'] callable with string chunks when present.
	 *
	 * @param list<array{role: string, content: string}> $messages Messages.
	 * @param array<string, mixed>                       $options  Options.
	 * @return string|\WP_Error Full assembled text.
	 */
	public function stream( array $messages, array $options = array() );

	/**
	 * Create embeddings when the provider supports it.
	 *
	 * @param string               $input   Input text.
	 * @param array<string, mixed> $options Options.
	 * @return array<int, float>|\WP_Error
	 */
	public function embeddings( string $input, array $options = array() );

	/**
	 * List available models (may use cache).
	 *
	 * @return list<string>|\WP_Error
	 */
	public function models();

	/**
	 * Live connection test with diagnostics.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function testConnection();

	/**
	 * Lightweight health snapshot (status, latency, last check).
	 *
	 * @return array<string, mixed>
	 */
	public function health(): array;

	/**
	 * Estimate USD cost for token usage (best-effort).
	 */
	public function estimateCost( int $prompt_tokens, int $completion_tokens ): float;

	/**
	 * Default chat/completions endpoint for this provider.
	 */
	public function defaultEndpoint(): string;

	/**
	 * Default model id.
	 */
	public function defaultModel(): string;
}
