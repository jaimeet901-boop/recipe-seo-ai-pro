<?php
declare(strict_types=1);

/**
 * OpenAiHubProvider — AI Hub adapter.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OpenAiHubProvider
 */
final class OpenAiHubProvider extends OpenAiStyleHubProvider {

	/**
	 * @inheritDoc
	 */
	public function id(): string {
		return 'openai';
	}
}
