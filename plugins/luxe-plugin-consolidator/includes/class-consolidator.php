<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivates redundant plugins. Never deletes files. Never touches the keep list.
 */
class Luxe_Plugin_Consolidator {

	const OPTION = 'luxe_consolidator_last_run';

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
	 * Folders that must stay active.
	 *
	 * @return array
	 */
	public static function keep_folders() {
		return array(
			'woocommerce',
			'seo-by-rank-math',
			'google-site-kit',
			'wordfence',
			'litespeed-cache',
			'richard-brummer-seo-mission-control',
			'richard-brummer-seo-mission-control-stay',
			'luxe-plugin-consolidator',
			'contact-form-7',
			'classic-editor',
			'luxe-performance-link-guardian-suite',
			'luxe-amazon-bridge',
			'luxe-unified-site-guardian',
			'luxe-hard-rescue-admin-cleaner',
			'yith-woocommerce-wishlist',
		);
	}

	/**
	 * Folder prefixes to deactivate (unused checkout/YITH/overlap scanners).
	 *
	 * @return array
	 */
	public static function deactivate_prefixes() {
		return array(
			'yith-woocommerce-points',
			'yith-woocommerce-coupon',
			'yith-woocommerce-product-add',
			'yith-woocommerce-minimum',
			'yith-woocommerce-custom-order',
			'yith-woocommerce-product-countdown',
			'yith-woocommerce-color',
			'yith-woocommerce-frequently',
			'yith-woocommerce-product-image',
			'yith-woocommerce-product-gallery',
			'yith-woocommerce-compare',
			'yith-woocommerce-ajax',
			'nextend-facebook',
			'nextend-social',
			'jetpack',
			'wordpress-seo',
			'wordpress-seo-premium',
			'redirection',
			'luxe-keyword-intelligence',
			'luxe-ai-readiness',
			'luxe-reader-love',
			'woocommerce-paypal-payments',
			'elementor',
			'autoptimize',
			'wp-optimize',
		);
	}

	/**
	 * Run on plugin activate.
	 */
	public static function activate() {
		self::instance()->run( 'activation' );
	}

	/**
	 * Admin UI.
	 */
	public function boot() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'admin_post_luxe_consol_run', array( $this, 'handle_run' ) );
	}

	/**
	 * Menu.
	 */
	public function menu() {
		add_management_page(
			'Luxe Plugin Consolidator',
			'Luxe Consolidator',
			'activate_plugins',
			'luxe-consolidator',
			array( $this, 'render' )
		);
	}

	/**
	 * Manual run.
	 */
	public function handle_run() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'luxe_consol_run' );
		$this->run( 'manual' );
		wp_safe_redirect( admin_url( 'tools.php?page=luxe-consolidator&done=1' ) );
		exit;
	}

	/**
	 * Notice after activation.
	 */
	public function notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$run = get_option( self::OPTION );
		if ( ! is_array( $run ) || empty( $run['deactivated'] ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'plugins' !== $screen->id && ( empty( $screen->id ) || false === strpos( (string) $screen->id, 'luxe-consolidator' ) ) ) {
			if ( 'plugins' !== $screen->id ) {
				return;
			}
		}
		$count = count( $run['deactivated'] );
		echo '<div class="notice notice-success"><p><strong>Luxe Consolidator:</strong> deactivated ' . esc_html( (string) $count ) . ' unused plugin(s). Files were not deleted. <a href="' . esc_url( admin_url( 'tools.php?page=luxe-consolidator' ) ) . '">Review list</a>.</p></div>';
	}

	/**
	 * Admin page.
	 */
	public function render() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$run = get_option( self::OPTION, array() );
		echo '<div class="wrap"><h1>Luxe Plugin Consolidator</h1>';
		echo '<p>Keeps the store/SEO/security stack. Deactivates unused YITH checkout plugins and overlapping Luxe scanners. Does not delete anything.</p>';
		echo '<h2>Keep active</h2><ul>';
		foreach ( self::keep_folders() as $folder ) {
			echo '<li><code>' . esc_html( $folder ) . '</code></li>';
		}
		echo '</ul><h2>Deactivate if found</h2><ul>';
		foreach ( self::deactivate_prefixes() as $prefix ) {
			echo '<li><code>' . esc_html( $prefix ) . '*</code></li>';
		}
		echo '</ul>';
		if ( ! empty( $run['deactivated'] ) ) {
			echo '<h2>Last run</h2><p>' . esc_html( gmdate( 'Y-m-d H:i:s', absint( $run['at'] ) ) ) . ' UTC via ' . esc_html( $run['via'] ) . '</p><ol>';
			foreach ( $run['deactivated'] as $file ) {
				echo '<li><code>' . esc_html( $file ) . '</code></li>';
			}
			echo '</ol>';
		} else {
			echo '<p>No matching active plugins were deactivated yet (they may already be off, or the folder names differ).</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'luxe_consol_run' );
		echo '<input type="hidden" name="action" value="luxe_consol_run" />';
		echo '<p><button class="button button-primary" type="submit">Run deactivation now</button></p></form></div>';
	}

	/**
	 * Deactivate matches.
	 *
	 * @param string $via activation|manual.
	 */
	public function run( $via ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active = (array) get_option( 'active_plugins', array() );
		$keep   = self::keep_folders();
		$prefs  = self::deactivate_prefixes();
		$off    = array();
		foreach ( $active as $file ) {
			$folder = strtolower( strtok( $file, '/' ) );
			if ( in_array( $folder, $keep, true ) ) {
				continue;
			}
			foreach ( $prefs as $prefix ) {
				if ( 0 === strpos( $folder, $prefix ) ) {
					$off[] = $file;
					break;
				}
			}
		}
		$off = array_values( array_unique( $off ) );
		if ( $off ) {
			deactivate_plugins( $off, true );
		}
		update_option(
			self::OPTION,
			array(
				'at'           => time(),
				'via'          => $via,
				'deactivated'  => $off,
				'keep'         => $keep,
			),
			false
		);
	}
}
