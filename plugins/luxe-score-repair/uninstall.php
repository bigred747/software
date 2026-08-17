<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'luxe_score_repair_settings' );
delete_option( 'luxe_score_repair_activated_at' );
delete_option( 'luxe_score_repair_last_learn' );
delete_option( 'luxe_score_repair_learn_log' );
delete_option( 'luxe_score_repair_learned_redirects' );
delete_option( 'luxe_score_repair_amazon_last' );
delete_option( 'luxe_score_repair_amazon_log' );
delete_option( 'luxe_score_repair_amazon_cursor' );
delete_option( 'luxe_score_repair_amazon_token' );
