<?php
/**
 * Loader alias. Not a separate WordPress plugin. Command Center is the
 * listed plugin. Do not add a Plugin Name header here — that made
 * WordPress offer a 2.8.1 downgrade over Luxe Blog Master 2.9.6.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'LUXE_BMC_FILE' ) ) {
	require_once __DIR__ . '/luxe-blog-master-command-center.php';
}
