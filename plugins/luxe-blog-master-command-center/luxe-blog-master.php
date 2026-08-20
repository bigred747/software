<?php
/**
 * 2.8.1 bootstrap filename kept for Mission Control companion detection.
 * Not a second WordPress plugin (no Plugin Name header).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'LUXE_BMC_FILE' ) ) {
	require_once __DIR__ . '/luxe-blog-master-command-center.php';
}
