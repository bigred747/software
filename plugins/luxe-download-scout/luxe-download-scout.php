<?php
/**
 * Plugin Name: Luxe Download Scout
 * Plugin URI: https://luxetrendsetters.com/
 * Description: Puts only 100-score WZone products into WooCommerce as unpublished drafts, in the matching category. Never publishes or invents an ASIN or affiliate URL.
 * Version: 1.1.0
 * Requires at least: 6.4
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Author: LuxeTrendsetters
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-download-scout
 * Update URI: false
 *
 * Copyright (c) 2026 LuxeTrendsetters / Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LDS_VERSION' ) ) {
	define( 'LDS_VERSION', '1.1.0' );
}
if ( ! defined( 'LDS_FILE' ) ) {
	define( 'LDS_FILE', __FILE__ );
}
if ( ! defined( 'LDS_DIR' ) ) {
	define( 'LDS_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'LDS_URL' ) ) {
	define( 'LDS_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'LDS_BASENAME' ) ) {
	define( 'LDS_BASENAME', plugin_basename( __FILE__ ) );
}

/**
 * Product Scout owns the supported-brand list. Load it if this plugin is opened alone.
 *
 * @return bool
 */
if ( ! function_exists( 'lds_require_catalog' ) ) {
	function lds_require_catalog() {
		if ( class_exists( 'LAPS_Catalog' ) ) {
			return true;
		}
		$dirs = array();
		if ( defined( 'LAPS_DIR' ) ) {
			$dirs[] = LAPS_DIR;
		}
		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$dirs[] = trailingslashit( WP_PLUGIN_DIR ) . 'luxe-affiliate-product-scout/';
		}
		$dirs[] = trailingslashit( dirname( LDS_DIR ) ) . 'luxe-affiliate-product-scout/';
		foreach ( $dirs as $dir ) {
			$file = $dir . 'includes/class-catalog.php';
			$data = $dir . 'data/catalog.php';
			if ( ! is_readable( $file ) || ! is_readable( $data ) ) {
				continue;
			}
			if ( ! defined( 'LAPS_DIR' ) ) {
				define( 'LAPS_DIR', $dir );
			}
			require_once $file;
			return class_exists( 'LAPS_Catalog' );
		}
		return false;
	}
}

$lds_includes = array(
	'includes/class-parser.php',
	'includes/class-picker.php',
	'includes/class-placer.php',
	'includes/class-plugin.php',
	'includes/class-admin.php',
);
foreach ( $lds_includes as $lds_include ) {
	$lds_path = LDS_DIR . $lds_include;
	if ( is_readable( $lds_path ) ) {
		require_once $lds_path;
	}
}

if ( ! function_exists( 'lds_boot' ) ) {
	/**
	 * Boot after Product Scout. Admin only. Never throws to the public site.
	 */
	function lds_boot() {
		if ( ! class_exists( 'LDS_Plugin' ) ) {
			return;
		}
		try {
			lds_require_catalog();
			LDS_Plugin::instance()->boot();
		} catch ( Exception $e ) {
			if ( function_exists( 'error_log' ) ) {
				error_log( 'Luxe Download Scout: ' . $e->getMessage() );
			}
		} catch ( Throwable $e ) {
			if ( function_exists( 'error_log' ) ) {
				error_log( 'Luxe Download Scout: ' . $e->getMessage() );
			}
		}
	}
}
add_action( 'plugins_loaded', 'lds_boot', 40 );

if ( class_exists( 'LDS_Plugin' ) ) {
	register_activation_hook( __FILE__, array( 'LDS_Plugin', 'activate' ) );
	register_deactivation_hook( __FILE__, array( 'LDS_Plugin', 'deactivate' ) );
}
