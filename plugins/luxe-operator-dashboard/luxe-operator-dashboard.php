<?php
/**
 * Plugin Name: Luxe Operator Dashboard
 * Plugin URI: https://luxetrendsetters.com/
 * Description: Admin-only WordPress dashboard cleaner for LuxeTrendsetters. Hides vendor blogs, empty widgets, and repeating plugin nags on the Dashboard screen. Explains Rank Math zeros, Site Kit 1s lag, WooCommerce $0, and the Mission Control red. Does not auto-write, auto-publish, hook frontend content, or run content cron.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Richard Brummer
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-operator-dashboard
 * Update URI: false
 *
 * Copyright (c) 2026 Richard Brummer
 *
 * Works beside Luxe Hard Rescue Admin Cleaner. Does not deactivate it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUXE_OP_VERSION', '1.0.0' );
define( 'LUXE_OP_FILE', __FILE__ );
define( 'LUXE_OP_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUXE_OP_URL', plugin_dir_url( __FILE__ ) );

require_once LUXE_OP_DIR . 'includes/class-plugin.php';
require_once LUXE_OP_DIR . 'includes/class-cleaner.php';
require_once LUXE_OP_DIR . 'includes/class-widget.php';

/**
 * Bootstrap. Admin only.
 */
function luxe_op_boot() {
	if ( ! is_admin() ) {
		return;
	}
	Luxe_Operator_Dashboard::instance()->boot();
}
add_action( 'plugins_loaded', 'luxe_op_boot', 30 );

register_activation_hook( __FILE__, array( 'Luxe_Operator_Dashboard', 'activate' ) );
