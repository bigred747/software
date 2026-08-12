<?php
/**
 * Plugin Name: Richard Brummer SEO Mission Control
 * Plugin URI: https://luxetrendsetters.com/
 * Description: Evidence-based SEO mission control with Amazon compliance, JSON-LD quarantine, 38-plugin compatibility catalog, Search Console connection, verified scoring, and Stay Repair for real 5–15 minute reading time. Does not auto-publish or trap the back button.
 * Version: 1.9.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Richard Brummer
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rbsmc
 * Update URI: false
 *
 * Copyright (c) 2026 Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_init',
		function () {
			deactivate_plugins( plugin_basename( __FILE__ ) );
		}
	);
	return;
}

define( 'RBSMC_VERSION', '1.9.0' );
define( 'RBSMC_FILE', __FILE__ );
define( 'RBSMC_DIR', plugin_dir_path( __FILE__ ) );
define( 'RBSMC_URL', plugin_dir_url( __FILE__ ) );

require_once RBSMC_DIR . 'includes/catalog-plugins.php';
require_once RBSMC_DIR . 'includes/class-amazon.php';
require_once RBSMC_DIR . 'includes/class-schema.php';
require_once RBSMC_DIR . 'includes/class-compat.php';
require_once RBSMC_DIR . 'includes/class-score.php';
require_once RBSMC_DIR . 'includes/class-gsc.php';
require_once RBSMC_DIR . 'includes/class-audit.php';
require_once RBSMC_DIR . 'includes/class-stay-frontend.php';
require_once RBSMC_DIR . 'includes/class-notfound.php';
require_once RBSMC_DIR . 'includes/class-heartbeat.php';
require_once RBSMC_DIR . 'includes/class-plugin.php';
require_once RBSMC_DIR . 'includes/class-admin.php';

add_action(
	'plugins_loaded',
	function () {
		RBSMC_Plugin::instance()->boot();
	},
	20
);

register_activation_hook( __FILE__, array( 'RBSMC_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RBSMC_Plugin', 'deactivate' ) );
