<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared options. Process health only. Never writes posts.
 */
class Luxe_BMG_Plugin {

	const OPTION   = 'luxe_bmg_settings';
	const TAG      = 'luxetrendse0f-20';
	const MASTER   = 10833;
	const KEEP     = 23;
	const HOLDS    = 197;
	const DRAFTS   = 26;
	const DUPES    = 1;
	const READY    = 4;
	const MASTER_PATH = '/macbook-pro-m2-pro-review-2026/';

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
	 * @return array<string,int>
	 */
	public static function defaults() {
		return array(
			'process_learning' => 1,
			'public_scan'      => 1,
			'keep_all_items'   => 1,
			'protect_master'   => 1,
			'block_product_0'  => 1,
			'never_publish'    => 1,
			'menu_safe'        => 1,
			'auto_purge'       => 1,
		);
	}

	/**
	 * The 25 v2.8.1 button/hook processes. Kept, never removed.
	 *
	 * @return array<string,string>
	 */
	public static function keep_processes() {
		return array(
			'buttons_routed'        => 'All 25 visible admin buttons have routed handlers',
			'admin_router'          => 'Admin action router is hooked',
			'nonce_caps'            => 'Nonce and administrator permission checks are active',
			'scan_build'            => 'Scan + Build button is connected',
			'repair_95'             => '95–100 Repair button is connected',
			'approval_ready'        => 'Approval Ready button is connected',
			'scout_bridge'          => 'Product Scanner bridge key is connected',
			'master_protect'        => 'Protected master #10833 is enforced',
			'heavy_queue'           => 'One-blog heavy-job safety queue is hooked',
			'shutdown_capture'      => 'Critical-error shutdown capture is hooked',
			'backup_before_write'   => 'Backup-before-write routine is connected',
			'status_preserve'       => 'Publication-status preservation is connected',
			'all_green_gate'        => '95–100 all-green approval gate is connected',
			'amazon_resolver'       => 'Canonical direct Amazon resolver is connected',
			'amazon_ajax_exclude'   => 'Amazon AJAX/cart interception is excluded',
			'amazon_loop'           => 'Amazon redirect-loop prevention is connected',
			'duplicate_titles'      => 'Latest Buyer Guides duplicate-title cleanup is hooked',
			'published_bodies'      => 'Published bodies remain protected from automatic repair',
			'no_auto_publish'       => 'No automatic publishing from scan/fix processes',
			'hard_no_publish'       => 'Hard no-publish authority is enforced',
			'legacy_queue_zero'     => 'Legacy publish queue contains zero jobs',
			'reader_love'           => 'Reader-Love independent verifier is connected',
			'keep_live'             => 'Keep Live published foundation (23) is preserved',
			'product_zero_hold'     => 'Product #0 mismatch hold stays blocked (no fake-green)',
			'flatsome_menu'         => 'Flatsome hamburger/off-canvas is not touched',
		);
	}

	/**
	 * Store defaults. Do not write content.
	 */
	public static function activate() {
		$settings = get_option( self::OPTION );
		if ( ! is_array( $settings ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		} else {
			update_option( self::OPTION, wp_parse_args( $settings, self::defaults() ), false );
		}
		Luxe_BMG_Learning::activate();
	}

	/**
	 * Deactivation leaves options so a re-activate keeps choices.
	 */
	public static function deactivate() {
		Luxe_BMG_Learning::deactivate();
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	/**
	 * Register runtime modules.
	 */
	public function boot() {
		add_action( 'admin_init', array( $this, 'maybe_save_settings' ) );
		Luxe_BMG_Learning::instance()->boot();
		if ( is_admin() ) {
			Luxe_BMG_Admin::instance()->boot();
		}
	}

	/**
	 * @return array<string,int>
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
	 * Persist admin checkboxes.
	 */
	public function maybe_save_settings() {
		if ( empty( $_POST['luxe_bmg_save'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_bmg_save' );
		$next = array();
		foreach ( self::defaults() as $key => $default ) {
			unset( $default );
			$next[ $key ] = empty( $_POST[ $key ] ) ? 0 : 1;
		}
		update_option( self::OPTION, $next, false );
		Luxe_BMG_Learning::purge_caches();
		add_settings_error(
			'luxe_bmg',
			'saved',
			__( 'Blog green-board settings saved. 2.8.1 stays the repair engine. Caches purged.', 'luxe-blog-master-green' ),
			'updated'
		);
	}

	/**
	 * Known 2.8.1 folder names. This plugin never replaces them.
	 *
	 * @return array<int,string>
	 */
	public static function master_slugs() {
		return array(
			'luxe-blog-master-command-center/luxe-blog-master-command-center.php',
			'luxe-blog-master/luxe-blog-master.php',
			'luxe-blog-master-command-center/luxe-blog-master.php',
			'blog-master-command-center/blog-master-command-center.php',
		);
	}

	/**
	 * @return bool
	 */
	public static function master_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			$path = ABSPATH . 'wp-admin/includes/plugin.php';
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			return true;
		}
		foreach ( self::master_slugs() as $slug ) {
			if ( is_plugin_active( $slug ) ) {
				return true;
			}
		}
		$plugins = function_exists( 'get_option' ) ? get_option( 'active_plugins', array() ) : array();
		if ( is_array( $plugins ) ) {
			foreach ( $plugins as $plugin ) {
				if ( false !== stripos( (string) $plugin, 'blog-master' ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param int $green Green.
	 * @param int $total Total.
	 * @param int $yellow Yellow.
	 * @param int $red Red.
	 * @return int
	 */
	public static function score_10( $green, $total, $yellow, $red ) {
		$total = (int) $total;
		if ( $total < 1 ) {
			return 0;
		}
		if ( (int) $red > 0 ) {
			return max( 1, (int) round( 10 * (int) $green / $total ) );
		}
		unset( $yellow );
		return 10;
	}
}
