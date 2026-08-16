<?php
/**
 * Plugin Name: Luxe Theme Guard
 * Plugin URI: https://luxetrendsetters.com/
 * Description: 24/7 process learning, Amazon AI (Associates + official Creators API), and official Flatsome auto-update. Never downloads nulled zips, never overwrites plugins, never publishes, never rewrites Amazon URLs.
 * Version: 1.3.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: LuxeTrendsetters
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-theme-guard
 * Update URI: false
 *
 * Copyright (c) 2026 LuxeTrendsetters / Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUXE_THEME_GUARD_VERSION', '1.3.0' );
define( 'LUXE_THEME_GUARD_FILE', __FILE__ );
define( 'LUXE_THEME_GUARD_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUXE_THEME_GUARD_URL', plugin_dir_url( __FILE__ ) );
define( 'LUXE_THEME_GUARD_BASENAME', plugin_basename( __FILE__ ) );

require_once LUXE_THEME_GUARD_DIR . 'includes/class-plugin.php';
require_once LUXE_THEME_GUARD_DIR . 'includes/class-signals.php';
require_once LUXE_THEME_GUARD_DIR . 'includes/class-updater.php';
require_once LUXE_THEME_GUARD_DIR . 'includes/class-learning.php';
require_once LUXE_THEME_GUARD_DIR . 'includes/class-amazon.php';
require_once LUXE_THEME_GUARD_DIR . 'includes/class-amazon-api.php';
require_once LUXE_THEME_GUARD_DIR . 'includes/class-admin.php';

/**
 * Boot after most plugins. Theme upgrades never run on public HTML.
 */
function luxe_theme_guard_boot() {
	Luxe_Theme_Guard_Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'luxe_theme_guard_boot', 40 );

register_activation_hook( __FILE__, array( 'Luxe_Theme_Guard_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Luxe_Theme_Guard_Plugin', 'deactivate' ) );
