<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'luxe_stack_harmony_settings' );
delete_option( 'luxe_stack_harmony_activated_at' );
delete_option( 'luxe_stack_harmony_last_learn' );
delete_option( 'luxe_stack_harmony_learn_log' );
delete_option( 'luxe_stack_harmony_cancel_log' );
