<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'rbsmc_settings' );
delete_option( 'rbsmc_404_log' );
delete_option( 'rbsmc_dwell' );
delete_option( 'rbsmc_last_audit' );
delete_option( 'rbsmc_traffic_snapshot' );
delete_option( 'rbsmc_signal_history' );
delete_option( 'rbsmc_last_heartbeat' );
