<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'rbsmc_stay_settings' );
delete_option( 'rbsmc_stay_404_log' );
delete_option( 'rbsmc_stay_dwell' );
delete_option( 'rbsmc_stay_last_audit' );
delete_option( 'rbsmc_stay_traffic_snapshot' );
