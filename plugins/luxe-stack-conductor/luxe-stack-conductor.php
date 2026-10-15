<?php
/**
 * Plugin Name: Luxe Stack Conductor
 * Plugin URI: https://luxetrendsetters.com/
 * Description: One queue for every Luxe 24/7 learner. Each process has a real green, yellow, or red signal. Heavy jobs take turns so WP-Cron finishes. Never publishes, never changes post status, never rewrites Amazon URLs, and never deactivates another plugin.
 * Version: 1.0.2
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: LuxeTrendsetters
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-stack-conductor
 * Update URI: false
 *
 * Copyright (c) 2026 LuxeTrendsetters / Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUXE_CONDUCTOR_VERSION', '1.0.2' );
define( 'LUXE_CONDUCTOR_FILE', __FILE__ );
define( 'LUXE_CONDUCTOR_DIR', plugin_dir_path( __FILE__ ) );

require_once LUXE_CONDUCTOR_DIR . 'includes/class-registry.php';
require_once LUXE_CONDUCTOR_DIR . 'includes/class-dispatcher.php';
require_once LUXE_CONDUCTOR_DIR . 'includes/class-signals.php';
require_once LUXE_CONDUCTOR_DIR . 'includes/class-plugin.php';

/**
 * Boot early enough to register the 5-minute schedule.
 */
function luxe_conductor_boot() {
	Luxe_Conductor_Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'luxe_conductor_boot', 5 );

register_activation_hook( __FILE__, array( 'Luxe_Conductor_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Luxe_Conductor_Plugin', 'deactivate' ) );
