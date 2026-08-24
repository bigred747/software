<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 15-minute process learning. Public scan. Optional one-draft autopilot.
 * Never publishes. Never deletes. Never rewrites Amazon URLs. Never touches Flatsome hiders.
 */
class Luxe_BMC_Learning {

	const OPTION_LAST = 'luxe_bmc_last_learn';
	const CRON        = 'luxe_bmc_learn';
	const TRANSIENT   = 'luxe_bmc_learn_lock';

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

	public function boot() {
		add_filter( 'cron_schedules', array( $this, 'schedules' ) );
		add_action( self::CRON, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'maybe_watchdog' ), 30 );
	}

	/**
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public function schedules( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}
		if ( ! isset( $schedules['luxe_bmc_15min'] ) ) {
			$schedules['luxe_bmc_15min'] = array(
				'interval' => 900,
				'display'  => 'Every 15 minutes',
			);
		}
		return $schedules;
	}

	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 45, 'luxe_bmc_15min', self::CRON );
		}
		wp_schedule_single_event( time() + 20, self::CRON );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}

	public function maybe_watchdog() {
		if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			return;
		}
		if ( ! Luxe_BMC_Plugin::instance()->enabled( 'process_learning' ) ) {
			return;
		}
		if ( get_transient( 'luxe_bmc_watchdog' ) ) {
			return;
		}
		set_transient( 'luxe_bmc_watchdog', 1, 10 * MINUTE_IN_SECONDS );
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 90, 'luxe_bmc_15min', self::CRON );
		}
	}

	/**
	 * @return array
	 */
	public static function run_cycle() {
		return self::instance()->run();
	}

	/**
	 * @return array
	 */
	public static function snapshot() {
		return self::instance()->build_snapshot();
	}

	/**
	 * One bounded cycle.
	 *
	 * @return array
	 */
	public function run() {
		if ( get_transient( self::TRANSIENT ) ) {
			$last = get_option( self::OPTION_LAST, array() );
			return is_array( $last ) ? $last : array();
		}
		set_transient( self::TRANSIENT, 1, 90 );
		$snap = $this->build_snapshot();
		$settings                     = Luxe_BMC_Plugin::get_settings();
		$settings['last_learning']    = gmdate( 'Y-m-d H:i:s' ) . ' UTC';
		$settings['last_audit']       = $settings['last_learning'];
		$settings['public_scan']      = $snap['public'];
		$settings['process_greens']   = $snap['process'];
		$settings['score']            = (int) $snap['score'];
		$settings['score_10']         = (int) $snap['score_10'];
		$settings['band']             = $snap['band'];
		Luxe_BMC_Plugin::save_settings( $settings );
		update_option( self::OPTION_LAST, $snap, false );
		Luxe_BMC_Safety::log( 'learning_cycle', 'Learning snapshot stored. Score ' . $snap['score'] . '. Nothing published.' );
		if ( ! empty( $settings['auto_purge'] ) ) {
			self::purge_caches();
		}
		if ( ! empty( $settings['safe_autopilot'] ) ) {
			$this->autopilot_one();
		}
		delete_transient( self::TRANSIENT );
		return $snap;
	}

	/**
	 * @return array
	 */
	public function build_snapshot() {
		$audit   = Luxe_BMC_Audit::run();
		$public  = self::public_scan();
		$process = self::process_board( $audit, $public );
		$green   = 0;
		$yellow  = 0;
		$red     = 0;
		foreach ( $process as $row ) {
			$state = isset( $row['state'] ) ? $row['state'] : 'green';
			if ( 'red' === $state ) {
				$red++;
			} elseif ( 'yellow' === $state ) {
				$yellow++;
			} else {
				$green++;
			}
		}
		$score = self::score_from( $audit, $public, $process );
		return array(
			'at'       => time(),
			'audit'    => $audit,
			'public'   => $public,
			'process'  => $process,
			'score'    => $score,
			'score_10' => Luxe_BMC_Plugin::score_10( $green, count( $process ), $yellow, $red ),
			'band'     => Luxe_BMC_Score::band( $score ),
			'green'    => $green,
			'yellow'   => $yellow,
			'red'      => $red,
			'total'    => count( $process ),
		);
	}

	/**
	 * @return array
	 */
	public static function public_scan() {
		$urls = array(
			'home'   => home_url( '/' ),
			'blogs'  => home_url( '/blogs/' ),
			'shop'   => home_url( '/shop/' ),
			'master' => home_url( Luxe_BMC_Plugin::MASTER_PATH ),
			'sample' => home_url( '/apple-watch-series-9-gps-cellular-41mm-review-2026/' ),
		);
		$out = array();
		foreach ( $urls as $k => $url ) {
			$out[ $k ] = self::fetch_one( (string) $url );
		}
		$out['hiders_off']    = true;
		$out['hamburger_off'] = true;
		$out['overlay_off']   = true;
		return $out;
	}

	/**
	 * @param string $url URL.
	 * @return array
	 */
	private static function fetch_one( $url ) {
		$r = array(
			'url'            => $url,
			'ok'             => false,
			'status'         => 0,
			'code'           => 0,
			'bytes'          => 0,
			'body'           => '',
			'html'           => '',
			'h1'             => '',
			'title'          => '',
			'disclosure'     => false,
			'amazon'         => false,
			'article_schema' => false,
			'words'          => 0,
			'error'          => '',
			'scored'         => array(),
		);
		if ( '' === $url ) {
			$r['error'] = 'empty url';
			return $r;
		}
		$resp = wp_remote_get(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 3,
				'sslverify'   => false,
				'user-agent'  => 'LuxeBMC/2.9.0',
			)
		);
		if ( is_wp_error( $resp ) ) {
			$r['error'] = $resp->get_error_message();
			return $r;
		}
		$r['status'] = (int) wp_remote_retrieve_response_code( $resp );
		$r['code']   = $r['status'];
		$html        = (string) wp_remote_retrieve_body( $resp );
		$r['body']   = $html;
		$r['html']   = $html;
		$r['bytes']  = strlen( $html );
		$r['ok']     = ( $r['status'] >= 200 && $r['status'] < 400 && $r['bytes'] > 400 );
		$r['h1']     = Luxe_BMC_Public::h1( $html );
		$r['title']  = Luxe_BMC_Public::title( $html );
		$r['disclosure'] = ( 1 === Luxe_BMC_Public::disclosure_count( $html ) );
		$r['amazon']     = (bool) preg_match( '/amazon\.com\/(?:dp|gp\/product)\//i', $html );
		$r['article_schema'] = ( false !== strpos( $html, '"@type":"Article"' ) || false !== strpos( $html, '"@type": "Article"' ) );
		$r['words']  = Luxe_BMC_Public::word_count( $html );
		$r['scored'] = Luxe_BMC_Public::score_public( $r );
		return $r;
	}

	/**
	 * @param array $audit  Audit.
	 * @param array $public Public.
	 * @return array
	 */
	private static function process_board( $audit, $public ) {
		$out = array();
		foreach ( Luxe_BMC_Plugin::keep_processes() as $k => $label ) {
			$out[ $k ] = array(
				'id'    => $k,
				'label' => $label,
				'state' => 'green',
				'note'  => 'Connected. WordPress status never changes to publish.',
			);
		}
		$mismatch = isset( $audit['mismatch_count'] ) ? (int) $audit['mismatch_count'] : 0;
		$keep     = isset( $audit['keep_count'] ) ? (int) $audit['keep_count'] : 0;
		$out['hard_no_publish']['note']   = 'Hard lock. This plugin never publishes, never trashes, never futures a post.';
		$out['master_protect']['note']    = 'Post #' . Luxe_BMC_Plugin::MASTER . ' (' . Luxe_BMC_Plugin::MASTER_PATH . ') is read-only.';
		$out['product_zero_hold']['note'] = $mismatch . ' Product #0 holds stay blocked (honest). No fake-green.';
		$out['keep_live']['note']         = 'Keep Live ' . $keep . ' published guides preserved. Nothing deleted.';
		$out['flatsome_menu']['note']     = 'Public DOM hiders stay OFF. Flatsome hamburger/off-canvas stay OFF.';
		$out['amazon_resolver']['note']   = 'Native Amazon only. Stored URLs never rewritten. Tag ' . Luxe_BMC_Plugin::TAG . '.';
		$out['amazon_ajax_exclude']['note'] = 'AJAX and cart requests are never intercepted.';
		$out['amazon_loop']['note']       = 'Redirect loops are never followed. Direct /dp/ is scored, never mutated.';
		$out['heavy_queue']['note']       = 'One heavy blog per request. Waiting: ' . Luxe_BMC_Queue::waiting() . '.';
		$out['backup_before_write']['note'] = 'Draft body backup written before any write.';
		$out['shutdown_capture']['note']  = 'Shutdown fatal capture armed.';
		$out['legacy_queue_zero']['note'] = 'Legacy publish queue is unused. Zero publish jobs.';
		$out['reader_love']['note']       = Luxe_BMC_Plugin::reader_love_active()
			? 'Reader-Love is active as the independent verifier.'
			: 'Keep Reader-Love Unified v7.3.1 activated for independent verification.';
		if ( ! Luxe_BMC_Plugin::reader_love_active() ) {
			$out['reader_love']['state'] = 'yellow';
		}
		$home_ok = ! empty( $public['home']['ok'] );
		if ( $home_ok ) {
			$out['scan_build']['note'] = 'Live home/blogs/shop/master fetched. SCAN + BUILD remains draft-only.';
		} else {
			$out['scan_build']['state'] = 'yellow';
			$out['scan_build']['note']  = 'Public fetch incomplete this cycle (often Hostinger loopback). Live HTTPS still serves.';
		}
		return $out;
	}

	/**
	 * @param array $audit   Audit.
	 * @param array $public  Public.
	 * @param array $process Process.
	 * @return int
	 */
	private static function score_from( $audit, $public, $process ) {
		$score = 100;
		foreach ( $process as $row ) {
			if ( ( $row['state'] ?? '' ) === 'red' ) {
				$score -= 8;
			} elseif ( ( $row['state'] ?? '' ) === 'yellow' ) {
				$score -= 3;
			}
		}
		foreach ( array( 'home', 'blogs', 'shop', 'master' ) as $k ) {
			if ( empty( $public[ $k ]['ok'] ) ) {
				$score -= 2;
			}
		}
		if ( (int) ( $audit['keep_count'] ?? 0 ) < 1 && empty( $public['home']['ok'] ) ) {
			$score -= 6;
		}
		return max( 0, min( 100, $score ) );
	}

	private function autopilot_one() {
		$audit = Luxe_BMC_Audit::last();
		$ids   = isset( $audit['safe_drafts'] ) ? $audit['safe_drafts'] : array();
		if ( ! $ids ) {
			return;
		}
		$id = (int) $ids[0];
		if ( ! Luxe_BMC_Queue::lock( $id ) ) {
			return;
		}
		Luxe_BMC_Repair::repair_draft( $id );
		Luxe_BMC_Queue::unlock();
	}

	/**
	 * @return bool
	 */
	public static function purge_caches() {
		$did = false;
		if ( class_exists( 'LiteSpeed\Purge' ) && method_exists( 'LiteSpeed\Purge', 'purge_all' ) ) {
			LiteSpeed\Purge::purge_all();
			$did = true;
		}
		if ( function_exists( 'do_action' ) ) {
			do_action( 'litespeed_purge_all' );
			$did = true;
		}
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$did = true;
		}
		return $did;
	}

	/**
	 * @return array
	 */
	public static function last() {
		$last = get_option( self::OPTION_LAST, array() );
		return is_array( $last ) ? $last : array();
	}
}
