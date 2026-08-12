<?php
/**
 * Plugin Name: Richard Brummer SEO Mission Control Stay Repair
 * Plugin URI: https://luxetrendsetters.com/
 * Description: Mission Control 1.9.0 stay-repair module. Restores real 5–15 minute reading time without back-button hijacking, stops LiteSpeed Guest Mode from killing sessions, repairs leaked shortcodes and utility-page AI filler on output, logs 404s, and explains Rank Math / Search Console zeros. Does not auto-publish or rewrite stored posts.
 * Version: 1.9.1
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Richard Brummer
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rbsmc-stay
 * Update URI: false
 *
 * Copyright (c) 2026 Richard Brummer
 *
 * Companion to Richard Brummer SEO Mission Control 1.8.1. Install beside it.
 * Do not upload this ZIP over the 1.8.1 folder.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RBSMC_STAY_VERSION', '1.9.1' );
define( 'RBSMC_STAY_FILE', __FILE__ );
define( 'RBSMC_STAY_DIR', plugin_dir_path( __FILE__ ) );
define( 'RBSMC_STAY_URL', plugin_dir_url( __FILE__ ) );
define( 'RBSMC_STAY_BASENAME', plugin_basename( __FILE__ ) );

require_once RBSMC_STAY_DIR . 'includes/class-plugin.php';
require_once RBSMC_STAY_DIR . 'includes/class-audit.php';
require_once RBSMC_STAY_DIR . 'includes/class-frontend.php';
require_once RBSMC_STAY_DIR . 'includes/class-notfound.php';
require_once RBSMC_STAY_DIR . 'includes/class-admin.php';

/**
 * Bootstrap.
 */
function rbsmc_stay_boot() {
	RBSMC_Stay_Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'rbsmc_stay_boot', 20 );

register_activation_hook( __FILE__, array( 'RBSMC_Stay_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RBSMC_Stay_Plugin', 'deactivate' ) );
