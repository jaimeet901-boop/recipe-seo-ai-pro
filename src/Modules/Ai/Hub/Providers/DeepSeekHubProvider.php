<?php
declare(strict_types=1);

/**
 * DeepSeekHubProvider — AI Hub adapter.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DeepSeekHubProvider
 */
final class DeepSeekHubProvider extends OpenAiStyleHubProvider {

	/**
	 * @inheritDoc
	 */
	public function id(): string {
		return 'deepseek';
	}
}
