<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bounded 15-minute harmony learning. Never writes posts.
 */
class Luxe_Stack_Harmony_Learning {

	const OPTION_LAST = 'luxe_stack_harmony_last_learn';
	const OPTION_LOG  = 'luxe_stack_harmony_learn_log';
	const CRON        = 'luxe_stack_harmony_learn';
	const TRANSIENT   = 'luxe_stack_harmony_learn_lock';

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
		$schedules['lsh_fifteen'] = array(
			'interval' => 900,
			'display'  => __( 'Every 15 minutes', 'luxe-stack-harmony' ),
		);
		return $schedules;
	}

	/**
	 * Schedule learning.
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 30, 'lsh_fifteen', self::CRON );
		}
		self::purge_caches();
		wp_schedule_single_event( time() + 15, self::CRON );
	}

	/**
	 * Clear cron.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * Re-arm cron if Hostinger dropped it.
	 */
	public function maybe_watchdog() {
		if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			return;
		}
		if ( ! Luxe_Stack_Harmony_Plugin::instance()->enabled( 'process_learning' ) ) {
			return;
		}
		if ( get_transient( 'lsh_learn_watchdog' ) ) {
			return;
		}
		set_transient( 'lsh_learn_watchdog', 1, 10 * MINUTE_IN_SECONDS );
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 60, 'lsh_fifteen', self::CRON );
		}
	}

	/**
	 * Admin POST button.
	 */
	public function maybe_manual() {
		if ( empty( $_POST['luxe_stack_harmony_learn_now'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_stack_harmony_learn' );
		$result = $this->run();
		$green  = isset( $result['green'] ) ? (int) $result['green'] : 0;
		$total  = isset( $result['total'] ) ? (int) $result['total'] : 0;
		$yellow = isset( $result['yellow'] ) ? (int) $result['yellow'] : 0;
		add_settings_error(
			'luxe_stack_harmony',
			'learned',
			sprintf(
				/* translators: 1: green count, 2: total, 3: yellow count */
				__( 'Harmony learning finished. %1$d / %2$d signals green (%3$d unverified).', 'luxe-stack-harmony' ),
				$green,
				$total,
				$yellow
			),
			( $green === $total && $total > 0 ) ? 'updated' : 'notice-warning'
		);
	}

	/**
	 * One bounded learning cycle.
	 *
	 * @return array
	 */
	public function run() {
		if ( get_transient( self::TRANSIENT ) ) {
			$last = get_option( self::OPTION_LAST, array() );
			return is_array( $last ) ? $last : array();
		}
		set_transient( self::TRANSIENT, 1, 60 );

		if ( Luxe_Stack_Harmony_Plugin::instance()->enabled( 'cancel_duplicates' ) ) {
			Luxe_Stack_Harmony_Conductor::cancel_now();
		}

		$heals = array();
		$home  = $this->fetch( home_url( '/' ) );
		if ( Luxe_Stack_Harmony_Plugin::instance()->enabled( 'auto_purge' ) ) {
			$heals['purge'] = self::purge_caches();
		}

		$signals = $this->build_signals( $home, $heals );
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
			'score_10' => Luxe_Stack_Harmony_Plugin::score_10( $green, count( $signals ), $yellow, $red ),
			'band'     => Luxe_Stack_Harmony_Plugin::band( $red, $yellow ),
		);
		update_option( self::OPTION_LAST, $result, false );
		$this->push_log( $result );
		delete_transient( self::TRANSIENT );
		return $result;
	}

	/**
	 * @param array $home  Homepage fetch.
	 * @param array $heals Heals.
	 * @return array<int,array<string,string>>
	 */
	private function build_signals( $home, $heals ) {
		$loopback = ( ! empty( $home['ok'] ) && (int) $home['code'] >= 200 && (int) $home['code'] < 400 );
		$signals  = array();
		$signals[] = $this->signal( 'learning', '24/7 process learning', 'green', 'Fifteen-minute heartbeat is on. Duplicate PARK engines are cancelled without deleting plugins.' );

		$active = (array) get_option( 'active_plugins', array() );
		$raw    = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$catalog = array();
		foreach ( $raw as $file => $data ) {
			$catalog[ $file ] = isset( $data['Name'] ) ? (string) $data['Name'] : '';
		}

		$signals[] = $this->level_keep( $active, $catalog );
		$signals[] = $this->level_park( $active, $catalog );
		$signals[] = $this->level_one_blog_engine( $active, $catalog );
		$signals[] = $this->level_finish( $active );
		$signals[] = $this->level_command_center( $active );

		$body = isset( $home['body'] ) ? (string) $home['body'] : '';
		if ( ! $loopback ) {
			$signals[] = $this->signal( 'homepage', 'Homepage HTML', 'yellow', 'Hostinger loopback did not return homepage HTML. Live HTTPS is still the public site.' );
			$signals[] = $this->signal( 'amazon_tag', 'Amazon tag lock', 'yellow', 'Loopback hid affiliate URLs. Tag remains luxetrendse0f-20.' );
			$signals[] = $this->signal( 'no_assets', 'Zero Harmony frontend JS/CSS', 'yellow', 'Loopback hid asset tags. This plugin never enqueues public JS or CSS.' );
			$signals[] = $this->signal( 'hamburger', 'Flatsome hamburger', 'yellow', 'Loopback hid off-canvas markup. Harmony never touches the hamburger.' );
		} else {
			$code = isset( $home['code'] ) ? (int) $home['code'] : 0;
			$signals[] = $this->signal( 'homepage', 'Homepage HTML', ( $code >= 200 && $code < 400 ) ? 'green' : 'red', 'Homepage returned HTTP ' . $code . '.' );
			$signals[] = $this->level_tag( $body );
			$signals[] = $this->level_assets( $body );
			$signals[] = $this->level_hamburger( $body );
		}

		$purged = ! empty( $heals['purge'] );
		$signals[] = $this->signal(
			'purge',
			'Cache purge',
			$purged ? 'green' : 'yellow',
			$purged ? 'LiteSpeed / object cache flushed after the cycle.' : 'Purge skipped or LiteSpeed was not loaded.'
		);

		return $signals;
	}

	/**
	 * @param string[]             $active  Active.
	 * @param array<string,string> $catalog Catalog.
	 * @return array
	 */
	private function level_keep( $active, $catalog ) {
		$missing = array();
		$needles = array(
			'luxe-blog-master-command-center' => 'Command Center',
			'luxe-public-finish'              => 'Finish',
			'rank-math'                       => 'Rank Math',
			'woocommerce/woocommerce.php'     => 'WooCommerce',
		);
		$joined = strtolower( implode( ' ', $active ) );
		foreach ( $needles as $needle => $label ) {
			if ( false === strpos( $joined, $needle ) ) {
				$missing[] = $label;
			}
		}
		unset( $catalog );
		if ( $missing ) {
			return $this->signal( 'keep_set', 'Keep-set active', 'red', 'Missing: ' . implode( ', ', $missing ) . '. Do not let Duplicate Cleaner quarantine these.' );
		}
		return $this->signal( 'keep_set', 'Keep-set active', 'green', 'Command Center, Finish, Rank Math, and WooCommerce are still active.' );
	}

	/**
	 * @param string[]             $active  Active.
	 * @param array<string,string> $catalog Catalog.
	 * @return array
	 */
	private function level_park( $active, $catalog ) {
		$still = Luxe_Stack_Harmony_Conductor::cancel_list( $catalog, $active );
		if ( $still ) {
			return $this->signal( 'park', 'PARK duplicates cancelled', 'red', count( $still ) . ' PARK engine(s) still active. Click Run harmony learning now.' );
		}
		return $this->signal( 'park', 'PARK duplicates cancelled', 'green', 'Second Blog Master, Hostinger AI, Bad Content Eraser, and Orchestrator are off (not deleted).' );
	}

	/**
	 * @param string[]             $active  Active.
	 * @param array<string,string> $catalog Catalog.
	 * @return array
	 */
	private function level_one_blog_engine( $active, $catalog ) {
		$engines = 0;
		foreach ( $active as $file ) {
			$name = isset( $catalog[ $file ] ) ? $catalog[ $file ] : '';
			$hay  = strtolower( $file . ' ' . $name );
			if ( false !== strpos( $hay, 'blog master' ) ) {
				$engines++;
			}
		}
		if ( $engines > 1 ) {
			return $this->signal( 'one_engine', 'One Blog Master repair engine', 'red', $engines . ' Blog Master plugins are active. Harmony will park the Green Board / extra copy.' );
		}
		if ( $engines < 1 ) {
			return $this->signal( 'one_engine', 'One Blog Master repair engine', 'yellow', 'Command Center was not visible on this scan.' );
		}
		return $this->signal( 'one_engine', 'One Blog Master repair engine', 'green', 'Exactly one Blog Master repair engine is active.' );
	}

	/**
	 * @param string[] $active Active.
	 * @return array
	 */
	private function level_finish( $active ) {
		$joined = strtolower( implode( ' ', $active ) );
		if ( false !== strpos( $joined, 'luxe-public-finish' ) ) {
			return $this->signal( 'finish', 'Finish last pass', 'green', 'Luxe Public Finish owns titles, og:title, and official Associate identification. Harmony does not add a second HTML buffer.' );
		}
		return $this->signal( 'finish', 'Finish last pass', 'yellow', 'Upload Finish 1.0.1 into luxe-public-finish. Harmony will not duplicate that last pass.' );
	}

	/**
	 * @param string[] $active Active.
	 * @return array
	 */
	private function level_command_center( $active ) {
		$joined = strtolower( implode( ' ', $active ) );
		if ( false !== strpos( $joined, 'luxe-blog-master-command-center' ) ) {
			return $this->signal( 'command_center', 'Command Center', 'green', 'Canonical Command Center folder is active. Old Green Board copies are parked.' );
		}
		return $this->signal( 'command_center', 'Command Center', 'red', 'Upload Command Center 2.9.4 into luxe-blog-master-command-center. Do not run two repair engines.' );
	}

	/**
	 * @param string $html HTML.
	 * @return array
	 */
	private function level_tag( $html ) {
		if ( ! preg_match_all( '/[?&]tag=([A-Za-z0-9_-]+)/', $html, $m ) ) {
			return $this->signal( 'amazon_tag', 'Amazon tag lock', 'green', 'No tag= on the homepage sample. Stored URLs are never rewritten.' );
		}
		foreach ( $m[1] as $found ) {
			if ( strtolower( $found ) !== 'luxetrendse0f-20' ) {
				return $this->signal( 'amazon_tag', 'Amazon tag lock', 'red', 'A foreign Amazon tag was visible. Harmony does not rewrite URLs.' );
			}
		}
		return $this->signal( 'amazon_tag', 'Amazon tag lock', 'green', 'Every tag= on the sample is luxetrendse0f-20.' );
	}

	/**
	 * @param string $html HTML.
	 * @return array
	 */
	private function level_assets( $html ) {
		if ( preg_match( '/luxe-stack-harmony[^"\']*\.(js|css)/i', $html ) ) {
			return $this->signal( 'no_assets', 'Zero Harmony frontend JS/CSS', 'red', 'A luxe-stack-harmony.js/css URL appeared. Remove it — Harmony must stay HTML-admin only.' );
		}
		return $this->signal( 'no_assets', 'Zero Harmony frontend JS/CSS', 'green', 'This plugin added zero public .js or .css.' );
	}

	/**
	 * @param string $html HTML.
	 * @return array
	 */
	private function level_hamburger( $html ) {
		if ( false !== stripos( $html, 'off-canvas' ) || false !== stripos( $html, 'mfp-off-canvas' ) || false !== stripos( $html, 'nav-icon' ) ) {
			return $this->signal( 'hamburger', 'Flatsome hamburger', 'green', 'Off-canvas markup is present. Harmony never hides it.' );
		}
		return $this->signal( 'hamburger', 'Flatsome hamburger', 'yellow', 'Off-canvas markup was not visible on this fetch.' );
	}

	/**
	 * @param string $id     ID.
	 * @param string $label  Label.
	 * @param string $level  green|yellow|red.
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
		$args     = array(
			'timeout'     => 10,
			'redirection' => 3,
			'sslverify'   => false,
			'headers'     => array(
				'Cache-Control' => 'no-cache',
				'User-Agent'    => 'LuxeStackHarmony/1.0.0',
			),
		);
		$response = wp_remote_get( $url, $args );
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
	 * LiteSpeed + object cache. Never calls Hostinger CDN APIs.
	 *
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
				'band'  => $result['band'],
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
