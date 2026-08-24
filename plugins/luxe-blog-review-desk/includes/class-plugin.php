<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LBRD_Plugin' ) ) {
	return;
}

/**
 * Admin-only review desk.
 */
class LBRD_Plugin {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register admin only. No public hooks.
	 */
	public function boot() {
		if ( is_admin() && class_exists( 'LBRD_Admin' ) ) {
			LBRD_Admin::instance()->boot();
		}
	}
}
