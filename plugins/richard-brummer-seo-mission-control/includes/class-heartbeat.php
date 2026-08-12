<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 15-minute homepage + REST heartbeat.
 */
class RBSMC_Heartbeat {

	/**
	 * Lightweight probe.
	 */
	public static function run() {
		$home  = wp_remote_get( home_url( '/' ), array( 'timeout' => 8 ) );
		$rest  = wp_remote_get( rest_url( 'wp/v2/types' ), array( 'timeout' => 8 ) );
		$state = array(
			'at'         => time(),
			'home_code'  => is_wp_error( $home ) ? 0 : (int) wp_remote_retrieve_response_code( $home ),
			'rest_code'  => is_wp_error( $rest ) ? 0 : (int) wp_remote_retrieve_response_code( $rest ),
			'home_error' => is_wp_error( $home ) ? $home->get_error_message() : '',
			'rest_error' => is_wp_error( $rest ) ? $rest->get_error_message() : '',
		);
		update_option( 'rbsmc_last_heartbeat', $state, false );
	}
}
