<?php
declare(strict_types=1);

/**
 * Recipe fact availability for article generation (Article Generator A).
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\ArticleGeneration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeFactAvailability
 */
final class RecipeFactAvailability {

	public const KNOWN = 'known';
	public const UNAVAILABLE = 'unavailable';

	/**
	 * @return list<string>
	 */
	public static function all(): array {
		return array( self::KNOWN, self::UNAVAILABLE );
	}

	public static function is_known( string $status ): bool {
		return $status === self::KNOWN;
	}
}
