<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'luxe_google_presence_settings' );
delete_option( 'luxe_google_presence_last_scan' );
