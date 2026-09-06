<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSAIP_Activator {
	public static function activate(): void {
		try {
			if ( class_exists( 'RSAIP_DB' ) ) {
				RSAIP_DB::install();
				RSAIP_DB::maybe_upgrade();
			}

			$existing = get_option( rsaip_option_key(), null );
			if ( ! is_array( $existing ) ) {
				update_option( rsaip_option_key(), rsaip_default_settings(), false );
			} else {
				$defaults = rsaip_default_settings();
				$merged   = array_merge( $defaults, $existing );
				update_option( rsaip_option_key(), $merged, false );
			}

			if ( ! wp_next_scheduled( 'rsaip_cron_rebuild_link_graph' ) ) {
				wp_schedule_event( time() + 120, 'hourly', 'rsaip_cron_rebuild_link_graph' );
			}

			if ( ! wp_next_scheduled( 'rsaip_cron_check_broken_links' ) ) {
				wp_schedule_event( time() + 300, 'twicedaily', 'rsaip_cron_check_broken_links' );
			}

			if ( ! wp_next_scheduled( 'rsaip_cron_process_bulk_queue' ) ) {
				wp_schedule_event( time() + 180, 'hourly', 'rsaip_cron_process_bulk_queue' );
			}
		} catch ( \Throwable $e ) {
			// Avoid a hard activation white-screen; surface details via option for support.
			update_option(
				'rsaip_last_activation_error',
				array(
					'message' => $e->getMessage(),
					'file'    => $e->getFile(),
					'line'    => $e->getLine(),
					'time'    => gmdate( 'c' ),
				),
				false
			);
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'rsaip_cron_rebuild_link_graph' );
		wp_clear_scheduled_hook( 'rsaip_cron_check_broken_links' );
		wp_clear_scheduled_hook( 'rsaip_cron_process_bulk_queue' );
	}
}
