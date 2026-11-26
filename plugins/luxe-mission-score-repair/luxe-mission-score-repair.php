<?php
/**
 * Plugin Name: Luxe Mission Score Repair
 * Plugin URI: https://luxetrendsetters.com/
 * Description: Real repairs for the Mission Control checks that are still yellow or red: homepage output budget, WooCommerce 11.2 template overrides, and Amazon final-output false failures on share links and image-CDN anchors. Never publishes and never rewrites Amazon retail URLs.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Richard Brummer
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-mission-score-repair
 * Update URI: false
 *
 * Copyright (c) 2026 Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUXE_MSR_VERSION', '1.0.0' );
define( 'LUXE_MSR_FILE', __FILE__ );
define( 'LUXE_MSR_DIR', plugin_dir_path( __FILE__ ) );

require_once LUXE_MSR_DIR . 'includes/class-repair.php';
require_once LUXE_MSR_DIR . 'includes/class-plugin.php';

/**
 * Boot after WooCommerce and the theme are available.
 */
function luxe_msr_boot() {
	Luxe_MSR_Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'luxe_msr_boot', 40 );

register_activation_hook( __FILE__, array( 'Luxe_MSR_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Luxe_MSR_Plugin', 'deactivate' ) );
