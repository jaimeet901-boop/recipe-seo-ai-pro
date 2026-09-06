<?php
declare(strict_types=1);

/**
 * No-op AI provider when settings.ai_provider = disabled.
 *
 * Does not perform HTTP. Returns the same config-style WP_Error shape used when
 * AI is unavailable so RSAIP_AI callers keep treating failures uniformly.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\Providers;

use RecipeSeoAiPro\Contracts\AiProviderInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DisabledProvider
 */
final class DisabledProvider implements AiProviderInterface {

	/**
	 * @inheritDoc
	 */
	public function id(): string {
		return 'disabled';
	}

	/**
	 * @inheritDoc
	 */
	public function complete( string $prompt, array $options = array() ) {
		unset( $prompt, $options );
		return new \WP_Error( 'rsaip_ai_config', 'Missing AI configuration' );
	}
}
