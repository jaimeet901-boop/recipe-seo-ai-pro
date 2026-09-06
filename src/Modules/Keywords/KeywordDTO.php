<?php
declare(strict_types=1);

/**
 * Keyword Workspace DTO (Phase 3.4) — management only, no research.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Keywords;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KeywordDTO
 */
final class KeywordDTO {

	public int $id = 0;

	public string $primary_keyword = '';

	public string $intent = '';

	public int $difficulty = 0;

	public int $priority = 50;

	public string $status = 'idea';

	public int $project_id = 0;

	public int $brief_id = 0;

	public int $article_id = 0;

	public string $target_url = '';

	public int $cluster_id = 0;

	public int $parent_keyword_id = 0;

	public string $language = '';

	public string $country = '';

	public string $roadmap_phase = '';

	public int $roadmap_order = 0;

	public int $user_id = 0;

	public string $created_at = '';

	public string $updated_at = '';

	public string $cluster_name = '';

	public string $project_name = '';

	/** @var list<array<string, mixed>> */
	public array $notes = array();

	/** @var list<array<string, mixed>> */
	public array $history = array();

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'                 => $this->id,
			'primary_keyword'    => $this->primary_keyword,
			'intent'             => $this->intent,
			'difficulty'         => $this->difficulty,
			'priority'           => $this->priority,
			'status'             => $this->status,
			'project_id'         => $this->project_id,
			'brief_id'           => $this->brief_id,
			'article_id'         => $this->article_id,
			'target_url'         => $this->target_url,
			'cluster_id'         => $this->cluster_id,
			'parent_keyword_id'  => $this->parent_keyword_id,
			'language'           => $this->language,
			'country'            => $this->country,
			'roadmap_phase'      => $this->roadmap_phase,
			'roadmap_order'      => $this->roadmap_order,
			'user_id'            => $this->user_id,
			'created_at'         => $this->created_at,
			'updated_at'         => $this->updated_at,
			'cluster_name'       => $this->cluster_name,
			'project_name'       => $this->project_name,
			'notes'              => $this->notes,
			'history'            => $this->history,
		);
	}
}
