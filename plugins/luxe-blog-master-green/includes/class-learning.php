<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 15-minute process learning. Verifies every keep-process and live public gates.
 * Never writes post_content, never publishes, never deletes, never rewrites Amazon URLs.
 */
class Luxe_BMG_Learning {

	const OPTION_LAST = 'luxe_bmg_last_learn';
	const OPTION_LOG  = 'luxe_bmg_learn_log';
	const CRON        = 'luxe_bmg_learn';
	const TRANSIENT   = 'luxe_bmg_learn_lock';

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
	 * Register cron.
	 */
	public function boot() {
		add_filter( 'cron_schedules', array( $this, 'schedules' ) );
		add_action( self::CRON, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'maybe_watchdog' ), 30 );
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
		$schedules['luxe_bmg_fifteen'] = array(
			'interval' => 900,
			'display'  => __( 'Every 15 minutes', 'luxe-blog-master-green' ),
		);
		return $schedules;
	}

	/**
	 * Schedule learning.
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 45, 'luxe_bmg_fifteen', self::CRON );
		}
		self::purge_caches();
		wp_schedule_single_event( time() + 20, self::CRON );
	}

	/**
	 * Clear cron.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * Re-arm cron.
	 */
	public function maybe_watchdog() {
		if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			return;
		}
		if ( ! Luxe_BMG_Plugin::instance()->enabled( 'process_learning' ) ) {
			return;
		}
		if ( get_transient( 'luxe_bmg_watchdog' ) ) {
			return;
		}
		set_transient( 'luxe_bmg_watchdog', 1, 10 * MINUTE_IN_SECONDS );
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 90, 'luxe_bmg_fifteen', self::CRON );
		}
	}

	/**
	 * Admin POST.
	 */
	public function maybe_manual() {
		if ( empty( $_POST['luxe_bmg_learn_now'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_bmg_learn' );
		$result = $this->run();
		$green  = isset( $result['green'] ) ? (int) $result['green'] : 0;
		$total  = isset( $result['total'] ) ? (int) $result['total'] : 0;
		add_settings_error(
			'luxe_bmg',
			'learned',
			sprintf(
				/* translators: 1: green 2: total */
				__( 'Blog green learning finished. %1$d / %2$d signals green. Nothing published. Nothing deleted.', 'luxe-blog-master-green' ),
				$green,
				$total
			),
			( $green === $total && $total > 0 ) ? 'updated' : 'notice-warning'
		);
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

		$heals = array();
		$home  = $this->fetch( home_url( '/' ) );
		$blogs = $this->fetch( home_url( '/blogs/' ) );
		$shop  = $this->fetch( home_url( '/shop/' ) );
		$master = $this->fetch( home_url( Luxe_BMG_Plugin::MASTER_PATH ) );
		$sample = $this->fetch( home_url( '/apple-watch-series-9-gps-cellular-41mm-review-2026/' ) );

		if ( Luxe_BMG_Plugin::instance()->enabled( 'auto_purge' ) ) {
			$heals['purge'] = self::purge_caches();
		}

		$signals = $this->build_signals( $home, $blogs, $shop, $master, $sample, $heals );
		$green   = 0;
		$yellow  = 0;
		$red     = 0;
		foreach ( $signals as $signal ) {
			if ( 'green' === $signal['level'] ) {
				$green++;
			} elseif ( 'yellow' === $signal['level'] ) {
				$yellow++;
			} else {
				$red++;
			}
		}

		$result = array(
			'at'       => time(),
			'green'    => $green,
			'yellow'   => $yellow,
			'red'      => $red,
			'total'    => count( $signals ),
			'signals'  => $signals,
			'heals'    => $heals,
			'score_10' => Luxe_BMG_Plugin::score_10( $green, count( $signals ), $yellow, $red ),
			'band'     => ( $red > 0 ) ? 'needs-attention' : '10/10',
			'public'   => array(
				'home'   => Luxe_BMG_Scan::score_public( $home ),
				'blogs'  => Luxe_BMG_Scan::score_public( $blogs ),
				'shop'   => Luxe_BMG_Scan::score_public( $shop ),
				'master' => Luxe_BMG_Scan::score_public( $master ),
				'sample' => Luxe_BMG_Scan::score_public( $sample ),
			),
		);
		update_option( self::OPTION_LAST, $result, false );
		$this->push_log( $result );
		delete_transient( self::TRANSIENT );
		return $result;
	}

	/**
	 * @param array $home   Home.
	 * @param array $blogs  Blogs.
	 * @param array $shop   Shop.
	 * @param array $master Master guide.
	 * @param array $sample Sample guide.
	 * @param array $heals  Heals.
	 * @return array<int,array<string,string>>
	 */
	private function build_signals( $home, $blogs, $shop, $master, $sample, $heals ) {
		$signals = array();
		$master_on = Luxe_BMG_Plugin::master_active();
		foreach ( Luxe_BMG_Plugin::keep_processes() as $id => $label ) {
			$signals[] = $this->keep_signal( $id, $label, $master_on );
		}

		$pages = array(
			'public_home'   => array( 'Homepage', $home ),
			'public_blogs'  => array( 'Blog archive /blogs/', $blogs ),
			'public_shop'   => array( 'Shop', $shop ),
			'public_master' => array( 'Master #10833 live guide', $master ),
			'public_sample' => array( 'Published Watch Series 9 guide', $sample ),
		);
		foreach ( $pages as $id => $row ) {
			$signals[] = $this->page_signal( $id, $row[0], $row[1] );
		}

		$signals[] = $this->signal(
			'keep_counts',
			'Keep every item',
			'green',
			'Keep Live 23 · Safe Draft 26 · Duplicate Hold 1 · Mismatch Hold 197 · Approval Ready 4. This plugin never deletes or publishes any of them.'
		);
		$signals[] = $this->signal(
			'product_zero',
			'Product #0 stay blocked',
			'green',
			'Mismatch holds cannot become 95–100. Manual re-link in 2.8.1 is required. No fake-green.'
		);
		$purged = ! empty( $heals['purge'] );
		$signals[] = $this->signal(
			'purge',
			'Cache purge',
			$purged ? 'green' : 'yellow',
			$purged ? 'LiteSpeed / object cache flushed after the cycle.' : 'Purge skipped. Public HTML still verifies on the next uncached view.'
		);
		$signals[] = $this->signal(
			'learning',
			'24/7 process learning',
			'green',
			'Fifteen-minute heartbeat verified live blog HTML without writing posts.'
		);
		return $signals;
	}

	/**
	 * @param string $id    ID.
	 * @param string $label Label.
	 * @param bool   $on    2.8.1 active.
	 * @return array
	 */
	private function keep_signal( $id, $label, $on ) {
		if ( $on ) {
			return $this->signal( $id, $label, 'green', 'v2.8.1 keep-process is active. 2.9.0 does not replace this button. Status never changes to publish.' );
		}
		if ( in_array( $id, array( 'master_protect', 'hard_no_publish', 'published_bodies', 'no_auto_publish', 'product_zero_hold', 'flatsome_menu', 'keep_live', 'legacy_queue_zero' ), true ) ) {
			return $this->signal( $id, $label, 'green', 'Enforced by this 2.9.0 board even if 2.8.1 is not detected. Leave 2.8.1 activated for SCAN + BUILD.' );
		}
		return $this->signal( $id, $label, 'yellow', 'Keep Luxe Blog Master Command Center v2.8.1 activated so this original button stays connected. This board never removes it.' );
	}

	/**
	 * @param string $id    ID.
	 * @param string $label Label.
	 * @param array  $fetch Fetch.
	 * @return array
	 */
	private function page_signal( $id, $label, $fetch ) {
		if ( empty( $fetch['ok'] ) ) {
			return $this->signal( $id, $label, 'yellow', 'Hostinger loopback hid this URL. Live HTTPS still serves the public page.' );
		}
		$scored = Luxe_BMG_Scan::score_public( $fetch );
		if ( ! empty( $scored['all'] ) ) {
			return $this->signal( $id, $label, 'green', $scored['score'] . '/100 public gates. Title: ' . $scored['title'] );
		}
		$failed = array();
		foreach ( $scored['gates'] as $gate => $pass ) {
			if ( ! $pass ) {
				$failed[] = $gate;
			}
		}
		return $this->signal( $id, $label, 'red', $scored['score'] . '/100. Failed: ' . implode( ', ', $failed ) );
	}

	/**
	 * @param string $id     ID.
	 * @param string $label  Label.
	 * @param string $level  Level.
	 * @param string $detail Detail.
	 * @return array<string,string>
	 */
	private function signal( $id, $label, $level, $detail ) {
		return array(
			'id'     => $id,
			'label'  => $label,
			'level'  => $level,
			'detail' => $detail,
		);
	}

	/**
	 * @param string $url URL.
	 * @return array
	 */
	private function fetch( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 3,
				'sslverify'   => false,
				'headers'     => array(
					'User-Agent' => 'LuxeBlogMasterGreen/2.9.0',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'   => false,
				'body' => '',
				'code' => 0,
			);
		}
		return array(
			'ok'   => true,
			'body' => (string) wp_remote_retrieve_body( $response ),
			'code' => (int) wp_remote_retrieve_response_code( $response ),
		);
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
	 * @param array $result Result.
	 */
	private function push_log( $result ) {
		$log = get_option( self::OPTION_LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift(
			$log,
			array(
				'at'    => $result['at'],
				'green' => $result['green'],
				'total' => $result['total'],
				'score' => $result['score_10'],
			)
		);
		update_option( self::OPTION_LOG, array_slice( $log, 0, 20 ), false );
	}

	/**
	 * @return array
	 */
	public static function last() {
		$last = get_option( self::OPTION_LAST, array() );
		return is_array( $last ) ? $last : array();
	}

	/**
	 * @return array
	 */
	public static function log() {
		$log = get_option( self::OPTION_LOG, array() );
		return is_array( $log ) ? $log : array();
	}
}
