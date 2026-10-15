<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'luxe_conductor_tick' );
delete_option( 'luxe_conductor_log' );
delete_option( 'luxe_conductor_memory' );
delete_option( 'luxe_conductor_last' );
delete_transient( 'luxe_conductor_wake' );
