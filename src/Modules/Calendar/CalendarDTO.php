<?php
declare(strict_types=1);

/**
 * Content Calendar event DTO (Phase 3.7).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Calendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CalendarDTO
 */
final class CalendarDTO {

	public int $id = 0;

	public int $calendar_id = 0;

	public string $title = '';

	public int $project_id = 0;

	public int $keyword_id = 0;

	public int $brief_id = 0;

	public int $article_id = 0;

	public string $publish_at = '';

	public string $status = 'planned';

	public int $priority = 50;

	public int $assigned_user_id = 0;

	public string $publishing_channel = 'blog';

	public string $timezone = 'UTC';

	public string $notes = '';

	public string $roadmap_phase = 'now';

	public string $color = '';

	public int $user_id = 0;

	public string $created_at = '';

	public string $updated_at = '';

	public string $project_name = '';

	public string $calendar_name = '';

	public bool $is_overdue = false;

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'                  => $this->id,
			'calendar_id'         => $this->calendar_id,
			'title'               => $this->title,
			'project_id'          => $this->project_id,
			'keyword_id'          => $this->keyword_id,
			'brief_id'            => $this->brief_id,
			'article_id'          => $this->article_id,
			'publish_at'          => $this->publish_at,
			'status'              => $this->status,
			'priority'            => $this->priority,
			'assigned_user_id'    => $this->assigned_user_id,
			'publishing_channel'  => $this->publishing_channel,
			'timezone'            => $this->timezone,
			'notes'               => $this->notes,
			'roadmap_phase'       => $this->roadmap_phase,
			'color'               => $this->color,
			'user_id'             => $this->user_id,
			'created_at'          => $this->created_at,
			'updated_at'          => $this->updated_at,
			'project_name'        => $this->project_name,
			'calendar_name'       => $this->calendar_name,
			'is_overdue'          => $this->is_overdue,
		);
	}
}
