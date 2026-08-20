<?php
/**
 * Leave options on disk so a re-install keeps the board.
 * Never deletes posts, products, or Amazon URLs.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
