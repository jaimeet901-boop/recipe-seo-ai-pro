<?php
declare(strict_types=1);

/**
 * View model for AI Content Calendar (Phase 3.7).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Calendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CalendarViewModel
 */
final class CalendarViewModel {

	private CalendarService $service;

	public function __construct( CalendarService $service ) {
		$this->service = $service;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function for_admin_page( int $project_id = 0 ): array {
		$projects = array();
		if ( class_exists( 'RSAIP_DB' ) ) {
			global $wpdb;
			$table = \RSAIP_DB::table_projects();
			$rows  = $wpdb->get_results( "SELECT id, name FROM {$table} ORDER BY name ASC", ARRAY_A );
			if ( is_array( $rows ) ) {
				$projects = $rows;
			}
		}

		return array(
			'title'      => __( 'AI Content Calendar', 'recipe-seo-ai-pro' ),
			'project_id' => $project_id,
			'projects'   => $projects,
			'calendars'  => $this->service->list_calendars( $project_id ),
			'statuses'   => $this->status_labels(),
			'channels'   => $this->channel_labels(),
			'phases'     => $this->phase_labels(),
			'views'      => $this->view_labels(),
			'note'       => __( 'Plan, reschedule, and queue content. Drag events onto a new day to reschedule.', 'recipe-seo-ai-pro' ),
			'timezone'   => function_exists( 'wp_timezone_string' ) ? (string) wp_timezone_string() : 'UTC',
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function status_labels(): array {
		return array(
			'all'         => __( 'All statuses', 'recipe-seo-ai-pro' ),
			'idea'        => __( 'Idea', 'recipe-seo-ai-pro' ),
			'planned'     => __( 'Planned', 'recipe-seo-ai-pro' ),
			'in_progress' => __( 'In progress', 'recipe-seo-ai-pro' ),
			'ready'       => __( 'Ready', 'recipe-seo-ai-pro' ),
			'scheduled'   => __( 'Scheduled', 'recipe-seo-ai-pro' ),
			'published'   => __( 'Published', 'recipe-seo-ai-pro' ),
			'cancelled'   => __( 'Cancelled', 'recipe-seo-ai-pro' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function channel_labels(): array {
		return array(
			'blog'     => __( 'Blog', 'recipe-seo-ai-pro' ),
			'social'   => __( 'Social', 'recipe-seo-ai-pro' ),
			'email'    => __( 'Email', 'recipe-seo-ai-pro' ),
			'youtube'  => __( 'YouTube', 'recipe-seo-ai-pro' ),
			'other'    => __( 'Other', 'recipe-seo-ai-pro' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function phase_labels(): array {
		return array(
			'now'     => __( 'Now', 'recipe-seo-ai-pro' ),
			'next'    => __( 'Next', 'recipe-seo-ai-pro' ),
			'later'   => __( 'Later', 'recipe-seo-ai-pro' ),
			'backlog' => __( 'Backlog', 'recipe-seo-ai-pro' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function view_labels(): array {
		return array(
			'month'   => __( 'Month', 'recipe-seo-ai-pro' ),
			'week'    => __( 'Week', 'recipe-seo-ai-pro' ),
			'day'     => __( 'Day', 'recipe-seo-ai-pro' ),
			'agenda'  => __( 'Agenda', 'recipe-seo-ai-pro' ),
			'roadmap' => __( 'Roadmap', 'recipe-seo-ai-pro' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function present( CalendarDTO $dto ): array {
		return array( 'event' => $dto->to_array() );
	}
}
