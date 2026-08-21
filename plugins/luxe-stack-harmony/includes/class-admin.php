<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tools → Luxe Harmony.
 */
class Luxe_Stack_Harmony_Admin {

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
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_filter( 'plugin_action_links_' . LUXE_STACK_HARMONY_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Settings page.
	 */
	public function menu() {
		add_menu_page(
			__( 'Luxe Harmony', 'luxe-stack-harmony' ),
			__( 'Luxe Harmony', 'luxe-stack-harmony' ),
			'manage_options',
			'luxe-stack-harmony',
			array( $this, 'render' ),
			'dashicons-admin-site-alt3',
			57
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( 'toplevel_page_luxe-stack-harmony' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'luxe-stack-harmony-admin',
			LUXE_STACK_HARMONY_URL . 'assets/admin.css',
			array(),
			LUXE_STACK_HARMONY_VERSION
		);
	}

	/**
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=luxe-stack-harmony' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'luxe-stack-harmony' ) . '</a>' );
		return $links;
	}

	/**
	 * Render settings.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = Luxe_Stack_Harmony_Plugin::instance()->settings();
		$last     = Luxe_Stack_Harmony_Learning::last();
		$log      = Luxe_Stack_Harmony_Learning::log();
		$cancels  = Luxe_Stack_Harmony_Conductor::log();
		settings_errors( 'luxe_stack_harmony' );
		include LUXE_STACK_HARMONY_DIR . 'templates/admin.php';
	}

	/**
	 * Admin notice after learning.
	 */
	public function notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && isset( $screen->id ) && 'toplevel_page_luxe-stack-harmony' === $screen->id ) {
			return;
		}
		$last = Luxe_Stack_Harmony_Learning::last();
		if ( empty( $last['total'] ) ) {
			return;
		}
		$green = isset( $last['green'] ) ? (int) $last['green'] : 0;
		$total = isset( $last['total'] ) ? (int) $last['total'] : 0;
		$url   = admin_url( 'admin.php?page=luxe-stack-harmony' );
		printf(
			'<div class="notice notice-info is-dismissible"><p>Luxe Harmony: %1$d / %2$d processes green. <a href="%3$s">Open signal board</a></p></div>',
			(int) $green,
			(int) $total,
			esc_url( $url )
		);
	}
}
