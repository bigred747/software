<?php
/**
 * Pick which heavy learners may run on this cron hit.
 *
 * @package Luxe_Stack_Conductor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'Luxe_Conductor_Dispatcher' ) ) {
	return;
}

/**
 * One heavy job per lane, and only a few lanes per request.
 * WooCommerce, Rank Math, LiteSpeed, Wordfence, and WZone are never moved.
 */
class Luxe_Conductor_Dispatcher {

	/**
	 * @param array $due Due events. Each needs hook, timestamp, lane, heavy, protected, priority.
	 * @param int   $now Unix time.
	 * @param int   $cap Maximum heavy jobs to keep. Clamped to 1–3.
	 * @return array
	 */
	public static function plan( $due, $now, $cap ) {
		$cap  = max( 1, min( 3, (int) $cap ) );
		$now  = (int) $now;
		$keep = array();
		$move = array();
		$lanes = array();
		$heavy = array();

		foreach ( (array) $due as $event ) {
			if ( ! is_array( $event ) || empty( $event['hook'] ) ) {
				continue;
			}
			if ( ! empty( $event['protected'] ) || empty( $event['heavy'] ) ) {
				continue;
			}
			$heavy[] = $event;
		}

		usort(
			$heavy,
			function ( $a, $b ) {
				$pa = isset( $a['priority'] ) ? (int) $a['priority'] : 0;
				$pb = isset( $b['priority'] ) ? (int) $b['priority'] : 0;
				if ( $pa !== $pb ) {
					return $pb - $pa;
				}
				$ta = isset( $a['timestamp'] ) ? (int) $a['timestamp'] : 0;
				$tb = isset( $b['timestamp'] ) ? (int) $b['timestamp'] : 0;
				if ( $ta !== $tb ) {
					return $ta - $tb;
				}
				return strcmp( (string) $a['hook'], (string) $b['hook'] );
			}
		);

		foreach ( $heavy as $event ) {
			$lane = ! empty( $event['lane'] ) ? (string) $event['lane'] : 'general';
			if ( count( $keep ) < $cap && empty( $lanes[ $lane ] ) ) {
				$keep[]         = (string) $event['hook'];
				$lanes[ $lane ] = true;
				continue;
			}
			$move[] = $event;
		}

		$slot = 1;
		foreach ( $move as $i => $event ) {
			$move[ $i ]['new_timestamp'] = $now + ( 75 * $slot );
			$slot++;
		}

		return array(
			'keep' => $keep,
			'move' => $move,
			'cap'  => $cap,
		);
	}

	/**
	 * Learn a faster cap only after three quick cron hits. A slow hit drops back to one job.
	 *
	 * @param float $duration     Seconds the last cron request took. 0 means unknown.
	 * @param int   $previous_cap Previous cap.
	 * @param int   $fast_streak  Consecutive fast hits.
	 * @return array{0:int,1:int}
	 */
	public static function next_cap( $duration, $previous_cap, $fast_streak ) {
		$cap      = max( 1, min( 3, (int) $previous_cap ) );
		$streak   = max( 0, (int) $fast_streak );
		$duration = (float) $duration;
		if ( $duration <= 0 ) {
			return array( 1, 0 );
		}
		if ( $duration > 12 ) {
			return array( 1, 0 );
		}
		if ( $duration < 8 ) {
			$streak++;
			if ( $streak >= 3 && $cap < 3 ) {
				return array( $cap + 1, 0 );
			}
			return array( $cap, $streak );
		}
		return array( $cap, 0 );
	}

	/**
	 * Give a stalled hook a real future timestamp.
	 * A past duplicate behind an upcoming run is removed.
	 * Protected plugins get the same repair, so a late store hook is scheduled again instead of left red.
	 *
	 * @param array $events Events with hook, timestamp, protected.
	 * @param int   $now    Unix time.
	 * @return array
	 */
	public static function catch_up( $events, $now ) {
		$now    = (int) $now;
		$grace  = 900;
		$groups = array();
		foreach ( (array) $events as $event ) {
			if ( ! is_array( $event ) || empty( $event['hook'] ) ) {
				continue;
			}
			$groups[ $event['hook'] ][] = $event;
		}
		$drop = array();
		$move = array();
		$slot = 1;
		foreach ( $groups as $group ) {
			$future = array();
			$soon   = array();
			$stale  = array();
			foreach ( $group as $event ) {
				$ts = isset( $event['timestamp'] ) ? (int) $event['timestamp'] : 0;
				if ( $ts > $now ) {
					$future[] = $event;
				} elseif ( ( $now - $ts ) <= $grace ) {
					$soon[] = $event;
				} else {
					$stale[] = $event;
				}
			}
			if ( ! $stale ) {
				continue;
			}
			if ( $future || $soon ) {
				foreach ( $stale as $event ) {
					$drop[] = $event;
				}
				continue;
			}
			usort(
				$stale,
				function ( $a, $b ) {
					return (int) $a['timestamp'] - (int) $b['timestamp'];
				}
			);
			$keep = array_shift( $stale );
			foreach ( $stale as $event ) {
				$drop[] = $event;
			}
			$keep['new_timestamp'] = $now + ( 60 * $slot );
			$move[]                = $keep;
			$slot++;
		}
		return array(
			'drop' => $drop,
			'move' => $move,
		);
	}

	/**
	 * Write the catch-up plan into a WordPress cron array.
	 * Edits the array directly so a failed wp_unschedule_event cannot leave the stale time in place.
	 *
	 * @param array $cron Cron option value.
	 * @param array $plan Catch-up plan.
	 * @return array
	 */
	public static function apply_cron( $cron, $plan ) {
		if ( ! is_array( $cron ) ) {
			$cron = array();
		}
		$touched = array();
		if ( ! empty( $plan['drop'] ) ) {
			$touched = array_merge( $touched, $plan['drop'] );
		}
		if ( ! empty( $plan['move'] ) ) {
			$touched = array_merge( $touched, $plan['move'] );
		}
		foreach ( $touched as $event ) {
			if ( empty( $event['hook'] ) ) {
				continue;
			}
			$ts   = (int) $event['timestamp'];
			$hook = (string) $event['hook'];
			if ( ! isset( $cron[ $ts ][ $hook ] ) ) {
				continue;
			}
			if ( isset( $event['key'] ) && isset( $cron[ $ts ][ $hook ][ $event['key'] ] ) ) {
				unset( $cron[ $ts ][ $hook ][ $event['key'] ] );
			} else {
				unset( $cron[ $ts ][ $hook ] );
			}
			if ( empty( $cron[ $ts ][ $hook ] ) ) {
				unset( $cron[ $ts ][ $hook ] );
			}
			if ( empty( $cron[ $ts ] ) ) {
				unset( $cron[ $ts ] );
			}
		}
		foreach ( (array) $plan['move'] as $event ) {
			if ( empty( $event['hook'] ) || empty( $event['new_timestamp'] ) ) {
				continue;
			}
			$when = (int) $event['new_timestamp'];
			$hook = (string) $event['hook'];
			$args = isset( $event['args'] ) ? (array) $event['args'] : array();
			$schedule = ! empty( $event['schedule'] ) ? $event['schedule'] : false;
			$stored   = array(
				'schedule' => $schedule,
				'args'     => $args,
			);
			if ( $schedule && ! empty( $event['interval'] ) ) {
				$stored['interval'] = (int) $event['interval'];
			}
			$key = md5( serialize( $args ) );
			if ( ! isset( $cron[ $when ] ) || ! is_array( $cron[ $when ] ) ) {
				$cron[ $when ] = array();
			}
			$cron[ $when ][ $hook ][ $key ] = $stored;
		}
		ksort( $cron );
		return $cron;
	}

	/**
	 * The next run the board should show. A future timestamp wins over an older stalled one.
	 *
	 * @param array $timestamps Timestamps.
	 * @param int   $now        Unix time.
	 * @return int
	 */
	public static function display_next( $timestamps, $now ) {
		$now    = (int) $now;
		$future = array();
		$all    = array();
		foreach ( (array) $timestamps as $ts ) {
			$ts    = (int) $ts;
			$all[] = $ts;
			if ( $ts > $now ) {
				$future[] = $ts;
			}
		}
		if ( $future ) {
			return min( $future );
		}
		if ( $all ) {
			return min( $all );
		}
		return 0;
	}
}
