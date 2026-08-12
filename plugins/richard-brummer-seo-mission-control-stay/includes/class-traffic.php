<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classify administrator-entered Site Kit snapshots. No writes.
 */
class RBSMC_Stay_Traffic {

	/**
	 * @param int $users Unique visitors.
	 * @param int $direct_pct Direct channel percent 0–100.
	 * @param int $avg_seconds Average session/page seconds.
	 * @param int $clicks Search clicks.
	 * @return string
	 */
	public static function classify( $users, $direct_pct, $avg_seconds, $clicks ) {
		$users       = (int) max( 0, $users );
		$direct_pct  = (int) max( 0, $direct_pct );
		$avg_seconds = (int) max( 0, $avg_seconds );
		$clicks      = (int) max( 0, $clicks );
		if ( $users >= 1000 && $direct_pct >= 90 && $avg_seconds <= 3 && 0 === $clicks ) {
			return 'crawler-or-guest-reload';
		}
		if ( $users >= 1000 && $clicks < 10 && $direct_pct >= 70 ) {
			return 'not-google-search';
		}
		return 'mixed';
	}
}
