<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin-only operator dashboard. No frontend hooks. No cron. No content writes.
 */
class Luxe_Operator_Dashboard {

	const OPTION = 'luxe_op_settings';

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
	 * @return array
	 */
	public static function defaults() {
		return array(
			'clean_widgets'          => 1,
			'hide_dashboard_notices' => 1,
			'hide_rank_math_blog'    => 1,
			'show_operator_widget'   => 1,
		);
	}

	/**
	 * Activation: store defaults. Do not schedule cron. Do not write posts.
	 */
	public static function activate() {
		$settings = get_option( self::OPTION );
		if ( ! is_array( $settings ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
			return;
		}
		update_option( self::OPTION, wp_parse_args( $settings, self::defaults() ), false );
	}

	/**
	 * Register admin hooks only.
	 */
	public function boot() {
		add_action( 'admin_init', array( $this, 'maybe_save_settings' ) );
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		Luxe_Operator_Cleaner::instance()->boot();
		Luxe_Operator_Widget::instance()->boot();
	}

	/**
	 * @return array
	 */
	public function settings() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * @param string $key Setting key.
	 * @return bool
	 */
	public function enabled( $key ) {
		$settings = $this->settings();
		return ! empty( $settings[ $key ] );
	}

	/**
	 * Tools menu so settings remain reachable if the widget is hidden.
	 */
	public function menu() {
		add_management_page(
			'Luxe Operator Dashboard',
			'Luxe Operator',
			'manage_options',
			'luxe-operator-dashboard',
			array( $this, 'render_page' )
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		$on_dash = ( 'index.php' === $hook );
		$on_page = ( false !== strpos( (string) $hook, 'luxe-operator-dashboard' ) );
		if ( ! $on_dash && ! $on_page ) {
			return;
		}
		wp_enqueue_style( 'luxe-op-admin', LUXE_OP_URL . 'assets/admin.css', array(), LUXE_OP_VERSION );
		wp_enqueue_script( 'luxe-op-admin', LUXE_OP_URL . 'assets/admin.js', array(), LUXE_OP_VERSION, true );
		wp_localize_script(
			'luxe-op-admin',
			'luxeOpAdmin',
			array(
				'hideNotices' => $this->enabled( 'hide_dashboard_notices' ) && $on_dash ? 1 : 0,
				'hideBlog'    => $this->enabled( 'hide_rank_math_blog' ) ? 1 : 0,
			)
		);
	}

	/**
	 * Persist settings from Tools page POST.
	 */
	public function maybe_save_settings() {
		if ( empty( $_POST['luxe_op_save'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_op_save' );
		$defaults = self::defaults();
		$next     = array();
		foreach ( $defaults as $key => $default ) {
			$next[ $key ] = empty( $_POST[ $key ] ) ? 0 : 1;
		}
		update_option( self::OPTION, $next, false );
		add_settings_error( 'luxe_op', 'saved', __( 'Operator Dashboard settings saved. Reload the Dashboard screen.', 'luxe-operator-dashboard' ), 'updated' );
	}

	/**
	 * Tools page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		settings_errors( 'luxe_op' );
		$settings = $this->settings();
		$brief    = Luxe_Operator_Widget::instance()->brief();
		include LUXE_OP_DIR . 'templates/page.php';
	}
}
