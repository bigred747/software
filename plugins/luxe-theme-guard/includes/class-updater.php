<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Official Flatsome parent updates only. Never runs on public HTML.
 */
class Luxe_Theme_Guard_Updater {

	const LOCK           = 'luxe_theme_guard_lock';
	const AUTO_SITE      = 'auto_update_themes';
	const OPTION_WATCHED = 'luxe_theme_guard_watched_version';
	const OPTION_WATCHED_AT = 'luxe_theme_guard_watched_at';
	const OPTION_NOTE    = 'luxe_theme_guard_update_note';
	const WATCH_EVERY    = 3600;

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
	 * Write the Envato purchase code where Flatsome's official updater reads it.
	 *
	 * @param string $code Purchase code.
	 * @return bool
	 */
	public static function apply_purchase_code( $code ) {
		$code = Luxe_Theme_Guard_Signals::sanitize_purchase_code( $code );
		if ( ! $code ) {
			return false;
		}
		update_option( 'flatsome_wup_purchase_code', $code, false );
		$reg = get_option( 'flatsome_registration', array() );
		if ( ! is_array( $reg ) ) {
			$reg = array();
		}
		$reg['purchase_code'] = $code;
		update_option( 'flatsome_registration', $reg, false );
		if ( function_exists( 'delete_site_transient' ) ) {
			delete_site_transient( 'update_themes' );
		}
		return true;
	}

	/**
	 * Write a saved purchase code into Flatsome's updater before a version check.
	 */
	public static function sync_saved_license() {
		if ( ! class_exists( 'Luxe_Theme_Guard_Plugin' ) ) {
			return;
		}
		$settings = Luxe_Theme_Guard_Plugin::instance()->settings();
		if ( ! empty( $settings['flatsome_purchase_code'] ) ) {
			self::apply_purchase_code( $settings['flatsome_purchase_code'] );
		}
	}

	/**
	 * Read the official demo stylesheet version. Cached for one hour.
	 * Does not download a theme zip.
	 *
	 * @param bool $force Skip the cache.
	 * @return string
	 */
	public function watch_official_version( $force = false ) {
		$cached = function_exists( 'get_option' ) ? (string) get_option( self::OPTION_WATCHED, '' ) : '';
		$at     = function_exists( 'get_option' ) ? (int) get_option( self::OPTION_WATCHED_AT, 0 ) : 0;
		if ( Luxe_Theme_Guard_Signals::plausible_flatsome_version( $cached ) ) {
			$cached = Luxe_Theme_Guard_Signals::plain_version( $cached );
		} else {
			$cached = '';
		}
		if ( ! $force && $cached && ( time() - $at ) < self::WATCH_EVERY ) {
			return $cached;
		}
		if ( ! function_exists( 'wp_remote_get' ) ) {
			return $cached;
		}
		$url = Luxe_Theme_Guard_Signals::official_watch_url();
		if ( ! Luxe_Theme_Guard_Signals::watch_url_allowed( $url ) ) {
			return $cached;
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 0,
				'user-agent'  => 'LuxeThemeGuard/' . ( defined( 'LUXE_THEME_GUARD_VERSION' ) ? LUXE_THEME_GUARD_VERSION : '1.4.0' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return $cached;
		}
		$body = substr( (string) wp_remote_retrieve_body( $response ), 0, 4000 );
		$ver  = Luxe_Theme_Guard_Signals::parse_style_version( $body );
		if ( ! $ver || Luxe_Theme_Guard_Signals::compare( $ver, Luxe_Theme_Guard_Signals::RECOMMENDED ) < 0 ) {
			return $cached;
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_WATCHED, $ver, false );
			update_option( self::OPTION_WATCHED_AT, time(), false );
		}
		return $ver;
	}

	/**
	 * Ask Flatsome's official updater for a package and store why if it does not send one.
	 *
	 * @return string
	 */
	public function fetch_official_package() {
		self::sync_saved_license();
		if ( function_exists( 'delete_site_transient' ) ) {
			delete_site_transient( 'update_themes' );
		}
		$code = '';
		if ( class_exists( 'Luxe_Theme_Guard_Plugin' ) ) {
			$settings = Luxe_Theme_Guard_Plugin::instance()->settings();
			$code     = isset( $settings['flatsome_purchase_code'] ) ? Luxe_Theme_Guard_Signals::sanitize_purchase_code( $settings['flatsome_purchase_code'] ) : '';
		}
		$verified = array(
			'ok'     => false,
			'detail' => $code ? '' : 'Paste the ThemeForest purchase code and save.',
		);
		if ( $code ) {
			$verified = $this->verify_purchase_code( $code );
		}
		if ( function_exists( 'wp_update_themes' ) ) {
			wp_update_themes();
		}
		$note    = $verified['detail'];
		$version = '';
		if ( function_exists( 'wp_get_theme' ) ) {
			$theme   = wp_get_theme( Luxe_Theme_Guard_Signals::THEME );
			$version = ( $theme && ! is_wp_error( $theme ) && $theme->exists() ) ? (string) $theme->get( 'Version' ) : '';
		}
		if ( function_exists( 'wupdates_check_JQ9eJ' ) ) {
			$transient           = new stdClass();
			$transient->checked  = array( Luxe_Theme_Guard_Signals::THEME => $version );
			$transient->response = array();
			$checked             = wupdates_check_JQ9eJ( $transient );
			$pkg                 = Luxe_Theme_Guard_Signals::package_from_update_transient( $checked );
			if ( $pkg['package'] && Luxe_Theme_Guard_Signals::may_upgrade( Luxe_Theme_Guard_Signals::THEME, $pkg['package'], $version, $pkg['version'] ? $pkg['version'] : '0' ) ) {
				$this->store_theme_package( $version, $checked );
				$note = 'Official package ' . $pkg['version'] . ' is ready.';
			}
		}
		if ( $verified['ok'] && false === strpos( $note, 'Official package' ) ) {
			$note = 'UX Themes accepted the purchase code, but did not return a download. Guard will ask again on the next cycle.';
		} elseif ( '' === $note ) {
			$note = 'The official updater did not return a Flatsome package.';
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_NOTE, $note, false );
		}
		return $note;
	}

	/**
	 * @param string $code Purchase code.
	 * @return array{ok:bool,detail:string}
	 */
	private function verify_purchase_code( $code ) {
		$url = Luxe_Theme_Guard_Signals::official_purchase_check_url();
		if ( ! Luxe_Theme_Guard_Signals::purchase_check_url_allowed( $url ) || ! function_exists( 'wp_remote_post' ) || ! function_exists( 'home_url' ) ) {
			return array(
				'ok'     => false,
				'detail' => 'The official purchase check could not run.',
			);
		}
		$response = wp_remote_post(
			$url,
			array(
				'timeout'    => 15,
				'body'       => array(
					'slug'          => Luxe_Theme_Guard_Signals::THEME,
					'url'           => home_url(),
					'purchase_code' => $code,
				),
				'user-agent' => 'WordPress/' . ( isset( $GLOBALS['wp_version'] ) ? $GLOBALS['wp_version'] : '6.8' ) . '; ' . home_url(),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array(
				'ok'     => false,
				'detail' => 'Could not reach the official Flatsome update server.',
			);
		}
		return Luxe_Theme_Guard_Signals::interpret_purchase_check( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * @param string $version   Installed version.
	 * @param object $checked   Transient returned by Flatsome's updater.
	 */
	private function store_theme_package( $version, $checked ) {
		if ( ! function_exists( 'get_site_transient' ) || ! function_exists( 'set_site_transient' ) ) {
			return;
		}
		$stored = get_site_transient( 'update_themes' );
		if ( ! is_object( $stored ) ) {
			$stored = new stdClass();
		}
		if ( empty( $stored->checked ) || ! is_array( $stored->checked ) ) {
			$stored->checked = array();
		}
		if ( empty( $stored->response ) || ! is_array( $stored->response ) ) {
			$stored->response = array();
		}
		$stored->checked[ Luxe_Theme_Guard_Signals::THEME ]  = $version;
		$stored->response[ Luxe_Theme_Guard_Signals::THEME ] = (array) $checked->response[ Luxe_Theme_Guard_Signals::THEME ];
		$stored->last_checked                                = time();
		set_site_transient( 'update_themes', $stored );
	}

	/**
	 * Cron upgrades need a writable themes directory.
	 *
	 * @param string $method Method.
	 * @return string
	 */
	public function force_direct( $method ) {
		return 'direct';
	}

	/**
	 * WordPress auto-update for Flatsome parent only. 24/7 learning owns cron.
	 */
	public function boot() {
		add_filter( 'auto_update_theme', array( $this, 'auto_update_theme' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'maybe_manual' ) );
	}

	/**
	 * Arm official Flatsome auto-update. Do not upgrade on activate.
	 */
	public static function activate() {
		self::arm_auto();
		Luxe_Theme_Guard_Learning::activate();
	}

	/**
	 * Allow WordPress to apply official Flatsome packages.
	 */
	public static function arm_auto() {
		$auto = get_site_option( self::AUTO_SITE, array() );
		if ( ! is_array( $auto ) ) {
			$auto = array();
		}
		if ( ! in_array( Luxe_Theme_Guard_Signals::THEME, $auto, true ) ) {
			$auto[] = Luxe_Theme_Guard_Signals::THEME;
			update_site_option( self::AUTO_SITE, $auto );
		}
	}

	/**
	 * Stop asking WordPress to auto-update Flatsome. Other themes are left alone.
	 */
	public static function disarm_auto() {
		$auto = get_site_option( self::AUTO_SITE, array() );
		if ( ! is_array( $auto ) ) {
			return;
		}
		$auto = array_values( array_diff( $auto, array( Luxe_Theme_Guard_Signals::THEME ) ) );
		update_site_option( self::AUTO_SITE, $auto );
	}

	/**
	 * Clear 24/7 cron. Leave other plugins' events alone.
	 */
	public static function deactivate() {
		Luxe_Theme_Guard_Learning::deactivate();
	}

	/**
	 * WordPress automatic updates: Flatsome parent only.
	 *
	 * @param bool   $update Whether to update.
	 * @param object $item   Theme item.
	 * @return bool
	 */
	public function auto_update_theme( $update, $item ) {
		if ( ! Luxe_Theme_Guard_Plugin::instance()->enabled( 'auto_update' ) ) {
			return $update;
		}
		$slug = '';
		if ( is_object( $item ) && ! empty( $item->theme ) ) {
			$slug = (string) $item->theme;
		} elseif ( is_array( $item ) && ! empty( $item['theme'] ) ) {
			$slug = (string) $item['theme'];
		}
		if ( Luxe_Theme_Guard_Signals::THEME === $slug ) {
			return true;
		}
		return $update;
	}

	/**
	 * Admin button.
	 */
	public function maybe_manual() {
		if ( empty( $_POST['luxe_theme_guard_run'] ) ) {
			return;
		}
		if ( ! current_user_can( 'update_themes' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_theme_guard_run' );
		delete_transient( self::LOCK );
		$result = $this->run( true );
		$green  = isset( $result['green'] ) ? (int) $result['green'] : 0;
		$total  = isset( $result['total'] ) ? (int) $result['total'] : 0;
		$note   = ( ! empty( $result['snap']['update_note'] ) && $green !== $total ) ? ' ' . $result['snap']['update_note'] : '';
		add_settings_error(
			'luxe_theme_guard',
			'ran',
			sprintf(
				/* translators: 1: green, 2: total, 3: update note */
				__( 'Theme Guard finished. %1$d / %2$d signals green.%3$s', 'luxe-theme-guard' ),
				$green,
				$total,
				$note
			),
			$green === $total && $total > 0 ? 'updated' : 'notice-warning'
		);
	}

	/**
	 * Refresh updates, maybe upgrade Flatsome, rebuild signals.
	 *
	 * @param bool $manual Manual.
	 * @return array
	 */
	public function run( $manual = false ) {
		if ( get_transient( self::LOCK ) ) {
			$last = Luxe_Theme_Guard_Signals::last();
			return $last ? $last : array();
		}
		set_transient( self::LOCK, 1, 120 );

		$heals = array();
		self::sync_saved_license();
		$watched        = $this->watch_official_version( true );
		$heals['watch'] = $watched ? $watched : 'cached-or-unavailable';
		$heals['fetch'] = $this->fetch_official_package();

		$snap = Luxe_Theme_Guard_Signals::snapshot( $watched );
		if ( Luxe_Theme_Guard_Plugin::instance()->enabled( 'auto_update' ) && ! empty( $snap['can_upgrade'] ) ) {
			$heals['upgrade'] = $this->upgrade_flatsome();
			$snap             = Luxe_Theme_Guard_Signals::snapshot( $watched );
			if ( ! empty( $heals['upgrade']['ok'] ) && Luxe_Theme_Guard_Plugin::instance()->enabled( 'auto_purge' ) ) {
				$heals['purge'] = $this->purge();
			}
		}

		$board = Luxe_Theme_Guard_Signals::build( $snap, $heals );
		$board['source']      = 'manual';
		$board['focus']       = 'upgrade';
		$board['focus_label'] = 'Check and update Flatsome now';
		update_option( Luxe_Theme_Guard_Signals::OPTION_LAST, $board, false );
		$this->push_log( $board );
		delete_transient( self::LOCK );
		return $board;
	}

	/**
	 * @return array
	 */
	public function attempt_upgrade() {
		return $this->upgrade_flatsome();
	}

	/**
	 * @return string[]
	 */
	public function purge_caches() {
		return $this->purge();
	}

	/**
	 * @return array
	 */
	private function upgrade_flatsome() {
		if ( ! current_user_can( 'update_themes' ) && ! wp_doing_cron() ) {
			return array( 'ok' => false, 'detail' => 'not-allowed' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		if ( ! class_exists( 'Theme_Upgrader' ) ) {
			return array( 'ok' => false, 'detail' => 'upgrader-missing' );
		}
		add_filter( 'filesystem_method', array( $this, 'force_direct' ), 99 );
		$skin     = class_exists( 'Automatic_Upgrader_Skin' ) ? new Automatic_Upgrader_Skin() : new WP_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );
		$result   = $upgrader->upgrade( Luxe_Theme_Guard_Signals::THEME );
		remove_filter( 'filesystem_method', array( $this, 'force_direct' ), 99 );
		if ( is_wp_error( $result ) ) {
			return array(
				'ok'     => false,
				'detail' => $result->get_error_message(),
			);
		}
		return array(
			'ok'     => ( false !== $result ),
			'detail' => $result ? 'flatsome-updated' : 'no-package-or-already-current',
		);
	}

	/**
	 * @return string[]
	 */
	private function purge() {
		$did = array();
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$did[] = 'wp_cache_flush';
		}
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
			$did[] = 'litespeed_purge_all';
		}
		if ( class_exists( '\LiteSpeed\Purge' ) && method_exists( '\LiteSpeed\Purge', 'purge_all' ) ) {
			\LiteSpeed\Purge::purge_all();
			$did[] = 'LiteSpeed\\Purge::purge_all';
		}
		return $did;
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
				'source' => 'manual',
				'focus'  => 'Check and update Flatsome now',
				'level'  => ( ! empty( $board['green'] ) && isset( $board['total'] ) && (int) $board['green'] === (int) $board['total'] ) ? 'green' : 'red',
			)
		);
		update_option( Luxe_Theme_Guard_Signals::OPTION_LOG, array_slice( $log, 0, 30 ), false );
	}
}
