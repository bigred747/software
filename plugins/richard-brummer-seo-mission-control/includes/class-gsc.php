<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Search Console connection. Private key is never saved in options.
 */
class RBSMC_GSC {

	/**
	 * @return array
	 */
	public static function status() {
		$property = defined( 'RBSMC_GSC_PROPERTY' ) ? (string) RBSMC_GSC_PROPERTY : '';
		$key_file = defined( 'RBSMC_GSC_KEY_FILE' ) ? (string) RBSMC_GSC_KEY_FILE : '';
		$email    = defined( 'RBSMC_GSC_CLIENT_EMAIL' ) ? (string) RBSMC_GSC_CLIENT_EMAIL : '';
		$has_key  = ( $key_file && is_readable( $key_file ) ) || ( defined( 'RBSMC_GSC_PRIVATE_KEY' ) && RBSMC_GSC_PRIVATE_KEY ) || defined( 'RBSMC_GSC_SERVICE_ACCOUNT_JSON' );

		$state = 'not-connected';
		if ( $property && $has_key ) {
			$state = 'credentials-present';
		}

		$signals = array();
		if ( ! $property || ! $has_key ) {
			$signals[] = array(
				'level'  => 'info',
				'title'  => 'Missing GSC credentials',
				'detail' => 'Define RBSMC_GSC_PROPERTY and RBSMC_GSC_KEY_FILE in wp-config.php. Unscored INFO.',
				'scored' => false,
			);
		}

		return array(
			'state'    => $state,
			'property' => $property,
			'key_file' => $key_file ? 'configured' : '',
			'email'    => $email ? 'configured' : '',
			'signals'  => $signals,
			'note'     => 'Rank Math Overview at 0 while Site Kit shows impressions usually means Rank Math is not connected to the same Search Console property.',
		);
	}
}
