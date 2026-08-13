<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LAPS_Admin' ) ) {
	return;
}

/**
 * Luxe Product Scout admin board.
 */
class LAPS_Admin {

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
	 * Register UI.
	 */
	public function boot() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . LAPS_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Menu.
	 */
	public function menu() {
		add_menu_page(
			__( 'Luxe Product Scout', 'luxe-affiliate-product-scout' ),
			__( 'Luxe Product Scout', 'luxe-affiliate-product-scout' ),
			'manage_options',
			'luxe-affiliate-product-scout',
			array( $this, 'render' ),
			'dashicons-search',
			56
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( 'toplevel_page_luxe-affiliate-product-scout' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'laps-admin',
			LAPS_URL . 'assets/admin.css',
			array(),
			LAPS_VERSION
		);
		wp_enqueue_script(
			'laps-admin',
			LAPS_URL . 'assets/admin.js',
			array(),
			LAPS_VERSION,
			true
		);
	}

	/**
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=luxe-affiliate-product-scout' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'luxe-affiliate-product-scout' ) . '</a>' );
		return $links;
	}

	/**
	 * Render.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		settings_errors( 'laps' );
		$settings = LAPS_Plugin::instance()->settings();
		$dash     = LAPS_Audit::dashboard();
		$log      = LAPS_Audit::log();
		include LAPS_DIR . 'templates/admin.php';
	}
}
