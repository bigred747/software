<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tools → Luxe Product Integrity.
 */
class Luxe_PIR_Admin {

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
		add_filter( 'plugin_action_links_' . LUXE_PIR_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Menu.
	 */
	public function menu() {
		add_management_page(
			__( 'Luxe Product Integrity', 'luxe-product-integrity-repair' ),
			__( 'Luxe Product Integrity', 'luxe-product-integrity-repair' ),
			'manage_options',
			'luxe-product-integrity-repair',
			array( $this, 'render' )
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( 'tools_page_luxe-product-integrity-repair' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'luxe-pir-admin',
			LUXE_PIR_URL . 'assets/admin.css',
			array(),
			LUXE_PIR_VERSION
		);
	}

	/**
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'tools.php?page=luxe-product-integrity-repair' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'luxe-product-integrity-repair' ) . '</a>' );
		return $links;
	}

	/**
	 * Render.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		settings_errors( 'luxe_pir' );
		$settings = Luxe_PIR_Plugin::instance()->settings();
		$last     = Luxe_PIR_Repair::last();
		$log      = Luxe_PIR_Repair::log();
		include LUXE_PIR_DIR . 'templates/admin.php';
	}
}
