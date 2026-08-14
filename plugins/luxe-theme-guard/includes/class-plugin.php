<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared options. Flatsome parent auto-update defaults on.
 */
class Luxe_Theme_Guard_Plugin {

	const OPTION = 'luxe_theme_guard_settings';

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
			'auto_update'      => 1,
			'process_learning' => 1,
			'auto_purge'       => 1,
		);
	}

	/**
	 * Store defaults. Do not write theme files on activate.
	 */
	public static function activate() {
		$settings = get_option( self::OPTION );
		if ( ! is_array( $settings ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		} else {
			update_option( self::OPTION, wp_parse_args( $settings, self::defaults() ), false );
		}
		Luxe_Theme_Guard_Updater::activate();
	}

	/**
	 * Clear cron only.
	 */
	public static function deactivate() {
		Luxe_Theme_Guard_Updater::deactivate();
	}

	/**
	 * Register modules.
	 */
	public function boot() {
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
		Luxe_Theme_Guard_Updater::instance()->boot();
		if ( is_admin() ) {
			Luxe_Theme_Guard_Admin::instance()->boot();
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
	 * Persist checkboxes.
	 */
	public function maybe_save() {
		if ( empty( $_POST['luxe_theme_guard_save'] ) ) {
			return;
		}
		if ( ! current_user_can( 'update_themes' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_theme_guard_save' );
		$next = array();
		foreach ( self::defaults() as $key => $default ) {
			$next[ $key ] = empty( $_POST[ $key ] ) ? 0 : 1;
		}
		update_option( self::OPTION, $next, false );
		if ( ! empty( $next['auto_update'] ) && ! empty( $next['process_learning'] ) ) {
			Luxe_Theme_Guard_Updater::activate();
		} else {
			Luxe_Theme_Guard_Updater::deactivate();
		}
		add_settings_error(
			'luxe_theme_guard',
			'saved',
			__( 'Theme Guard settings saved. Open the green signal board below.', 'luxe-theme-guard' ),
			'updated'
		);
	}
}
