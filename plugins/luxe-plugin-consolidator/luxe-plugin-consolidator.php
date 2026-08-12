<?php
/**
 * Plugin Name: Luxe Plugin Consolidator
 * Plugin URI: https://luxetrendsetters.com/
 * Description: One-time efficiency tool. On activate it deactivates unused store/YITH/overlap Luxe plugins and leaves WooCommerce, Rank Math, Site Kit, Wordfence, LiteSpeed, Mission Control, Contact Form 7, Classic Editor, and the Amazon link guardian running. It does not delete plugin files.
 * Version: 1.0.0
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

define( 'LUXE_CONSOL_VERSION', '1.0.0' );
define( 'LUXE_CONSOL_FILE', __FILE__ );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-consolidator.php';

register_activation_hook( __FILE__, array( 'Luxe_Plugin_Consolidator', 'activate' ) );

add_action(
	'plugins_loaded',
	function () {
		Luxe_Plugin_Consolidator::instance()->boot();
	}
);
