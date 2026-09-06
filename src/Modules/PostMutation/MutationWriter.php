<?php
declare(strict_types=1);

/**
 * Writer contract for a single mutation type.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface MutationWriter
 */
interface MutationWriter {

	public function mutation_type(): string;

	/**
	 * @return mixed Canonical current value for fingerprinting / verify.
	 */
	public function read_current( int $post_id, string $owner );

	/**
	 * Write using server-resolved owner targets only. Never accepts client meta keys.
	 *
	 * @param mixed $new_value New value.
	 */
	public function write( int $post_id, string $owner, $new_value ): bool;

	/**
	 * @param mixed $expected Expected value after write.
	 */
	public function values_match( $expected, $actual ): bool;

	/**
	 * Normalize inbound proposal value or return null if invalid.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed|null
	 */
	public function normalize_new_value( $value );
}
