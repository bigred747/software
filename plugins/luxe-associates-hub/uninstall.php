<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'lah_settings' );
delete_option( 'lah_link_log' );
delete_option( 'lah_last_link' );
