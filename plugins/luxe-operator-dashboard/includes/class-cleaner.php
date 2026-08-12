<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remove vendor dashboard widgets. Dashboard screen only.
 */
class Luxe_Operator_Cleaner {

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
	 * Widget IDs that add noise without operator value.
	 *
	 * @return array
	 */
	public static function remove_ids() {
		return array(
			'dashboard_primary',
			'dashboard_secondary',
			'dashboard_quick_press',
			'dashboard_activity',
			'dashboard_site_health',
			'woocommerce_dashboard_recent_reviews',
			'yith_dashboard_blog_news',
			'yith_dashboard_products_news',
			'yith_dashboard_latest_updates',
			'yith_latest_updates',
		);
	}

	/**
	 * Title fragments for widgets whose IDs vary by YITH version.
	 *
	 * @return array
	 */
	public static function remove_title_needles() {
		return array(
			'yith latest updates',
			'latest news from yith',
			'latest blog posts from rank math',
			'woocommerce recent reviews',
			'wordpress events and news',
			'site health status',
		);
	}

	/**
	 * Register cleaner.
	 */
	public function boot() {
		add_action( 'wp_dashboard_setup', array( $this, 'clean' ), 99 );
		add_action( 'wp_network_dashboard_setup', array( $this, 'clean' ), 99 );
	}

	/**
	 * Remove vendor widgets. Keep At a Glance, Site Kit, Wordfence, Woo status, Rank Math Overview.
	 */
	public function clean() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! Luxe_Operator_Dashboard::instance()->enabled( 'clean_widgets' ) ) {
			return;
		}
		global $wp_meta_boxes;
		foreach ( self::remove_ids() as $id ) {
			remove_meta_box( $id, 'dashboard', 'normal' );
			remove_meta_box( $id, 'dashboard', 'side' );
			remove_meta_box( $id, 'dashboard', 'column3' );
			remove_meta_box( $id, 'dashboard', 'column4' );
		}
		if ( empty( $wp_meta_boxes['dashboard'] ) || ! is_array( $wp_meta_boxes['dashboard'] ) ) {
			return;
		}
		$needles = self::remove_title_needles();
		foreach ( $wp_meta_boxes['dashboard'] as $context => $priorities ) {
			if ( ! is_array( $priorities ) ) {
				continue;
			}
			foreach ( $priorities as $priority => $boxes ) {
				if ( ! is_array( $boxes ) ) {
					continue;
				}
				foreach ( $boxes as $id => $box ) {
					$title = '';
					if ( is_array( $box ) && isset( $box['title'] ) ) {
						$title = strtolower( wp_strip_all_tags( (string) $box['title'] ) );
					}
					foreach ( $needles as $needle ) {
						if ( '' !== $title && false !== strpos( $title, $needle ) ) {
							remove_meta_box( $id, 'dashboard', $context );
							break;
						}
					}
				}
			}
		}
	}
}
