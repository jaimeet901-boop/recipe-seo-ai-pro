<?php
declare(strict_types=1);

/**
 * Prompt builder for AI Recipe Assistant (Phase 5.2).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\RecipeAI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipePromptBuilder
 */
final class RecipePromptBuilder {

	/**
	 * @param array<string, mixed> $recipe Recipe payload.
	 */
	public function build( string $action, string $mode, array $recipe, string $extra = '' ): string {
		$json = (string) wp_json_encode( $recipe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$base = "You are an expert culinary editor and recipe SEO specialist.\n"
			. "Return ONLY valid JSON (no markdown fences).\n"
			. "Recipe JSON:\n{$json}\n\n";

		switch ( $action ) {
			case 'ingredients':
				return $base . $this->ingredients_prompt( $mode, $extra );
			case 'instructions':
				return $base . $this->instructions_prompt( $mode, $extra );
			case 'rewrite':
				return $base . $this->rewrite_prompt( $mode );
			case 'variation':
				return $base . $this->variation_prompt( $mode );
			case 'improve':
				return $base . $this->improve_prompt();
			case 'analyze':
				return $base . $this->analyze_prompt();
			case 'nutrition':
				return $base . $this->nutrition_prompt();
			default:
				return $base . 'Provide helpful recipe improvements as JSON with keys: summary, suggestions (array of strings), recipe (full updated recipe object).';
		}
	}

	private function ingredients_prompt( string $mode, string $extra ): string {
		$focus = array(
			'missing'     => 'Suggest missing ingredients that would improve the recipe.',
			'substitutes' => 'Suggest substitutes for each major ingredient.',
			'premium'     => 'Suggest premium alternatives for key ingredients.',
			'budget'      => 'Suggest budget-friendly alternatives.',
			'duplicates'  => 'Detect duplicate or redundant ingredients.',
			'all'         => 'Cover missing, substitutes, premium, budget, and duplicates.',
		);
		$line = $focus[ $mode ] ?? $focus['all'];
		return "Task: Ingredient Assistant — {$line}\n"
			. ( $extra !== '' ? "Notes: {$extra}\n" : '' )
			. "JSON shape:\n"
			. "{\n"
			. "  \"summary\": string,\n"
			. "  \"missing\": [string],\n"
			. "  \"substitutes\": [{\"ingredient\":string,\"alternatives\":[string]}],\n"
			. "  \"premium_alternatives\": [{\"ingredient\":string,\"alternative\":string}],\n"
			. "  \"budget_alternatives\": [{\"ingredient\":string,\"alternative\":string}],\n"
			. "  \"duplicates\": [string],\n"
			. "  \"recipe\": { full updated recipe with ingredients/steps/sections/title/description/servings/prep_time/cook_time/total_time/notes/tips/equipment }\n"
			. '}';
	}

	private function instructions_prompt( string $mode, string $extra ): string {
		$focus = array(
			'improve'  => 'Improve clarity and technique of each step.',
			'rewrite'  => 'Rewrite all steps for clarity and flow.',
			'simplify' => 'Simplify the cooking process for home cooks.',
			'tips'     => 'Add cooking tips into or after relevant steps.',
			'chef'     => 'Add chef notes for professional technique.',
			'all'      => 'Improve, simplify where useful, and add tips/chef notes.',
		);
		$line = $focus[ $mode ] ?? $focus['all'];
		return "Task: Instruction Assistant — {$line}\n"
			. ( $extra !== '' ? "Notes: {$extra}\n" : '' )
			. "JSON shape:\n"
			. "{\n"
			. "  \"summary\": string,\n"
			. "  \"tips\": [string],\n"
			. "  \"chef_notes\": [string],\n"
			. "  \"recipe\": { full updated recipe object with improved steps }\n"
			. '}';
	}

	private function rewrite_prompt( string $mode ): string {
		$modes = array(
			'beginner_friendly' => 'Beginner Friendly — simple language, clear cues, low intimidation.',
			'professional_chef' => 'Professional Chef — precise technique and culinary vocabulary.',
			'restaurant_style'  => 'Restaurant Style — plated, service-ready tone.',
			'seo_optimized'     => 'SEO Optimized — keyword-rich title/description without stuffing.',
			'family_friendly'   => 'Family Friendly — approachable, kid-conscious, weeknight-ready.',
		);
		$label = $modes[ $mode ] ?? $modes['beginner_friendly'];
		return "Task: Rewrite the entire recipe in mode: {$label}\n"
			. "Preserve quantities unless the mode requires safer/simpler amounts.\n"
			. "JSON shape: {\"summary\":string,\"mode\":string,\"recipe\":{...full recipe...}}";
	}

	private function variation_prompt( string $mode ): string {
		$diets = array(
			'keto'         => 'Keto',
			'low_carb'     => 'Low Carb',
			'gluten_free'  => 'Gluten Free',
			'vegan'        => 'Vegan',
			'vegetarian'   => 'Vegetarian',
			'dairy_free'   => 'Dairy Free',
			'high_protein' => 'High Protein',
			'low_fat'      => 'Low Fat',
		);
		$label = $diets[ $mode ] ?? 'Gluten Free';
		return "Task: Create a {$label} variation of this recipe.\n"
			. "Adapt ingredients and steps realistically. Note substitutions in tips.\n"
			. "JSON shape: {\"summary\":string,\"variation\":string,\"recipe\":{...full recipe...},\"substitutions\":[string]}";
	}

	private function improve_prompt(): string {
		return "Task: Suggest recipe improvements for title, description, serving suggestions, storage tips, reheating tips.\n"
			. "JSON shape:\n"
			. "{\n"
			. "  \"summary\": string,\n"
			. "  \"better_title\": string,\n"
			. "  \"better_description\": string,\n"
			. "  \"serving_suggestions\": [string],\n"
			. "  \"storage_tips\": [string],\n"
			. "  \"reheating_tips\": [string],\n"
			. "  \"recipe\": { full recipe with improvements applied to title/description/notes/tips }\n"
			. '}';
	}

	private function analyze_prompt(): string {
		return "Task: Recipe Quality Analyzer.\n"
			. "Score each dimension 0-100 and explain briefly.\n"
			. "JSON shape:\n"
			. "{\n"
			. "  \"summary\": string,\n"
			. "  \"quality_score\": number,\n"
			. "  \"ingredient_completeness\": number,\n"
			. "  \"instruction_quality\": number,\n"
			. "  \"seo_score\": number,\n"
			. "  \"schema_completeness\": number,\n"
			. "  \"cooking_difficulty\": \"easy\"|\"medium\"|\"hard\",\n"
			. "  \"issues\": [string],\n"
			. "  \"recommendations\": [string]\n"
			. '}';
	}

	private function nutrition_prompt(): string {
		return "Task: Suggest approximate nutrition guidance and ingredient tweaks (not medical advice).\n"
			. "JSON shape:\n"
			. "{\n"
			. "  \"summary\": string,\n"
			. "  \"estimated_per_serving\": {\"calories\":number,\"protein_g\":number,\"carbs_g\":number,\"fat_g\":number},\n"
			. "  \"suggestions\": [string],\n"
			. "  \"recipe\": { optional lightly updated recipe if nutrition tweaks are proposed }\n"
			. '}';
	}
}
