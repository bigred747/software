<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared options and boot.
 */
class Luxe_PIR_Plugin {

	const OPTION = 'luxe_pir_settings';

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
	 * @return array<string,int>
	 */
	public static function defaults() {
		return array(
			'auto_repair' => 1,
			'batch_size'  => 25,
		);
	}

	/**
	 * Activation.
	 */
	public static function activate() {
		$settings = get_option( self::OPTION );
		if ( ! is_array( $settings ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		} else {
			update_option( self::OPTION, wp_parse_args( $settings, self::defaults() ), false );
		}
		Luxe_PIR_Repair::activate();
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		Luxe_PIR_Repair::deactivate();
	}

	/**
	 * Register modules.
	 */
	public function boot() {
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
		Luxe_PIR_Repair::instance()->boot();
		if ( is_admin() ) {
			Luxe_PIR_Admin::instance()->boot();
		}
	}

	/**
	 * @return array<string,int>
	 */
	public function settings() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * @param string $key Key.
	 * @return bool
	 */
	public function enabled( $key ) {
		$settings = $this->settings();
		return ! empty( $settings[ $key ] );
	}

	/**
	 * @return int
	 */
	public function batch_size() {
		$settings = $this->settings();
		$size     = isset( $settings['batch_size'] ) ? (int) $settings['batch_size'] : 25;
		if ( $size < 5 ) {
			$size = 5;
		}
		if ( $size > 50 ) {
			$size = 50;
		}
		return $size;
	}

	/**
	 * Persist settings.
	 */
	public function maybe_save() {
		if ( empty( $_POST['luxe_pir_save'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_pir_save' );
		$next = array(
			'auto_repair' => empty( $_POST['auto_repair'] ) ? 0 : 1,
			'batch_size'  => isset( $_POST['batch_size'] ) ? (int) $_POST['batch_size'] : 25,
		);
		update_option( self::OPTION, wp_parse_args( $next, self::defaults() ), false );
		add_settings_error(
			'luxe_pir',
			'saved',
			__( 'Integrity Repair settings saved.', 'luxe-product-integrity-repair' ),
			'updated'
		);
	}
}
