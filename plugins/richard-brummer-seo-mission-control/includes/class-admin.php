<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin dashboard.
 */
class RBSMC_Admin {

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
	 * Hooks.
	 */
	public function boot() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Menu.
	 */
	public function menu() {
		add_menu_page(
			'Richard Brummer SEO',
			'Richard Brummer SEO',
			'manage_options',
			'rbsmc',
			array( $this, 'render' ),
			'dashicons-chart-area',
			58
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'rbsmc' ) ) {
			return;
		}
		wp_enqueue_style( 'rbsmc-admin', RBSMC_URL . 'assets/admin.css', array(), RBSMC_VERSION );
		wp_enqueue_script( 'rbsmc-admin', RBSMC_URL . 'assets/admin.js', array(), RBSMC_VERSION, true );
		wp_localize_script(
			'rbsmc-admin',
			'rbsmcAdmin',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'rbsmc_admin' ),
			)
		);
	}

	/**
	 * Red-signal notice.
	 */
	public function notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$audit = get_option( RBSMC_Plugin::OPTION_AUDIT, array() );
		$red   = isset( $audit['score']['red'] ) ? absint( $audit['score']['red'] ) : 0;
		if ( $red < 1 ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>Richard Brummer SEO:</strong> ' . esc_html( (string) $red ) . ' verified red signal(s) need review. <a href="' . esc_url( admin_url( 'admin.php?page=rbsmc' ) ) . '">Open evidence dashboard</a>.</p></div>';
	}

	/**
	 * Render.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		settings_errors( 'rbsmc' );
		$settings = RBSMC_Plugin::instance()->settings();
		$audit    = get_option( RBSMC_Plugin::OPTION_AUDIT, array() );
		$dwell    = get_option( RBSMC_Plugin::OPTION_DWELL, array() );
		$log      = get_option( RBSMC_Plugin::OPTION_404, array() );
		$snap     = get_option( RBSMC_Plugin::OPTION_SNAPSHOT, array() );
		$beat     = get_option( 'rbsmc_last_heartbeat', array() );
		$compat   = RBSMC_Compat::inventory();
		$gsc      = RBSMC_GSC::status();
		include RBSMC_DIR . 'templates/admin.php';
	}
}
