<?php
declare(strict_types=1);

/**
 * Content Optimizer DTO (Phase 4.1).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentOptimizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ContentOptimizerDTO
 */
final class ContentOptimizerDTO {

	public int $id = 0;

	public int $post_id = 0;

	public string $workflow = '';

	public string $scope = 'full_article';

	public string $status = 'draft';

	public int $seo_score = 0;

	public int $readability_score = 0;

	public int $eeat_score = 0;

	public int $recipe_score = 0;

	public string $title = '';

	public string $meta_description = '';

	public string $original_content = '';

	public string $optimized_content = '';

	public string $original_title = '';

	public string $optimized_title = '';

	public string $original_meta = '';

	public string $optimized_meta = '';

	/** @var array<string, mixed> */
	public array $analysis = array();

	/** @var list<array<string, mixed>> */
	public array $diff = array();

	/** @var list<array<string, mixed>> */
	public array $versions = array();

	public int $user_id = 0;

	public string $created_at = '';

	public string $updated_at = '';

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'                 => $this->id,
			'post_id'            => $this->post_id,
			'workflow'           => $this->workflow,
			'scope'              => $this->scope,
			'status'             => $this->status,
			'seo_score'          => $this->seo_score,
			'readability_score'  => $this->readability_score,
			'eeat_score'         => $this->eeat_score,
			'recipe_score'       => $this->recipe_score,
			'title'              => $this->title,
			'meta_description'   => $this->meta_description,
			'original_content'   => $this->original_content,
			'optimized_content'  => $this->optimized_content,
			'original_title'     => $this->original_title,
			'optimized_title'    => $this->optimized_title,
			'original_meta'      => $this->original_meta,
			'optimized_meta'     => $this->optimized_meta,
			'analysis'           => $this->analysis,
			'diff'               => $this->diff,
			'versions'           => $this->versions,
			'user_id'            => $this->user_id,
			'created_at'         => $this->created_at,
			'updated_at'         => $this->updated_at,
		);
	}
}
