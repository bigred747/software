<?php
/**
 * Plugin Name: Luxe Blog Review Desk
 * Plugin URI: https://luxetrendsetters.com/
 * Description: View live buyer guides and publish only drafts that already pass Command Center gates. No cron. No auto-publish. Never deletes. Never rewrites Amazon URLs. Master #10833 stays read-only.
 * Version: 1.0.1
 * Requires at least: 6.4
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Author: LuxeTrendsetters
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-blog-review-desk
 * Update URI: false
 *
 * Copyright (c) 2026 LuxeTrendsetters / Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LBRD_VERSION' ) ) {
	define( 'LBRD_VERSION', '1.0.1' );
}
if ( ! defined( 'LBRD_FILE' ) ) {
	define( 'LBRD_FILE', __FILE__ );
}
if ( ! defined( 'LBRD_DIR' ) ) {
	define( 'LBRD_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'LBRD_URL' ) ) {
	define( 'LBRD_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'LBRD_BASENAME' ) ) {
	define( 'LBRD_BASENAME', plugin_basename( __FILE__ ) );
}

$lbrd_includes = array(
	'includes/class-eligibility.php',
	'includes/class-admin.php',
	'includes/class-plugin.php',
);
foreach ( $lbrd_includes as $lbrd_include ) {
	$lbrd_path = LBRD_DIR . $lbrd_include;
	if ( is_readable( $lbrd_path ) ) {
		require_once $lbrd_path;
	}
}

if ( ! function_exists( 'lbrd_boot' ) ) {
	function lbrd_boot() {
		if ( ! class_exists( 'LBRD_Plugin' ) ) {
			return;
		}
		try {
			LBRD_Plugin::instance()->boot();
		} catch ( Exception $e ) {
			if ( function_exists( 'error_log' ) ) {
				error_log( 'Luxe Blog Review Desk: ' . $e->getMessage() );
			}
		} catch ( Throwable $e ) {
			if ( function_exists( 'error_log' ) ) {
				error_log( 'Luxe Blog Review Desk: ' . $e->getMessage() );
			}
		}
	}
}
add_action( 'plugins_loaded', 'lbrd_boot', 40 );
