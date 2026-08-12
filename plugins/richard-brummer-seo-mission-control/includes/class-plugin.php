<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared options and bootstrap for Mission Control 1.9.0.
 */
class RBSMC_Plugin {

	const OPTION          = 'rbsmc_settings';
	const OPTION_404      = 'rbsmc_404_log';
	const OPTION_DWELL    = 'rbsmc_dwell';
	const OPTION_AUDIT    = 'rbsmc_last_audit';
	const OPTION_SNAPSHOT = 'rbsmc_traffic_snapshot';
	const OPTION_HISTORY  = 'rbsmc_signal_history';
	const CRON_HEARTBEAT  = 'rbsmc_heartbeat';
	const CRON_AUDIT      = 'rbsmc_hourly_audit';

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
	 * @return array
	 */
	public static function defaults() {
		return array(
			'guest_vary_shield'            => 1,
			'hide_leaked_shortcodes'       => 1,
			'hide_utility_ai_filler'       => 1,
			'related_rail'                 => 1,
			'reading_progress'             => 1,
			'helpful_404'                  => 1,
			'log_404'                      => 1,
			'dwell_beacon'                 => 1,
			'amazon_identification_output' => 1,
			'schema_quarantine'            => 1,
			'neutralize_history_traps'     => 0,
			'auto_redirect_404'            => 0,
			'target_dwell_minutes_min'     => 5,
			'target_dwell_minutes_max'     => 15,
			'social_facebook'              => '',
			'social_instagram'             => '',
			'social_youtube'               => '',
			'social_pinterest'             => '',
			'red_email'                    => '',
		);
	}

	/**
	 * Activation.
	 */
	public static function activate() {
		$settings = get_option( self::OPTION );
		if ( ! is_array( $settings ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		} else {
			update_option( self::OPTION, wp_parse_args( $settings, self::defaults() ), false );
		}
		if ( ! wp_next_scheduled( self::CRON_HEARTBEAT ) ) {
			wp_schedule_event( time() + 120, 'rbsmc_fifteen', self::CRON_HEARTBEAT );
		}
		if ( ! wp_next_scheduled( self::CRON_AUDIT ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::CRON_AUDIT );
		}
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HEARTBEAT );
		wp_clear_scheduled_hook( self::CRON_AUDIT );
	}

	/**
	 * Runtime hooks.
	 */
	public function boot() {
		add_filter(
			'cron_schedules',
			function ( $schedules ) {
				$schedules['rbsmc_fifteen'] = array(
					'interval' => 15 * MINUTE_IN_SECONDS,
					'display'  => 'Every 15 minutes (RBSMC)',
				);
				return $schedules;
			}
		);
		add_action( self::CRON_HEARTBEAT, array( 'RBSMC_Heartbeat', 'run' ) );
		add_action( self::CRON_AUDIT, array( $this, 'run_audit' ) );
		add_action( 'wp_ajax_rbsmc_dwell', array( $this, 'ajax_dwell' ) );
		add_action( 'wp_ajax_nopriv_rbsmc_dwell', array( $this, 'ajax_dwell' ) );
		add_action( 'wp_ajax_rbsmc_run_audit', array( $this, 'ajax_run_audit' ) );
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
		add_action( 'template_redirect', array( $this, 'buffer_start' ), 0 );

		RBSMC_Stay_Frontend::instance()->boot();
		RBSMC_NotFound::instance()->boot();
		if ( is_admin() ) {
			RBSMC_Admin::instance()->boot();
		}
	}

	/**
	 * Optional full-page buffer for identification + JSON-LD quarantine only.
	 */
	public function buffer_start() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( ! $this->enabled( 'amazon_identification_output' ) && ! $this->enabled( 'schema_quarantine' ) ) {
			return;
		}
		ob_start( array( $this, 'buffer_end' ) );
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public function buffer_end( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		$html = RBSMC_Schema::quarantine_invalid( $html );
		$html = RBSMC_Amazon::maybe_append_identification( $html );
		return $html;
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
	 * @param string $key Key.
	 * @return bool
	 */
	public function enabled( $key ) {
		$s = $this->settings();
		return ! empty( $s[ $key ] );
	}

	/**
	 * Save settings.
	 */
	public function maybe_save() {
		if ( empty( $_POST['rbsmc_save'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'rbsmc_save' );
		$defaults = self::defaults();
		$next     = $this->settings();
		foreach ( $defaults as $key => $default ) {
			if ( in_array( $key, array( 'social_facebook', 'social_instagram', 'social_youtube', 'social_pinterest', 'red_email' ), true ) ) {
				$next[ $key ] = isset( $_POST[ $key ] ) ? esc_url_raw( wp_unslash( $_POST[ $key ] ) ) : '';
				if ( 'red_email' === $key ) {
					$next[ $key ] = isset( $_POST[ $key ] ) ? sanitize_email( wp_unslash( $_POST[ $key ] ) ) : '';
				}
				continue;
			}
			if ( 'target_dwell_minutes_min' === $key || 'target_dwell_minutes_max' === $key ) {
				$next[ $key ] = isset( $_POST[ $key ] ) ? max( 1, min( 60, absint( $_POST[ $key ] ) ) ) : $default;
				continue;
			}
			$next[ $key ] = empty( $_POST[ $key ] ) ? 0 : 1;
		}
		update_option( self::OPTION, $next, false );
		if ( isset( $_POST['snapshot_users'] ) ) {
			update_option(
				self::OPTION_SNAPSHOT,
				array(
					'users'       => absint( wp_unslash( $_POST['snapshot_users'] ) ),
					'avg_seconds' => absint( wp_unslash( $_POST['snapshot_avg_seconds'] ) ),
					'impressions' => absint( wp_unslash( $_POST['snapshot_impressions'] ) ),
					'clicks'      => absint( wp_unslash( $_POST['snapshot_clicks'] ) ),
					'not_found'   => absint( wp_unslash( $_POST['snapshot_404'] ) ),
					'wc_sales'    => sanitize_text_field( wp_unslash( $_POST['snapshot_wc_sales'] ) ),
					'saved_at'    => time(),
					'source'      => 'administrator-entered',
				),
				false
			);
		}
		add_settings_error( 'rbsmc', 'saved', 'Mission Control settings saved. Purge LiteSpeed cache once.', 'updated' );
	}

	/**
	 * Run audit.
	 */
	public function run_audit() {
		$audit  = new RBSMC_Audit();
		$result = $audit->run();
		$hist   = get_option( self::OPTION_HISTORY, array() );
		if ( ! is_array( $hist ) ) {
			$hist = array();
		}
		array_unshift( $hist, array( 'at' => time(), 'score' => $result['score']['score'], 'red' => $result['score']['red'] ) );
		update_option( self::OPTION_HISTORY, array_slice( $hist, 0, 40 ), false );
		return $result;
	}

	/**
	 * AJAX audit.
	 */
	public function ajax_run_audit() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( 'rbsmc_admin', 'nonce' );
		wp_send_json_success( $this->run_audit() );
	}

	/**
	 * Dwell beacon.
	 */
	public function ajax_dwell() {
		if ( ! $this->enabled( 'dwell_beacon' ) ) {
			wp_send_json_success( array( 'ignored' => true ) );
		}
		check_ajax_referer( 'rbsmc_dwell', 'nonce' );
		$seconds = isset( $_POST['seconds'] ) ? min( 86400, absint( $_POST['seconds'] ) ) : 0;
		$scroll  = isset( $_POST['scroll'] ) ? min( 100, absint( $_POST['scroll'] ) ) : 0;
		$path    = isset( $_POST['path'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['path'] ) ), 0, 180 ) : '/';
		$bucket  = $this->dwell_bucket( $seconds );
		$stats   = get_option( self::OPTION_DWELL, array() );
		if ( ! is_array( $stats ) ) {
			$stats = array();
		}
		if ( empty( $stats['buckets'] ) ) {
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
		if ( empty( $stats['paths'] ) ) {
			$stats['paths'] = array();
		}
		if ( ! isset( $stats['paths'][ $path ] ) ) {
			$stats['paths'][ $path ] = array( 'n' => 0, 's' => 0 );
		}
		$stats['paths'][ $path ]['n']++;
		$stats['paths'][ $path ]['s'] += $seconds;
		update_option( self::OPTION_DWELL, $stats, false );
		wp_send_json_success( array( 'bucket' => $bucket ) );
	}

	/**
	 * @param int $seconds Seconds.
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
}
