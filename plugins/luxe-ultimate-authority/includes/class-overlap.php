<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Names this engine can retire, and names it must never deactivate.
 */
class LUAE_Overlap {

	/**
	 * @param string $name Plugin name.
	 * @return string
	 */
	public static function normalize( $name ) {
		$name = html_entity_decode( strip_tags( (string) $name ), ENT_QUOTES, 'UTF-8' );
		$name = str_replace( array( '–', '—', '−' ), '-', $name );
		$name = strtolower( $name );
		$name = preg_replace( '/[^a-z0-9+&]+/', ' ', $name );
		$name = preg_replace( '/\s+/', ' ', (string) $name );
		return trim( (string) $name );
	}

	/**
	 * @return string[]
	 */
	public static function retire_names() {
		return array(
			'Luxe Reader-Love Unified 95–100 Prep & Score',
			'Luxe Keyword Intelligence Autopilot',
			'Luxe Blog Master Command Center',
			'Richard Brummer SEO Mission Control',
			'Luxe AI Readiness Autopilot 100',
			'Luxe Performance Core Fusion',
			'Luxe Performance Link Guardian Suite',
			'Luxe Optimizer',
			'Luxe Unified Site Guardian – Green Signals Safe Rebuild',
			'Luxe Index Recovery Guard',
			'Luxe Duplicate Plugin Cleaner',
			'Amazon Affiliate Autopilot for WooCommerce',
			'Luxe Amazon Correct Tag + Green Signals',
			'Luxe Amazon + Mobile Recovery',
			'Luxe CRON Mobilizer Pro',
			'Action Scheduler Healer PRO (Autonomous & Self-Learning)',
		);
	}

	/**
	 * @return string[]
	 */
	public static function protected_names() {
		return array(
			'WooCommerce',
			'Rank Math SEO',
			'LiteSpeed Cache',
			'Wordfence Security',
			'WZone - WooCommerce Amazon Affiliates',
			'Link Whisper Premium',
			'Site Kit by Google',
			'Luxe Amazon View on Amazon Bridge',
			'Luxe Affiliate Product Scout',
			'Luxe Theme Guard',
			'Snapshot Pro',
			'Contact Form 7',
			'Classic Editor',
			'YITH WooCommerce Wishlist',
			'Luxe WZone Deletion Shield',
			'Luxe Comment Shield Learning Guard',
			'Luxe Site Hardening Guard',
			'Luxe Hard Rescue Admin Cleaner',
			'Luxe Hard Rescue Safe Trash Extension',
			'Luxe Safe Plugin Deleter',
			'Luxe Ultimate Authority & Revenue Engine',
		);
	}

	/**
	 * @param string $name Plugin name.
	 * @return string keep|retire|leave
	 */
	public static function classify( $name ) {
		$normalized = self::normalize( $name );
		foreach ( self::protected_names() as $protected_name ) {
			if ( self::normalize( $protected_name ) === $normalized ) {
				return 'keep';
			}
		}
		foreach ( self::retire_names() as $retire_name ) {
			if ( self::normalize( $retire_name ) === $normalized ) {
				return 'retire';
			}
		}
		return 'leave';
	}

	/**
	 * @param string[] $names Active plugin names.
	 * @return array{retire:string[],keep:string[],leave:string[]}
	 */
	public static function split( $names ) {
		$out = array(
			'retire' => array(),
			'keep'   => array(),
			'leave'  => array(),
		);
		foreach ( $names as $name ) {
			$bucket = self::classify( $name );
			$out[ $bucket ][] = (string) $name;
		}
		return $out;
	}
}
