<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Left-menu Luxe Theme board.
 */
class Luxe_Theme_Guard_Admin {

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
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_filter( 'plugin_action_links_' . LUXE_THEME_GUARD_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Visible menu so it is not buried under Tools.
	 */
	public function menu() {
		add_menu_page(
			__( 'Luxe Theme', 'luxe-theme-guard' ),
			__( 'Luxe Theme', 'luxe-theme-guard' ),
			'update_themes',
			'luxe-theme-guard',
			array( $this, 'render' ),
			'dashicons-update',
			58
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( 'toplevel_page_luxe-theme-guard' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'luxe-theme-guard-admin',
			LUXE_THEME_GUARD_URL . 'assets/admin.css',
			array(),
			LUXE_THEME_GUARD_VERSION
		);
	}

	/**
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=luxe-theme-guard' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'luxe-theme-guard' ) . '</a>' );
		return $links;
	}

	/**
	 * Render board.
	 */
	public function render() {
		if ( ! current_user_can( 'update_themes' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		settings_errors( 'luxe_theme_guard' );
		$settings = Luxe_Theme_Guard_Plugin::instance()->settings();
		$last     = Luxe_Theme_Guard_Signals::last();
		$log      = Luxe_Theme_Guard_Signals::log();
		if ( empty( $last['signals'] ) ) {
			$last = Luxe_Theme_Guard_Signals::build( Luxe_Theme_Guard_Signals::snapshot() );
		}
		include LUXE_THEME_GUARD_DIR . 'templates/admin.php';
	}

	/**
	 * Compact notice on other screens.
	 */
	public function notice() {
		if ( ! current_user_can( 'update_themes' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && isset( $screen->id ) && 'toplevel_page_luxe-theme-guard' === $screen->id ) {
			return;
		}
		$last = Luxe_Theme_Guard_Signals::last();
		if ( empty( $last['total'] ) ) {
			return;
		}
		$green = (int) $last['green'];
		$total = (int) $last['total'];
		$class = ( $green === $total ) ? 'notice-success' : 'notice-warning';
		$url   = admin_url( 'admin.php?page=luxe-theme-guard' );
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p><strong>Luxe Theme:</strong> ';
		echo esc_html( $green . ' / ' . $total . ' processes green.' );
		echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open signal board', 'luxe-theme-guard' ) . '</a></p></div>';
	}
}
