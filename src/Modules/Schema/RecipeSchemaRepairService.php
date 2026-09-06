<?php
declare(strict_types=1);

/**
 * Recipe Schema repair boundary (Milestone 5B.1).
 *
 * INTENTIONALLY DEFERRED: safe write/repair is not enabled in this milestone.
 * Analyze remains read-only. Do not call PostMutationService for schema.
 * Do not use RSAIP_Schema_Validator::fix_post_schema() (Article schema).
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeSchemaRepairService
 */
final class RecipeSchemaRepairService {

	private RecipeSchemaDetector $detector;

	public function __construct( ?RecipeSchemaDetector $detector = null ) {
		$this->detector = $detector instanceof RecipeSchemaDetector ? $detector : new RecipeSchemaDetector();
	}

	/**
	 * Explicit repair entrypoint — always fail-closed in 5B.1.
	 *
	 * Future implementation must: capability+nonce (AJAX), Safe Mode OFF,
	 * re-detect before write, abort on valid/external/multiple, write Recipe
	 * JSON-LD only from server recipe data, never AI JSON, never Rank Math
	 * overwrite, verify after write.
	 *
	 * @return array{ok: bool, code: string, message: string, detection?: array<string, mixed>}
	 */
	public function repair( int $post_id ): array {
		$detection = $this->detector->detect_for_post( $post_id );

		if ( function_exists( 'rsaip_never_modify_posts' ) && rsaip_never_modify_posts() ) {
			return array(
				'ok'         => false,
				'code'       => 'rsaip_recipe_schema_safe_mode',
				'message'    => 'Recipe Schema repair is blocked while Safe Article Mode is enabled.',
				'detection'  => $detection->to_array(),
			);
		}

		if ( ! $detection->repair_allowed() || $detection->status() !== RecipeSchemaStatus::MISSING ) {
			return array(
				'ok'        => false,
				'code'      => 'rsaip_recipe_schema_abort_state',
				'message'   => 'Repair aborted: current detector state does not allow creating Recipe Schema (' . $detection->status() . ').',
				'detection' => $detection->to_array(),
			);
		}

		return array(
			'ok'        => false,
			'code'      => 'rsaip_recipe_schema_repair_deferred',
			'message'   => 'Safe Recipe Schema repair is deferred. Detection is authoritative; no write was performed.',
			'detection' => $detection->to_array(),
		);
	}
}
