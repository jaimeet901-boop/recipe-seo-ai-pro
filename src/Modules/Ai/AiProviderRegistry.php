<?php
declare(strict_types=1);

/**
 * Resolves AiProviderInterface implementations by settings.ai_provider.
 *
 * Phase 2C: transport selection only. Does not build prompts or run features.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai;

use RecipeSeoAiPro\Contracts\AiProviderInterface;
use RecipeSeoAiPro\Modules\Ai\Providers\DisabledProvider;
use RecipeSeoAiPro\Modules\Ai\Providers\OpenAiCompatibleProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AiProviderRegistry
 */
final class AiProviderRegistry {

	/** @var array<string, AiProviderInterface> */
	private array $providers = array();

	/**
	 * @param AiProviderInterface[] $providers Optional initial providers.
	 */
	public function __construct( array $providers = array() ) {
		foreach ( $providers as $provider ) {
			if ( $provider instanceof AiProviderInterface ) {
				$this->register( $provider );
			}
		}
	}

	/**
	 * Register (or replace) a provider by its id().
	 */
	public function register( AiProviderInterface $provider ): void {
		$this->providers[ $provider->id() ] = $provider;
	}

	/**
	 * Whether a provider id is registered.
	 */
	public function has( string $id ): bool {
		return isset( $this->providers[ $id ] );
	}

	/**
	 * Get a provider by id. Unknown ids fall back to DisabledProvider.
	 */
	public function get( string $id ): AiProviderInterface {
		if ( isset( $this->providers[ $id ] ) ) {
			return $this->providers[ $id ];
		}

		if ( isset( $this->providers['disabled'] ) ) {
			return $this->providers['disabled'];
		}

		return new DisabledProvider();
	}

	/**
	 * Resolve the active provider from rsaip_settings.ai_provider.
	 *
	 * Historical RSAIP_AI::chat() did not gate on ai_provider (callers did).
	 * For HTTP parity when openai_compatible is selected, OpenAiCompatibleProvider
	 * runs the same request. When disabled, DisabledProvider returns WP_Error
	 * without network I/O.
	 */
	public function resolve(): AiProviderInterface {
		$settings = rsaip_get_settings();
		$id       = is_string( $settings['ai_provider'] ?? null ) ? (string) $settings['ai_provider'] : 'openai_compatible';
		$id       = sanitize_key( $id );
		if ( $id === '' ) {
			$id = 'openai_compatible';
		}

		return $this->get( $id );
	}

	/**
	 * Factory used when the DI container is not booted yet.
	 */
	public static function create_default(): self {
		return new self(
			array(
				new OpenAiCompatibleProvider(),
				new DisabledProvider(),
			)
		);
	}
}
