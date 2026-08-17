<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Official Flatsome parent updates only. Never runs on public HTML.
 */
class Luxe_Theme_Guard_Updater {

	const LOCK      = 'luxe_theme_guard_lock';
	const AUTO_SITE = 'auto_update_themes';

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
		add_settings_error(
			'luxe_theme_guard',
			'ran',
			sprintf(
				/* translators: 1: green, 2: total */
				__( 'Theme Guard finished. %1$d / %2$d signals green.', 'luxe-theme-guard' ),
				$green,
				$total
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
		if ( function_exists( 'wp_update_themes' ) ) {
			wp_update_themes();
			$heals['refresh'] = 'wp_update_themes';
		}

		$snap = Luxe_Theme_Guard_Signals::snapshot();
		if ( Luxe_Theme_Guard_Plugin::instance()->enabled( 'auto_update' ) && ! empty( $snap['can_upgrade'] ) ) {
			$heals['upgrade'] = $this->upgrade_flatsome();
			$snap             = Luxe_Theme_Guard_Signals::snapshot();
			if ( Luxe_Theme_Guard_Plugin::instance()->enabled( 'auto_purge' ) ) {
				$heals['purge'] = $this->purge();
			}
		} elseif ( $manual && Luxe_Theme_Guard_Plugin::instance()->enabled( 'auto_purge' ) ) {
			$heals['purge'] = $this->purge();
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
