<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'luae_settings' );
delete_option( 'luae_board' );
delete_option( 'luae_stats' );
delete_option( 'luae_noindex_paths' );
delete_option( 'luae_paths' );
delete_option( 'luae_scan_cursor' );
delete_option( 'luae_sample' );

if ( function_exists( 'delete_post_meta_by_key' ) ) {
	delete_post_meta_by_key( 'luae_modules' );
	delete_post_meta_by_key( 'luae_report' );
}
