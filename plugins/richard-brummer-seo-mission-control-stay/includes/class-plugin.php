<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared options, cron, and AJAX for Stay Repair 1.9.2.
 */
class RBSMC_Stay_Plugin {

	const OPTION          = 'rbsmc_stay_settings';
	const OPTION_404      = 'rbsmc_stay_404_log';
	const OPTION_DWELL    = 'rbsmc_stay_dwell';
	const OPTION_AUDIT    = 'rbsmc_stay_last_audit';
	const OPTION_SNAPSHOT = 'rbsmc_stay_traffic_snapshot';
	const CRON_HOOK       = 'rbsmc_stay_hourly_audit';

	/**
	 * Singleton.
	 *
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
	 * Default settings. Safe output fixes are on. History hijacking and auto-redirects stay off.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'guest_vary_shield'         => 1,
			'hide_leaked_shortcodes'    => 1,
			'hide_utility_ai_filler'    => 1,
			'related_rail'              => 1,
			'reading_progress'          => 1,
			'helpful_404'               => 1,
			'log_404'                   => 1,
			'dwell_beacon'              => 1,
			'drop_missing_assets'       => 1,
			'serve_bot_placeholders'    => 1,
			'repair_known_dead_urls'    => 1,
			'neutralize_history_traps'  => 0,
			'auto_redirect_404'         => 0,
			'target_dwell_minutes_min'  => 5,
			'target_dwell_minutes_max'  => 15,
		);
	}

	/**
	 * Activation: schedule hourly evidence, do not write content.
	 */
	public static function activate() {
		$settings = get_option( self::OPTION );
		if ( ! is_array( $settings ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		} else {
			update_option( self::OPTION, wp_parse_args( $settings, self::defaults() ), false );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * Deactivation: stop cron only.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Register runtime hooks.
	 */
	public function boot() {
		add_action( self::CRON_HOOK, array( $this, 'run_hourly_audit' ) );
		add_action( 'wp_ajax_rbsmc_stay_dwell', array( $this, 'ajax_dwell' ) );
		add_action( 'wp_ajax_nopriv_rbsmc_stay_dwell', array( $this, 'ajax_dwell' ) );
		add_action( 'wp_ajax_rbsmc_stay_run_audit', array( $this, 'ajax_run_audit' ) );
		add_action( 'admin_init', array( $this, 'maybe_save_settings' ) );

		RBSMC_Stay_Frontend::instance()->boot();
		RBSMC_Stay_NotFound::instance()->boot();
		if ( is_admin() ) {
			RBSMC_Stay_Admin::instance()->boot();
		}
	}

	/**
	 * @return array
	 */
	public function settings() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * @param string $key Setting key.
	 * @return bool
	 */
	public function enabled( $key ) {
		$settings = $this->settings();
		return ! empty( $settings[ $key ] );
	}

	/**
	 * Persist settings from admin POST.
	 */
	public function maybe_save_settings() {
		if ( empty( $_POST['rbsmc_stay_save'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'rbsmc_stay_save' );

		$defaults = self::defaults();
		$next     = array();
		foreach ( $defaults as $key => $default ) {
			if ( 'target_dwell_minutes_min' === $key || 'target_dwell_minutes_max' === $key ) {
				$next[ $key ] = isset( $_POST[ $key ] ) ? max( 1, min( 60, absint( $_POST[ $key ] ) ) ) : $default;
				continue;
			}
			$next[ $key ] = empty( $_POST[ $key ] ) ? 0 : 1;
		}
		update_option( self::OPTION, $next, false );

		if ( isset( $_POST['snapshot_users'] ) ) {
			$snapshot = array(
				'users'        => absint( wp_unslash( $_POST['snapshot_users'] ) ),
				'avg_seconds'  => absint( wp_unslash( $_POST['snapshot_avg_seconds'] ) ),
				'impressions'  => absint( wp_unslash( $_POST['snapshot_impressions'] ) ),
				'clicks'       => absint( wp_unslash( $_POST['snapshot_clicks'] ) ),
				'not_found'    => absint( wp_unslash( $_POST['snapshot_404'] ) ),
				'wc_sales'     => sanitize_text_field( wp_unslash( $_POST['snapshot_wc_sales'] ) ),
				'saved_at'     => time(),
				'source'       => 'administrator-entered Site Kit / Rank Math snapshot',
			);
			update_option( self::OPTION_SNAPSHOT, $snapshot, false );
		}

		add_settings_error( 'rbsmc_stay', 'saved', __( 'Stay Repair settings saved. Purge LiteSpeed cache once so public pages drop missing assets and pick up the guest-vary shield.', 'rbsmc-stay' ), 'updated' );
	}

	/**
	 * Hourly bounded audit.
	 */
	public function run_hourly_audit() {
		$audit = new RBSMC_Stay_Audit();
		$audit->run();
	}

	/**
	 * Admin-only audit button.
	 */
	public function ajax_run_audit() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( 'rbsmc_stay_admin', 'nonce' );
		$audit  = new RBSMC_Stay_Audit();
		$result = $audit->run();
		wp_send_json_success( $result );
	}

	/**
	 * First-party dwell beacon. No IP, no user agent, path prefix only.
	 */
	public function ajax_dwell() {
		if ( ! $this->enabled( 'dwell_beacon' ) ) {
			wp_send_json_success( array( 'ignored' => true ) );
		}
		check_ajax_referer( 'rbsmc_stay_dwell', 'nonce' );

		$seconds = isset( $_POST['seconds'] ) ? absint( $_POST['seconds'] ) : 0;
		$scroll  = isset( $_POST['scroll'] ) ? min( 100, absint( $_POST['scroll'] ) ) : 0;
		$path    = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '/';
		$path    = substr( $path, 0, 180 );

		if ( $seconds > 86400 ) {
			$seconds = 86400;
		}

		$bucket = $this->dwell_bucket( $seconds );
		$stats  = get_option( self::OPTION_DWELL, array() );
		if ( ! is_array( $stats ) ) {
			$stats = array();
		}
		if ( empty( $stats['buckets'] ) || ! is_array( $stats['buckets'] ) ) {
			$stats['buckets'] = array();
		}
		if ( empty( $stats['buckets'][ $bucket ] ) ) {
			$stats['buckets'][ $bucket ] = 0;
		}
		$stats['buckets'][ $bucket ]++;
		$stats['samples']     = isset( $stats['samples'] ) ? absint( $stats['samples'] ) + 1 : 1;
		$stats['seconds_sum'] = isset( $stats['seconds_sum'] ) ? absint( $stats['seconds_sum'] ) + $seconds : $seconds;
		$stats['scroll_sum']  = isset( $stats['scroll_sum'] ) ? absint( $stats['scroll_sum'] ) + $scroll : $scroll;
		$stats['updated']     = time();

		if ( empty( $stats['paths'] ) || ! is_array( $stats['paths'] ) ) {
			$stats['paths'] = array();
		}
		if ( ! isset( $stats['paths'][ $path ] ) ) {
			$stats['paths'][ $path ] = array(
				'n' => 0,
				's' => 0,
			);
		}
		$stats['paths'][ $path ]['n']++;
		$stats['paths'][ $path ]['s'] += $seconds;
		if ( count( $stats['paths'] ) > 80 ) {
			uasort(
				$stats['paths'],
				function ( $a, $b ) {
					return $b['n'] - $a['n'];
				}
			);
			$stats['paths'] = array_slice( $stats['paths'], 0, 80, true );
		}

		update_option( self::OPTION_DWELL, $stats, false );
		wp_send_json_success( array( 'bucket' => $bucket ) );
	}

	/**
	 * @param int $seconds Dwell seconds.
	 * @return string
	 */
	public function dwell_bucket( $seconds ) {
		if ( $seconds < 3 ) {
			return '0-3s';
		}
		if ( $seconds < 15 ) {
			return '3-15s';
		}
		if ( $seconds < 60 ) {
			return '15-60s';
		}
		if ( $seconds < 300 ) {
			return '1-5m';
		}
		if ( $seconds < 900 ) {
			return '5-15m';
		}
		return '15m+';
	}

	/**
	 * Whether Mission Control 1.8.x is active.
	 *
	 * @return bool
	 */
	public function mission_control_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( 'richard-brummer-seo-mission-control/richard-brummer-seo-mission-control.php' );
	}
}
