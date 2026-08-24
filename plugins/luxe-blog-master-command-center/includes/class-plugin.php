<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared constants and settings. Full Command Center 2.9.0.
 */
class Luxe_BMC_Plugin {

	const OPTION     = 'luxe_bmc_settings';
	const TAG        = 'luxetrendse0f-20';
	const MASTER     = 10833;
	const MASTER_ID  = 10833;
	const MASTER_PATH = '/macbook-pro-m2-pro-review-2026/';
	const MIN_WORDS  = 1800;
	const SIM_LIMIT  = 72;

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
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'process_learning'  => 1,
			'safe_autopilot'    => 0,
			'display_polish'    => 0,
			'mobile_guard'      => 0,
			'date_2026'         => 1,
			'auto_purge'        => 1,
			'last_scan'         => '',
			'last_audit'        => '',
			'last_learning'     => '',
			'last_public_scan'  => '',
			'score'             => 100,
			'score_10'          => 10,
			'band'              => '95-100-ready',
			'public_scan'       => array(),
			'process_greens'    => array(),
		);
	}

	/**
	 * Visible admin action buttons. Every one has a routed handler.
	 *
	 * @return array<string,string>
	 */
	public static function action_buttons() {
		return array(
			'scan_build'     => 'SCAN + BUILD 95–100 MASTER LOCK',
			'repair_95'      => 'REPAIR SELECTED TO 95–100 GREEN',
			'approval_ready' => 'FINAL VERIFY + APPROVAL READY',
			'audit'          => 'RUN READ-ONLY AUDIT',
			'relink'         => 'RE-LINK + REBUILD SELECTED DRAFT',
			'autopilot'      => 'RUN SAFE AUTOPILOT BATCH NOW',
			'harden'         => 'HARDEN PUBLISHED GREEN LOCKS',
			'daily_draft'    => "CREATE TODAY'S SAFE DRAFT",
			'learning'       => 'RUN LEARNING SNAPSHOT NOW',
			'public_scan'    => 'RUN PUBLIC SITE GREEN SCAN',
			'daily_cycle'    => 'RUN DAILY QUALITY CYCLE NOW',
			'cat_fix'        => 'FIX NEXT DRAFTS IN THIS CATEGORY',
			'cat_verify'     => 'VERIFY 95–100 + MARK APPROVAL READY',
		);
	}

	/**
	 * The 25 original 2.8.1 button/hook processes. Kept and routed.
	 *
	 * @return array<string,string>
	 */
	public static function keep_processes() {
		return array(
			'buttons_routed'      => 'All 25 visible admin buttons have routed handlers',
			'admin_router'        => 'Admin action router is hooked',
			'nonce_caps'          => 'Nonce and administrator permission checks are active',
			'scan_build'          => 'Scan + Build button is connected',
			'repair_95'           => '95–100 Repair button is connected',
			'approval_ready'      => 'Approval Ready button is connected',
			'scout_bridge'        => 'Product Scanner bridge key is connected',
			'master_protect'      => 'Protected master #10833 is enforced',
			'heavy_queue'         => 'One-blog heavy-job safety queue is hooked',
			'shutdown_capture'    => 'Critical-error shutdown capture is hooked',
			'backup_before_write' => 'Backup-before-write routine is connected',
			'status_preserve'     => 'Publication-status preservation is connected',
			'all_green_gate'      => '95–100 all-green approval gate is connected',
			'amazon_resolver'     => 'Canonical direct Amazon resolver is connected',
			'amazon_ajax_exclude' => 'Amazon AJAX/cart interception is excluded',
			'amazon_loop'         => 'Amazon redirect-loop prevention is connected',
			'duplicate_titles'    => 'Latest Buyer Guides duplicate-title cleanup is hooked',
			'published_bodies'    => 'Published bodies remain protected from automatic repair',
			'no_auto_publish'     => 'No automatic publishing from scan/fix processes',
			'hard_no_publish'     => 'Hard no-publish authority is enforced',
			'legacy_queue_zero'   => 'Legacy publish queue contains zero jobs',
			'reader_love'         => 'Reader-Love independent verifier is connected',
			'keep_live'           => 'Keep Live published foundation is preserved',
			'product_zero_hold'   => 'Product #0 mismatch hold stays blocked (no fake-green)',
			'flatsome_menu'       => 'Flatsome hamburger/off-canvas is not touched',
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function processes() {
		return array_merge( self::action_buttons(), self::keep_processes() );
	}

	/**
	 * @return array<string,string>
	 */
	public static function process_labels() {
		return self::processes();
	}

	/**
	 * Product meta keys used by 2.8.1 and this update.
	 *
	 * @return array<int,string>
	 */
	public static function product_meta_keys() {
		return array(
			'_luxe_bmc_product_id',
			'_luxe_locked_product_id',
			'_bmc_locked_product',
			'_locked_product_id',
			'luxe_product_id',
			'_product_id',
		);
	}

	public static function activate() {
		$settings = get_option( self::OPTION );
		if ( ! is_array( $settings ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		} else {
			update_option( self::OPTION, wp_parse_args( $settings, self::defaults() ), false );
		}
		if ( class_exists( 'Luxe_BMC_Companion' ) ) {
			Luxe_BMC_Companion::arm();
			Luxe_BMC_Companion::maybe_activate_legacy_identity();
		}
		Luxe_BMC_Learning::activate();
	}

	public static function deactivate() {
		Luxe_BMC_Learning::deactivate();
	}

	public function boot() {
		Luxe_BMC_Queue::instance()->boot();
		Luxe_BMC_Learning::instance()->boot();
		Luxe_BMC_Safety::instance()->boot();
		if ( is_admin() ) {
			Luxe_BMC_Admin::instance()->boot();
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get_settings() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$settings = wp_parse_args( $stored, self::defaults() );
		$settings['mobile_guard']   = 0;
		$settings['display_polish'] = 0;
		return $settings;
	}

	/**
	 * @param array $settings Settings.
	 */
	public static function save_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return;
		}
		$settings = wp_parse_args( $settings, self::defaults() );
		$settings['mobile_guard']   = 0;
		$settings['display_polish'] = 0;
		update_option( self::OPTION, $settings, false );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function settings() {
		return self::get_settings();
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
	 * Locked product ID for a blog. 0 means hold.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public static function product_id( $post_id ) {
		$post_id = (int) $post_id;
		foreach ( self::product_meta_keys() as $key ) {
			$val = (int) get_post_meta( $post_id, $key, true );
			if ( $val > 0 ) {
				return $val;
			}
		}
		return 0;
	}

	/**
	 * @param int $post_id    Post ID.
	 * @param int $product_id Product ID.
	 */
	public static function lock_product( $post_id, $product_id ) {
		$product_id = (int) $product_id;
		if ( $product_id < 1 ) {
			return;
		}
		update_post_meta( (int) $post_id, '_luxe_bmc_product_id', $product_id );
		update_post_meta( (int) $post_id, '_luxe_locked_product_id', $product_id );
		update_post_meta( (int) $post_id, '_bmc_locked_product', $product_id );
	}

	/**
	 * @param int $green  Green.
	 * @param int $total  Total.
	 * @param int $yellow Yellow.
	 * @param int $red    Red.
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

	/**
	 * Reader-Love 7.3.1 stays the independent verifier.
	 *
	 * @return bool
	 */
	public static function reader_love_active() {
		$plugins = get_option( 'active_plugins', array() );
		if ( ! is_array( $plugins ) ) {
			return false;
		}
		foreach ( $plugins as $plugin ) {
			if ( false !== stripos( (string) $plugin, 'reader-love' ) || false !== stripos( (string) $plugin, 'reader_love' ) ) {
				return true;
			}
		}
		return false;
	}
}
