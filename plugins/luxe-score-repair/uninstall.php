<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'luxe_score_repair_settings' );
delete_option( 'luxe_score_repair_activated_at' );
delete_option( 'luxe_score_repair_last_learn' );
delete_option( 'luxe_score_repair_learn_log' );
delete_option( 'luxe_score_repair_learned_redirects' );
delete_option( 'lah_settings' );
delete_option( 'lah_link_log' );
delete_option( 'lah_last_link' );
