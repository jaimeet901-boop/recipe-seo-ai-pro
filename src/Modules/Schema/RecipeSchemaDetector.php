<?php
declare(strict_types=1);

/**
 * Authoritative Recipe Schema detector (Milestone 5B.1).
 *
 * Server-owned. Never trusts OpenAI. Does not write.
 *
 * Detection is based on post_content JSON-LD structure plus known external
 * recipe/SEO plugin signals. Rank Math / Yoast rendered head JSON-LD is not
 * reliably readable without an HTTP self-request; when those plugins are active
 * and content has no Recipe node, status is external_or_unknown (not missing).
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeSchemaDetector
 */
final class RecipeSchemaDetector {

	/**
	 * Detect from raw post HTML/content (unit-test friendly).
	 *
	 * @param array{
	 *   content?: string,
	 *   title?: string,
	 *   rank_math_active?: bool,
	 *   yoast_active?: bool,
	 *   external_recipe_plugin?: bool,
	 *   external_recipe_plugin_id?: string
	 * } $input
	 */
	public function detect_from_content( string $content, array $input = array() ): RecipeSchemaDetectionResult {
		$title = isset( $input['title'] ) ? trim( (string) $input['title'] ) : '';
		$rank_math = ! empty( $input['rank_math_active'] );
		$yoast     = ! empty( $input['yoast_active'] );
		$external_plugin = ! empty( $input['external_recipe_plugin'] );
		$external_id = isset( $input['external_recipe_plugin_id'] ) ? (string) $input['external_recipe_plugin_id'] : '';

		$has_card = $this->content_has_rsaip_card( $content );
		$nodes    = $this->extract_recipe_nodes( $content );
		$count    = count( $nodes );

		if ( $count > 1 ) {
			return new RecipeSchemaDetectionResult(
				RecipeSchemaStatus::MULTIPLE,
				'post_content',
				$this->first_recipe_name( $nodes, $title ),
				$count,
				'Multiple Recipe JSON-LD nodes found in post_content. Do not create another.',
				true,
				$has_card,
				false
			);
		}

		if ( $count === 1 ) {
			$node   = $nodes[0];
			$completeness = $this->node_completeness( $node );
			$name   = $this->node_recipe_name( $node, $title );
			$source = $has_card ? 'rsaip' : 'post_content';

			if ( $completeness === 'incomplete' ) {
				return new RecipeSchemaDetectionResult(
					RecipeSchemaStatus::INCOMPLETE,
					$source,
					$name,
					1,
					'Recipe JSON-LD found but missing recipeIngredient and/or recipeInstructions.',
					true,
					$has_card,
					false
				);
			}

			return new RecipeSchemaDetectionResult(
				RecipeSchemaStatus::VALID_RECIPE,
				$source,
				$name,
				1,
				'Valid Recipe JSON-LD detected in post_content.',
				true,
				$has_card,
				false
			);
		}

		// No Recipe node in content.
		$external_hit = $this->detect_external_in_content( $content );
		if ( $external_hit['hit'] || $external_plugin ) {
			$id = $external_plugin && $external_id !== '' ? $external_id : (string) $external_hit['id'];
			return new RecipeSchemaDetectionResult(
				RecipeSchemaStatus::EXTERNAL_OR_UNKNOWN,
				$id !== '' ? $id : 'external',
				$title,
				0,
				'External recipe system markers detected. Do not create a competing Recipe Schema.',
				true,
				$has_card,
				false
			);
		}

		if ( $rank_math || $yoast ) {
			$src = $rank_math ? 'rank_math' : 'yoast';
			return new RecipeSchemaDetectionResult(
				RecipeSchemaStatus::EXTERNAL_OR_UNKNOWN,
				$src,
				$title,
				0,
				'SEO plugin active; Recipe Schema may be injected at render time outside post_content. Not declared missing.',
				false,
				$has_card,
				false
			);
		}

		return new RecipeSchemaDetectionResult(
			RecipeSchemaStatus::MISSING,
			'none',
			$title,
			0,
			'No Recipe JSON-LD found in post_content and no external schema owner detected.',
			true,
			$has_card,
			true
		);
	}

	/**
	 * Detect for a live WordPress post (read-only).
	 */
	public function detect_for_post( int $post_id ): RecipeSchemaDetectionResult {
		$post_id = max( 0, $post_id );
		if ( $post_id <= 0 || ! function_exists( 'get_post' ) ) {
			return new RecipeSchemaDetectionResult(
				RecipeSchemaStatus::EXTERNAL_OR_UNKNOWN,
				'unknown',
				'',
				0,
				'Post unavailable for schema detection.',
				false,
				false,
				false
			);
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return new RecipeSchemaDetectionResult(
				RecipeSchemaStatus::EXTERNAL_OR_UNKNOWN,
				'unknown',
				'',
				0,
				'Post not found.',
				false,
				false,
				false
			);
		}

		$title   = function_exists( 'get_the_title' ) ? (string) get_the_title( $post_id ) : (string) $post->post_title;
		$content = (string) $post->post_content;
		$ext     = $this->detect_external_recipe_plugins();

		return $this->detect_from_content(
			$content,
			array(
				'title'                     => $title,
				'rank_math_active'          => $this->is_rank_math_active(),
				'yoast_active'              => $this->is_yoast_active(),
				'external_recipe_plugin'    => $ext['active'],
				'external_recipe_plugin_id' => $ext['id'],
			)
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function extract_recipe_nodes( string $content ): array {
		$nodes = array();
		if ( $content === '' ) {
			return $nodes;
		}

		if ( ! preg_match_all( '/<script[^>]*type\s*=\s*["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/isu', $content, $matches ) ) {
			return $nodes;
		}

		foreach ( (array) $matches[1] as $raw ) {
			$raw = trim( html_entity_decode( (string) $raw, ENT_QUOTES, 'UTF-8' ) );
			if ( $raw === '' ) {
				continue;
			}
			$decoded = json_decode( $raw, true );
			if ( ! is_array( $decoded ) ) {
				continue; // Malformed JSON: ignore (no false positive).
			}
			foreach ( $this->collect_recipe_nodes( $decoded ) as $node ) {
				$nodes[] = $node;
			}
		}

		return $nodes;
	}

	/**
	 * @param mixed $data Decoded JSON-LD.
	 * @return list<array<string, mixed>>
	 */
	private function collect_recipe_nodes( $data ): array {
		$out = array();
		if ( ! is_array( $data ) ) {
			return $out;
		}

		if ( $this->node_is_recipe( $data ) ) {
			$out[] = $data;
		}

		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			foreach ( $data['@graph'] as $node ) {
				foreach ( $this->collect_recipe_nodes( $node ) as $child ) {
					$out[] = $child;
				}
			}
		}

		// Walk nested arrays but avoid double-counting @graph already handled.
		foreach ( $data as $key => $value ) {
			if ( $key === '@graph' ) {
				continue;
			}
			if ( is_array( $value ) && $this->is_list_array( $value ) ) {
				foreach ( $value as $item ) {
					if ( is_array( $item ) && $this->node_is_recipe( $item ) ) {
						$out[] = $item;
					} elseif ( is_array( $item ) ) {
						foreach ( $this->collect_recipe_nodes( $item ) as $child ) {
							$out[] = $child;
						}
					}
				}
			} elseif ( is_array( $value ) && isset( $value['@type'] ) ) {
				foreach ( $this->collect_recipe_nodes( $value ) as $child ) {
					$out[] = $child;
				}
			}
		}

		return $this->unique_nodes( $out );
	}

	/**
	 * @param array<string, mixed> $node Node.
	 */
	private function node_is_recipe( array $node ): bool {
		if ( ! isset( $node['@type'] ) ) {
			return false;
		}
		$type = $node['@type'];
		if ( is_string( $type ) ) {
			return strcasecmp( $type, 'Recipe' ) === 0;
		}
		if ( is_array( $type ) ) {
			foreach ( $type as $t ) {
				if ( is_string( $t ) && strcasecmp( $t, 'Recipe' ) === 0 ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $node Node.
	 */
	private function node_completeness( array $node ): string {
		$has_ings  = $this->has_nonempty( $node['recipeIngredient'] ?? null );
		$has_steps = $this->has_nonempty( $node['recipeInstructions'] ?? null );
		if ( $has_ings && $has_steps ) {
			return 'complete';
		}
		// Name-only Recipe stubs are incomplete.
		return 'incomplete';
	}

	/**
	 * @param mixed $value Field.
	 */
	private function has_nonempty( $value ): bool {
		if ( is_string( $value ) ) {
			return trim( $value ) !== '';
		}
		if ( is_array( $value ) ) {
			return $value !== array();
		}
		return false;
	}

	/**
	 * @param list<array<string, mixed>> $nodes Nodes.
	 */
	private function first_recipe_name( array $nodes, string $fallback ): string {
		foreach ( $nodes as $node ) {
			$name = $this->node_recipe_name( $node, '' );
			if ( $name !== '' ) {
				return $name;
			}
		}
		return $fallback;
	}

	/**
	 * @param array<string, mixed> $node Node.
	 */
	private function node_recipe_name( array $node, string $fallback ): string {
		if ( isset( $node['name'] ) && is_string( $node['name'] ) && trim( $node['name'] ) !== '' ) {
			return trim( $node['name'] );
		}
		return $fallback;
	}

	public function content_has_rsaip_card( string $content ): bool {
		return strpos( $content, 'RSAIP_RECIPE_CARD_START' ) !== false
			|| strpos( $content, 'rsaip-recipe-card' ) !== false;
	}

	/**
	 * @return array{hit: bool, id: string}
	 */
	private function detect_external_in_content( string $content ): array {
		$patterns = array(
			'wp_recipe_maker' => array( 'wprm-recipe', '[wprm-recipe', 'wp-recipe-maker' ),
			'tasty_recipes'   => array( 'tasty-recipes', '[tasty-recipe', 'tasty_recipe' ),
			'yumprint'        => array( 'yumprint', 'wp-yumprint' ),
			'mediavine_create'=> array( 'mv-create', 'mediavine' ),
		);
		$lower = strtolower( $content );
		foreach ( $patterns as $id => $needles ) {
			foreach ( $needles as $needle ) {
				if ( strpos( $lower, strtolower( $needle ) ) !== false ) {
					return array( 'hit' => true, 'id' => $id );
				}
			}
		}
		return array( 'hit' => false, 'id' => '' );
	}

	/**
	 * @return array{active: bool, id: string}
	 */
	private function detect_external_recipe_plugins(): array {
		$map = array(
			'wp-recipe-maker/wp-recipe-maker.php' => 'wp_recipe_maker',
			'tasty-recipes/tasty-recipes.php'     => 'tasty_recipes',
			'wp-ultimate-recipe/wp-ultimate-recipe.php' => 'wp_ultimate_recipe',
		);
		foreach ( $map as $plugin => $id ) {
			if ( $this->plugin_active( $plugin ) ) {
				return array( 'active' => true, 'id' => $id );
			}
		}
		return array( 'active' => false, 'id' => '' );
	}

	private function is_rank_math_active(): bool {
		return $this->plugin_active( 'seo-by-rank-math/rank-math.php' )
			|| ( defined( 'RANK_MATH_VERSION' ) )
			|| class_exists( '\\RankMath' );
	}

	private function is_yoast_active(): bool {
		return $this->plugin_active( 'wordpress-seo/wp-seo.php' )
			|| ( defined( 'WPSEO_VERSION' ) );
	}

	private function plugin_active( string $plugin ): bool {
		if ( function_exists( 'is_plugin_active' ) ) {
			return (bool) is_plugin_active( $plugin );
		}
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}
		$active = get_option( 'active_plugins', array() );
		if ( ! is_array( $active ) ) {
			return false;
		}
		return in_array( $plugin, $active, true );
	}

	/**
	 * @param array<int|string, mixed> $value Array.
	 */
	private function is_list_array( array $value ): bool {
		if ( $value === array() ) {
			return true;
		}
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * @param list<array<string, mixed>> $nodes Nodes.
	 * @return list<array<string, mixed>>
	 */
	private function unique_nodes( array $nodes ): array {
		$out  = array();
		$seen = array();
		foreach ( $nodes as $node ) {
			$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $node ) : json_encode( $node );
			$key     = md5( is_string( $encoded ) ? $encoded : serialize( $node ) );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $node;
		}
		return $out;
	}
}
