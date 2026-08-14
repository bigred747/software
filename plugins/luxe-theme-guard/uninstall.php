<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'luxe_theme_guard_settings' );
delete_option( 'luxe_theme_guard_last' );
delete_option( 'luxe_theme_guard_log' );
