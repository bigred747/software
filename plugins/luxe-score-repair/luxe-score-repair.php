<?php
/**
 * Plugin Name: Luxe Score Repair
 * Plugin URI: https://luxetrendsetters.com/
 * Description: One plugin for LuxeTrendsetters. Score repair, the Associates Hub, and strict Amazon Special Links. Never writes post content, never changes post status, never rewrites Amazon URLs, never stores an Amazon password.
 * Version: 1.3.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Richard Brummer
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-score-repair
 * Update URI: false
 *
 * Copyright (c) 2026 Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUXE_SCORE_REPAIR_VERSION', '1.3.0' );
if ( ! defined( 'LAH_VERSION' ) ) {
	define( 'LAH_VERSION', '1.3.0' );
}
if ( ! defined( 'LAH_FILE' ) ) {
	define( 'LAH_FILE', __FILE__ );
}
if ( ! defined( 'LAH_DIR' ) ) {
	define( 'LAH_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'LAH_URL' ) ) {
	define( 'LAH_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'LAH_BASENAME' ) ) {
	define( 'LAH_BASENAME', plugin_basename( __FILE__ ) );
}
define( 'LUXE_SCORE_REPAIR_FILE', __FILE__ );
define( 'LUXE_SCORE_REPAIR_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUXE_SCORE_REPAIR_URL', plugin_dir_url( __FILE__ ) );
define( 'LUXE_SCORE_REPAIR_BASENAME', plugin_basename( __FILE__ ) );

require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-plugin.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-seo.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-schema.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-robots.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-content.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-headers.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-redirects.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-buffer.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-learning.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-admin.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-links.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-guide.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-suite.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-associates.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-amazon.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-associates-admin.php';

/**
 * Boot after most SEO plugins so filters win on the public page.
 */
function luxe_score_repair_boot() {
	Luxe_Score_Repair_Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'luxe_score_repair_boot', 30 );

register_activation_hook( __FILE__, array( 'Luxe_Score_Repair_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Luxe_Score_Repair_Plugin', 'deactivate' ) );
