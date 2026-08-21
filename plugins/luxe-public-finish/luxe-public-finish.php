<?php
/**
 * Plugin Name: Luxe Public Finish
 * Plugin URI: https://luxetrendsetters.com/
 * Description: Last-pass public polish: complete titles, unique listing labels, one Amazon identification, one Organization schema. 24/7 learning and a green-signal board. Adds zero frontend JavaScript or CSS. Never writes post content, never publishes, never rewrites Amazon URLs.
 * Version: 1.0.1
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Richard Brummer
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-public-finish
 * Update URI: false
 *
 * Copyright (c) 2026 Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUXE_PUBLIC_FINISH_VERSION', '1.0.1' );
define( 'LUXE_PUBLIC_FINISH_FILE', __FILE__ );
define( 'LUXE_PUBLIC_FINISH_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUXE_PUBLIC_FINISH_URL', plugin_dir_url( __FILE__ ) );
define( 'LUXE_PUBLIC_FINISH_BASENAME', plugin_basename( __FILE__ ) );

require_once LUXE_PUBLIC_FINISH_DIR . 'includes/class-plugin.php';
require_once LUXE_PUBLIC_FINISH_DIR . 'includes/class-titles.php';
require_once LUXE_PUBLIC_FINISH_DIR . 'includes/class-buffer.php';
require_once LUXE_PUBLIC_FINISH_DIR . 'includes/class-learning.php';
require_once LUXE_PUBLIC_FINISH_DIR . 'includes/class-admin.php';

/**
 * Boot last so this plugin polishes HTML after Rank Math and Luxe SEO.
 */
function luxe_public_finish_boot() {
	Luxe_Public_Finish_Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'luxe_public_finish_boot', 99 );

register_activation_hook( __FILE__, array( 'Luxe_Public_Finish_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Luxe_Public_Finish_Plugin', 'deactivate' ) );
