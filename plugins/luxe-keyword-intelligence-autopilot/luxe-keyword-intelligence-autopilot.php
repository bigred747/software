<?php
/**
 * Plugin Name: Luxe Keyword Intelligence Autopilot
 * Plugin URI: https://luxetrendsetters.com/
 * Description: Fast attention-first Rank Math phrase repair. Bounded batches, honest 95–100 scores, noindex on weight and date filters. Never publishes, never changes post status, and never rewrites article bodies or Amazon URLs.
 * Version: 1.4.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Richard Brummer
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-keyword-intelligence-autopilot
 * Update URI: false
 *
 * Copyright (c) 2026 Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUXE_KIA_VERSION', '1.4.0' );
define( 'LUXE_KIA_FILE', __FILE__ );
define( 'LUXE_KIA_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUXE_KIA_URL', plugin_dir_url( __FILE__ ) );
define( 'LUXE_KIA_BASENAME', plugin_basename( __FILE__ ) );

require_once LUXE_KIA_DIR . 'includes/class-phrases.php';
require_once LUXE_KIA_DIR . 'includes/class-score.php';
require_once LUXE_KIA_DIR . 'includes/class-plan.php';
require_once LUXE_KIA_DIR . 'includes/class-gate.php';
require_once LUXE_KIA_DIR . 'includes/class-taxonomies.php';
require_once LUXE_KIA_DIR . 'includes/class-engine.php';
require_once LUXE_KIA_DIR . 'includes/class-plugin.php';
require_once LUXE_KIA_DIR . 'includes/class-admin.php';

/**
 * Boot after the other Luxe plugins.
 */
function luxe_kia_boot() {
	Luxe_KIA_Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'luxe_kia_boot', 60 );

register_activation_hook( __FILE__, array( 'Luxe_KIA_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Luxe_KIA_Plugin', 'deactivate' ) );
