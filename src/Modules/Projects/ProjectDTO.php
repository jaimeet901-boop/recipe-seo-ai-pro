<?php
declare(strict_types=1);

/**
 * SEO Project data transfer object (Phase 3.3).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Projects;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProjectDTO
 */
final class ProjectDTO {

	public int $id = 0;

	public string $name = '';

	public string $description = '';

	public string $target_country = '';

	public string $language = '';

	public string $niche = '';

	public string $status = 'active';

	public int $owner_user_id = 0;

	public string $created_at = '';

	public string $updated_at = '';

	/** @var array<string, mixed> */
	public array $statistics = array();

	/** @var list<array<string, mixed>> */
	public array $members = array();

	/** @var list<array<string, mixed>> */
	public array $upcoming_tasks = array();

	/** @var list<array<string, mixed>> */
	public array $recent_activity = array();

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'              => $this->id,
			'name'            => $this->name,
			'description'     => $this->description,
			'target_country'  => $this->target_country,
			'language'        => $this->language,
			'niche'           => $this->niche,
			'status'          => $this->status,
			'owner_user_id'   => $this->owner_user_id,
			'created_at'      => $this->created_at,
			'updated_at'      => $this->updated_at,
			'statistics'      => $this->statistics,
			'members'         => $this->members,
			'upcoming_tasks'  => $this->upcoming_tasks,
			'recent_activity' => $this->recent_activity,
		);
	}
}
