<?php
declare(strict_types=1);

/**
 * SEO metadata ownership identifiers.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SeoOwner
 */
final class SeoOwner {

	public const RANKMATH = 'rankmath';
	public const YOAST    = 'yoast';
	public const RSAIP    = 'rsaip';
	public const NONE     = 'none';

	/**
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::RANKMATH,
			self::YOAST,
			self::RSAIP,
			self::NONE,
		);
	}

	public static function is_valid( string $owner ): bool {
		return in_array( $owner, self::all(), true );
	}
}
