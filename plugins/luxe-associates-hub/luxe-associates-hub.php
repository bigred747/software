<?php
/**
 * Plugin Name: Luxe Associates Hub
 * Plugin URI: https://luxetrendsetters.com/
 * Description: Associates starter guide plus strict Amazon Special Links for the Luxe master set. Disclosure sits on the link, Google sponsored rel is added, and the Amazon URL is never cloaked. Never logs into Amazon and never stores your password, bank account, or tax ID.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Author: LuxeTrendsetters
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-associates-hub
 * Update URI: false
 *
 * Copyright (c) 2026 LuxeTrendsetters / Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LAH_VERSION' ) ) {
	define( 'LAH_VERSION', '1.0.0' );
}
if ( ! defined( 'LAH_FILE' ) ) {
	define( 'LAH_FILE', __FILE__ );
}
if ( ! defined( 'LAH_DIR' ) ) {
	define( 'LAH_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'LAH_URL' ) ) {
	define( 'LAH_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'LAH_BASENAME' ) ) {
	define( 'LAH_BASENAME', plugin_basename( __FILE__ ) );
}

$lah_includes = array(
	'includes/class-links.php',
	'includes/class-guide.php',
	'includes/class-suite.php',
	'includes/class-plugin.php',
	'includes/class-public.php',
	'includes/class-admin.php',
);
foreach ( $lah_includes as $lah_include ) {
	$lah_path = LAH_DIR . $lah_include;
	if ( is_readable( $lah_path ) ) {
		require_once $lah_path;
	}
}

/**
 * Boot after the Luxe master set so Product Scout classes are available.
 */
if ( ! function_exists( 'lah_boot' ) ) {
	function lah_boot() {
		if ( ! class_exists( 'LAH_Plugin' ) ) {
			return;
		}
		LAH_Plugin::instance()->boot();
	}
}
add_action( 'plugins_loaded', 'lah_boot', 35 );

if ( class_exists( 'LAH_Plugin' ) ) {
	register_activation_hook( __FILE__, array( 'LAH_Plugin', 'activate' ) );
	register_deactivation_hook( __FILE__, array( 'LAH_Plugin', 'deactivate' ) );
}
