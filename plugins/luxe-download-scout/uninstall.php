<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'lds_last_paste' );
delete_option( 'lds_last_catalog' );
