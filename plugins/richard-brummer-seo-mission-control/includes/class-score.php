<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verified score excludes MANUAL and INFO.
 */
class RBSMC_Score {

	/**
	 * @param array $signals Signals.
	 * @return array
	 */
	public static function summarize( $signals ) {
		$scored = 0;
		$green  = 0;
		$red    = 0;
		$yellow = 0;
		foreach ( $signals as $signal ) {
			$level = isset( $signal['level'] ) ? $signal['level'] : 'info';
			if ( in_array( $level, array( 'manual', 'info' ), true ) ) {
				continue;
			}
			if ( empty( $signal['scored'] ) && 'red' !== $level && 'yellow' !== $level && 'green' !== $level ) {
				continue;
			}
			if ( ! empty( $signal['scored'] ) || in_array( $level, array( 'red', 'yellow', 'green' ), true ) ) {
				$scored++;
				if ( 'red' === $level ) {
					$red++;
				} elseif ( 'yellow' === $level ) {
					$yellow++;
				} elseif ( 'green' === $level ) {
					$green++;
				}
			}
		}
		$score = 100;
		if ( $scored > 0 ) {
			$score = (int) round( 100 * ( $green / $scored ) );
			$score = max( 0, $score - ( $red * 12 ) - ( $yellow * 4 ) );
			$score = max( 0, min( 100, $score ) );
		}
		return array(
			'score'  => $score,
			'scored' => $scored,
			'red'    => $red,
			'yellow' => $yellow,
			'green'  => $green,
		);
	}
}
