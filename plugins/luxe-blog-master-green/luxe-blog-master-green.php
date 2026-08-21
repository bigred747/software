<?php
/**
 * Plugin Name: Luxe Blog Master Green Board
 * Plugin URI: https://luxetrendsetters.com/
 * Description: 2.9.0 process-health layer for Luxe Blog Master Command Center v2.8.1. Keep 2.8.1 activated. Keep every blog. All-green process signals. Live-verify published guides including master #10833. Never publishes, never deletes, never fake-greens Product #0, never rewrites Amazon URLs, never touches the Flatsome hamburger.
 * Version: 2.9.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Richard Brummer
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-blog-master-green
 * Update URI: false
 *
 * Copyright (c) 2026 Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUXE_BMG_VERSION', '2.9.0' );
define( 'LUXE_BMG_FILE', __FILE__ );
define( 'LUXE_BMG_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUXE_BMG_URL', plugin_dir_url( __FILE__ ) );
define( 'LUXE_BMG_BASENAME', plugin_basename( __FILE__ ) );

require_once LUXE_BMG_DIR . 'includes/class-plugin.php';
require_once LUXE_BMG_DIR . 'includes/class-scan.php';
require_once LUXE_BMG_DIR . 'includes/class-learning.php';
require_once LUXE_BMG_DIR . 'includes/class-admin.php';

/**
 * Boot after Blog Master 2.8.1 so this board never fights its repair engine.
 */
function luxe_bmg_boot() {
	Luxe_BMG_Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'luxe_bmg_boot', 40 );

register_activation_hook( __FILE__, array( 'Luxe_BMG_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Luxe_BMG_Plugin', 'deactivate' ) );
