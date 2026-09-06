<?php
declare(strict_types=1);

/**
 * Strict allowlist of PostMutation types (Milestone 2).
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MutationType
 */
final class MutationType {

	public const TITLE            = 'title';
	public const META_DESCRIPTION = 'meta_description';

	/**
	 * Primary-only keyword mutation. Reserved for a future dedicated control.
	 * Existing AI "Apply Keywords" UI/AJAX must NOT use this type.
	 */
	public const FOCUS_KEYWORD = 'focus_keyword';

	/**
	 * Keyword list mutation. Existing AI "Apply Keywords" maps exclusively here.
	 */
	public const KEYWORDS = 'keywords';

	/**
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::TITLE,
			self::META_DESCRIPTION,
			self::FOCUS_KEYWORD,
			self::KEYWORDS,
		);
	}

	public static function is_valid( string $type ): bool {
		return in_array( $type, self::all(), true );
	}

	/**
	 * Normalize and validate. Returns empty string when invalid.
	 */
	public static function sanitize( string $type ): string {
		$key = strtolower( trim( $type ) );
		$key = preg_replace( '/[^a-z0-9_]/', '', $key ) ?? '';
		return self::is_valid( $key ) ? $key : '';
	}
}
