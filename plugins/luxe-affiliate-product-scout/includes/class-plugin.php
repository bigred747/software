<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared options and boot.
 */
class LAPS_Plugin {

	const OPTION = 'laps_settings';

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
			'auto_repair'     => 1,
			'related_filter'  => 1,
			'batch_size'      => 25,
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
		LAPS_Audit::activate();
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		LAPS_Audit::deactivate();
	}

	/**
	 * Register modules.
	 */
	public function boot() {
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
		LAPS_Audit::instance()->boot();
		if ( $this->enabled( 'related_filter' ) ) {
			$related = new LAPS_Related();
			$related->boot();
		}
		if ( is_admin() ) {
			LAPS_Admin::instance()->boot();
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
		if ( empty( $_POST['laps_save'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'laps_save' );
		$next = array(
			'auto_repair'    => empty( $_POST['auto_repair'] ) ? 0 : 1,
			'related_filter' => empty( $_POST['related_filter'] ) ? 0 : 1,
			'batch_size'     => isset( $_POST['batch_size'] ) ? (int) $_POST['batch_size'] : 25,
		);
		update_option( self::OPTION, wp_parse_args( $next, self::defaults() ), false );
		add_settings_error(
			'laps',
			'saved',
			__( 'Product Scout settings saved.', 'luxe-affiliate-product-scout' ),
			'updated'
		);
	}
}
