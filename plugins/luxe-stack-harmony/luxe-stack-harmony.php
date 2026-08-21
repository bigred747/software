<?php
/**
 * Plugin Name: Luxe Stack Harmony
 * Plugin URI: https://luxetrendsetters.com/
 * Description: Conductor for the live 46-plugin stack. KEEP plugins stay on. PARK duplicates (second Blog Master repair engine, Hostinger AI, Bad Content Eraser, Master Plugin Orchestrator) are deactivated, never deleted. 24/7 process learning. Zero frontend JavaScript or CSS. Never publishes, never writes post content, never rewrites Amazon URLs, never touches the Flatsome hamburger.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Richard Brummer
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-stack-harmony
 * Update URI: false
 *
 * Copyright (c) 2026 Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUXE_STACK_HARMONY_VERSION', '1.0.0' );
define( 'LUXE_STACK_HARMONY_FILE', __FILE__ );
define( 'LUXE_STACK_HARMONY_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUXE_STACK_HARMONY_URL', plugin_dir_url( __FILE__ ) );
define( 'LUXE_STACK_HARMONY_BASENAME', plugin_basename( __FILE__ ) );

require_once LUXE_STACK_HARMONY_DIR . 'includes/class-plugin.php';
require_once LUXE_STACK_HARMONY_DIR . 'includes/class-conductor.php';
require_once LUXE_STACK_HARMONY_DIR . 'includes/class-learning.php';
require_once LUXE_STACK_HARMONY_DIR . 'includes/class-admin.php';

/**
 * Boot early so duplicate repair engines are cancelled before they queue work.
 */
function luxe_stack_harmony_boot() {
	Luxe_Stack_Harmony_Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'luxe_stack_harmony_boot', 1 );

register_activation_hook( __FILE__, array( 'Luxe_Stack_Harmony_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Luxe_Stack_Harmony_Plugin', 'deactivate' ) );
