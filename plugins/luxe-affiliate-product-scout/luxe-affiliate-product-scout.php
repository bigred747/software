<?php
/**
 * Plugin Name: Luxe Affiliate Product Scout
 * Plugin URI: https://luxetrendsetters.com/
 * Description: 24/7 WooCommerce catalog learning plus copyright-safe Instagram video scanning. One product per cycle, hourly snapshot, honest 95-100 scores. Never copies Instagram video, never publishes, never deletes, never rewrites Amazon URLs.
 * Version: 5.7.0
 * Requires at least: 6.4
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 9.9
 * Author: LuxeTrendsetters
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-affiliate-product-scout
 * Update URI: false
 *
 * Copyright (c) 2026 LuxeTrendsetters / Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LAPS_VERSION' ) ) {
	define( 'LAPS_VERSION', '5.7.0' );
}
if ( ! defined( 'LAPS_FILE' ) ) {
	define( 'LAPS_FILE', __FILE__ );
}
if ( ! defined( 'LAPS_DIR' ) ) {
	define( 'LAPS_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'LAPS_URL' ) ) {
	define( 'LAPS_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'LAPS_BASENAME' ) ) {
	define( 'LAPS_BASENAME', plugin_basename( __FILE__ ) );
}

$laps_includes = array(
	'includes/class-catalog.php',
	'includes/class-cache.php',
	'includes/class-score.php',
	'includes/class-repair.php',
	'includes/class-related.php',
	'includes/class-audit.php',
	'includes/class-learning.php',
	'includes/class-instagram.php',
	'includes/class-plugin.php',
	'includes/class-admin.php',
);
foreach ( $laps_includes as $laps_include ) {
	$laps_path = LAPS_DIR . $laps_include;
	if ( is_readable( $laps_path ) ) {
		require_once $laps_path;
	}
}

/**
 * Boot after WooCommerce. Never throws to the public site.
 */
if ( ! function_exists( 'laps_boot' ) ) {
	function laps_boot() {
		if ( ! class_exists( 'LAPS_Plugin' ) ) {
			return;
		}
		try {
			LAPS_Plugin::instance()->boot();
		} catch ( Exception $e ) {
			if ( function_exists( 'error_log' ) ) {
				error_log( 'Luxe Product Scout: ' . $e->getMessage() );
			}
		} catch ( Throwable $e ) {
			if ( function_exists( 'error_log' ) ) {
				error_log( 'Luxe Product Scout: ' . $e->getMessage() );
			}
		}
	}
}
add_action( 'plugins_loaded', 'laps_boot', 30 );

/**
 * @return bool
 */
if ( ! function_exists( 'laps_woocommerce_ready' ) ) {
	function laps_woocommerce_ready() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_products' );
	}
}

if ( class_exists( 'LAPS_Plugin' ) ) {
	register_activation_hook( __FILE__, array( 'LAPS_Plugin', 'activate' ) );
	register_deactivation_hook( __FILE__, array( 'LAPS_Plugin', 'deactivate' ) );
}
