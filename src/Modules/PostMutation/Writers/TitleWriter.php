<?php
declare(strict_types=1);

/**
 * Title-only writer. Never touches post_content or meta.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation\Writers;

use RecipeSeoAiPro\Modules\PostMutation\MutationType;
use RecipeSeoAiPro\Modules\PostMutation\MutationWriter;
use RecipeSeoAiPro\Modules\PostMutation\PostMutationEnvironment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TitleWriter
 */
final class TitleWriter implements MutationWriter {

	private PostMutationEnvironment $env;

	public function __construct( PostMutationEnvironment $env ) {
		$this->env = $env;
	}

	public function mutation_type(): string {
		return MutationType::TITLE;
	}

	public function read_current( int $post_id, string $owner ) {
		unset( $owner );
		$post = $this->env->get_post( $post_id );
		return $post ? (string) $post['post_title'] : '';
	}

	public function write( int $post_id, string $owner, $new_value ): bool {
		unset( $owner );
		$title = $this->normalize_new_value( $new_value );
		if ( ! is_string( $title ) || $title === '' ) {
			return false;
		}
		return $this->env->update_post_title( $post_id, $title );
	}

	public function values_match( $expected, $actual ): bool {
		return trim( (string) $expected ) === trim( (string) $actual );
	}

	public function normalize_new_value( $value ) {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return null;
		}
		$title = preg_replace( '/\s+/u', ' ', trim( (string) $value ) );
		$title = is_string( $title ) ? $title : '';
		if ( function_exists( 'mb_substr' ) ) {
			$title = mb_substr( $title, 0, 60 );
		} else {
			$title = substr( $title, 0, 60 );
		}
		return $title === '' ? null : $title;
	}
}
