<?php
/**
 * Plugin Name: Luxe Overlap Remover
 * Plugin URI: https://luxetrendsetters.com/
 * Description: Deactivates and deletes 18 named overlap plugins after a backup confirmation. Does not hook the public site and cannot match WooCommerce, WZone, Rank Math, LiteSpeed, Wordfence, Amazon Bridge, Product Scout, or Ultimate Authority.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Author: LuxeTrendsetters
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-overlap-remover
 * Update URI: false
 *
 * Copyright (c) 2026 LuxeTrendsetters / Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUOR_VERSION', '1.0.0' );
define( 'LUOR_FILE', __FILE__ );
define( 'LUOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUOR_URL', plugin_dir_url( __FILE__ ) );
define( 'LUOR_BASENAME', plugin_basename( __FILE__ ) );

$luor_includes = array(
	'includes/class-targets.php',
	'includes/class-board.php',
	'includes/class-plugin.php',
	'includes/class-admin.php',
);
foreach ( $luor_includes as $luor_include ) {
	$luor_path = LUOR_DIR . $luor_include;
	if ( is_readable( $luor_path ) ) {
		require_once $luor_path;
	}
}

/**
 * Boot admin hooks only.
 */
function luor_boot() {
	if ( ! class_exists( 'LUOR_Plugin' ) ) {
		return;
	}
	try {
		LUOR_Plugin::instance()->boot();
	} catch ( Exception $e ) {
		if ( function_exists( 'error_log' ) ) {
			error_log( 'Luxe Overlap Remover: ' . $e->getMessage() );
		}
	} catch ( Throwable $e ) {
		if ( function_exists( 'error_log' ) ) {
			error_log( 'Luxe Overlap Remover: ' . $e->getMessage() );
		}
	}
}
add_action( 'plugins_loaded', 'luor_boot', 30 );

register_activation_hook( __FILE__, array( 'LUOR_Plugin', 'activate' ) );
