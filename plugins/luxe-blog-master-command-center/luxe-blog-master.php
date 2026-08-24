<?php
/**
 * Plugin Name: Luxe Blog Master
 * Plugin URI: https://luxetrendsetters.com/
 * Description: Complete Blog Master approval companion. Hard no-publish. Approval-only. Loads Command Center. Never publishes.
 * Version: 2.9.5
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Richard Brummer
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-blog-master
 * Update URI: false
 * Blog Master Companion: complete
 * RBSMC Companion: blog-master
 * Approval Companion: true
 * Approval Only: true
 * Hard No-Publish: true
 * Complete Build: true
 *
 * 2.8.1 bootstrap filename + Plugin Name so Mission Control 1.8.2 can
 * detect the complete build. Same folder as Command Center. Not a second
 * repair engine.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'LUXE_BMC_FILE' ) ) {
	require_once __DIR__ . '/luxe-blog-master-command-center.php';
}
if ( function_exists( 'register_activation_hook' ) && class_exists( 'Luxe_BMC_Plugin' ) ) {
	register_activation_hook( __FILE__, array( 'Luxe_BMC_Plugin', 'activate' ) );
}
