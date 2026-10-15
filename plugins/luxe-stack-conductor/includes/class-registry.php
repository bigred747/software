<?php
/**
 * Known plugin lanes. Names are matched loosely so en dashes in WordPress headers still hit.
 *
 * @package Luxe_Stack_Conductor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'Luxe_Conductor_Registry' ) ) {
	return;
}

/**
 * Catalog of the live LuxeTrendsetters stack plus the in-repo companions.
 */
class Luxe_Conductor_Registry {

	/**
	 * @var array|null
	 */
	private static $catalog = null;

	/**
	 * @return array
	 */
	public static function catalog() {
		if ( null === self::$catalog ) {
			self::$catalog = self::definitions();
		}
		return self::$catalog;
	}

	/**
	 * @param string $name Plugin name.
	 * @return array|null
	 */
	public static function by_name( $name ) {
		$key = self::normalize( $name );
		if ( '' === $key ) {
			return null;
		}
		foreach ( self::catalog() as $entry ) {
			if ( self::normalize( $entry['name'] ) === $key ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * @param string $hook Cron hook.
	 * @return array|null
	 */
	public static function by_hook( $hook ) {
		foreach ( self::catalog() as $entry ) {
			if ( ! empty( $entry['hooks'] ) && isset( $entry['hooks'][ $hook ] ) ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * @param string $hook Cron hook.
	 * @return string
	 */
	public static function owner_for_hook( $hook ) {
		$entry = self::by_hook( $hook );
		return $entry ? $entry['name'] : '';
	}

	/**
	 * @param string $name Raw plugin name.
	 * @return string
	 */
	public static function normalize( $name ) {
		$name = html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' );
		$name = preg_replace( '/[\x{2010}-\x{2015}\x{2212}]/u', '-', $name );
		$name = preg_replace( '/\s+/u', ' ', trim( $name ) );
		return $name;
	}

	/**
	 * Flags used by the dispatcher. A known hook wins over a guessed owner.
	 *
	 * @param string $hook  Cron hook.
	 * @param string $owner Plugin name from the callback file.
	 * @return array
	 */
	public static function event_flags( $hook, $owner ) {
		$entry = self::by_hook( $hook );
		if ( ! $entry ) {
			$entry = self::by_name( $owner );
		}
		$plugin    = $entry ? $entry['name'] : (string) $owner;
		$protected = ( $entry && ! empty( $entry['protected'] ) ) || self::is_protected_name( $owner );
		$heavy     = false;
		if ( ! $protected ) {
			if ( $entry && array_key_exists( 'heavy', $entry ) ) {
				$heavy = (bool) $entry['heavy'];
			} else {
				$heavy = self::hook_looks_heavy( $hook ) && self::owner_looks_managed( $owner );
			}
		}
		$lane = ( $entry && ! empty( $entry['lane'] ) ) ? $entry['lane'] : 'general';
		return array(
			'plugin'    => $plugin,
			'lane'      => $lane,
			'protected' => $protected,
			'heavy'     => (bool) $heavy,
		);
	}

	/**
	 * @param string $hook Hook.
	 * @return bool
	 */
	public static function hook_looks_heavy( $hook ) {
		return (bool) preg_match( '/(learn|heal|scout|audit|repair|mobiliz|guardian|autopilot|snapshot|batch|cycle|warmup|rescan)/i', (string) $hook );
	}

	/**
	 * @param string $owner Plugin name.
	 * @return bool
	 */
	public static function owner_looks_managed( $owner ) {
		$owner = self::normalize( $owner );
		return (bool) preg_match( '/^(Luxe |Richard Brummer|Action Scheduler Healer|24\/7 Smart|Amazon Affiliate Autopilot)/', $owner );
	}

	/**
	 * @param string $owner Plugin name.
	 * @return bool
	 */
	public static function is_protected_name( $owner ) {
		$entry = self::by_name( $owner );
		return $entry && ! empty( $entry['protected'] );
	}

	/**
	 * @return array
	 */
	private static function definitions() {
		return array(
			self::row( '24/7 Smart 90-Day Commission Tracker ULTIMATE', 'finance', true, false, '4.0.0', 'Read-only until live mode is switched on by hand.' ),
			self::row( 'Action Scheduler Healer PRO (Autonomous & Self-Learning)', 'cron', true, false, '2.0.0', 'Cleans stuck Action Scheduler jobs. Conductor does not postpone WooCommerce queues.' ),
			self::row( 'Amazon Affiliate Autopilot for WooCommerce', 'amazon', true, false, '1.0.0', 'Amazon buttons, disclosure, and cart-flow. Conductor never rewrites affiliate URLs itself.' ),
			self::row( 'Classic Editor', 'editor', false, true, '1.7.0', '' ),
			self::row( 'Contact Form 7', 'forms', false, true, '6.2.2', '' ),
			self::row( 'Hostinger AI', 'host', false, true, '3.1.3', '' ),
			self::row( 'Link Whisper Premium', 'links', false, true, '4.0.4', '' ),
			self::row( 'LiteSpeed Cache', 'cache', false, true, '7.9.1', 'Cache stays in LiteSpeed. Conductor never purges the public site on its own.', true ),
			self::row( 'Luxe Affiliate Product Scout', 'catalog', true, false, '5.6.8', 'One product per cycle. Never publishes, deletes, or rewrites Amazon URLs.', false, array( 'laps_learn' => 300 ), 'admin.php?page=luxe-affiliate-product-scout' ),
			self::row( 'Luxe AI Readiness Autopilot 100', 'robots', true, false, '1.0.0', 'robots.txt, agents.json, and llms.txt. Serialized so it does not stack with other public-file learners.' ),
			self::row( 'Luxe Amazon + Mobile Recovery', 'amazon', true, false, '1.7.0', 'Tag repair and mobile menu recovery. One Amazon-lane job per cron hit.' ),
			self::row( 'Luxe Amazon Correct Tag + Green Signals', 'amazon', true, false, '1.0.0', 'Associates tag audit. Conductor does not change the tag.' ),
			self::row( 'Luxe Amazon View on Amazon Bridge', 'amazon', true, false, '1.9.0', 'One orange View on Amazon button. URLs stay with the bridge.' ),
			self::row( 'Luxe Blog Master Command Center', 'blog', true, false, '2.8.1', 'Publishing stays blocked inside Blog Master. Conductor does not approve posts.' ),
			self::row( 'Luxe Category Authority Lock - Safe Activation', 'taxonomy', false, false, '1.1.0', 'Category map stays locked. Conductor does not remap products.' ),
			self::row( 'Luxe Comment Shield Learning Guard', 'comments', true, false, '1.1.0', 'Bounded comment learning. Cleanup still needs an administrator.' ),
			self::row( 'Luxe CRON Mobilizer Pro', 'cron', true, false, '1.2.1', 'Conductor is the last stagger on wp_loaded, so Mobilizer and Conductor do not double-book the same second.' ),
			self::row( 'Luxe Download Scout', 'catalog', true, false, '1.1.2', 'Drafts only. Same catalog lane as Product Scout, so only one of them runs per hit.' ),
			self::row( 'Luxe Duplicate Plugin Cleaner', 'admin', false, false, '1.0.0', 'Quarantine stays manual. Conductor never deactivates a plugin.' ),
			self::row( 'Luxe Google Presence', 'public', false, false, '1.1.0', 'Crawl-ready pages. Amazon URLs stay untouched.' ),
			self::row( 'Luxe Hard Rescue Admin Cleaner', 'admin', false, false, '3.7.8', 'Admin-only. No frontend hook and no content cron.' ),
			self::row( 'Luxe Hard Rescue Safe Trash Extension', 'admin', false, false, '1.1.0', 'Preview first. Conductor never sends posts to trash.' ),
			self::row( 'Luxe Index Recovery Guard', 'seo', false, false, '1.8.0', 'Read-only discovery. Rank Math, robots, and redirects stay put.' ),
			self::row( 'Luxe Keyword Intelligence Autopilot', 'seo-meta', true, false, '1.4.0', 'Bounded Rank Math phrases. Article bodies and Amazon URLs stay put.' ),
			self::row( 'Luxe Master Plugin Orchestrator', 'coordination', false, false, '1.0.0', 'Left active. Conductor adds the queue, it does not replace this scanner.' ),
			self::row( 'Luxe Optimizer', 'public', false, false, '2.3.15', 'Public title and description polish stays on the request, not in this queue.' ),
			self::row( 'Luxe Performance Core Fusion', 'speed', false, false, '1.0.0', 'Frontend speed checks. No product writes.' ),
			self::row( 'Luxe Performance Link Guardian Suite', 'public', true, false, '1.0.1', 'Link and layout guards share the public lane and take turns.' ),
			self::row( 'Luxe Rank Math Schema Guard', 'schema', false, false, '1.0', 'Keeps broken JSON-LD from crashing the site.' ),
			self::row( 'Luxe Reader-Love Unified 95-100 Prep & Score', 'blog', true, false, '7.3.1', 'Hourly blog learning stays read-only. The 15-minute guard keeps every status.' ),
			self::row( 'Luxe Safe Plugin Deleter', 'admin', false, false, '1.0.0', 'Deletions stay on that screen. Conductor never deletes a plugin.' ),
			self::row( 'Luxe Simple Homepage Mirror Master', 'public', true, false, '2.9.6-hamburger-module-9.7', 'Homepage markup stays with Mirror Master. Conductor does not rewrite it.' ),
			self::row( 'Luxe Site Hardening Guard', 'security', false, false, '1.0.0', 'Headers and login hardening. Product links stay untouched.' ),
			self::row( 'Luxe Stay', 'public', true, false, '1.1.0', 'Fifteen-minute page check. One public-lane learner per cron hit.' ),
			self::row( 'Luxe Storefront Repair', 'public', false, false, '1.2.0', 'Listing photos and the known storefront routes stay with this plugin.' ),
			self::row( 'Luxe Theme Guard', 'theme', true, false, '1.4.4', 'Official Flatsome updates only. Conductor never installs a theme zip.' ),
			self::row( 'Luxe Unified Site Guardian - Green Signals Safe Rebuild', 'public', true, false, '5.1.0', 'Its own evidence board stays the source for its probes. Conductor only clears its cron slot.' ),
			self::row( 'Luxe WOW Homepage Rotator - Ultra Premium Edition', 'public', false, false, '3.2.0', 'Homepage showcase stays on the request. Learning jobs wait their turn.' ),
			self::row( 'Luxe WZone Deletion Shield', 'security', false, false, '1.0.2', 'Blocks dangerous WZone file deletes. Conductor does not relax that shield.' ),
			self::row( 'Luxe WZone Stats Guardian', 'stats', true, false, '1.2.0', 'Click stats and Associates alignment. Amazon URLs are never rewritten here.' ),
			self::row( 'Rank Math SEO', 'seo', false, true, '1.0.280', 'Rank Math cron is never postponed.', true ),
			self::row( 'Richard Brummer SEO Mission Control', 'evidence', true, false, '1.8.0', 'Evidence reds stay on the Mission Control dashboard. Conductor does not repaint them.' ),
			self::row( 'Site Kit by Google', 'evidence', false, true, '1.189.0', '' ),
			self::row( 'Snapshot Pro', 'backups', false, true, '4.27.0', '' ),
			self::row( 'Transients Manager', 'data', false, true, '2.0.7', '' ),
			self::row( 'WooCommerce', 'commerce', false, true, '11.2.1', 'Store cron is never postponed.', true ),
			self::row( 'Wordfence Security', 'security', false, true, '9.0.2', 'Firewall cron is never postponed.' ),
			self::row( 'WZone - WooCommerce Amazon Affiliates', 'commerce', false, true, '15.3.0', 'Importer cron is never postponed. Conductor does not import products.', true ),
			self::row( 'YITH WooCommerce Wishlist', 'wishlist', false, true, '4.19.0', '' ),
			self::row( 'Luxe Score Repair', 'public', true, false, '1.1.0', 'Fifteen-minute public-output learning. Post content and Amazon URLs stay put.', false, array( 'luxe_score_repair_learn' => 900 ), 'tools.php?page=luxe-score-repair' ),
			self::row( 'Luxe Product Integrity Repair', 'catalog', true, false, '1.1.0', 'Taxonomy heal shares the catalog lane with Product Scout.', false, array( 'luxe_pir_heal' => 21600 ), 'tools.php?page=luxe-product-integrity-repair' ),
			self::row( 'Luxe Mission Score Repair', 'public', true, false, '1.0.0', 'Daily template sync. Amazon retail hosts are never rewritten.', false, array( 'luxe_msr_template_sync' => 86400 ), 'tools.php?page=luxe-mission-score-repair' ),
			self::row( 'Luxe Stack Conductor', 'coordination', true, false, '1.0.1', 'Read-only 5-minute learning snapshot. Never publishes and never writes another plugin\'s content.', false, array( 'luxe_conductor_tick' => 300 ), 'admin.php?page=luxe-stack-conductor', false ),
		);
	}

	/**
	 * @param string $name      Name.
	 * @param string $lane      Lane.
	 * @param bool   $learning  Learning plugin.
	 * @param bool   $protected Never postpone.
	 * @param string $version   Known version.
	 * @param string $safety    Safety line.
	 * @param bool   $critical  Missing is red.
	 * @param array  $hooks     Hook => interval.
	 * @param string $menu      Admin URL.
	 * @param bool   $heavy     Explicit heavy flag. Null follows $learning.
	 * @return array
	 */
	private static function row( $name, $lane, $learning, $protected, $version, $safety, $critical = false, $hooks = array(), $menu = '', $heavy = null ) {
		if ( null === $heavy ) {
			$heavy = $learning && ! $protected;
		}
		return array(
			'name'      => $name,
			'lane'      => $lane,
			'learning'  => (bool) $learning,
			'protected' => (bool) $protected,
			'heavy'     => (bool) $heavy,
			'critical'  => (bool) $critical,
			'version'   => (string) $version,
			'safety'    => (string) $safety,
			'hooks'     => $hooks,
			'menu'      => (string) $menu,
		);
	}
}
