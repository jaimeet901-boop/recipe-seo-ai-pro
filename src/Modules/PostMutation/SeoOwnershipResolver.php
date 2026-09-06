<?php
declare(strict_types=1);

/**
 * Hard SEO ownership policy for PostMutation.
 *
 * Rank Math active => Rank Math owns.
 * Yoast only => Yoast owns.
 * Both => Rank Math owns.
 * Neither => RSAIP fallback.
 * Client target may only narrow to the server owner; never override.
 *
 * Keyword / meta write targets (no automatic RSAIP mirror):
 * - Rank Math owner => Rank Math keys only (never also write rsaip_generated_*).
 * - Yoast owner => Yoast keys only (never also write rsaip_generated_*).
 * - RSAIP owner (no SEO plugin) => rsaip_generated_keywords / rsaip_generated_metadesc only.
 *
 * Historical rsaip_generated_* post meta left by legacy helpers is not rewritten or deleted here.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\PostMutation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SeoOwnershipResolver
 */
final class SeoOwnershipResolver {

	private PostMutationEnvironment $env;

	public function __construct( PostMutationEnvironment $env ) {
		$this->env = $env;
	}

	/**
	 * Server-authoritative owner (ignores client).
	 */
	public function resolve_server_owner(): string {
		if ( $this->env->is_rankmath_active() ) {
			return SeoOwner::RANKMATH;
		}
		if ( $this->env->is_yoast_active() ) {
			return SeoOwner::YOAST;
		}
		return SeoOwner::RSAIP;
	}

	/**
	 * Apply optional client narrow. Returns owner or empty string on illegal override.
	 *
	 * @param string|null $client_target auto|rankmath|yoast|rsaip|null
	 */
	public function resolve( ?string $client_target = null ): string {
		$owner = $this->resolve_server_owner();
		if ( $client_target === null || $client_target === '' || $client_target === 'auto' ) {
			return $owner;
		}

		$wanted = strtolower( trim( $client_target ) );
		$wanted = preg_replace( '/[^a-z0-9_]/', '', $wanted ) ?? '';

		// Historical aliases.
		if ( $wanted === 'rank_math' ) {
			$wanted = SeoOwner::RANKMATH;
		}

		if ( $wanted === $owner ) {
			return $owner;
		}

		// Illegal override attempt.
		return '';
	}

	/**
	 * Meta keys this owner is allowed to write for a mutation type.
	 *
	 * Exactly one owner key is returned. Rank Math / Yoast ownership never
	 * includes rsaip_generated_keywords or rsaip_generated_metadesc (no mirror).
	 *
	 * @return list<string>
	 */
	public function allowed_meta_keys( string $owner, string $mutation_type ): array {
		if ( $mutation_type === MutationType::TITLE ) {
			return array();
		}

		if ( $mutation_type === MutationType::META_DESCRIPTION ) {
			if ( $owner === SeoOwner::RANKMATH ) {
				return array( 'rank_math_description' );
			}
			if ( $owner === SeoOwner::YOAST ) {
				return array( '_yoast_wpseo_metadesc' );
			}
			if ( $owner === SeoOwner::RSAIP ) {
				return array( 'rsaip_generated_metadesc' );
			}
			return array();
		}

		if ( $mutation_type === MutationType::FOCUS_KEYWORD ) {
			if ( $owner === SeoOwner::RANKMATH ) {
				return array( 'rank_math_focus_keyword' );
			}
			if ( $owner === SeoOwner::YOAST ) {
				return array( '_yoast_wpseo_focuskw' );
			}
			if ( $owner === SeoOwner::RSAIP ) {
				return array( 'rsaip_generated_keywords' );
			}
			return array();
		}

		if ( $mutation_type === MutationType::KEYWORDS ) {
			if ( $owner === SeoOwner::RANKMATH ) {
				return array( 'rank_math_focus_keyword' );
			}
			if ( $owner === SeoOwner::YOAST ) {
				return array( '_yoast_wpseo_focuskw' );
			}
			if ( $owner === SeoOwner::RSAIP ) {
				return array( 'rsaip_generated_keywords' );
			}
			return array();
		}

		return array();
	}
}
