<?php
declare(strict_types=1);

/**
 * Backward-compatible AI transport facade (id: openai_compatible).
 *
 * Feature modules continue calling get('openai_compatible')->complete().
 * All provider selection, failover, logging, and official API adapters live
 * in the AI Provider Hub (ProviderManager).
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Providers;

use RecipeSeoAiPro\Contracts\AiProviderInterface;
use RecipeSeoAiPro\Modules\Ai\Hub\ProviderManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OpenAiCompatibleProvider
 */
final class OpenAiCompatibleProvider implements AiProviderInterface {

	private ?ProviderManager $manager;

	public function __construct( ?ProviderManager $manager = null ) {
		$this->manager = $manager;
	}

	/**
	 * @inheritDoc
	 */
	public function id(): string {
		return 'openai_compatible';
	}

	/**
	 * @inheritDoc
	 */
	public function complete( string $prompt, array $options = array() ) {
		$manager = $this->manager;
		if ( ! $manager instanceof ProviderManager ) {
			$manager = ProviderManager::instance();
		}

		return $manager->complete_with_failover( $prompt, $options );
	}
}
