<?php
/**
 * Plugin Name: Luxe Score Repair
 * Plugin URI: https://luxetrendsetters.com/
 * Description: Safe public-output repair for LuxeTrendsetters with process learning and a green-signal board. 1.2.2 points Google at products and published buyer guides, and stops false reds from WordPress loopback fetches. Never writes post content, never changes post status, never rewrites Amazon URLs, never creates Rank Math redirects, never fakes traffic.
 * Version: 1.2.2
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

define( 'LUXE_SCORE_REPAIR_VERSION', '1.2.2' );
define( 'LUXE_SCORE_REPAIR_FILE', __FILE__ );
define( 'LUXE_SCORE_REPAIR_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUXE_SCORE_REPAIR_URL', plugin_dir_url( __FILE__ ) );
define( 'LUXE_SCORE_REPAIR_BASENAME', plugin_basename( __FILE__ ) );

require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-plugin.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-seo.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-search-focus.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-schema.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-robots.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-content.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-headers.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-redirects.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-buffer.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-learning.php';
require_once LUXE_SCORE_REPAIR_DIR . 'includes/class-admin.php';

/**
 * Boot after most SEO plugins so filters win on the public page.
 */
function luxe_score_repair_boot() {
	Luxe_Score_Repair_Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'luxe_score_repair_boot', 30 );

register_activation_hook( __FILE__, array( 'Luxe_Score_Repair_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Luxe_Score_Repair_Plugin', 'deactivate' ) );
