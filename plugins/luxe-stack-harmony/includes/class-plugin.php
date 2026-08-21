<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared options. Conductor only. Never writes posts.
 */
class Luxe_Stack_Harmony_Plugin {

	const OPTION = 'luxe_stack_harmony_settings';
	const TAG    = 'luxetrendse0f-20';

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
			'cancel_duplicates' => 1,
			'mute_park_hooks'   => 1,
			'process_learning'  => 1,
			'auto_purge'        => 1,
		);
	}

	/**
	 * Store defaults and run one cancel pass.
	 */
	public static function activate() {
		$settings = get_option( self::OPTION );
		if ( ! is_array( $settings ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		} else {
			update_option( self::OPTION, wp_parse_args( $settings, self::defaults() ), false );
		}
		add_option( 'luxe_stack_harmony_activated_at', time(), '', false );
		Luxe_Stack_Harmony_Conductor::cancel_now();
		Luxe_Stack_Harmony_Learning::activate();
	}

	/**
	 * Deactivation leaves options.
	 */
	public static function deactivate() {
		Luxe_Stack_Harmony_Learning::deactivate();
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	/**
	 * Register runtime modules.
	 */
	public function boot() {
		add_action( 'admin_init', array( $this, 'maybe_save_settings' ) );

		Luxe_Stack_Harmony_Conductor::instance()->boot();
		Luxe_Stack_Harmony_Learning::instance()->boot();

		if ( is_admin() ) {
			Luxe_Stack_Harmony_Admin::instance()->boot();
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
	 * @param string $key Setting key.
	 * @return bool
	 */
	public function enabled( $key ) {
		$settings = $this->settings();
		return ! empty( $settings[ $key ] );
	}

	/**
	 * Persist admin checkboxes.
	 */
	public function maybe_save_settings() {
		if ( empty( $_POST['luxe_stack_harmony_save'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_stack_harmony_save' );

		$next = array();
		foreach ( self::defaults() as $key => $default ) {
			unset( $default );
			$next[ $key ] = empty( $_POST[ $key ] ) ? 0 : 1;
		}
		update_option( self::OPTION, $next, false );
		if ( ! empty( $next['cancel_duplicates'] ) ) {
			Luxe_Stack_Harmony_Conductor::cancel_now();
		}
		Luxe_Stack_Harmony_Learning::purge_caches();
		add_settings_error(
			'luxe_stack_harmony',
			'saved',
			__( 'Harmony settings saved. Duplicate PARK engines cancelled. Caches purged.', 'luxe-stack-harmony' ),
			'updated'
		);
	}

	/**
	 * @param int $green Green.
	 * @param int $total Total.
	 * @param int $yellow Yellow.
	 * @param int $red Red.
	 * @return int
	 */
	public static function score_10( $green, $total, $yellow, $red ) {
		$total  = (int) $total;
		$green  = (int) $green;
		$yellow = (int) $yellow;
		$red    = (int) $red;
		if ( $total < 1 ) {
			return 0;
		}
		if ( $red > 0 ) {
			return max( 1, (int) round( 10 * $green / $total ) );
		}
		unset( $yellow );
		return 10;
	}

	/**
	 * @param int $red Red.
	 * @param int $yellow Yellow.
	 * @return string
	 */
	public static function band( $red, $yellow ) {
		if ( (int) $red > 0 ) {
			return 'needs-attention';
		}
		if ( (int) $yellow > 0 ) {
			return '10-ready';
		}
		return '10/10';
	}

	/**
	 * @return string
	 */
	public static function amazon_tag() {
		return self::TAG;
	}
}
