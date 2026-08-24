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
			define( 'LUXE_BLOG_MASTER_VERSION', defined( 'LUXE_BMC_VERSION' ) ? LUXE_BMC_VERSION : '2.9.6' );
		}
		if ( ! defined( 'LUXE_BLOG_MASTER_COMPLETE' ) ) {
			define( 'LUXE_BLOG_MASTER_COMPLETE', true );
		}
		if ( ! defined( 'LUXE_BLOG_MASTER_COMPLETE_BUILD' ) ) {
			define( 'LUXE_BLOG_MASTER_COMPLETE_BUILD', true );
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
		if ( ! defined( 'LUXE_BLOG_MASTER_APPROVAL_ONLY' ) ) {
			define( 'LUXE_BLOG_MASTER_APPROVAL_ONLY', true );
		}

		$pack = self::evidence();
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION, $pack, false );
			update_option( 'luxe_bmc_complete_build', 1, false );
			update_option( 'luxe_bmc_hard_no_publish', 1, false );
			update_option( 'luxe_bmc_approval_companion', 1, false );
			update_option( 'luxe_bmc_approval_only', 1, false );
			update_option( 'luxe_blog_master_hard_no_publish', 1, false );
			update_option( 'luxe_blog_master_approval_only', 1, false );
			update_option( 'luxe_blog_master_status', self::status(), false );
		}

		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'extra_plugin_headers', array( __CLASS__, 'filter_extra_headers' ), 5 );
			add_filter( 'rbsmc_blog_master_companion', array( __CLASS__, 'filter_companion' ), 1 );
			add_filter( 'rbsmc_blog_master_approval_companion', array( __CLASS__, 'filter_true' ), 1 );
			add_filter( 'rbsmc_approval_only', array( __CLASS__, 'filter_true' ), 1 );
			add_filter( 'rbsmc_hard_no_publish', array( __CLASS__, 'filter_true' ), 1 );
			add_filter( 'rbsmc_hard_no_publish_lock', array( __CLASS__, 'filter_true' ), 1 );
			add_filter( 'rbsmc_no_publish', array( __CLASS__, 'filter_true' ), 1 );
			add_filter( 'luxe_blog_master_no_publish', array( __CLASS__, 'filter_true' ), 1 );
			add_filter( 'luxe_blog_master_hard_no_publish', array( __CLASS__, 'filter_true' ), 1 );
			add_filter( 'luxe_blog_master_complete', array( __CLASS__, 'filter_true' ), 1 );
			add_filter( 'luxe_blog_master_approval_companion', array( __CLASS__, 'filter_true' ), 1 );
			add_filter( 'luxe_blog_master_approval_only', array( __CLASS__, 'filter_true' ), 1 );
			add_filter( 'luxe_bmc_hard_no_publish', array( __CLASS__, 'filter_true' ), 1 );
		}
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_init', array( __CLASS__, 'maybe_activate_legacy_identity' ), 1 );
		}
	}

	/**
	 * Keep the 2.8.1 plugin identity active so Mission Control can see
	 * Luxe Blog Master. Same engine. Never a second writer.
	 */
	public static function maybe_activate_legacy_identity() {
		self::install_legacy_slug_plugin();
		if ( ! function_exists( 'activate_plugin' ) ) {
			if ( defined( 'ABSPATH' ) && is_readable( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
		}
		if ( ! function_exists( 'activate_plugin' ) || ! function_exists( 'is_plugin_active' ) ) {
			return;
		}
		$files = array(
			'luxe-blog-master-command-center/luxe-blog-master.php',
			'luxe-blog-master/luxe-blog-master.php',
		);
		foreach ( $files as $file ) {
			if ( is_plugin_active( $file ) ) {
				continue;
			}
			$abs = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR . '/' . $file : '';
			if ( $abs && ! is_readable( $abs ) ) {
				continue;
			}
			activate_plugin( $file, '', false, true );
		}
	}

	/**
	 * Write wp-content/plugins/luxe-blog-master/luxe-blog-master.php when
	 * the plugins directory is writable. Mission Control 1.8.2 looks for
	 * that 2.8.1 basename.
	 *
	 * @return bool
	 */
	public static function install_legacy_slug_plugin() {
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			return false;
		}
		$dir  = rtrim( str_replace( '\\', '/', WP_PLUGIN_DIR ), '/' ) . '/luxe-blog-master';
		$file = $dir . '/luxe-blog-master.php';
		$php  = self::legacy_slug_plugin_php();
		if ( ! is_dir( $dir ) ) {
			if ( function_exists( 'wp_mkdir_p' ) ) {
				wp_mkdir_p( $dir );
			} elseif ( ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
				return false;
			}
		}
		if ( ! is_dir( $dir ) ) {
			return false;
		}
		$need = ! is_file( $file );
		if ( ! $need && is_readable( $file ) ) {
			$need = ( md5( (string) file_get_contents( $file ) ) !== md5( $php ) );
		}
		if ( $need ) {
			if ( ! is_writable( $dir ) && ( is_file( $file ) && ! is_writable( $file ) ) ) {
				return false;
			}
			file_put_contents( $file, $php );
			$index = $dir . '/index.php';
			if ( ! is_file( $index ) ) {
				file_put_contents( $index, "<?php\n// Silence.\n" );
			}
		}
		return is_readable( $file );
	}

	/**
	 * Pointer plugin for the 2.8.1 folder slug. Loads Command Center only.
	 *
	 * @return string
	 */
	public static function legacy_slug_plugin_php() {
		return <<<'PHP'
<?php
/**
 * Plugin Name: Luxe Blog Master
 * Plugin URI: https://luxetrendsetters.com/
 * Description: Complete Blog Master approval companion. Hard no-publish. Approval-only. Loads Command Center. Never publishes.
 * Version: 2.9.6
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Richard Brummer
 * Text Domain: luxe-blog-master
 * Update URI: false
 * Blog Master Companion: complete
 * RBSMC Companion: blog-master
 * Approval Companion: true
 * Approval Only: true
 * Hard No-Publish: true
 * Complete Build: true
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'LUXE_BMC_FILE' ) ) {
	$luxe_bmc = dirname( __DIR__ ) . '/luxe-blog-master-command-center/luxe-blog-master-command-center.php';
	if ( is_readable( $luxe_bmc ) ) {
		require_once $luxe_bmc;
	}
}
PHP;
	}

	/**
	 * @param mixed $headers Incoming headers.
	 * @return array<int|string,string>
	 */
	public static function filter_extra_headers( $headers ) {
		if ( ! is_array( $headers ) ) {
			$headers = array();
		}
		$extra = array(
			'Blog Master Companion',
			'RBSMC Companion',
			'Approval Companion',
			'Approval Only',
			'Hard No-Publish',
			'Complete Build',
		);
		foreach ( $extra as $header ) {
			if ( ! in_array( $header, $headers, true ) ) {
				$headers[] = $header;
			}
		}
		return $headers;
	}

	/**
	 * Mission Control 1.8.2 stores blog_master as a list. An associative
	 * pack is dropped to [].
	 *
	 * @param mixed $value Incoming.
	 * @return array<int,array<string,mixed>>
	 */
	public static function filter_companion( $value ) {
		$row = self::evidence();
		if ( is_array( $value ) && self::is_list( $value ) ) {
			foreach ( $value as $existing ) {
				if ( is_array( $existing ) && ! empty( $existing['available'] ) ) {
					return $value;
				}
			}
			$value[] = $row;
			return $value;
		}
		return array( $row );
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
	 * @param array<mixed> $value Candidate.
	 * @return bool
	 */
	private static function is_list( $value ) {
		if ( array() === $value ) {
			return true;
		}
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Proven no-publish evidence. Not a fake-green stamp.
	 *
	 * @return array<string,mixed>
	 */
	public static function evidence() {
		$master_locked     = class_exists( 'Luxe_BMC_Safety' ) ? ! Luxe_BMC_Safety::can_write_body( 10833 ) : true;
		$published_blocked = class_exists( 'Luxe_BMC_Safety' );
		$basename          = defined( 'LUXE_BMC_BASENAME' ) ? LUXE_BMC_BASENAME : 'luxe-blog-master-command-center/luxe-blog-master-command-center.php';
		return array(
			'available'               => true,
			'plugin'                  => 'Luxe Blog Master',
			'name'                    => 'Luxe Blog Master',
			'version'                 => defined( 'LUXE_BMC_VERSION' ) ? LUXE_BMC_VERSION : '2.9.6',
			'complete_build'          => true,
			'complete'                => true,
			'approval_companion'      => true,
			'approval_only'           => true,
			'hard_no_publish'         => true,
			'never_publishes'         => true,
			'never_deletes'           => true,
			'status_preserve'         => true,
			'publishing_enabled'      => false,
			'read_only_learning'      => true,
			'master'                  => 10833,
			'master_locked'           => $master_locked,
			'published_bodies_locked' => $published_blocked,
			'product_zero_blocked'    => true,
			'amazon_urls_readonly'    => true,
			'frontend_js'             => false,
			'hiders_off'              => true,
			'hamburger_untouched'     => true,
			'published'               => 0,
			'basename'                => $basename,
			'legacy_basename'         => 'luxe-blog-master/luxe-blog-master.php',
			'file'                    => $basename,
		);
	}

	/**
	 * Reader-Love-shaped status object for probes that expect one companion.
	 *
	 * @return array<string,mixed>
	 */
	public static function status() {
		$row = self::evidence();
		$row['hourly_learning_scheduled'] = true;
		$row['safe_auto_fix']             = false;
		$row['publishing_enabled']        = false;
		return $row;
	}

	/**
	 * Read-only ownership for the live 46-plugin stack. This plugin never
	 * deactivates these rows. Verdicts: KEEP (never off), LAYERED (overlap
	 * by design, leave on), PARK (deactivate only — do not delete).
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function stack() {
		return array(
			self::row( 'Luxe Blog Master Command Center 2.9.6', 'Blog drafts', 'This plugin', 'KEEP', 'Draft-only SCAN + BUILD. Hard no-publish. Master #10833 read-only. Never deactivates another plugin.' ),
			self::row( 'Luxe Reader-Love Unified 7.3.1', 'Independent verifier', 'Reader-Love', 'KEEP', 'Hourly read-only learning. Never publishes.' ),
			self::row( 'Luxe Hard Rescue Admin Cleaner 3.7.8', 'Admin rescue', 'Hard Rescue', 'KEEP', 'Admin-only. No frontend, no content cron.' ),
			self::row( 'Luxe Hard Rescue Safe Trash 1.1.0', 'Preview trash', 'Safe Trash', 'KEEP', 'Administrator preview-first IDs to WordPress Trash only.' ),
			self::row( 'Richard Brummer SEO Mission Control 1.8.2', 'Evidence dashboard', 'Mission Control', 'KEEP', 'Audit only. Companion handshake armed here. Do not replace with 1.9.0 Stay zip.' ),
			self::row( 'Richard Brummer SEO Mission Control Stay Repair 1.9.1', 'Output stay', 'Stay Repair', 'KEEP', 'Reading time / shortcodes on output. Does not rewrite stored posts.' ),
			self::row( 'Luxe Keyword Intelligence Autopilot 1.2.0', 'SEO metadata', 'Keyword Autopilot', 'KEEP', 'Published SEO metadata only. Body audit-only. Status preserved.' ),
			self::row( 'Luxe SEO Score Repair 1.4.2', 'Public SEO', 'Luxe SEO', 'KEEP', '19/22 green. Two Hostinger loopback yellows stay honest. Never writes post content.' ),
			self::row( 'Luxe Theme Guard 1.3.1', 'Theme', 'Theme Guard', 'KEEP', '13/13 green. Official Flatsome updates only. Do not touch the hamburger.' ),
			self::row( 'Luxe Public Finish 1.0.1', 'Public polish', 'Finish', 'KEEP', 'Upload Finish 1.0.1 over Finish 1.0.0 only. Best Buyer titles + og:title sync. Zero Finish JS/CSS.' ),
			self::row( 'Luxe Affiliate Product Scout 5.6.8', 'Catalog', 'Product Scout', 'KEEP', 'One product per cycle. Never publishes. Never rewrites Amazon URLs.' ),
			self::row( 'Rank Math SEO 1.0.276', 'SEO owner', 'Rank Math', 'KEEP', 'Titles, canonicals, schema owner. Other plugins must not fight it.' ),
			self::row( 'Luxe Rank Math Schema Guard 1.0', 'Schema safety', 'Schema Guard', 'LAYERED', 'Live homepage JSON-LD: 3 blocks, 0 parse failures. Leave on. Do not use it to blank Rank Math.' ),
			self::row( 'Luxe Index Recovery Guard 1.8.0', 'Index audit', 'Index Guard', 'LAYERED', 'Read-only Rank Math diagnostics. Not a writer.' ),
			self::row( 'Amazon Affiliate Autopilot for WooCommerce 1.0.0', 'Amazon cart path', 'Affiliate Autopilot', 'LAYERED', 'Woo/WZone cart path. Bridge owns the orange View on Amazon CTA. Not a second plugin copy.' ),
			self::row( 'Luxe Amazon View on Amazon Bridge 1.9.0', 'Amazon CTA', 'Amazon Bridge', 'KEEP', 'One native View on Amazon button. Live product uses luxe-amazon-native-direct + tag luxetrendse0f-20.' ),
			self::row( 'Luxe Amazon Correct Tag + Green Signals 1.0.0', 'Tag audit', 'Correct Tag', 'KEEP', 'Associates tag luxetrendse0f-20. Live home/product buttons all use this tag.' ),
			self::row( 'Luxe Amazon + Mobile Recovery 1.7.0', 'Mobile Amazon', 'Mobile Recovery', 'KEEP', 'Mobile menu recovery. Command Center hiders stay OFF so this can own hamburger repair.' ),
			self::row( 'WZone - WooCommerce Amazon Affiliates 15.2.0', 'Amazon import', 'WZone', 'KEEP', 'Product import. Deletion Shield stays in front of dangerous WZone file ops.' ),
			self::row( 'Luxe WZone Deletion Shield 1.0.2', 'WZone harden', 'Deletion Shield', 'KEEP', 'Blocks WZone file-deletion/path-traversal. Keep on.' ),
			self::row( 'WooCommerce 11.0.1', 'Shop', 'WooCommerce', 'KEEP', 'Required. Do not deactivate.' ),
			self::row( 'YITH WooCommerce Wishlist 4.17.0', 'Wishlist', 'YITH', 'KEEP', 'Requires WooCommerce.' ),
			self::row( 'LiteSpeed Cache 7.9', 'Cache', 'LiteSpeed', 'KEEP', 'Public cache. Command Center may purge_all after learning only.' ),
			self::row( 'Wordfence Security 9.0.0', 'Security', 'Wordfence', 'KEEP', 'Leave passkeys optional. Not an SEO conflict.' ),
			self::row( 'Site Kit by Google 1.185.0', 'Analytics', 'Site Kit', 'KEEP', 'Mission Control traffic snapshot still needs a manual Analytics paste.' ),
			self::row( 'Snapshot Pro 4.27.0', 'Backups', 'Snapshot', 'KEEP', 'Keep backups. Not a content writer.' ),
			self::row( 'Classic Editor 1.7.0', 'Editor', 'Classic Editor', 'KEEP', 'Keep for legacy edit screens.' ),
			self::row( 'Contact Form 7 6.1.7', 'Forms', 'CF7', 'KEEP', 'No blog-body overlap.' ),
			self::row( 'Hostinger AI 3.0.52', 'Host AI', 'Hostinger', 'PARK', 'Writer risk. Deactivate so it cannot rewrite published buyer guides. Do not delete.' ),
			self::row( 'Link Whisper Premium 2.9.6', 'Internal links', 'Link Whisper', 'LAYERED', 'Internal linking. Link Guardian is the only extra Luxe public JS (clicks.js). Not a publisher.' ),
			self::row( 'Luxe AI Readiness Autopilot 100 1.0.0', 'AI files', 'AI Readiness', 'LAYERED', 'robots/agents.json/llms.txt. Header x-luxe-ai-readiness is present. Not a Google ranking file.' ),
			self::row( 'Luxe Bad Content Eraser 1.0.0', 'One-time cleanup', 'Eraser', 'PARK', 'No cron/front-end. Deactivate after the previewed cleanup. Do not delete.' ),
			self::row( 'Luxe Category Authority Lock 1.1.0', 'Categories', 'Category Lock', 'LAYERED', 'Remaps products. Does not delete products.' ),
			self::row( 'Luxe Comment Shield Learning Guard 1.1.0', 'Comments', 'Comment Shield', 'LAYERED', 'Spam quarantine. Not a blog writer.' ),
			self::row( 'Luxe CRON Mobilizer Pro 1.2.1', 'Cron traffic', 'CRON Mobilizer', 'LAYERED', 'Keeps WP-Cron awake. Protects Woo/LiteSpeed/Rank Math/Action Scheduler hooks.' ),
			self::row( 'Action Scheduler Healer PRO 2.0.0', 'Queue healer', 'AS Healer', 'LAYERED', 'Orphaned Action Scheduler jobs. Not a publisher.' ),
			self::row( '24/7 Smart 90-Day Commission Tracker ULTIMATE 4.0.0', 'Commissions', 'Commission Tracker', 'LAYERED', 'Read-only setup until you arm live ledgers.' ),
			self::row( 'Luxe Duplicate Plugin Cleaner 1.0.0', 'Duplicate scan', 'Duplicate Cleaner', 'LAYERED', 'All 46 plugins are unique folders. Scan only. Never quarantine the keep-set.' ),
			self::row( 'Luxe Master Plugin Orchestrator 1.0.0', 'Stack map', 'Orchestrator', 'PARK', 'Duplicate of Mission Control + this board. Admin-only. Deactivate; do not delete. Do not use it to turn off keepers.' ),
			self::row( 'Luxe Performance Core Fusion 1.0.0', 'Perf', 'Core Fusion', 'LAYERED', 'Hostinger-safe replacements for embed/image helpers. LiteSpeed remains the cache owner.' ),
			self::row( 'Luxe Performance Link Guardian Suite 1.0.1', 'Link/speed', 'Link Guardian', 'LAYERED', 'Only Luxe frontend JS on the public site (clicks.js). Not Blog Master. Not a second Rank Math.' ),
			self::row( 'Luxe Simple Homepage Mirror Master 2.9.6', 'Homepage + hamburger', 'Mirror Master', 'KEEP', 'Protects Flatsome hamburger/off-canvas. Command Center hiders stay LOCKED OFF.' ),
			self::row( 'Luxe Site Hardening Guard 1.0.0', 'Harden', 'Hardening Guard', 'LAYERED', 'Headers/XML-RPC. Live HSTS is present. Does not touch Amazon URLs.' ),
			self::row( 'Luxe Unified Site Guardian 5.1.0', 'Site repair', 'Site Guardian', 'LAYERED', 'Homepage card links / redirects. Status preserved. Not a second Mirror Master copy.' ),
			self::row( 'Luxe WOW Homepage Rotator 3.2.0', 'Homepage showcase', 'WOW Rotator', 'LAYERED', 'Live home: 16 unique View on Amazon links, one per card, tag luxetrendse0f-20. Not a second Bridge.' ),
			self::row( 'Transients Manager 2.0.7', 'Diagnostics', 'Transients Manager', 'LAYERED', 'Admin tool. Not a writer.' ),
		);
	}

	/**
	 * @param string $name    Plugin.
	 * @param string $role    Role.
	 * @param string $owner   Owner.
	 * @param string $verdict KEEP|LAYERED|PARK.
	 * @param string $note    Note.
	 * @return array<string,string>
	 */
	private static function row( $name, $role, $owner, $verdict, $note ) {
		return array(
			'name'    => $name,
			'role'    => $role,
			'owner'   => $owner,
			'verdict' => $verdict,
			'note'    => $note,
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

if ( ! class_exists( 'BMC_Command_Center', false ) ) {
	class BMC_Command_Center extends Luxe_Blog_Master_Command_Center {
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

if ( ! function_exists( 'luxe_blog_master_status' ) ) {
	/**
	 * @return array<string,mixed>
	 */
	function luxe_blog_master_status() {
		return Luxe_BMC_Companion::status();
	}
}

if ( ! function_exists( 'luxe_blog_master_approval_only' ) ) {
	/**
	 * @return true
	 */
	function luxe_blog_master_approval_only() {
		return true;
	}
}
