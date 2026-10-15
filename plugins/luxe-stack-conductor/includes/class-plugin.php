<?php
/**
 * WordPress integration. The public site does not run learning on the request.
 *
 * @package Luxe_Stack_Conductor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'Luxe_Conductor_Plugin' ) ) {
	return;
}

/**
 * Schedule, stagger, and render the signal board.
 */
class Luxe_Conductor_Plugin {

	const CRON          = 'luxe_conductor_tick';
	const OPTION_LOG    = 'luxe_conductor_log';
	const OPTION_MEMORY = 'luxe_conductor_memory';
	const OPTION_LAST   = 'luxe_conductor_last';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var float
	 */
	private $started = 0;

	/**
	 * @var bool
	 */
	private $staggered = false;

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
	 * Hooks.
	 */
	public function boot() {
		add_filter( 'cron_schedules', array( $this, 'schedules' ) );
		add_action( self::CRON, array( $this, 'tick' ) );
		add_action( 'init', array( $this, 'ensure_cron' ), 20 );
		add_action( 'wp_loaded', array( $this, 'stagger' ), 9999 );
		add_action( 'shutdown', array( $this, 'record_duration' ), 0 );
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_snapshot' ) );
		add_action( 'admin_init', array( $this, 'maybe_wake' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( LUXE_CONDUCTOR_FILE ), array( $this, 'links' ) );
	}

	/**
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public function schedules( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}
		$schedules['luxe_conductor_five'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 minutes', 'luxe-stack-conductor' ),
		);
		return $schedules;
	}

	/**
	 * Activation schedules the snapshot only.
	 */
	public static function activate() {
		add_filter( 'cron_schedules', array( self::instance(), 'schedules' ) );
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 90, 'luxe_conductor_five', self::CRON );
		}
	}

	/**
	 * Deactivation clears only this plugin's cron.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * Keep the snapshot scheduled from wp-admin and cron, never from a public page.
	 */
	public function ensure_cron() {
		if ( ! $this->is_control_request() ) {
			return;
		}
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 90, 'luxe_conductor_five', self::CRON );
		}
	}

	/**
	 * On a cron hit, leave one heavy learner in place and slide the rest forward.
	 */
	public function stagger() {
		if ( $this->staggered || ! defined( 'DOING_CRON' ) || ! DOING_CRON ) {
			return;
		}
		$this->staggered = true;
		$this->started   = microtime( true );
		if ( ! function_exists( '_get_cron_array' ) ) {
			return;
		}
		$this->catch_up_queue();
		$due = $this->due_events( time() );
		$memory = $this->memory();
		$cap    = isset( $memory['cap'] ) ? (int) $memory['cap'] : 1;
		$plan   = Luxe_Conductor_Dispatcher::plan( $due, time(), $cap );
		$moved  = 0;
		foreach ( $plan['move'] as $event ) {
			if ( $moved >= 30 ) {
				break;
			}
			$args = isset( $event['args'] ) ? (array) $event['args'] : array();
			$ok   = wp_unschedule_event( (int) $event['timestamp'], $event['hook'], $args );
			if ( ! $ok ) {
				continue;
			}
			$when = (int) $event['new_timestamp'];
			if ( ! empty( $event['schedule'] ) ) {
				wp_schedule_event( $when, $event['schedule'], $event['hook'], $args );
			} else {
				wp_schedule_single_event( $when, $event['hook'], $args );
			}
			$moved++;
		}
		update_option(
			self::OPTION_LAST,
			array(
				'at'    => time(),
				'keep'  => $plan['keep'],
				'moved' => $moved,
				'cap'   => $plan['cap'],
			),
			false
		);
	}

	/**
	 * Read-only learning snapshot.
	 */
	public function tick() {
		if ( $this->started <= 0 ) {
			$this->started = microtime( true );
		}
		$board = $this->live_board();
		$this->remember( $board );
		$log = get_option( self::OPTION_LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift(
			$log,
			array(
				'at'     => time(),
				'green'  => $board['green'],
				'yellow' => $board['yellow'],
				'red'    => $board['red'],
				'total'  => $board['total'],
			)
		);
		update_option( self::OPTION_LOG, array_slice( $log, 0, 30 ), false );
	}

	/**
	 * Learn the next cap from how long this cron request took.
	 */
	public function record_duration() {
		if ( ! defined( 'DOING_CRON' ) || ! DOING_CRON || $this->started <= 0 ) {
			return;
		}
		$duration = microtime( true ) - $this->started;
		$memory   = $this->memory();
		$cap      = isset( $memory['cap'] ) ? (int) $memory['cap'] : 1;
		$streak   = isset( $memory['streak'] ) ? (int) $memory['streak'] : 0;
		list( $cap, $streak ) = Luxe_Conductor_Dispatcher::next_cap( $duration, $cap, $streak );
		$memory['cap']      = $cap;
		$memory['streak']   = $streak;
		$memory['duration'] = round( $duration, 2 );
		update_option( self::OPTION_MEMORY, $memory, false );
	}

	/**
	 * Tools-level menu would hide the board. It gets its own item.
	 */
	public function menu() {
		add_menu_page(
			__( 'Luxe Conductor', 'luxe-stack-conductor' ),
			__( 'Luxe Conductor', 'luxe-stack-conductor' ),
			'manage_options',
			'luxe-stack-conductor',
			array( $this, 'render' ),
			'dashicons-networking',
			58
		);
	}

	/**
	 * @param array $links Plugin links.
	 * @return array
	 */
	public function links( $links ) {
		$url = admin_url( 'admin.php?page=luxe-stack-conductor' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Signal board', 'luxe-stack-conductor' ) . '</a>' );
		return $links;
	}

	/**
	 * Manual snapshot. Does not call another plugin's repair.
	 */
	public function maybe_snapshot() {
		if ( empty( $_POST['luxe_conductor_snapshot'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_conductor_snapshot' );
		$board = $this->live_board();
		$this->remember( $board );
		add_settings_error(
			'luxe_conductor',
			'snap',
			sprintf(
				/* translators: 1: green count, 2: total count */
				__( 'Learning snapshot stored. %1$d / %2$d processes green.', 'luxe-stack-conductor' ),
				(int) $board['green'],
				(int) $board['total']
			),
			'updated'
		);
	}

	/**
	 * Opening this screen wakes wp-cron once every five minutes when the snapshot is late.
	 */
	public function maybe_wake() {
		if ( ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'luxe-stack-conductor' !== $page ) {
			return;
		}
		$next = wp_next_scheduled( self::CRON );
		if ( $next && $next > ( time() - 120 ) ) {
			return;
		}
		if ( get_transient( 'luxe_conductor_wake' ) ) {
			return;
		}
		set_transient( 'luxe_conductor_wake', 1, 5 * MINUTE_IN_SECONDS );
		wp_remote_post(
			site_url( 'wp-cron.php' ),
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'body'      => array( 'doing_wp_cron' => (string) microtime( true ) ),
			)
		);
	}

	/**
	 * Admin screen.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$this->catch_up_queue();
		$board  = $this->live_board();
		$memory = $this->memory();
		$log    = get_option( self::OPTION_LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		settings_errors( 'luxe_conductor' );
		include LUXE_CONDUCTOR_DIR . 'templates/board.php';
	}

	/**
	 * @return array
	 */
	public function live_board() {
		$hooks   = $this->cron_hooks();
		$owners  = array();
		foreach ( $hooks as $hook ) {
			$owner = $this->callback_plugin_name( $hook['hook'] );
			if ( ! $owner ) {
				$owner = Luxe_Conductor_Registry::owner_for_hook( $hook['hook'] );
			}
			if ( $owner ) {
				$owners[ $hook['hook'] ] = $owner;
			}
		}
		$options = array(
			'laps_dashboard'                 => get_option( 'laps_dashboard', array() ),
			'luxe_score_repair_last_learn'   => get_option( 'luxe_score_repair_last_learn', array() ),
			'luxe_pir_last_run'              => get_option( 'luxe_pir_last_run', array() ),
			'luxe_conductor_last'            => get_option( self::OPTION_LAST, array() ),
			'child_counts'                   => array(),
		);
		$options = apply_filters( 'luxe_conductor_options', $options );
		return Luxe_Conductor_Signals::board( $this->installed_plugins(), $hooks, $owners, time(), $options );
	}

	/**
	 * @param array $board Board.
	 */
	private function remember( $board ) {
		$memory = $this->memory();
		if ( empty( $memory['priority'] ) || ! is_array( $memory['priority'] ) ) {
			$memory['priority'] = array();
		}
		foreach ( $board['rows'] as $row ) {
			if ( empty( $row['signals'] ) ) {
				continue;
			}
			foreach ( $row['signals'] as $signal ) {
				if ( empty( $signal['hook'] ) ) {
					continue;
				}
				$hook = $signal['hook'];
				$have = isset( $memory['priority'][ $hook ] ) ? (int) $memory['priority'][ $hook ] : 0;
				if ( 'green' === $signal['level'] ) {
					$memory['priority'][ $hook ] = max( 0, $have - 1 );
				} else {
					$memory['priority'][ $hook ] = min( 20, $have + 1 );
				}
			}
		}
		update_option( self::OPTION_MEMORY, $memory, false );
	}

	/**
	 * @return array
	 */
	private function memory() {
		$memory = get_option( self::OPTION_MEMORY, array() );
		return is_array( $memory ) ? $memory : array();
	}

	/**
	 * @return array
	 */
	private function installed_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$rows = array();
		foreach ( get_plugins() as $file => $data ) {
			$rows[] = array(
				'name'    => isset( $data['Name'] ) ? (string) $data['Name'] : '',
				'version' => isset( $data['Version'] ) ? (string) $data['Version'] : '',
				'active'  => is_plugin_active( $file ),
				'file'    => $file,
			);
		}
		return $rows;
	}

	/**
	 * @return array
	 */
	private function cron_hooks() {
		if ( ! function_exists( '_get_cron_array' ) ) {
			return array();
		}
		$cron = _get_cron_array();
		$best = array();
		if ( ! is_array( $cron ) ) {
			return array();
		}
		foreach ( $cron as $timestamp => $hooks ) {
			foreach ( (array) $hooks as $hook => $events ) {
				$next = (int) $timestamp;
				if ( ! isset( $best[ $hook ] ) || $next < $best[ $hook ]['next'] ) {
					$interval = 0;
					foreach ( (array) $events as $event ) {
						if ( isset( $event['interval'] ) ) {
							$interval = (int) $event['interval'];
							break;
						}
					}
					$best[ $hook ] = array(
						'hook'     => $hook,
						'next'     => $next,
						'interval' => $interval,
					);
				}
			}
		}
		return array_values( $best );
	}

	/**
	 * @param int $now Now.
	 * @return array
	 */
	private function due_events( $now ) {
		$cron = _get_cron_array();
		$due  = array();
		if ( ! is_array( $cron ) ) {
			return $due;
		}
		$memory = $this->memory();
		$priority = ( isset( $memory['priority'] ) && is_array( $memory['priority'] ) ) ? $memory['priority'] : array();
		foreach ( $cron as $timestamp => $hooks ) {
			if ( (int) $timestamp > $now ) {
				continue;
			}
			foreach ( (array) $hooks as $hook => $events ) {
				$owner = $this->callback_plugin_name( $hook );
				$flags = Luxe_Conductor_Registry::event_flags( $hook, $owner );
				foreach ( (array) $events as $key => $event ) {
					$due[] = array_merge(
						$flags,
						array(
							'hook'      => $hook,
							'timestamp' => (int) $timestamp,
							'key'       => $key,
							'args'      => isset( $event['args'] ) ? (array) $event['args'] : array(),
							'schedule'  => isset( $event['schedule'] ) ? $event['schedule'] : '',
							'interval'  => isset( $event['interval'] ) ? (int) $event['interval'] : 0,
							'priority'  => isset( $priority[ $hook ] ) ? (int) $priority[ $hook ] : 0,
						)
					);
				}
			}
		}
		return $due;
	}

	/**
	 * Replace stalled timestamps with a real future run, then the board reads that run.
	 */
	private function catch_up_queue() {
		if ( ! function_exists( '_get_cron_array' ) || ! function_exists( 'wp_unschedule_event' ) ) {
			return;
		}
		$plan  = Luxe_Conductor_Dispatcher::catch_up( $this->cron_events(), time() );
		$moved = 0;
		foreach ( $plan['drop'] as $event ) {
			$this->unschedule_event( $event );
		}
		foreach ( $plan['move'] as $event ) {
			if ( $moved >= 40 ) {
				break;
			}
			if ( ! $this->unschedule_event( $event ) ) {
				continue;
			}
			$args = isset( $event['args'] ) ? (array) $event['args'] : array();
			$when = isset( $event['new_timestamp'] ) ? (int) $event['new_timestamp'] : ( time() + 60 );
			if ( ! empty( $event['schedule'] ) ) {
				wp_schedule_event( $when, $event['schedule'], $event['hook'], $args );
			} else {
				wp_schedule_single_event( $when, $event['hook'], $args );
			}
			$moved++;
		}
	}

	/**
	 * @param array $event Cron event.
	 * @return bool
	 */
	private function unschedule_event( $event ) {
		$args = isset( $event['args'] ) ? (array) $event['args'] : array();
		return (bool) wp_unschedule_event( (int) $event['timestamp'], $event['hook'], $args );
	}

	/**
	 * @return array
	 */
	private function cron_events() {
		$cron = _get_cron_array();
		$rows = array();
		if ( ! is_array( $cron ) ) {
			return $rows;
		}
		foreach ( $cron as $timestamp => $hooks ) {
			foreach ( (array) $hooks as $hook => $events ) {
				$owner = $this->callback_plugin_name( $hook );
				$flags = Luxe_Conductor_Registry::event_flags( $hook, $owner );
				foreach ( (array) $events as $key => $event ) {
					$rows[] = array_merge(
						$flags,
						array(
							'hook'      => $hook,
							'timestamp' => (int) $timestamp,
							'key'       => $key,
							'args'      => isset( $event['args'] ) ? (array) $event['args'] : array(),
							'schedule'  => isset( $event['schedule'] ) ? $event['schedule'] : '',
							'interval'  => isset( $event['interval'] ) ? (int) $event['interval'] : 0,
						)
					);
				}
			}
		}
		return $rows;
	}

	/**
	 * @param string $hook Cron hook.
	 * @return string
	 */
	private function callback_plugin_name( $hook ) {
		global $wp_filter;
		if ( empty( $wp_filter[ $hook ] ) || ! is_object( $wp_filter[ $hook ] ) ) {
			return '';
		}
		$callbacks = $wp_filter[ $hook ]->callbacks;
		if ( ! is_array( $callbacks ) ) {
			return '';
		}
		foreach ( $callbacks as $group ) {
			foreach ( (array) $group as $callback ) {
				if ( empty( $callback['function'] ) ) {
					continue;
				}
				$file = $this->callback_file( $callback['function'] );
				$name = $this->plugin_name_from_file( $file );
				if ( $name ) {
					return $name;
				}
			}
		}
		return '';
	}

	/**
	 * @param mixed $function Callback.
	 * @return string
	 */
	private function callback_file( $function ) {
		try {
			if ( is_string( $function ) && function_exists( $function ) ) {
				$ref = new ReflectionFunction( $function );
				return (string) $ref->getFileName();
			}
			if ( $function instanceof Closure ) {
				$ref = new ReflectionFunction( $function );
				return (string) $ref->getFileName();
			}
			if ( is_array( $function ) && isset( $function[0], $function[1] ) && is_string( $function[1] ) ) {
				$ref = new ReflectionMethod( $function[0], $function[1] );
				return (string) $ref->getFileName();
			}
		} catch ( Exception $e ) {
			return '';
		} catch ( Throwable $e ) {
			return '';
		}
		return '';
	}

	/**
	 * @param string $file Absolute file.
	 * @return string
	 */
	private function plugin_name_from_file( $file ) {
		if ( '' === $file || ! defined( 'WP_PLUGIN_DIR' ) ) {
			return '';
		}
		$file = wp_normalize_path( $file );
		$dir  = wp_normalize_path( WP_PLUGIN_DIR );
		if ( 0 !== strpos( $file, $dir ) ) {
			return '';
		}
		$rel    = ltrim( substr( $file, strlen( $dir ) ), '/' );
		$folder = strtok( $rel, '/' );
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( get_plugins() as $plugin_file => $data ) {
			$plugin_folder = strtok( $plugin_file, '/' );
			if ( $plugin_folder === $folder || $plugin_file === $rel ) {
				return isset( $data['Name'] ) ? (string) $data['Name'] : '';
			}
		}
		return '';
	}

	/**
	 * @return bool
	 */
	private function is_control_request() {
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}
		return is_admin() && ! wp_doing_ajax();
	}
}
