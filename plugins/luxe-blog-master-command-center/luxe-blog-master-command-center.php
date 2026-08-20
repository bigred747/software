<?php
/**
 * Plugin Name: Luxe Blog Master Command Center
 * Plugin URI: https://luxetrendsetters.com/
 * Description: v2.9.2 Full-preservation automation. Scan, repair drafts, rescan, independent verification, APPROVAL READY. Keeps every blog. Protects master #10833. Never publishes. Never deletes. Never fake-greens Product #0. Never rewrites Amazon URLs. Never touches the Flatsome hamburger.
 * Version: 2.9.2
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Richard Brummer
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-blog-master-command-center
 * Update URI: false
 *
 * Copyright (c) 2026 Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUXE_BMC_VERSION', '2.9.2' );
define( 'LUXE_BMC_FILE', __FILE__ );
define( 'LUXE_BMC_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUXE_BMC_URL', plugin_dir_url( __FILE__ ) );
define( 'LUXE_BMC_BASENAME', plugin_basename( __FILE__ ) );

$luxe_bmc_files = array(
	'includes/class-plugin.php',
	'includes/class-safety.php',
	'includes/class-amazon.php',
	'includes/class-score.php',
	'includes/class-public.php',
	'includes/class-blueprint.php',
	'includes/class-repair.php',
	'includes/class-audit.php',
	'includes/class-queue.php',
	'includes/class-learning.php',
	'includes/class-admin.php',
);
foreach ( $luxe_bmc_files as $luxe_bmc_file ) {
	require_once LUXE_BMC_DIR . $luxe_bmc_file;
}

function luxe_bmc_boot() {
	Luxe_BMC_Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'luxe_bmc_boot', 35 );

register_activation_hook( __FILE__, array( 'Luxe_BMC_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Luxe_BMC_Plugin', 'deactivate' ) );
