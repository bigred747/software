<?php
/**
 * Plugin Name: Luxe Ultimate Authority & Revenue Engine
 * Plugin URI: https://luxetrendsetters.com/
 * Description: One signal board for authority, revenue gaps, content drafts, SEO checks, index recommendations, and speed. Never publishes, never changes post status, never rewrites articles or Amazon URLs.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Author: LuxeTrendsetters
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-ultimate-authority
 * Update URI: false
 *
 * Copyright (c) 2026 LuxeTrendsetters / Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUAE_VERSION', '1.0.0' );
define( 'LUAE_FILE', __FILE__ );
define( 'LUAE_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUAE_URL', plugin_dir_url( __FILE__ ) );
define( 'LUAE_BASENAME', plugin_basename( __FILE__ ) );

$luae_includes = array(
	'includes/class-text.php',
	'includes/class-junk.php',
	'includes/class-approve.php',
	'includes/class-render.php',
	'includes/class-post.php',
	'includes/class-overlap.php',
	'includes/class-speed.php',
	'includes/class-board.php',
	'includes/class-stats.php',
	'includes/class-plugin.php',
	'includes/class-admin.php',
);
foreach ( $luae_includes as $luae_include ) {
	$luae_path = LUAE_DIR . $luae_include;
	if ( is_readable( $luae_path ) ) {
		require_once $luae_path;
	}
}

/**
 * Boot without taking the public site down.
 */
function luae_boot() {
	if ( ! class_exists( 'LUAE_Plugin' ) ) {
		return;
	}
	try {
		LUAE_Plugin::instance()->boot();
	} catch ( Exception $e ) {
		if ( function_exists( 'error_log' ) ) {
			error_log( 'Luxe Ultimate Authority: ' . $e->getMessage() );
		}
	} catch ( Throwable $e ) {
		if ( function_exists( 'error_log' ) ) {
			error_log( 'Luxe Ultimate Authority: ' . $e->getMessage() );
		}
	}
}
add_action( 'plugins_loaded', 'luae_boot', 30 );

register_activation_hook( __FILE__, array( 'LUAE_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LUAE_Plugin', 'deactivate' ) );
