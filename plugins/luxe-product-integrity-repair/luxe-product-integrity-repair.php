<?php
/**
 * Plugin Name: Luxe Product Integrity Repair
 * Plugin URI: https://luxetrendsetters.com/
 * Description: Safe companion to Luxe Affiliate Product Scout 5.6.4. Repairs live taxonomy and brand-attribute conflicts that cap products at 94. Never publishes, never deletes, never rewrites Amazon URLs, never invents brands or ratings.
 * Version: 1.1.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Richard Brummer
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-product-integrity-repair
 * Update URI: false
 *
 * Copyright (c) 2026 Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUXE_PIR_VERSION', '1.1.0' );
define( 'LUXE_PIR_FILE', __FILE__ );
define( 'LUXE_PIR_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUXE_PIR_URL', plugin_dir_url( __FILE__ ) );
define( 'LUXE_PIR_BASENAME', plugin_basename( __FILE__ ) );

require_once LUXE_PIR_DIR . 'includes/class-plugin.php';
require_once LUXE_PIR_DIR . 'includes/class-repair.php';
require_once LUXE_PIR_DIR . 'includes/class-admin.php';

function luxe_pir_boot() {
	Luxe_PIR_Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'luxe_pir_boot', 40 );

register_activation_hook( __FILE__, array( 'Luxe_PIR_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Luxe_PIR_Plugin', 'deactivate' ) );
