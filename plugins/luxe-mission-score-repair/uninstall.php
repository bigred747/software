<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'luxe_msr_state' );
wp_clear_scheduled_hook( 'luxe_msr_template_sync' );
