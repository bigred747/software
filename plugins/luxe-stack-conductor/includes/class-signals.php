<?php
/**
 * Honest process signals. Green means the check passed.
 *
 * @package Luxe_Stack_Conductor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'Luxe_Conductor_Signals' ) ) {
	return;
}

/**
 * Build the signal board from installed plugins, the cron queue, and stored evidence.
 */
class Luxe_Conductor_Signals {

	/**
	 * @param array $installed Plugin rows: name, version, active.
	 * @param array $hooks     Cron rows: hook, next, interval.
	 * @param array $owners    Hook => plugin name.
	 * @param int   $now       Unix time.
	 * @param array $options   Known evidence options.
	 * @return array
	 */
	public static function board( $installed, $hooks, $owners, $now, $options = array() ) {
		$now     = (int) $now;
		$options = is_array( $options ) ? $options : array();
		$present = array();
		foreach ( (array) $installed as $plugin ) {
			if ( ! is_array( $plugin ) || empty( $plugin['name'] ) ) {
				continue;
			}
			$present[ Luxe_Conductor_Registry::normalize( $plugin['name'] ) ] = $plugin;
		}

		$hooks_by_plugin = array();
		foreach ( (array) $hooks as $hook ) {
			if ( ! is_array( $hook ) || empty( $hook['hook'] ) ) {
				continue;
			}
			$owner = isset( $owners[ $hook['hook'] ] ) ? (string) $owners[ $hook['hook'] ] : '';
			if ( '' === $owner ) {
				$owner = Luxe_Conductor_Registry::owner_for_hook( $hook['hook'] );
			}
			if ( '' === $owner ) {
				continue;
			}
			$key = Luxe_Conductor_Registry::normalize( $owner );
			$hooks_by_plugin[ $key ][] = $hook;
		}

		$rows = array();
		foreach ( Luxe_Conductor_Registry::catalog() as $entry ) {
			$key = Luxe_Conductor_Registry::normalize( $entry['name'] );
			if ( ! empty( $entry['critical'] ) && ! isset( $present[ $key ] ) ) {
				$rows[] = self::missing( $entry );
			}
		}
		foreach ( (array) $installed as $plugin ) {
			if ( ! is_array( $plugin ) || empty( $plugin['name'] ) ) {
				continue;
			}
			$key   = Luxe_Conductor_Registry::normalize( $plugin['name'] );
			$entry = Luxe_Conductor_Registry::by_name( $plugin['name'] );
			$mine  = isset( $hooks_by_plugin[ $key ] ) ? $hooks_by_plugin[ $key ] : array();
			$rows[] = self::row( $plugin, $entry, $mine, $now, $options );
		}

		usort(
			$rows,
			function ( $a, $b ) {
				$rank = array(
					'red'    => 0,
					'yellow' => 1,
					'green'  => 2,
				);
				$ra = isset( $rank[ $a['level'] ] ) ? $rank[ $a['level'] ] : 1;
				$rb = isset( $rank[ $b['level'] ] ) ? $rank[ $b['level'] ] : 1;
				if ( $ra !== $rb ) {
					return $ra - $rb;
				}
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return self::summarize( $rows, $now );
	}

	/**
	 * @param array $entry Catalog row.
	 * @return array
	 */
	private static function missing( $entry ) {
		return array(
			'name'     => $entry['name'],
			'version'  => '',
			'level'    => 'red',
			'lane'     => $entry['lane'],
			'menu'     => '',
			'detail'   => 'Not installed. This stack expects it to be active.',
			'signals'  => array(
				self::signal( 'missing', $entry['name'], 'red', 'Not installed.', '' ),
			),
		);
	}

	/**
	 * @param array      $plugin  Installed row.
	 * @param array|null $entry   Catalog row.
	 * @param array      $hooks   Cron hooks for this plugin.
	 * @param int        $now     Now.
	 * @param array      $options Evidence.
	 * @return array
	 */
	private static function row( $plugin, $entry, $hooks, $now, $options ) {
		$name    = (string) $plugin['name'];
		$version = isset( $plugin['version'] ) ? (string) $plugin['version'] : '';
		$active  = ! empty( $plugin['active'] );
		$lane    = $entry ? $entry['lane'] : 'general';
		$menu    = $entry ? $entry['menu'] : '';
		$signals = array();

		if ( ! $active ) {
			$signals[] = self::signal( 'active', $name, 'red', 'Installed, but not active.', '' );
			return self::finish( $name, $version, $lane, $menu, $signals );
		}

		$detail = 'Version ' . ( $version ? $version : 'unknown' ) . ' is active.';
		if ( $entry && ! empty( $entry['safety'] ) ) {
			$detail .= ' ' . $entry['safety'];
		}
		$signals[] = self::signal( 'active', $name, 'green', $detail, '' );

		if ( $entry && ! empty( $entry['version'] ) && '' !== $version && version_compare( $version, $entry['version'], '<' ) ) {
			$signals[] = self::signal(
				'version',
				'Version',
				'yellow',
				'Installed ' . $version . ' is older than the known ' . $entry['version'] . '.',
				''
			);
		}

		$seen = array();
		foreach ( $hooks as $hook ) {
			$signals[] = self::hook_signal( $hook, $now );
			$seen[ $hook['hook'] ] = true;
		}
		if ( $entry && ! empty( $entry['hooks'] ) ) {
			foreach ( $entry['hooks'] as $hook_name => $interval ) {
				if ( isset( $seen[ $hook_name ] ) ) {
					continue;
				}
				$signals[] = self::signal(
					'cron-' . $hook_name,
					$hook_name,
					'yellow',
					$hook_name . ' is not on the WP-Cron queue.',
					$hook_name
				);
			}
		} elseif ( ! $hooks && ( ! $entry || empty( $entry['learning'] ) ) ) {
			$signals[] = self::signal( 'idle', 'Queue', 'green', 'No learning cron. It stays out of the 24/7 queue.', '' );
		} elseif ( ! $hooks ) {
			$signals[] = self::signal( 'idle', 'Queue', 'green', 'No WP-Cron event is waiting, so it is not colliding with the other learners.', '' );
		}

		$extra = self::evidence( $name, $options, $now );
		if ( $extra ) {
			$signals[] = $extra;
		}

		return self::finish( $name, $version, $lane, $menu, $signals );
	}

	/**
	 * @param array $hook Cron row.
	 * @param int   $now  Now.
	 * @return array
	 */
	private static function hook_signal( $hook, $now ) {
		$name = (string) $hook['hook'];
		$next = isset( $hook['next'] ) ? (int) $hook['next'] : 0;
		if ( $next <= 0 ) {
			return self::signal( 'cron-' . $name, $name, 'red', $name . ' has no next run.', $name );
		}
		$interval = isset( $hook['interval'] ) ? (int) $hook['interval'] : 0;
		$late     = $now - $next;
		if ( $late <= 0 ) {
			return self::signal( 'cron-' . $name, $name, 'green', 'Scheduled. Next run is in ' . self::span( $next - $now ) . '.', $name );
		}
		if ( $late <= 900 ) {
			return self::signal( 'cron-' . $name, $name, 'green', 'Due. Next clear slot is within ' . self::span( $late ) . '.', $name );
		}
		$yellow_for = max( 3600, $interval * 2 );
		if ( $late <= $yellow_for ) {
			return self::signal( 'cron-' . $name, $name, 'yellow', 'Overdue by ' . self::span( $late ) . '. The queue has not given this hook a future run yet.', $name );
		}
		return self::signal( 'cron-' . $name, $name, 'red', 'Stalled for ' . self::span( $late ) . '. No future run is stored for this hook.', $name );
	}

	/**
	 * @param string $name    Plugin name.
	 * @param array  $options Evidence options.
	 * @param int    $now     Now.
	 * @return array|null
	 */
	private static function evidence( $name, $options, $now ) {
		$key    = Luxe_Conductor_Registry::normalize( $name );
		$counts = ( isset( $options['child_counts'] ) && is_array( $options['child_counts'] ) ) ? $options['child_counts'] : array();
		if ( isset( $counts[ $name ] ) || isset( $counts[ $key ] ) ) {
			$count = isset( $counts[ $name ] ) ? $counts[ $name ] : $counts[ $key ];
			$green = isset( $count['green'] ) ? (int) $count['green'] : 0;
			$total = isset( $count['total'] ) ? (int) $count['total'] : 0;
			if ( $total > 0 ) {
				$level = ( $green === $total ) ? 'green' : ( ( $total - $green ) > 2 ? 'red' : 'yellow' );
				return self::signal( 'evidence', 'Reported signals', $level, 'This plugin reports ' . $green . ' / ' . $total . ' green.', '' );
			}
		}

		if ( 'Luxe Affiliate Product Scout' === Luxe_Conductor_Registry::normalize( $name ) && ! empty( $options['laps_dashboard'] ) && is_array( $options['laps_dashboard'] ) ) {
			$dash    = $options['laps_dashboard'];
			$scanned = isset( $dash['scanned'] ) ? (int) $dash['scanned'] : 0;
			$ready   = isset( $dash['ready'] ) ? (int) $dash['ready'] : 0;
			$held    = isset( $dash['held'] ) ? (int) $dash['held'] : 0;
			$review  = isset( $dash['review'] ) ? (int) $dash['review'] : 0;
			$r       = isset( $dash['avg_readiness'] ) ? (int) $dash['avg_readiness'] : 0;
			$i       = isset( $dash['avg_integrity'] ) ? (int) $dash['avg_integrity'] : 0;
			$ok      = $scanned > 0 && ( $ready + $held + $review ) === $scanned;
			$detail  = 'Readiness ' . $r . ', integrity ' . $i . ', homepage-ready ' . $ready . '/' . $scanned . ', hold ' . $held . ', review ' . $review . '.';
			return self::signal( 'evidence', 'Catalog board', $ok ? 'green' : 'yellow', $ok ? $detail : $detail . ' Those counts do not add up to the scan.', '' );
		}

		if ( 'Luxe Score Repair' === Luxe_Conductor_Registry::normalize( $name ) && ! empty( $options['luxe_score_repair_last_learn'] ) && is_array( $options['luxe_score_repair_last_learn'] ) ) {
			$last  = $options['luxe_score_repair_last_learn'];
			$green = isset( $last['green'] ) ? (int) $last['green'] : 0;
			$total = isset( $last['total'] ) ? (int) $last['total'] : 0;
			if ( $total > 0 ) {
				$level = ( $green === $total ) ? 'green' : 'yellow';
				return self::signal( 'evidence', 'Score Repair board', $level, 'Score Repair last stored ' . $green . ' / ' . $total . ' green.', '' );
			}
		}

		if ( 'Luxe Product Integrity Repair' === Luxe_Conductor_Registry::normalize( $name ) && ! empty( $options['luxe_pir_last_run']['at'] ) ) {
			$at   = (int) $options['luxe_pir_last_run']['at'];
			$late = $now - $at;
			$ok   = $late >= 0 && $late <= 26 * 3600;
			return self::signal( 'evidence', 'Last integrity heal', $ok ? 'green' : 'yellow', $ok ? 'Last heal is ' . self::span( $late ) . ' old.' : 'No heal in the last day.', '' );
		}

		if ( 'Luxe Stack Conductor' === Luxe_Conductor_Registry::normalize( $name ) ) {
			$last = ( isset( $options['luxe_conductor_last'] ) && is_array( $options['luxe_conductor_last'] ) ) ? $options['luxe_conductor_last'] : array();
			$cap  = isset( $last['cap'] ) ? (int) $last['cap'] : 1;
			$keep = ( isset( $last['keep'] ) && is_array( $last['keep'] ) ) ? count( $last['keep'] ) : 0;
			$moved = isset( $last['moved'] ) ? (int) $last['moved'] : 0;
			$ok   = $keep <= max( 1, $cap );
			if ( ! $last ) {
				$detail = 'Armed. The next wp-cron hit runs one heavy learner in a clear slot.';
			} elseif ( $moved > 0 ) {
				$detail = 'Last cron kept ' . $keep . ' heavy job(s) and postponed ' . $moved . '.';
			} else {
				$detail = 'Queue is clear. Heavy learners are not stacked on the same second.';
			}
			return self::signal( 'slot', 'One heavy learner per slot', $ok ? 'green' : 'yellow', $detail, '' );
		}

		if ( 'Richard Brummer SEO Mission Control' === Luxe_Conductor_Registry::normalize( $name ) ) {
			return self::signal( 'evidence', 'Evidence dashboard', 'green', 'Active. Mission Control still owns its own red and yellow evidence. This row does not repaint that score.', '' );
		}

		return null;
	}

	/**
	 * @param string $name     Name.
	 * @param string $version  Version.
	 * @param string $lane     Lane.
	 * @param string $menu     Menu URL.
	 * @param array  $signals  Signals.
	 * @return array
	 */
	private static function finish( $name, $version, $lane, $menu, $signals ) {
		$levels = array();
		$detail = '';
		foreach ( $signals as $signal ) {
			$levels[] = $signal['level'];
			if ( '' === $detail && 'green' !== $signal['level'] ) {
				$detail = $signal['detail'];
			}
		}
		if ( '' === $detail && $signals ) {
			$detail = $signals[0]['detail'];
		}
		return array(
			'name'    => $name,
			'version' => $version,
			'level'   => self::worst( $levels ),
			'lane'    => $lane,
			'menu'    => $menu,
			'detail'  => $detail,
			'signals' => $signals,
		);
	}

	/**
	 * @param array $rows Rows.
	 * @param int   $now  Now.
	 * @return array
	 */
	private static function summarize( $rows, $now ) {
		$green  = 0;
		$yellow = 0;
		$red    = 0;
		foreach ( $rows as $row ) {
			if ( 'green' === $row['level'] ) {
				$green++;
			} elseif ( 'red' === $row['level'] ) {
				$red++;
			} else {
				$yellow++;
			}
		}
		return array(
			'rows'   => $rows,
			'green'  => $green,
			'yellow' => $yellow,
			'red'    => $red,
			'total'  => count( $rows ),
			'at'     => $now,
		);
	}

	/**
	 * @param array $levels Levels.
	 * @return string
	 */
	private static function worst( $levels ) {
		if ( in_array( 'red', $levels, true ) ) {
			return 'red';
		}
		if ( in_array( 'yellow', $levels, true ) ) {
			return 'yellow';
		}
		return 'green';
	}

	/**
	 * @param string $id     ID.
	 * @param string $label  Label.
	 * @param string $level  Level.
	 * @param string $detail Detail.
	 * @param string $hook   Hook.
	 * @return array
	 */
	private static function signal( $id, $label, $level, $detail, $hook ) {
		return array(
			'id'     => $id,
			'label'  => $label,
			'level'  => $level,
			'detail' => $detail,
			'hook'   => $hook,
		);
	}

	/**
	 * @param int $seconds Seconds.
	 * @return string
	 */
	private static function span( $seconds ) {
		$seconds = abs( (int) $seconds );
		if ( $seconds < 90 ) {
			return $seconds . 's';
		}
		if ( $seconds < 5400 ) {
			return (int) round( $seconds / 60 ) . ' min';
		}
		if ( $seconds < 172800 ) {
			return (int) round( $seconds / 3600 ) . ' h';
		}
		return (int) round( $seconds / 86400 ) . ' d';
	}
}
