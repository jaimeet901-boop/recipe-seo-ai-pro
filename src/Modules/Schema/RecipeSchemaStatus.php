<?php
declare(strict_types=1);

/**
 * Authoritative Recipe Schema detection states (Milestone 5B.1).
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeSchemaStatus
 */
final class RecipeSchemaStatus {

	public const VALID_RECIPE        = 'valid_recipe';
	public const MISSING             = 'missing';
	public const INCOMPLETE          = 'incomplete';
	public const EXTERNAL_OR_UNKNOWN = 'external_or_unknown';
	public const MULTIPLE            = 'multiple';

	/**
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::VALID_RECIPE,
			self::MISSING,
			self::INCOMPLETE,
			self::EXTERNAL_OR_UNKNOWN,
			self::MULTIPLE,
		);
	}

	public static function is_known( string $status ): bool {
		return in_array( $status, self::all(), true );
	}
}
