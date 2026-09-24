<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LDS_Plugin' ) ) {
	return;
}

/**
 * Admin-only boot. No public hooks, no cron, no imports.
 */
class LDS_Plugin {

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
	 * Activation stores nothing and schedules nothing.
	 */
	public static function activate() {
	}

	/**
	 * Deactivation does not delete products or redirects.
	 */
	public static function deactivate() {
	}

	/**
	 * Register the admin screen only.
	 */
	public function boot() {
		if ( ! is_admin() || ! class_exists( 'LDS_Admin' ) ) {
			return;
		}
		LDS_Admin::instance()->boot();
	}
}
