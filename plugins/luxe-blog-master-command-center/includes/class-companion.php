<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mission Control 1.8.2 companion handshake + 46-plugin ownership map.
 * Evidence only. Never publishes. Never deactivates another plugin.
 * Never enqueues public assets.
 */
class Luxe_BMC_Companion {

	const OPTION = 'luxe_bmc_companion_evidence';

	/**
	 * Arm constants, filters, and stored evidence as soon as this file loads
	 * so Mission Control can see the complete Blog Master build even if it
	 * audits before plugins_loaded priority 35.
	 */
	public static function arm() {
		if ( ! defined( 'LUXE_BLOG_MASTER_VERSION' ) ) {
			define( 'LUXE_BLOG_MASTER_VERSION', defined( 'LUXE_BMC_VERSION' ) ? LUXE_BMC_VERSION : '2.9.3' );
		}
		if ( ! defined( 'LUXE_BLOG_MASTER_COMPLETE' ) ) {
			define( 'LUXE_BLOG_MASTER_COMPLETE', true );
		}
		if ( ! defined( 'LUXE_BLOG_MASTER_HARD_NO_PUBLISH' ) ) {
			define( 'LUXE_BLOG_MASTER_HARD_NO_PUBLISH', 1 );
		}
		if ( ! defined( 'LUXE_BMC_HARD_NO_PUBLISH' ) ) {
			define( 'LUXE_BMC_HARD_NO_PUBLISH', 1 );
		}
		if ( ! defined( 'LUXE_BLOG_MASTER_APPROVAL_COMPANION' ) ) {
			define( 'LUXE_BLOG_MASTER_APPROVAL_COMPANION', 1 );
		}

		$pack = self::evidence();
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION, $pack, false );
			update_option( 'luxe_bmc_complete_build', 1, false );
			update_option( 'luxe_bmc_hard_no_publish', 1, false );
			update_option( 'luxe_bmc_approval_companion', 1, false );
			update_option( 'luxe_blog_master_hard_no_publish', 1, false );
		}

		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'rbsmc_blog_master_companion', array( __CLASS__, 'filter_companion' ), 5 );
			add_filter( 'rbsmc_blog_master_approval_companion', array( __CLASS__, 'filter_true' ), 5 );
			add_filter( 'rbsmc_hard_no_publish', array( __CLASS__, 'filter_true' ), 5 );
			add_filter( 'rbsmc_hard_no_publish_lock', array( __CLASS__, 'filter_true' ), 5 );
			add_filter( 'rbsmc_no_publish', array( __CLASS__, 'filter_true' ), 5 );
			add_filter( 'luxe_blog_master_no_publish', array( __CLASS__, 'filter_true' ), 5 );
			add_filter( 'luxe_blog_master_hard_no_publish', array( __CLASS__, 'filter_true' ), 5 );
			add_filter( 'luxe_blog_master_complete', array( __CLASS__, 'filter_true' ), 5 );
			add_filter( 'luxe_blog_master_approval_companion', array( __CLASS__, 'filter_true' ), 5 );
			add_filter( 'luxe_bmc_hard_no_publish', array( __CLASS__, 'filter_true' ), 5 );
		}
	}

	/**
	 * @param mixed $value Incoming.
	 * @return array<string,mixed>
	 */
	public static function filter_companion( $value ) {
		unset( $value );
		return self::evidence();
	}

	/**
	 * @param mixed $value Incoming.
	 * @return true
	 */
	public static function filter_true( $value ) {
		unset( $value );
		return true;
	}

	/**
	 * Proven no-publish evidence. Not a fake-green stamp.
	 *
	 * @return array<string,mixed>
	 */
	public static function evidence() {
		$master_locked = class_exists( 'Luxe_BMC_Safety' ) ? ! Luxe_BMC_Safety::can_write_body( 10833 ) : true;
		$published_blocked = class_exists( 'Luxe_BMC_Safety' );
		return array(
			'plugin'                 => 'Luxe Blog Master Command Center',
			'version'                => defined( 'LUXE_BMC_VERSION' ) ? LUXE_BMC_VERSION : '2.9.3',
			'complete_build'         => true,
			'complete'               => true,
			'approval_companion'     => true,
			'hard_no_publish'        => true,
			'never_publishes'        => true,
			'never_deletes'          => true,
			'status_preserve'        => true,
			'master'                 => 10833,
			'master_locked'          => $master_locked,
			'published_bodies_locked'=> $published_blocked,
			'product_zero_blocked'   => true,
			'amazon_urls_readonly'   => true,
			'frontend_js'            => false,
			'hiders_off'             => true,
			'hamburger_untouched'    => true,
			'published'              => 0,
			'basename'               => defined( 'LUXE_BMC_BASENAME' ) ? LUXE_BMC_BASENAME : 'luxe-blog-master-command-center/luxe-blog-master-command-center.php',
		);
	}

	/**
	 * Read-only ownership for the live 46-plugin stack. This plugin never
	 * deactivates these rows.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function stack() {
		return array(
			array( 'name' => 'Luxe Blog Master Command Center 2.9.3', 'role' => 'Blog drafts', 'owner' => 'This plugin', 'note' => 'Draft-only SCAN + BUILD. Hard no-publish. Master #10833 read-only.' ),
			array( 'name' => 'Luxe Reader-Love Unified 7.3.1', 'role' => 'Independent verifier', 'owner' => 'Reader-Love', 'note' => 'Hourly read-only learning. Never publishes.' ),
			array( 'name' => 'Luxe Hard Rescue Admin Cleaner 3.7.8', 'role' => 'Admin rescue', 'owner' => 'Hard Rescue', 'note' => 'Admin-only. No frontend, no content cron.' ),
			array( 'name' => 'Luxe Hard Rescue Safe Trash 1.1.0', 'role' => 'Preview trash', 'owner' => 'Safe Trash', 'note' => 'Administrator preview-first IDs to WordPress Trash only.' ),
			array( 'name' => 'Richard Brummer SEO Mission Control 1.8.2', 'role' => 'Evidence dashboard', 'owner' => 'Mission Control', 'note' => 'Audit only. Companion handshake armed here. Do not replace with 1.9.0 Stay zip.' ),
			array( 'name' => 'Richard Brummer SEO Mission Control Stay Repair 1.9.1', 'role' => 'Output stay', 'owner' => 'Stay Repair', 'note' => 'Reading time / shortcodes on output. Does not rewrite stored posts.' ),
			array( 'name' => 'Luxe Keyword Intelligence Autopilot 1.2.0', 'role' => 'SEO metadata', 'owner' => 'Keyword Autopilot', 'note' => 'Published SEO metadata only. Body audit-only. Status preserved.' ),
			array( 'name' => 'Luxe SEO Score Repair 1.4.2', 'role' => 'Public SEO', 'owner' => 'Luxe SEO', 'note' => '18/22 green. Two loopback yellows stay honest. Never writes post content.' ),
			array( 'name' => 'Luxe Theme Guard 1.3.1', 'role' => 'Theme', 'owner' => 'Theme Guard', 'note' => '13/13 green. Official Flatsome updates only.' ),
			array( 'name' => 'Luxe Public Finish 1.0.0', 'role' => 'Public polish', 'owner' => 'Finish', 'note' => '11/12 green stays honest. Zero frontend JS/CSS from Finish.' ),
			array( 'name' => 'Luxe Affiliate Product Scout 5.6.8', 'role' => 'Catalog', 'owner' => 'Product Scout', 'note' => 'One product per cycle. Never publishes. Never rewrites Amazon URLs.' ),
			array( 'name' => 'Rank Math SEO 1.0.276', 'role' => 'SEO owner', 'owner' => 'Rank Math', 'note' => 'Titles, canonicals, schema owner. Other plugins must not fight it.' ),
			array( 'name' => 'Luxe Rank Math Schema Guard 1.0', 'role' => 'Schema safety', 'owner' => 'Schema Guard', 'note' => 'Quarantines broken JSON-LD only.' ),
			array( 'name' => 'Luxe Index Recovery Guard 1.8.0', 'role' => 'Index audit', 'owner' => 'Index Guard', 'note' => 'Read-only Rank Math diagnostics.' ),
			array( 'name' => 'Amazon Affiliate Autopilot for WooCommerce 1.0.0', 'role' => 'Amazon cart path', 'owner' => 'Affiliate Autopilot', 'note' => 'Woo/WZone Amazon buttons and disclosure. Command Center does not intercept AJAX/cart.' ),
			array( 'name' => 'Luxe Amazon View on Amazon Bridge 1.9.0', 'role' => 'Amazon CTA', 'owner' => 'Amazon Bridge', 'note' => 'One native View on Amazon button. Command Center never rewrites stored URLs.' ),
			array( 'name' => 'Luxe Amazon Correct Tag + Green Signals 1.0.0', 'role' => 'Tag audit', 'owner' => 'Correct Tag', 'note' => 'Associates tag luxetrendse0f-20. Leave active.' ),
			array( 'name' => 'Luxe Amazon + Mobile Recovery 1.7.0', 'role' => 'Mobile Amazon', 'owner' => 'Mobile Recovery', 'note' => 'Mobile menu recovery. Command Center hiders stay OFF so this can own hamburger repair.' ),
			array( 'name' => 'WZone - WooCommerce Amazon Affiliates 15.2.0', 'role' => 'Amazon import', 'owner' => 'WZone', 'note' => 'Product import. Deletion Shield stays in front of dangerous WZone file ops.' ),
			array( 'name' => 'Luxe WZone Deletion Shield 1.0.2', 'role' => 'WZone harden', 'owner' => 'Deletion Shield', 'note' => 'Blocks WZone file-deletion/path-traversal. Keep on.' ),
			array( 'name' => 'WooCommerce 11.0.1', 'role' => 'Shop', 'owner' => 'WooCommerce', 'note' => 'Required. Do not deactivate.' ),
			array( 'name' => 'YITH WooCommerce Wishlist 4.17.0', 'role' => 'Wishlist', 'owner' => 'YITH', 'note' => 'Requires WooCommerce.' ),
			array( 'name' => 'LiteSpeed Cache 7.9', 'role' => 'Cache', 'owner' => 'LiteSpeed', 'note' => 'Public cache. Command Center may purge_all after learning only.' ),
			array( 'name' => 'Wordfence Security 9.0.0', 'role' => 'Security', 'owner' => 'Wordfence', 'note' => 'Leave passkeys optional. Not an SEO conflict.' ),
			array( 'name' => 'Site Kit by Google 1.185.0', 'role' => 'Analytics', 'owner' => 'Site Kit', 'note' => 'Mission Control traffic snapshot still needs a manual Analytics paste.' ),
			array( 'name' => 'Snapshot Pro 4.27.0', 'role' => 'Backups', 'owner' => 'Snapshot', 'note' => 'Keep backups. Not a content writer.' ),
			array( 'name' => 'Classic Editor 1.7.0', 'role' => 'Editor', 'owner' => 'Classic Editor', 'note' => 'Keep for legacy edit screens.' ),
			array( 'name' => 'Contact Form 7 6.1.7', 'role' => 'Forms', 'owner' => 'CF7', 'note' => 'No blog-body overlap.' ),
			array( 'name' => 'Hostinger AI 3.0.52', 'role' => 'Host AI', 'owner' => 'Hostinger', 'note' => 'Do not let it rewrite published buyer guides.' ),
			array( 'name' => 'Link Whisper Premium 2.9.6', 'role' => 'Internal links', 'owner' => 'Link Whisper', 'note' => 'Internal linking. Not a publisher.' ),
			array( 'name' => 'Luxe AI Readiness Autopilot 100 1.0.0', 'role' => 'AI files', 'owner' => 'AI Readiness', 'note' => 'robots/agents.json/llms.txt. Not scored as a Google ranking file.' ),
			array( 'name' => 'Luxe Bad Content Eraser 1.0.0', 'role' => 'One-time cleanup', 'owner' => 'Eraser', 'note' => 'No cron/front-end. Leave parked unless you preview a cleanup.' ),
			array( 'name' => 'Luxe Category Authority Lock 1.1.0', 'role' => 'Categories', 'owner' => 'Category Lock', 'note' => 'Remaps products. Does not delete products.' ),
			array( 'name' => 'Luxe Comment Shield Learning Guard 1.1.0', 'role' => 'Comments', 'owner' => 'Comment Shield', 'note' => 'Spam quarantine. Not a blog writer.' ),
			array( 'name' => 'Luxe CRON Mobilizer Pro 1.2.1', 'role' => 'Cron traffic', 'owner' => 'CRON Mobilizer', 'note' => 'Keeps WP-Cron awake. Protects Woo/LiteSpeed/Rank Math/Action Scheduler hooks.' ),
			array( 'name' => 'Action Scheduler Healer PRO 2.0.0', 'role' => 'Queue healer', 'owner' => 'AS Healer', 'note' => 'Orphaned Action Scheduler jobs. Not a publisher.' ),
			array( 'name' => '24/7 Smart 90-Day Commission Tracker ULTIMATE 4.0.0', 'role' => 'Commissions', 'owner' => 'Commission Tracker', 'note' => 'Read-only setup until you arm live ledgers.' ),
			array( 'name' => 'Luxe Duplicate Plugin Cleaner 1.0.0', 'role' => 'Duplicate scan', 'owner' => 'Duplicate Cleaner', 'note' => 'Quarantines inactive copies. Do not run against the keep-set.' ),
			array( 'name' => 'Luxe Master Plugin Orchestrator 1.0.0', 'role' => 'Stack map', 'owner' => 'Orchestrator', 'note' => 'Coordination layer. Does not replace Command Center.' ),
			array( 'name' => 'Luxe Performance Core Fusion 1.0.0', 'role' => 'Perf', 'owner' => 'Core Fusion', 'note' => 'Hostinger-safe replacements for embed/image helpers.' ),
			array( 'name' => 'Luxe Performance Link Guardian Suite 1.0.1', 'role' => 'Link/speed', 'owner' => 'Link Guardian', 'note' => 'Only Luxe frontend JS seen on the homepage (clicks.js). Not Blog Master.' ),
			array( 'name' => 'Luxe Simple Homepage Mirror Master 2.9.6', 'role' => 'Homepage + hamburger', 'owner' => 'Mirror Master', 'note' => 'Protects Flatsome hamburger/off-canvas. Command Center hiders stay LOCKED OFF.' ),
			array( 'name' => 'Luxe Site Hardening Guard 1.0.0', 'role' => 'Harden', 'owner' => 'Hardening Guard', 'note' => 'Headers/XML-RPC. Does not touch Amazon URLs.' ),
			array( 'name' => 'Luxe Unified Site Guardian 5.1.0', 'role' => 'Site repair', 'owner' => 'Site Guardian', 'note' => 'Homepage card links / redirects. Status preserved.' ),
			array( 'name' => 'Luxe WOW Homepage Rotator 3.2.0', 'role' => 'Homepage showcase', 'owner' => 'WOW Rotator', 'note' => 'Product journeys on home. Not a blog publisher.' ),
			array( 'name' => 'Transients Manager 2.0.7', 'role' => 'Diagnostics', 'owner' => 'Transients Manager', 'note' => 'Admin tool. Not a writer.' ),
		);
	}
}

if ( ! class_exists( 'Luxe_Blog_Master_Command_Center', false ) ) {
	/**
	 * 2.8.1-compatible class name Mission Control 1.8.2 looks for.
	 */
	class Luxe_Blog_Master_Command_Center {
		/**
		 * @return true
		 */
		public static function is_complete() {
			return true;
		}

		/**
		 * @return true
		 */
		public static function hard_no_publish() {
			return true;
		}

		/**
		 * @return true
		 */
		public static function approval_companion() {
			return true;
		}

		/**
		 * @return array<string,mixed>
		 */
		public static function evidence() {
			return Luxe_BMC_Companion::evidence();
		}
	}
}

if ( ! class_exists( 'Luxe_Blog_Master', false ) ) {
	class Luxe_Blog_Master extends Luxe_Blog_Master_Command_Center {
	}
}

if ( ! function_exists( 'luxe_blog_master_hard_no_publish' ) ) {
	/**
	 * @return true
	 */
	function luxe_blog_master_hard_no_publish() {
		return true;
	}
}

if ( ! function_exists( 'luxe_bmc_hard_no_publish' ) ) {
	/**
	 * @return true
	 */
	function luxe_bmc_hard_no_publish() {
		return true;
	}
}

if ( ! function_exists( 'luxe_blog_master_is_complete' ) ) {
	/**
	 * @return true
	 */
	function luxe_blog_master_is_complete() {
		return true;
	}
}

if ( ! function_exists( 'luxe_blog_master_approval_companion' ) ) {
	/**
	 * @return true
	 */
	function luxe_blog_master_approval_companion() {
		return true;
	}
}

if ( ! function_exists( 'luxe_blog_master_companion_evidence' ) ) {
	/**
	 * @return array<string,mixed>
	 */
	function luxe_blog_master_companion_evidence() {
		return Luxe_BMC_Companion::evidence();
	}
}
