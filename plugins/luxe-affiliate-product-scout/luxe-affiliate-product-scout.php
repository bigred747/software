<?php
/**
 * Plugin Name: Luxe Affiliate Product Scout
 * Plugin URI: https://luxetrendsetters.com/
 * Description: Honest WooCommerce catalog audit and repair. Scores supported products 95-100 when brand, ASIN, price, and a primary image are present with no hard risk flags. Never publishes, never deletes, never rewrites Amazon URLs.
 * Version: 5.6.6
 * Requires at least: 6.4
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 9.9
 * Author: LuxeTrendsetters
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-affiliate-product-scout
 * Update URI: false
 *
 * Copyright (c) 2026 LuxeTrendsetters / Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LAPS_VERSION', '5.6.6' );
define( 'LAPS_FILE', __FILE__ );
define( 'LAPS_DIR', plugin_dir_path( __FILE__ ) );
define( 'LAPS_URL', plugin_dir_url( __FILE__ ) );
define( 'LAPS_BASENAME', plugin_basename( __FILE__ ) );

require_once LAPS_DIR . 'includes/class-catalog.php';
require_once LAPS_DIR . 'includes/class-cache.php';
require_once LAPS_DIR . 'includes/class-score.php';
require_once LAPS_DIR . 'includes/class-repair.php';
require_once LAPS_DIR . 'includes/class-related.php';
require_once LAPS_DIR . 'includes/class-audit.php';
require_once LAPS_DIR . 'includes/class-plugin.php';
require_once LAPS_DIR . 'includes/class-admin.php';

/**
 * Boot after WooCommerce.
 */
function laps_boot() {
	LAPS_Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'laps_boot', 30 );

/**
 * @return bool
 */
function laps_woocommerce_ready() {
	return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_products' );
}

register_activation_hook( __FILE__, array( 'LAPS_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LAPS_Plugin', 'deactivate' ) );
