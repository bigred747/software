<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'laps_settings' );
delete_option( 'laps_last_audit' );
delete_option( 'laps_last_repair' );
delete_option( 'laps_dashboard' );
delete_option( 'laps_log' );
delete_option( 'laps_cursor' );
delete_option( 'laps_learn_cursor' );
delete_option( 'laps_learn_last' );
delete_option( 'laps_learn_log' );
delete_option( 'laps_catalog' );
delete_option( 'laps_learn_snapshot_at' );
