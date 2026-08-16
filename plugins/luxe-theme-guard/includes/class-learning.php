<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 24/7 process learning for Flatsome. One signal per 5-minute cycle,
 * hourly licensed-package snapshot, one official upgrade at a time.
 * Never runs the upgrader on a shopper page view.
 */
class Luxe_Theme_Guard_Learning {

	const CRON           = 'luxe_theme_guard_learn';
	const LOCK           = 'luxe_theme_guard_learn_lock';
	const SPAWN          = 'luxe_theme_guard_spawn';
	const OPTION_CURSOR  = 'luxe_theme_guard_cursor';
	const OPTION_SNAP    = 'luxe_theme_guard_snapshot_at';
	const OPTION_UPGRADE = 'luxe_theme_guard_upgrade_at';
	const INTERVAL       = 300;
	const SNAPSHOT_EVERY = 3600;
	const UPGRADE_EVERY  = 43200;

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register 24/7 loop. Theme files are never written on a public HTML request.
	 */
	public function boot() {
		add_filter( 'cron_schedules', array( $this, 'schedules' ) );
		add_action( self::CRON, array( $this, 'cron_cycle' ) );
		add_action( 'init', array( $this, 'ensure_cron' ), 50 );
		add_action( 'init', array( $this, 'maybe_spawn' ), 60 );
		add_action( 'shutdown', array( $this, 'maybe_reschedule' ), 40 );
		add_action( 'admin_init', array( $this, 'maybe_manual' ) );
	}

	/**
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public function schedules( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}
		$schedules['ltg_five'] = array(
			'interval' => self::INTERVAL,
			'display'  => __( 'Every 5 minutes', 'luxe-theme-guard' ),
		);
		return $schedules;
	}

	/**
	 * Schedule 24/7. Do not upgrade on activate.
	 */
	public static function activate() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON );
		}
		if ( function_exists( 'wp_next_scheduled' ) && function_exists( 'wp_schedule_event' ) && ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 90, 'ltg_five', self::CRON );
		}
	}

	/**
	 * Clear learning cron only.
	 */
	public static function deactivate() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON );
		}
	}

	/**
	 * @param int $cursor Cursor.
	 * @param int $total  Signal count.
	 * @return int
	 */
	public static function next_cursor( $cursor, $total ) {
		$total = (int) $total;
		if ( $total < 1 ) {
			return 0;
		}
		return ( (int) $cursor + 1 ) % $total;
	}

	/**
	 * @param int $last Last unix time.
	 * @param int $now  Now.
	 * @return bool
	 */
	public static function should_snapshot( $last, $now ) {
		$last = (int) $last;
		$now  = (int) $now;
		return $last < 1 || ( $now - $last ) >= self::SNAPSHOT_EVERY;
	}

	/**
	 * Refresh WordPress theme metadata when Flatsome is behind, otherwise hourly.
	 *
	 * @param bool $behind Behind recommended.
	 * @param int  $last   Last refresh.
	 * @param int  $now    Now.
	 * @return bool
	 */
	public static function should_refresh_updates( $behind, $last, $now ) {
		if ( $behind ) {
			return true;
		}
		return self::should_snapshot( $last, $now );
	}

	/**
	 * Apply an official package as soon as WordPress has one.
	 *
	 * @param bool $can_upgrade Official package ready.
	 * @return bool
	 */
	public static function should_apply_upgrade( $can_upgrade ) {
		return (bool) $can_upgrade;
	}

	/**
	 * @param int $last Last unix time.
	 * @param int $now  Now.
	 * @return bool
	 */
	public static function should_upgrade_check( $last, $now ) {
		$last = (int) $last;
		$now  = (int) $now;
		return $last < 1 || ( $now - $last ) >= self::UPGRADE_EVERY;
	}

	/**
	 * @param array $signals Signals.
	 * @param int   $cursor  Cursor.
	 * @return array
	 */
	public static function focus_row( $signals, $cursor ) {
		if ( ! is_array( $signals ) || ! $signals ) {
			return array();
		}
		$signals = array_values( $signals );
		$i       = (int) $cursor;
		if ( $i < 0 || $i >= count( $signals ) ) {
			$i = 0;
		}
		return is_array( $signals[ $i ] ) ? $signals[ $i ] : array();
	}

	/**
	 * Admin: one 24/7 cycle without forcing a theme zip download.
	 */
	public function maybe_manual() {
		if ( empty( $_POST['luxe_theme_guard_learn_one'] ) ) {
			return;
		}
		if ( ! current_user_can( 'update_themes' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_theme_guard_run' );
		delete_transient( self::LOCK );
		$result = $this->cycle_one( 'manual' );
		$green  = isset( $result['green'] ) ? (int) $result['green'] : 0;
		$total  = isset( $result['total'] ) ? (int) $result['total'] : 0;
		$focus  = isset( $result['focus_label'] ) ? (string) $result['focus_label'] : 'process';
		add_settings_error(
			'luxe_theme_guard',
			'learned',
			sprintf(
				/* translators: 1: process name, 2: green, 3: total */
				__( '24/7 cycle finished. Learned “%1$s”. %2$d / %3$d signals green. Publication status preserved.', 'luxe-theme-guard' ),
				$focus,
				$green,
				$total
			),
			$green === $total && $total > 0 ? 'updated' : 'notice-warning'
		);
	}

	/**
	 * True when the stored WP-Cron recurrence is not the 5-minute 24/7 loop.
	 *
	 * @param string $schedule Recurrence name.
	 * @return bool
	 */
	public static function needs_reschedule( $schedule ) {
		return 'ltg_five' !== (string) $schedule;
	}

	/**
	 * @return string
	 */
	public static function cron_schedule_name() {
		if ( ! function_exists( '_get_cron_array' ) ) {
			return '';
		}
		$crons = _get_cron_array();
		if ( ! is_array( $crons ) ) {
			return '';
		}
		foreach ( $crons as $hooks ) {
			if ( empty( $hooks[ self::CRON ] ) || ! is_array( $hooks[ self::CRON ] ) ) {
				continue;
			}
			foreach ( $hooks[ self::CRON ] as $event ) {
				if ( ! empty( $event['schedule'] ) ) {
					return (string) $event['schedule'];
				}
			}
		}
		return '';
	}

	/**
	 * Keep the 5-minute event registered. Replace leftover 12-hour 1.0.x timers.
	 */
	public function ensure_cron() {
		if ( ! Luxe_Theme_Guard_Plugin::instance()->enabled( 'learning_24_7' ) ) {
			return;
		}
		$schedule = self::cron_schedule_name();
		$next     = wp_next_scheduled( self::CRON );
		if ( $next && ! self::needs_reschedule( $schedule ) ) {
			return;
		}
		wp_clear_scheduled_hook( self::CRON );
		wp_schedule_event( time() + 45, 'ltg_five', self::CRON );
	}

	/**
	 * Ping wp-cron.php in the background. Never upgrades during the shopper request.
	 */
	public function maybe_spawn() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( ! Luxe_Theme_Guard_Plugin::instance()->enabled( 'learning_24_7' ) ) {
			return;
		}
		if ( get_transient( self::SPAWN ) ) {
			return;
		}
		set_transient( self::SPAWN, 1, 4 * MINUTE_IN_SECONDS );
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * If the recurring event vanished, queue one more cycle.
	 */
	public function maybe_reschedule() {
		if ( ! Luxe_Theme_Guard_Plugin::instance()->enabled( 'learning_24_7' ) ) {
			return;
		}
		if ( wp_next_scheduled( self::CRON ) ) {
			return;
		}
		wp_schedule_single_event( time() + 90, self::CRON );
	}

	/**
	 * WP-Cron entry. Catch errors so a theme check cannot take the site down.
	 */
	public function cron_cycle() {
		if ( ! Luxe_Theme_Guard_Plugin::instance()->enabled( 'learning_24_7' ) ) {
			return;
		}
		try {
			$this->cycle_one( 'cron' );
		} catch ( Exception $e ) {
			error_log( 'Luxe Theme Guard 24/7: ' . $e->getMessage() );
		} catch ( Throwable $e ) {
			error_log( 'Luxe Theme Guard 24/7: ' . $e->getMessage() );
		}
	}

	/**
	 * One process + optional hourly snapshot + at-most-12h official upgrade.
	 *
	 * @param string $source Source label.
	 * @return array
	 */
	public function cycle_one( $source = 'cron' ) {
		if ( get_transient( self::LOCK ) ) {
			$last = Luxe_Theme_Guard_Signals::last();
			return $last ? $last : array();
		}
		set_transient( self::LOCK, 1, 80 );

		$now   = time();
		$heals = array(
			'source' => $source,
		);
		$snap    = Luxe_Theme_Guard_Signals::snapshot();
		$behind  = ! empty( $snap['installed'] ) && empty( $snap['recommended'] );
		$refresh = self::should_refresh_updates( $behind, (int) get_option( self::OPTION_SNAP, 0 ), $now );

		if ( $refresh && function_exists( 'wp_update_themes' ) ) {
			wp_update_themes();
			$heals['refresh'] = 'wp_update_themes';
			update_option( self::OPTION_SNAP, $now, false );
			$snap = Luxe_Theme_Guard_Signals::snapshot();
		}

		if ( Luxe_Theme_Guard_Plugin::instance()->enabled( 'auto_update' ) && self::should_apply_upgrade( ! empty( $snap['can_upgrade'] ) ) ) {
			update_option( self::OPTION_UPGRADE, $now, false );
			$heals['upgrade'] = Luxe_Theme_Guard_Updater::instance()->attempt_upgrade();
			$snap             = Luxe_Theme_Guard_Signals::snapshot();
			if ( Luxe_Theme_Guard_Plugin::instance()->enabled( 'auto_purge' ) ) {
				$heals['purge'] = Luxe_Theme_Guard_Updater::instance()->purge_caches();
			}
		}

		$board = Luxe_Theme_Guard_Signals::build( $snap, $heals );
		$cursor = (int) get_option( self::OPTION_CURSOR, 0 );
		$focus  = self::focus_row( isset( $board['signals'] ) ? $board['signals'] : array(), $cursor );
		$next   = self::next_cursor( $cursor, isset( $board['total'] ) ? (int) $board['total'] : 0 );
		update_option( self::OPTION_CURSOR, $next, false );

		$board['source']      = $source;
		$board['focus']       = isset( $focus['id'] ) ? (string) $focus['id'] : '';
		$board['focus_label'] = isset( $focus['label'] ) ? (string) $focus['label'] : '';
		$board['focus_level'] = isset( $focus['level'] ) ? (string) $focus['level'] : '';
		$board['cursor']      = $next;
		$board['snapshot']    = $refresh ? 1 : 0;
		$board['upgrade_due'] = ! empty( $heals['upgrade'] ) ? 1 : 0;
		update_option( Luxe_Theme_Guard_Signals::OPTION_LAST, $board, false );
		$this->push_log( $board );
		if ( class_exists( 'Luxe_Theme_Guard_Amazon' ) && Luxe_Theme_Guard_Plugin::instance()->enabled( 'amazon_ai' ) ) {
			try {
				Luxe_Theme_Guard_Amazon::instance()->cycle_one( $source, true );
			} catch ( Exception $e ) {
				error_log( 'Luxe Theme Guard Amazon AI: ' . $e->getMessage() );
			} catch ( Throwable $e ) {
				error_log( 'Luxe Theme Guard Amazon AI: ' . $e->getMessage() );
			}
		}
		delete_transient( self::LOCK );
		return $board;
	}

	/**
	 * @param array $board Board.
	 */
	private function push_log( $board ) {
		$log = get_option( Luxe_Theme_Guard_Signals::OPTION_LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift(
			$log,
			array(
				'at'     => isset( $board['at'] ) ? $board['at'] : time(),
				'green'  => isset( $board['green'] ) ? $board['green'] : 0,
				'total'  => isset( $board['total'] ) ? $board['total'] : 0,
				'band'   => isset( $board['band'] ) ? $board['band'] : '',
				'source' => isset( $board['source'] ) ? $board['source'] : 'cron',
				'focus'  => isset( $board['focus_label'] ) ? $board['focus_label'] : '',
				'level'  => isset( $board['focus_level'] ) ? $board['focus_level'] : '',
			)
		);
		update_option( Luxe_Theme_Guard_Signals::OPTION_LOG, array_slice( $log, 0, 30 ), false );
	}

	/**
	 * @return int
	 */
	public static function next_ts() {
		if ( ! function_exists( 'wp_next_scheduled' ) ) {
			return 0;
		}
		$next = wp_next_scheduled( self::CRON );
		return $next ? (int) $next : 0;
	}
}
