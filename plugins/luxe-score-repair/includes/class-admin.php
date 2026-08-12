<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tools → Luxe Score Repair.
 */
class Luxe_Score_Repair_Admin {

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
	 * Register admin UI.
	 */
	public function boot() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . LUXE_SCORE_REPAIR_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Settings page.
	 */
	public function menu() {
		add_management_page(
			__( 'Luxe Score Repair', 'luxe-score-repair' ),
			__( 'Luxe Score Repair', 'luxe-score-repair' ),
			'manage_options',
			'luxe-score-repair',
			array( $this, 'render' )
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( 'tools_page_luxe-score-repair' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'luxe-score-repair-admin',
			LUXE_SCORE_REPAIR_URL . 'assets/admin.css',
			array(),
			LUXE_SCORE_REPAIR_VERSION
		);
	}

	/**
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'tools.php?page=luxe-score-repair' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'luxe-score-repair' ) . '</a>' );
		return $links;
	}

	/**
	 * Render settings.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		settings_errors( 'luxe_score_repair' );
		$settings = Luxe_Score_Repair_Plugin::instance()->settings();
		include LUXE_SCORE_REPAIR_DIR . 'templates/admin.php';
	}
}
