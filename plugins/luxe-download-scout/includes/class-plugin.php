<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LDS_Plugin' ) ) {
	return;
}

/**
 * Admin screen plus a catalog guard so unpublished candidates stay off the scoreboard.
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
	 * Register the admin screen and keep draft candidates out of Product Scout totals.
	 */
	public function boot() {
		if ( class_exists( 'LDS_Placer' ) ) {
			add_filter( 'woocommerce_product_data_store_cpt_get_products_query', array( 'LDS_Placer', 'exclude_candidates' ), 10, 2 );
			add_action( 'transition_post_status', array( 'LDS_Placer', 'release_on_publish' ), 10, 3 );
		}
		if ( ! is_admin() || ! class_exists( 'LDS_Admin' ) ) {
			return;
		}
		LDS_Admin::instance()->boot();
	}
}
