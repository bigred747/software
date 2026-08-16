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
			'auto_update'           => 1,
			'process_learning'      => 1,
			'learning_24_7'         => 1,
			'auto_purge'            => 1,
			'amazon_ai'             => 1,
			'amazon_tag'            => 'luxetrendse0f-20',
			'amazon_client_id'      => '',
			'amazon_client_secret'  => '',
			'amazon_marketplace'    => 'www.amazon.com',
			'flatsome_purchase_code'=> '',
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
		Luxe_Theme_Guard_Learning::instance()->boot();
		Luxe_Theme_Guard_Amazon::instance()->boot();
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
		if ( 'process_learning' === $key || 'learning_24_7' === $key ) {
			return ! empty( $settings['process_learning'] ) || ! empty( $settings['learning_24_7'] );
		}
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
		$prev = $this->settings();
		$next = array(
			'auto_update'      => empty( $_POST['auto_update'] ) ? 0 : 1,
			'process_learning' => empty( $_POST['process_learning'] ) ? 0 : 1,
			'auto_purge'       => empty( $_POST['auto_purge'] ) ? 0 : 1,
			'amazon_ai'        => empty( $_POST['amazon_ai'] ) ? 0 : 1,
		);
		$next['learning_24_7']    = $next['process_learning'];
		$next['process_learning'] = $next['learning_24_7'];
		$tag = isset( $_POST['amazon_tag'] ) ? sanitize_text_field( wp_unslash( $_POST['amazon_tag'] ) ) : ( isset( $prev['amazon_tag'] ) ? $prev['amazon_tag'] : Luxe_Theme_Guard_Amazon::TAG );
		$next['amazon_tag']         = Luxe_Theme_Guard_Amazon::sanitize_tag( $tag );
		$next['amazon_marketplace'] = Luxe_Theme_Guard_Amazon::MARKET;
		$next['amazon_client_id']   = isset( $_POST['amazon_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['amazon_client_id'] ) ) : ( isset( $prev['amazon_client_id'] ) ? $prev['amazon_client_id'] : '' );
		$secret = isset( $_POST['amazon_client_secret'] ) ? trim( (string) wp_unslash( $_POST['amazon_client_secret'] ) ) : '';
		$next['amazon_client_secret'] = ( '' !== $secret ) ? $secret : ( isset( $prev['amazon_client_secret'] ) ? $prev['amazon_client_secret'] : '' );
		$code = isset( $_POST['flatsome_purchase_code'] ) ? sanitize_text_field( wp_unslash( $_POST['flatsome_purchase_code'] ) ) : '';
		$next['flatsome_purchase_code'] = isset( $prev['flatsome_purchase_code'] ) ? $prev['flatsome_purchase_code'] : '';
		if ( '' !== $code ) {
			$clean = Luxe_Theme_Guard_Signals::sanitize_purchase_code( $code );
			if ( $clean ) {
				$next['flatsome_purchase_code'] = $clean;
			} else {
				add_settings_error(
					'luxe_theme_guard',
					'bad-code',
					__( 'That purchase code is not a ThemeForest UUID. Example: bg91c1z0-bdcf-457f-a3e5-4e0762896c2d. Official Flatsome was not changed.', 'luxe-theme-guard' ),
					'error'
				);
			}
		}
		update_option( self::OPTION, $next, false );
		if ( ! empty( $next['flatsome_purchase_code'] ) ) {
			Luxe_Theme_Guard_Updater::apply_purchase_code( $next['flatsome_purchase_code'] );
		}
		if ( ! empty( $next['auto_update'] ) ) {
			Luxe_Theme_Guard_Updater::arm_auto();
		} else {
			Luxe_Theme_Guard_Updater::disarm_auto();
		}
		if ( ! empty( $next['learning_24_7'] ) ) {
			Luxe_Theme_Guard_Learning::activate();
		} else {
			Luxe_Theme_Guard_Learning::deactivate();
		}
		if ( ! empty( $next['auto_update'] ) && ! empty( $next['flatsome_purchase_code'] ) ) {
			delete_transient( Luxe_Theme_Guard_Updater::LOCK );
			Luxe_Theme_Guard_Updater::instance()->run( true );
		}
		add_settings_error(
			'luxe_theme_guard',
			'saved',
			__( 'Theme Guard settings saved. Official Flatsome auto-update runs every 5 minutes when WordPress has a licensed package.', 'luxe-theme-guard' ),
			'updated'
		);
	}
}
