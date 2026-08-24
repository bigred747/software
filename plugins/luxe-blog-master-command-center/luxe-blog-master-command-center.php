<?php
/**
 * Plugin Name: Luxe Blog Master Command Center
 * Plugin URI: https://luxetrendsetters.com/
 * Description: v2.9.8 Complete Blog Master approval companion. Hard no-publish evidence armed. Never writes a 2.8.1 identity plugin over 2.9.6. Never publishes. Read-only KEEP/LAYERED/PARK stack map. Scan, repair drafts, rescan, independent verification, APPROVAL READY. Protects master #10833. Never deletes. Never fake-greens Product #0. Never rewrites Amazon URLs. Never touches the Flatsome hamburger.
 * Version: 2.9.8
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Richard Brummer
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-blog-master-command-center
 * Update URI: false
 * Blog Master Companion: complete
 * RBSMC Companion: blog-master
 * Approval Companion: true
 * Approval Only: true
 * Hard No-Publish: true
 * Complete Build: true
 *
 * Copyright (c) 2026 Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'LUXE_BMC_FILE' ) ) {
	return;
}

define( 'LUXE_BMC_VERSION', '2.9.8' );
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
	'includes/class-companion.php',
);
foreach ( $luxe_bmc_files as $luxe_bmc_file ) {
	require_once LUXE_BMC_DIR . $luxe_bmc_file;
}

Luxe_BMC_Companion::arm();

if ( ! function_exists( 'luxe_bmc_boot' ) ) {
	function luxe_bmc_boot() {
		Luxe_BMC_Companion::arm();
		Luxe_BMC_Plugin::instance()->boot();
	}
}
add_action( 'plugins_loaded', 'luxe_bmc_boot', 5 );

register_activation_hook( __FILE__, array( 'Luxe_BMC_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Luxe_BMC_Plugin', 'deactivate' ) );
