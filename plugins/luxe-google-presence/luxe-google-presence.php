<?php
/**
 * Plugin Name: Luxe Google Presence
 * Plugin URI: https://luxetrendsetters.com/
 * Description: Crawl-ready pages for computer and mobile, on-site compare links, then the existing Amazon button. Honest 95–100 readiness. Never buys traffic, never auto-redirects to Amazon, never rewrites Amazon URLs, and leaves Score Repair, Product Scout, Product Integrity, and Rank Math in charge of their own work.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Richard Brummer
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-google-presence
 * Update URI: false
 *
 * Copyright (c) 2026 Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUXE_GOOGLE_PRESENCE_VERSION', '1.0.0' );
define( 'LUXE_GOOGLE_PRESENCE_FILE', __FILE__ );
define( 'LUXE_GOOGLE_PRESENCE_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUXE_GOOGLE_PRESENCE_URL', plugin_dir_url( __FILE__ ) );
define( 'LUXE_GOOGLE_PRESENCE_BASENAME', plugin_basename( __FILE__ ) );

require_once LUXE_GOOGLE_PRESENCE_DIR . 'includes/class-score.php';
require_once LUXE_GOOGLE_PRESENCE_DIR . 'includes/class-anchors.php';
require_once LUXE_GOOGLE_PRESENCE_DIR . 'includes/class-copy.php';
require_once LUXE_GOOGLE_PRESENCE_DIR . 'includes/class-scan.php';
require_once LUXE_GOOGLE_PRESENCE_DIR . 'includes/class-compat.php';
require_once LUXE_GOOGLE_PRESENCE_DIR . 'includes/class-contract.php';
require_once LUXE_GOOGLE_PRESENCE_DIR . 'includes/class-device.php';
require_once LUXE_GOOGLE_PRESENCE_DIR . 'includes/class-engage.php';
require_once LUXE_GOOGLE_PRESENCE_DIR . 'includes/class-plugin.php';
require_once LUXE_GOOGLE_PRESENCE_DIR . 'includes/class-admin.php';

/**
 * Boot after Score Repair (30), Product Scout (30), and Product Integrity (40).
 */
function luxe_google_presence_boot() {
	Luxe_Google_Presence_Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'luxe_google_presence_boot', 55 );

register_activation_hook( __FILE__, array( 'Luxe_Google_Presence_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Luxe_Google_Presence_Plugin', 'deactivate' ) );
