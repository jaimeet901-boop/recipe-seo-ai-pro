<?php
declare(strict_types=1);

/**
 * OpenRouterHubProvider — AI Hub adapter.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OpenRouterHubProvider
 */
final class OpenRouterHubProvider extends OpenAiStyleHubProvider {

	/**
	 * @inheritDoc
	 */
	public function id(): string {
		return 'openrouter';
	}

	/**
	 * OpenRouter recommends Referer / Title headers.
	 *
	 * @return array<string, string>
	 */
	protected function auth_headers( string $key ): array {
		$headers = parent::auth_headers( $key );
		$headers['HTTP-Referer'] = home_url( '/' );
		$headers['X-Title']      = 'Recipe SEO AI Pro';
		return $headers;
	}
}
