<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Batch size follows free memory and load. A daily cap still stops a runaway loop.
 */
class Luxe_KIA_Gate {

	/**
	 * @param float $load One-minute load.
	 * @param int   $free_bytes Free memory bytes. 0 means unknown.
	 * @param array $settings Settings.
	 * @return int
	 */
	public static function batch_size( $load, $free_bytes, $settings = array() ) {
		$max = isset( $settings['batch'] ) ? (int) $settings['batch'] : 12;
		if ( $max < 1 ) {
			$max = 1;
		}
		if ( $max > 20 ) {
			$max = 20;
		}
		if ( $free_bytes > 0 && $free_bytes < 64 * 1024 * 1024 ) {
			return 1;
		}
		$load = (float) $load;
		if ( $load >= 64 ) {
			return 0;
		}
		if ( $load >= 48 ) {
			return min( 2, $max );
		}
		if ( $load >= 32 ) {
			return min( 6, $max );
		}
		if ( $load >= 16 ) {
			return min( 8, $max );
		}
		return $max;
	}

	/**
	 * @param float $load Load.
	 * @param int   $free_bytes Free memory.
	 * @return int
	 */
	public static function burst_size( $load, $free_bytes ) {
		$batch = self::batch_size( $load, $free_bytes, array( 'batch' => 12 ) );
		if ( $batch < 1 ) {
			return 0;
		}
		return min( 80, $batch * 8 );
	}

	/**
	 * @param int $scans Scans used today.
	 * @param int $repairs Repairs used today.
	 * @param int $max_scans Cap.
	 * @param int $max_repairs Cap.
	 * @return string ok, scan_cap, or repair_cap.
	 */
	public static function allowance( $scans, $repairs, $max_scans, $max_repairs ) {
		if ( (int) $scans >= (int) $max_scans ) {
			return 'scan_cap';
		}
		if ( (int) $repairs >= (int) $max_repairs ) {
			return 'repair_cap';
		}
		return 'ok';
	}

	/**
	 * @return float
	 */
	public static function load() {
		if ( ! function_exists( 'sys_getloadavg' ) ) {
			return 0.0;
		}
		$load = sys_getloadavg();
		if ( ! is_array( $load ) || ! isset( $load[0] ) ) {
			return 0.0;
		}
		return (float) $load[0];
	}

	/**
	 * @return int
	 */
	public static function free_bytes() {
		$limit = ini_get( 'memory_limit' );
		$bytes = self::memory_bytes( $limit );
		if ( $bytes < 1 ) {
			return 0;
		}
		$used = memory_get_usage( true );
		return max( 0, $bytes - $used );
	}

	/**
	 * @param string|false $limit Limit string.
	 * @return int
	 */
	public static function memory_bytes( $limit ) {
		$limit = trim( (string) $limit );
		if ( '' === $limit || '-1' === $limit ) {
			return 0;
		}
		$unit = strtolower( substr( $limit, -1 ) );
		$number = (int) $limit;
		if ( 'g' === $unit ) {
			return $number * 1024 * 1024 * 1024;
		}
		if ( 'm' === $unit ) {
			return $number * 1024 * 1024;
		}
		if ( 'k' === $unit ) {
			return $number * 1024;
		}
		return (int) $limit;
	}
}
