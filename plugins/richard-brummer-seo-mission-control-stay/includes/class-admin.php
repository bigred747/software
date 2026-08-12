<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stay Repair admin screen.
 */
class RBSMC_Stay_Admin {

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
		add_action( 'admin_menu', array( $this, 'menu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Menu under Mission Control when present.
	 */
	public function menu() {
		$cap = 'manage_options';
		if ( RBSMC_Stay_Plugin::instance()->mission_control_active() ) {
			add_submenu_page(
				'rbsmc',
				'Stay Repair',
				'Stay Repair',
				$cap,
				'rbsmc-stay',
				array( $this, 'render' )
			);
			add_submenu_page(
				'richard-brummer-seo',
				'Stay Repair',
				'Stay Repair',
				$cap,
				'rbsmc-stay',
				array( $this, 'render' )
			);
			return;
		}
		add_menu_page(
			'Stay Repair',
			'Stay Repair',
			$cap,
			'rbsmc-stay',
			array( $this, 'render' ),
			'dashicons-clock',
			58
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'rbsmc-stay' ) ) {
			return;
		}
		wp_enqueue_style( 'rbsmc-stay-admin', RBSMC_STAY_URL . 'assets/admin.css', array(), RBSMC_STAY_VERSION );
		wp_enqueue_script( 'rbsmc-stay-admin', RBSMC_STAY_URL . 'assets/admin.js', array(), RBSMC_STAY_VERSION, true );
		wp_localize_script(
			'rbsmc-stay-admin',
			'rbsmcStayAdmin',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'rbsmc_stay_admin' ),
			)
		);
	}

	/**
	 * Dashboard nag for the 1s dwell problem.
	 */
	public function notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$audit = get_option( RBSMC_Stay_Plugin::OPTION_AUDIT, array() );
		$red   = isset( $audit['red_count'] ) ? absint( $audit['red_count'] ) : 0;
		if ( $red < 1 ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && false !== strpos( (string) $screen->id, 'rbsmc-stay' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>Richard Brummer SEO Stay Repair:</strong> ' . esc_html( $red ) . ' verified red signal(s) need review. <a href="' . esc_url( admin_url( 'admin.php?page=rbsmc-stay' ) ) . '">Open evidence dashboard</a>.</p></div>';
	}

	/**
	 * Render dashboard.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		settings_errors( 'rbsmc_stay' );
		$plugin   = RBSMC_Stay_Plugin::instance();
		$settings = $plugin->settings();
		$audit    = get_option( RBSMC_Stay_Plugin::OPTION_AUDIT, array() );
		$dwell    = get_option( RBSMC_Stay_Plugin::OPTION_DWELL, array() );
		$log      = get_option( RBSMC_Stay_Plugin::OPTION_404, array() );
		$snap     = get_option( RBSMC_Stay_Plugin::OPTION_SNAPSHOT, array() );
		if ( ! is_array( $snap ) ) {
			$snap = array();
		}
		include RBSMC_STAY_DIR . 'templates/admin.php';
	}
}
