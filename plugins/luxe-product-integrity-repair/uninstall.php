<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'luxe_pir_settings' );
delete_option( 'luxe_pir_last_run' );
delete_option( 'luxe_pir_log' );
delete_option( 'luxe_pir_cursor' );
