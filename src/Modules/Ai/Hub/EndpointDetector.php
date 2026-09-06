<?php
declare(strict_types=1);

/**
 * Detect AI provider id from an endpoint URL.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EndpointDetector
 */
final class EndpointDetector {

	/**
	 * Map endpoint URL → hub provider id (or empty when unknown).
	 */
	public static function detect( string $endpoint ): string {
		$endpoint = strtolower( trim( $endpoint ) );
		if ( $endpoint === '' ) {
			return '';
		}

		$host = (string) ( wp_parse_url( $endpoint, PHP_URL_HOST ) ?? '' );
		$path = (string) ( wp_parse_url( $endpoint, PHP_URL_PATH ) ?? '' );
		$port = (int) ( wp_parse_url( $endpoint, PHP_URL_PORT ) ?? 0 );

		if ( $host === 'api.openai.com' ) {
			return 'openai';
		}
		if ( $host === 'api.deepseek.com' ) {
			return 'deepseek';
		}
		if ( $host === 'openrouter.ai' || strpos( $endpoint, 'openrouter.ai/api' ) !== false ) {
			return 'openrouter';
		}
		if ( $host === 'api.groq.com' ) {
			return 'groq';
		}
		if ( $host === 'api.x.ai' ) {
			return 'xai';
		}
		if ( $host === 'api.together.xyz' || $host === 'api.together.ai' ) {
			return 'together';
		}
		if ( $host === 'api.mistral.ai' ) {
			return 'mistral';
		}
		if ( $host === 'api.anthropic.com' ) {
			return 'anthropic';
		}
		if ( $host === 'generativelanguage.googleapis.com' || strpos( $endpoint, 'googleapis.com/v1beta' ) !== false ) {
			return 'gemini';
		}
		if ( $host === 'api.cohere.ai' || $host === 'api.cohere.com' ) {
			return 'cohere';
		}
		if ( strpos( $host, 'openai.azure.com' ) !== false || strpos( $host, '.cognitiveservices.azure.com' ) !== false ) {
			return 'azure_openai';
		}
		if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
			if ( $port === 11434 || strpos( $path, '/api/' ) !== false ) {
				return 'ollama';
			}
			if ( $port === 1234 ) {
				return 'lmstudio';
			}
			// Generic local OpenAI-compatible.
			return 'custom';
		}

		return '';
	}
}
