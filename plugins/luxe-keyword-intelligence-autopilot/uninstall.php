<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'luxe_kia_settings' );
delete_option( 'luxe_kia_state' );
delete_option( 'luxe_kia_activity' );
delete_option( 'luxe_kia_lock' );
wp_clear_scheduled_hook( 'luxe_kia_cycle' );
