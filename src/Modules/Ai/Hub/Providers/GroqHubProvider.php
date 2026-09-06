<?php
declare(strict_types=1);

/**
 * GroqHubProvider — AI Hub adapter.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GroqHubProvider
 */
final class GroqHubProvider extends OpenAiStyleHubProvider {

	/**
	 * @inheritDoc
	 */
	public function id(): string {
		return 'groq';
	}
}
