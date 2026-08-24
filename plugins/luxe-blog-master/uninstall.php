<?php
/**
 * Leave Command Center options in place. This identity plugin never publishes
 * and must not wipe the draft engine on uninstall.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
