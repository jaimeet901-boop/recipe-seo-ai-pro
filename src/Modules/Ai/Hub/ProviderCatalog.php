<?php
declare(strict_types=1);

/**
 * Built-in AI Hub provider metadata (ids, labels, defaults).
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProviderCatalog
 */
final class ProviderCatalog {

	/**
	 * @return list<string>
	 */
	public static function ids(): array {
		return array(
			'openai',
			'deepseek',
			'gemini',
			'anthropic',
			'openrouter',
			'groq',
			'xai',
			'together',
			'mistral',
			'cohere',
			'ollama',
			'lmstudio',
			'azure_openai',
			'custom',
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			'openai'       => 'OpenAI',
			'deepseek'     => 'DeepSeek',
			'gemini'       => 'Google Gemini',
			'anthropic'    => 'Anthropic Claude',
			'openrouter'   => 'OpenRouter',
			'groq'         => 'Groq',
			'xai'          => 'xAI (Grok)',
			'together'     => 'Together AI',
			'mistral'      => 'Mistral AI',
			'cohere'       => 'Cohere',
			'ollama'       => 'Ollama',
			'lmstudio'     => 'LM Studio',
			'azure_openai' => 'Azure OpenAI',
			'custom'       => 'Custom OpenAI-Compatible',
		);
	}

	public static function label( string $id ): string {
		$labels = self::labels();
		$id     = sanitize_key( $id );
		return $labels[ $id ] ?? $id;
	}

	public static function default_endpoint( string $id ): string {
		$map = array(
			'openai'       => 'https://api.openai.com/v1/chat/completions',
			'deepseek'     => 'https://api.deepseek.com/chat/completions',
			'gemini'       => 'https://generativelanguage.googleapis.com/v1beta',
			'anthropic'    => 'https://api.anthropic.com/v1/messages',
			'openrouter'   => 'https://openrouter.ai/api/v1/chat/completions',
			'groq'         => 'https://api.groq.com/openai/v1/chat/completions',
			'xai'          => 'https://api.x.ai/v1/chat/completions',
			'together'     => 'https://api.together.xyz/v1/chat/completions',
			'mistral'      => 'https://api.mistral.ai/v1/chat/completions',
			'cohere'       => 'https://api.cohere.ai/v2/chat',
			'ollama'       => 'http://localhost:11434/v1/chat/completions',
			'lmstudio'     => 'http://localhost:1234/v1/chat/completions',
			'azure_openai' => '',
			'custom'       => '',
		);
		$id = sanitize_key( $id );
		return $map[ $id ] ?? '';
	}

	public static function default_model( string $id ): string {
		$map = array(
			'openai'       => 'gpt-4o-mini',
			'deepseek'     => 'deepseek-chat',
			'gemini'       => 'gemini-1.5-flash',
			'anthropic'    => 'claude-3-5-haiku-latest',
			'openrouter'   => 'openai/gpt-4o-mini',
			'groq'         => 'llama-3.3-70b-versatile',
			'xai'          => 'grok-2-latest',
			'together'     => 'meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo',
			'mistral'      => 'mistral-small-latest',
			'cohere'       => 'command-r-plus',
			'ollama'       => 'llama3.2',
			'lmstudio'     => 'local-model',
			'azure_openai' => 'gpt-4o-mini',
			'custom'       => 'gpt-4o-mini',
		);
		$id = sanitize_key( $id );
		return $map[ $id ] ?? 'gpt-4o-mini';
	}

	/**
	 * Whether provider typically needs an API key.
	 */
	public static function requires_api_key( string $id ): bool {
		return ! in_array( sanitize_key( $id ), array( 'ollama', 'lmstudio' ), true );
	}

	/**
	 * Whether the Hub may safely attach OpenAI-style response_format to chat bodies.
	 *
	 * Opt-in only for known-compatible providers. DeepSeek and others are excluded
	 * so existing callers without response_format stay unchanged and unsafe globals
	 * are never forced on every OpenAI-compatible endpoint.
	 */
	public static function supports_json_response_format( string $id ): bool {
		return in_array(
			self::normalize_id( $id ),
			array( 'openai', 'azure_openai' ),
			true
		);
	}

	private static function normalize_id( string $id ): string {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $id );
		}
		$id = strtolower( $id );
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', $id );
	}
}
