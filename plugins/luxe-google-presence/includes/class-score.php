<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Honest readiness scores.
 *
 * 100 means every signal in the set is green.
 * 95 means every required signal is green and only optional ones remain.
 * 94 means a required signal is still open.
 * 70 means a hard Google or Amazon rule failed.
 */
class Luxe_Google_Presence_Score {

	/**
	 * @param array $signals Signal rows.
	 * @param array $hard    IDs that cap the score at 70.
	 * @param array $optional IDs that may be red at a score of 95.
	 * @return array
	 */
	public static function decide( $signals, $hard = array(), $optional = array() ) {
		$signals = is_array( $signals ) ? $signals : array();
		$hard    = is_array( $hard ) ? $hard : array();
		$optional = is_array( $optional ) ? $optional : array();
		$green   = 0;
		$total   = 0;
		$hard_fail = false;
		$required_fail = false;

		foreach ( $signals as $signal ) {
			if ( ! is_array( $signal ) || empty( $signal['id'] ) ) {
				continue;
			}
			$total++;
			$pass = isset( $signal['level'] ) && 'green' === $signal['level'];
			if ( $pass ) {
				$green++;
				continue;
			}
			if ( in_array( $signal['id'], $hard, true ) ) {
				$hard_fail = true;
			} elseif ( ! in_array( $signal['id'], $optional, true ) ) {
				$required_fail = true;
			}
		}

		if ( $total < 1 ) {
			$score  = 0;
			$status = 'REVIEW';
		} elseif ( $hard_fail ) {
			$score  = 70;
			$status = 'HOLD';
		} elseif ( $required_fail ) {
			$score  = 94;
			$status = 'REVIEW';
		} elseif ( $green === $total ) {
			$score  = 100;
			$status = 'READY';
		} else {
			$score  = 95;
			$status = 'READY';
		}

		return array(
			'score'   => $score,
			'status'  => $status,
			'green'   => $green,
			'total'   => $total,
			'signals' => $signals,
		);
	}

	/**
	 * @param string $id     ID.
	 * @param string $label  Label.
	 * @param bool   $pass   Pass.
	 * @param string $detail Detail.
	 * @return array
	 */
	public static function signal( $id, $label, $pass, $detail ) {
		return array(
			'id'     => $id,
			'label'  => $label,
			'level'  => $pass ? 'green' : 'red',
			'detail' => is_string( $detail ) && '' !== $detail ? $detail : ( $pass ? 'Verified' : 'Needs review' ),
		);
	}
}
