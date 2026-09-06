<?php
declare(strict_types=1);

/**
 * CustomOpenAiHubProvider — AI Hub adapter.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\Hub\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CustomOpenAiHubProvider
 */
final class CustomOpenAiHubProvider extends OpenAiStyleHubProvider {

	/**
	 * @inheritDoc
	 */
	public function id(): string {
		return 'custom';
	}
}
