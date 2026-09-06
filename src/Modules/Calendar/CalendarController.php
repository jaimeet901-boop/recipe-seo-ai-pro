<?php
declare(strict_types=1);

/**
 * Admin + AJAX for AI Content Calendar (Phase 3.7).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Calendar;

use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CalendarController
 */
final class CalendarController {

	public const PAGE_SLUG    = 'rsaip-content-calendar';
	public const NONCE_ACTION = 'rsaip_content_calendar';

	private CalendarService $service;

	private CalendarManager $manager;

	private CalendarViewModel $view_model;

	public function __construct(
		CalendarService $service,
		CalendarManager $manager,
		CalendarViewModel $view_model
	) {
		$this->service    = $service;
		$this->manager    = $manager;
		$this->view_model = $view_model;
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 26 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		$map = array(
			'rsaip_calendar_view'         => 'ajax_view',
			'rsaip_calendar_get'          => 'ajax_get',
			'rsaip_calendar_create'       => 'ajax_create',
			'rsaip_calendar_update'       => 'ajax_update',
			'rsaip_calendar_reschedule'   => 'ajax_reschedule',
			'rsaip_calendar_duplicate'    => 'ajax_duplicate',
			'rsaip_calendar_delete'       => 'ajax_delete',
			'rsaip_calendar_bulk_move'    => 'ajax_bulk_move',
			'rsaip_calendar_bulk_delete'  => 'ajax_bulk_delete',
			'rsaip_calendar_upcoming'     => 'ajax_upcoming',
			'rsaip_calendar_overdue'      => 'ajax_overdue',
			'rsaip_calendar_create_cal'   => 'ajax_create_calendar',
			'rsaip_calendar_list_cals'    => 'ajax_list_calendars',
			'rsaip_calendar_queue'        => 'ajax_queue',
		);

		foreach ( $map as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( $this, $method ) );
		}
	}

	public function register_menu(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			return;
		}

		add_submenu_page(
			'rsaip-dashboard',
			__( 'AI Content Calendar', 'recipe-seo-ai-pro' ),
			__( 'AI Content Calendar', 'recipe-seo-ai-pro' ),
			$this->capability(),
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * @param string $hook_suffix Admin hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$page = sanitize_key( (string) wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $page !== self::PAGE_SLUG ) {
			return;
		}

		$css_path = RSAIP_PLUGIN_DIR . 'assets/admin.css';
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : RSAIP_VERSION;
		wp_enqueue_style( 'rsaip-admin', RSAIP_PLUGIN_URL . 'assets/admin.css', array(), $css_ver );

		$cal_css = RSAIP_PLUGIN_DIR . 'assets/content-calendar.css';
		$cal_ver = file_exists( $cal_css ) ? (string) filemtime( $cal_css ) : RSAIP_VERSION;
		wp_enqueue_style( 'rsaip-content-calendar', RSAIP_PLUGIN_URL . 'assets/content-calendar.css', array( 'rsaip-admin' ), $cal_ver );

		$js_path = RSAIP_PLUGIN_DIR . 'assets/content-calendar.js';
		$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : RSAIP_VERSION;
		wp_enqueue_script( 'rsaip-content-calendar', RSAIP_PLUGIN_URL . 'assets/content-calendar.js', array( 'jquery' ), $js_ver, true );

		$project_id = isset( $_GET['project_id'] ) ? absint( wp_unslash( $_GET['project_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		wp_localize_script(
			'rsaip-content-calendar',
			'RSAIP_CONTENT_CALENDAR',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
				'projectId' => $project_id,
				'actions'   => array(
					'view'        => 'rsaip_calendar_view',
					'get'         => 'rsaip_calendar_get',
					'create'      => 'rsaip_calendar_create',
					'update'      => 'rsaip_calendar_update',
					'reschedule'  => 'rsaip_calendar_reschedule',
					'duplicate'   => 'rsaip_calendar_duplicate',
					'delete'      => 'rsaip_calendar_delete',
					'bulkMove'    => 'rsaip_calendar_bulk_move',
					'bulkDelete'  => 'rsaip_calendar_bulk_delete',
					'upcoming'    => 'rsaip_calendar_upcoming',
					'overdue'     => 'rsaip_calendar_overdue',
					'createCal'   => 'rsaip_calendar_create_cal',
					'listCals'    => 'rsaip_calendar_list_cals',
					'queue'       => 'rsaip_calendar_queue',
				),
				'i18n'      => array(
					'saved'     => __( 'Saved.', 'recipe-seo-ai-pro' ),
					'deleted'   => __( 'Deleted.', 'recipe-seo-ai-pro' ),
					'error'     => __( 'Request failed.', 'recipe-seo-ai-pro' ),
					'confirmDel'=> __( 'Delete selected event(s)?', 'recipe-seo-ai-pro' ),
				),
				'labels'    => array(
					'statuses' => $this->view_model->status_labels(),
					'channels' => $this->view_model->channel_labels(),
					'phases'   => $this->view_model->phase_labels(),
					'views'    => $this->view_model->view_labels(),
				),
			)
		);

		unset( $hook_suffix );
	}

	public function render_page(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'Access denied', 'recipe-seo-ai-pro' ) );
		}
		$project_id = isset( $_GET['project_id'] ) ? absint( wp_unslash( $_GET['project_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		View::render( 'admin/content-calendar', $this->view_model->for_admin_page( $project_id ) );
	}

	public function ajax_view(): void {
		$this->guard();
		wp_send_json_success(
			$this->service->view_payload(
				array(
					'view'         => $this->post_text( 'view', 'month' ),
					'anchor_date'  => $this->post_text( 'anchor_date', gmdate( 'Y-m-d' ) ),
					'project_id'   => $this->post_int( 'project_id' ),
					'calendar_id'  => $this->post_int( 'calendar_id' ),
					'status'       => $this->post_text( 'status', 'all' ),
					'q'            => $this->post_text( 'q' ),
				)
			)
		);
	}

	public function ajax_get(): void {
		$this->guard();
		$this->respond_dto( $this->service->get_event( $this->post_int( 'id' ) ) );
	}

	public function ajax_create(): void {
		$this->guard();
		$this->respond_dto( $this->manager->create_event( $this->post_event_fields( true ), get_current_user_id() ) );
	}

	public function ajax_update(): void {
		$this->guard();
		$this->respond_dto(
			$this->manager->update_event( $this->post_int( 'id' ), $this->post_event_fields( false ), get_current_user_id() )
		);
	}

	public function ajax_reschedule(): void {
		$this->guard();
		$this->respond_dto(
			$this->manager->reschedule( $this->post_int( 'id' ), $this->post_text( 'publish_at' ), get_current_user_id() )
		);
	}

	public function ajax_duplicate(): void {
		$this->guard();
		$this->respond_dto( $this->manager->duplicate( $this->post_int( 'id' ), get_current_user_id() ) );
	}

	public function ajax_delete(): void {
		$this->guard();
		$ids = $this->post_ids();
		if ( count( $ids ) === 1 ) {
			$result = $this->manager->delete_event( $ids[0], get_current_user_id() );
			if ( is_wp_error( $result ) ) {
				$this->respond_error( $result );
			}
			wp_send_json_success( array( 'deleted' => 1 ) );
		}
		wp_send_json_success( $this->manager->bulk_delete( $ids, get_current_user_id() ) );
	}

	public function ajax_bulk_move(): void {
		$this->guard();
		$days = isset( $_POST['days'] ) ? (int) wp_unslash( $_POST['days'] ) : 0;
		if ( $days !== 0 ) {
			wp_send_json_success( $this->manager->bulk_shift_days( $this->post_ids(), $days, get_current_user_id() ) );
		}
		wp_send_json_success(
			$this->manager->bulk_move( $this->post_ids(), $this->post_text( 'publish_at' ), get_current_user_id() )
		);
	}

	public function ajax_bulk_delete(): void {
		$this->guard();
		wp_send_json_success( $this->manager->bulk_delete( $this->post_ids(), get_current_user_id() ) );
	}

	public function ajax_upcoming(): void {
		$this->guard();
		wp_send_json_success(
			array(
				'items' => $this->service->upcoming( $this->post_int( 'project_id' ), $this->post_int( 'limit', 20 ) ),
			)
		);
	}

	public function ajax_overdue(): void {
		$this->guard();
		wp_send_json_success(
			array(
				'items' => $this->service->overdue( $this->post_int( 'project_id' ), $this->post_int( 'limit', 50 ) ),
			)
		);
	}

	public function ajax_create_calendar(): void {
		$this->guard();
		$result = $this->manager->create_calendar(
			array(
				'name'        => $this->post_text( 'name' ),
				'project_id'  => $this->post_int( 'project_id' ),
				'timezone'    => $this->post_text( 'timezone', 'UTC' ),
				'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( (string) wp_unslash( $_POST['description'] ) ) : '',
			),
			get_current_user_id()
		);
		if ( is_wp_error( $result ) ) {
			$this->respond_error( $result );
		}
		wp_send_json_success( $result );
	}

	public function ajax_list_calendars(): void {
		$this->guard();
		wp_send_json_success( array( 'calendars' => $this->service->list_calendars( $this->post_int( 'project_id' ) ) ) );
	}

	public function ajax_queue(): void {
		$this->guard();
		wp_send_json_success(
			array(
				'items' => $this->service->list_queue(
					array(
						'project_id' => $this->post_int( 'project_id' ),
						'status'     => $this->post_text( 'status', 'all' ),
					)
				),
			)
		);
	}

	/**
	 * @param CalendarDTO|\WP_Error $result Result.
	 */
	private function respond_dto( $result ): void {
		if ( is_wp_error( $result ) ) {
			$this->respond_error( $result );
		}
		wp_send_json_success( $this->view_model->present( $result ) );
	}

	private function respond_error( \WP_Error $error ): void {
		$status = 400;
		$data   = $error->get_error_data();
		if ( is_array( $data ) && isset( $data['status'] ) ) {
			$status = (int) $data['status'];
		}
		wp_send_json_error(
			array(
				'message' => $error->get_error_message(),
				'code'    => $error->get_error_code(),
			),
			$status
		);
	}

	private function guard(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'recipe-seo-ai-pro' ) ), 403 );
		}
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function post_event_fields( bool $for_create ): array {
		$keys = array(
			'title',
			'calendar_id',
			'project_id',
			'keyword_id',
			'brief_id',
			'article_id',
			'publish_at',
			'status',
			'priority',
			'assigned_user_id',
			'publishing_channel',
			'timezone',
			'notes',
			'roadmap_phase',
			'color',
		);
		$out = array();
		foreach ( $keys as $key ) {
			if ( ! $for_create && ! isset( $_POST[ $key ] ) ) {
				continue;
			}
			if ( $key === 'notes' ) {
				$out[ $key ] = isset( $_POST['notes'] ) ? sanitize_textarea_field( (string) wp_unslash( $_POST['notes'] ) ) : '';
				continue;
			}
			if ( in_array( $key, array( 'calendar_id', 'project_id', 'keyword_id', 'brief_id', 'article_id', 'priority', 'assigned_user_id' ), true ) ) {
				$out[ $key ] = $this->post_int( $key, 'priority' === $key ? 50 : 0 );
				continue;
			}
			$out[ $key ] = $this->post_text( $key, $key === 'status' ? 'planned' : ( $key === 'publishing_channel' ? 'blog' : ( $key === 'timezone' ? 'UTC' : ( $key === 'roadmap_phase' ? 'now' : '' ) ) ) );
		}
		return $out;
	}

	/**
	 * @return list<int>
	 */
	private function post_ids(): array {
		if ( isset( $_POST['ids'] ) ) {
			$raw = wp_unslash( $_POST['ids'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( is_string( $raw ) ) {
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					return array_values( array_filter( array_map( 'absint', $decoded ) ) );
				}
			}
			if ( is_array( $raw ) ) {
				return array_values( array_filter( array_map( 'absint', $raw ) ) );
			}
		}
		$id = $this->post_int( 'id' );
		return $id > 0 ? array( $id ) : array();
	}

	private function post_text( string $key, string $default = '' ): string {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}
		return sanitize_text_field( (string) wp_unslash( $_POST[ $key ] ) );
	}

	private function post_int( string $key, int $default = 0 ): int {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}
		return absint( wp_unslash( $_POST[ $key ] ) );
	}

	private function capability(): string {
		return function_exists( 'rsaip_capability' ) ? rsaip_capability() : 'manage_options';
	}
}
