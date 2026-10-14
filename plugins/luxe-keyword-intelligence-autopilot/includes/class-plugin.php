<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings and cron. Public page views do not scan the catalog.
 */
class Luxe_KIA_Plugin {

	const OPTION = 'luxe_kia_settings';
	const HOOK   = 'luxe_kia_cycle';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var Luxe_KIA_Engine|null
	 */
	private $engine = null;

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
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'enabled'        => 1,
			'interval'       => 5,
			'batch'          => 12,
			'max_scans'      => 960,
			'max_repairs'    => 480,
			'phrases'        => 5,
			'green'          => 95,
			'confidence'     => 80,
			'cooldown'       => 6,
			'budget'         => 12,
			'repair_meta'    => 1,
			'repair_alt'     => 1,
			'scan_published' => 1,
			'scan_scheduled' => 1,
			'types_post'     => 1,
			'types_product'  => 1,
			'types_page'     => 1,
			'taxonomies'     => '',
			'protected'      => '',
		);
	}

	/**
	 * Store defaults and replace the one-item cron.
	 */
	public static function activate() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		update_option( self::OPTION, wp_parse_args( $stored, self::defaults() ), false );
		self::clear_legacy_crons();
		add_filter( 'cron_schedules', array( __CLASS__, 'activation_schedule' ) );
		self::reschedule();
	}

	/**
	 * The custom interval must exist during activation, before boot runs.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function activation_schedule( $schedules ) {
		$minutes = 5;
		$stored  = get_option( self::OPTION, array() );
		if ( is_array( $stored ) && isset( $stored['interval'] ) ) {
			$minutes = max( 2, min( 60, (int) $stored['interval'] ) );
		}
		$schedules['luxe_kia_interval'] = array(
			'interval' => $minutes * MINUTE_IN_SECONDS,
			'display'  => 'Keyword Autopilot batch',
		);
		return $schedules;
	}

	/**
	 * Stop the cron and keep the queue scores.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Register hooks.
	 */
	public function boot() {
		add_filter( 'cron_schedules', array( $this, 'schedules' ) );
		add_action( self::HOOK, array( $this, 'cron' ) );
		add_action( 'admin_init', array( $this, 'maybe_cycle' ), 20 );
		if ( is_admin() ) {
			Luxe_KIA_Admin::instance()->boot();
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			self::reschedule();
		}
	}

	/**
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public function schedules( $schedules ) {
		$minutes = max( 2, min( 60, (int) $this->setting( 'interval' ) ) );
		$schedules['luxe_kia_interval'] = array(
			'interval' => $minutes * MINUTE_IN_SECONDS,
			'display'  => 'Keyword Autopilot batch',
		);
		return $schedules;
	}

	/**
	 * Cron entry. Never publishes.
	 */
	public function cron() {
		$this->engine()->cycle( false );
	}

	/**
	 * An admin page view continues the queue between cron hits.
	 */
	public function maybe_cycle() {
		if ( ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
			return;
		}
		if ( ! $this->enabled() ) {
			return;
		}
		if ( ! empty( $_POST['luxe_kia_burst'] ) || ! empty( $_POST['luxe_kia_cycle'] ) || ! empty( $_POST['luxe_kia_id'] ) ) {
			return;
		}
		$state = get_option( Luxe_KIA_Engine::STATE, array() );
		$last  = ( is_array( $state ) && isset( $state['last'] ) ) ? (int) $state['last'] : 0;
		$wait  = max( 2, (int) $this->setting( 'interval' ) ) * MINUTE_IN_SECONDS;
		if ( $last && ( time() - $last ) < $wait ) {
			return;
		}
		$this->engine()->cycle( false );
	}

	/**
	 * @return Luxe_KIA_Engine
	 */
	public function engine() {
		if ( null === $this->engine ) {
			$this->engine = new Luxe_KIA_Engine();
		}
		return $this->engine;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function settings() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * @param string $key Key.
	 * @return mixed
	 */
	public function setting( $key ) {
		$settings = $this->settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * @return bool
	 */
	public function enabled() {
		return ! empty( $this->setting( 'enabled' ) );
	}

	/**
	 * Drop older one-item hooks from this plugin only.
	 */
	public static function clear_legacy_crons() {
		if ( ! function_exists( '_get_cron_array' ) ) {
			return;
		}
		$crons = _get_cron_array();
		if ( ! is_array( $crons ) ) {
			return;
		}
		foreach ( $crons as $hooks ) {
			if ( ! is_array( $hooks ) ) {
				continue;
			}
			foreach ( $hooks as $hook => $events ) {
				if ( ! is_string( $hook ) ) {
					continue;
				}
				if ( false === strpos( $hook, 'luxe_kia' ) && false === strpos( $hook, 'luxe_keyword' ) ) {
					continue;
				}
				wp_clear_scheduled_hook( $hook );
			}
		}
	}

	/**
	 * Schedule the batch hook.
	 */
	public static function reschedule() {
		wp_clear_scheduled_hook( self::HOOK );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 30, 'luxe_kia_interval', self::HOOK );
		}
	}
}
