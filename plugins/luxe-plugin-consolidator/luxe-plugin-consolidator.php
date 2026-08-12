<?php
/**
 * Plugin Name: Luxe Plugin Consolidator
 * Plugin URI: https://luxetrendsetters.com/
 * Description: One-time efficiency tool for the live 43-plugin stack. On activate it deactivates overlapping 24/7 Luxe scanners and leaves WooCommerce, WZone, Rank Math, Site Kit, Wordfence, LiteSpeed, Mission Control 1.8.2, Stay Repair, Amazon Bridge, and backups running. It does not delete plugin files.
 * Version: 1.1.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Richard Brummer
 * License: GPL-2.0-or-later
 * Text Domain: luxe-consolidator
 *
 * Copyright (c) 2026 Richard Brummer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUXE_CONSOL_VERSION', '1.1.0' );
define( 'LUXE_CONSOL_FILE', __FILE__ );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-consolidator.php';

register_activation_hook( __FILE__, array( 'Luxe_Plugin_Consolidator', 'activate' ) );

add_action(
	'plugins_loaded',
	function () {
		Luxe_Plugin_Consolidator::instance()->boot();
	}
);
