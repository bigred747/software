<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'luxe_score_repair_settings' );
delete_option( 'luxe_score_repair_activated_at' );
