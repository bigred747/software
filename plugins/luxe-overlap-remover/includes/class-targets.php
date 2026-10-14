<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exact folder allowlist. A lookalike name is left alone.
 */
class LUOR_Targets {

	/**
	 * @return string[]
	 */
	public static function folders() {
		return array(
			'luxe-reader-love-polisher',
			'luxe-keyword-intelligence-autopilot',
			'luxe-blog-master-command-center',
			'richard-brummer-seo-mission-control',
			'luxe-ai-readiness-autopilot',
			'luxe-performance-core-fusion',
			'luxe-performance-link-guardian-suite',
			'luxe-auto-fix-pro-green-signals-v4.3.0',
			'luxe-index-recovery-guard',
			'luxe-rankmath-schema-guard',
			'amazon-affiliate-autopilot',
			'luxe-amazon-correct-tag-green-signals',
			'luxe-amazon-blog-button-repair',
			'luxe-cron-mobilizer-pro-v1.2.1',
			'action-scheduler-healer-pro',
			'luxe-master-plugin-orchestrator',
			'luxe-wow-homepage-ultra-premium',
			'luxe-category-authority-lock-safe',
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function labels() {
		return array(
			'luxe-reader-love-polisher'                    => 'Luxe Reader-Love Unified',
			'luxe-keyword-intelligence-autopilot'          => 'Luxe Keyword Intelligence Autopilot',
			'luxe-blog-master-command-center'              => 'Luxe Blog Master Command Center',
			'richard-brummer-seo-mission-control'          => 'Richard Brummer SEO Mission Control',
			'luxe-ai-readiness-autopilot'                  => 'Luxe AI Readiness Autopilot 100',
			'luxe-performance-core-fusion'                 => 'Luxe Performance Core Fusion',
			'luxe-performance-link-guardian-suite'         => 'Luxe Performance Link Guardian Suite',
			'luxe-auto-fix-pro-green-signals-v4.3.0'       => 'Luxe Unified Site Guardian',
			'luxe-index-recovery-guard'                    => 'Luxe Index Recovery Guard',
			'luxe-rankmath-schema-guard'                   => 'Luxe Rank Math Schema Guard',
			'amazon-affiliate-autopilot'                   => 'Amazon Affiliate Autopilot',
			'luxe-amazon-correct-tag-green-signals'        => 'Luxe Amazon Correct Tag + Green Signals',
			'luxe-amazon-blog-button-repair'               => 'Luxe Amazon + Mobile Recovery',
			'luxe-cron-mobilizer-pro-v1.2.1'               => 'Luxe CRON Mobilizer Pro',
			'action-scheduler-healer-pro'                  => 'Action Scheduler Healer PRO',
			'luxe-master-plugin-orchestrator'              => 'Luxe Master Plugin Orchestrator',
			'luxe-wow-homepage-ultra-premium'              => 'Luxe WOW Homepage Rotator',
			'luxe-category-authority-lock-safe'            => 'Luxe Category Authority Lock',
		);
	}

	/**
	 * @return string[]
	 */
	public static function protected_folders() {
		return array(
			'woocommerce',
			'woozone',
			'seo-by-rank-math',
			'litespeed-cache',
			'wordfence',
			'google-site-kit',
			'link-whisper',
			'link-whisper-premium',
			'snapshot-backups',
			'luxe-ultimate-authority',
			'luxe-affiliate-product-scout',
			'luxe-amazon-view-on-amazon-bridge',
			'luxe-theme-guard',
			'luxe-wzone-deletion-shield',
			'luxe-wzone-stats-guardian',
			'luxe-comment-shield-learning-guard',
			'luxe-site-hardening-guard',
			'luxe-simple-homepage-mirror-master-v2.9.6-hamburger-9.7-score-module-full-250kb',
			'yith-woocommerce-wishlist',
			'classic-editor',
			'contact-form-7',
			'luxe-overlap-remover',
			'luxe-hard-rescue-admin-cleaner-v3.7.8',
			'luxe-hard-rescue-safe-trash-extension',
			'luxe-safe-plugin-deleter',
			'luxe-duplicate-plugin-cleaner',
			'luxe-optimizer',
			'luxe-download-scout',
			'luxe-stay',
			'luxe-storefront-repair',
		);
	}

	/**
	 * @param string $value Value.
	 * @return string
	 */
	public static function normalize( $value ) {
		$value = str_replace( '\\', '/', (string) $value );
		$value = strtolower( trim( $value, '/' ) );
		return $value;
	}

	/**
	 * @param string $basename Plugin basename.
	 * @return string
	 */
	public static function folder_of( $basename ) {
		$basename = str_replace( '\\', '/', (string) $basename );
		$parts    = explode( '/', $basename );
		return isset( $parts[0] ) ? $parts[0] : '';
	}

	/**
	 * @param string $basename Plugin basename.
	 * @return string keep|delete|leave
	 */
	public static function decision( $basename ) {
		$folder = self::normalize( self::folder_of( $basename ) );
		if ( '' === $folder || false !== strpos( $folder, '..' ) || false !== strpos( $folder, '/' ) ) {
			return 'leave';
		}
		if ( in_array( $folder, self::protected_folders(), true ) ) {
			return 'keep';
		}
		$allowed = array();
		foreach ( self::folders() as $name ) {
			$allowed[] = self::normalize( $name );
		}
		if ( in_array( $folder, $allowed, true ) ) {
			return 'delete';
		}
		return 'leave';
	}

	/**
	 * The folder must exist inside the plugins directory and must not be the directory itself.
	 *
	 * @param string $plugin_dir Plugins directory.
	 * @param string $folder     Folder name.
	 * @return bool
	 */
	public static function is_inside_plugins( $plugin_dir, $folder ) {
		$folder = str_replace( '\\', '/', (string) $folder );
		$folder = trim( $folder, '/' );
		if ( '' === $folder || false !== strpos( $folder, '/' ) || false !== strpos( $folder, '..' ) ) {
			return false;
		}
		$root = realpath( $plugin_dir );
		$path = realpath( rtrim( str_replace( '\\', '/', (string) $plugin_dir ), '/' ) . '/' . $folder );
		if ( false === $root || false === $path ) {
			return false;
		}
		$root = rtrim( str_replace( '\\', '/', $root ), '/' );
		$path = rtrim( str_replace( '\\', '/', $path ), '/' );
		if ( $path === $root ) {
			return false;
		}
		return 0 === strpos( $path, $root . '/' );
	}
}
