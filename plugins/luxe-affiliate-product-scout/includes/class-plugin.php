<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LAPS_Plugin' ) ) {
	return;
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
			'learning_24_7'   => 1,
			'auto_repair'     => 1,
			'related_filter'  => 0,
			'batch_size'      => 25,
			'ig_scan_24_7'    => 1,
			'ig_urls'         => '',
			'ig_notes'        => '',
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
		if ( class_exists( 'LAPS_Audit' ) ) {
			LAPS_Audit::activate();
		}
		if ( class_exists( 'LAPS_Learning' ) ) {
			LAPS_Learning::activate();
		}
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		LAPS_Audit::deactivate();
		if ( class_exists( 'LAPS_Learning' ) ) {
			LAPS_Learning::deactivate();
		}
	}

	/**
	 * Register modules.
	 */
	public function boot() {
		add_action( 'admin_notices', array( $this, 'woocommerce_notice' ) );
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
		if ( is_admin() ) {
			LAPS_Admin::instance()->boot();
		}
		if ( ! function_exists( 'laps_woocommerce_ready' ) || ! laps_woocommerce_ready() ) {
			return;
		}
		LAPS_Audit::instance()->boot();
		if ( class_exists( 'LAPS_Learning' ) ) {
			LAPS_Learning::instance()->boot();
		}
		if ( class_exists( 'LAPS_Instagram' ) && is_admin() ) {
			LAPS_Instagram::instance()->boot();
		}
		if ( $this->enabled( 'related_filter' ) ) {
			$related = new LAPS_Related();
			$related->boot();
		}
	}

	/**
	 * WordPress admin notice when WooCommerce is missing.
	 */
	public function woocommerce_notice() {
		if ( function_exists( 'laps_woocommerce_ready' ) && laps_woocommerce_ready() ) {
			return;
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Luxe Affiliate Product Scout needs WooCommerce to be installed and active.', 'luxe-affiliate-product-scout' );
		echo '</p></div>';
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
		if ( 'learning_24_7' === $key ) {
			return ! empty( $settings['learning_24_7'] ) || ! empty( $settings['auto_repair'] );
		}
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
			'learning_24_7'  => empty( $_POST['learning_24_7'] ) ? 0 : 1,
			'auto_repair'    => empty( $_POST['learning_24_7'] ) ? 0 : 1,
			'related_filter' => empty( $_POST['related_filter'] ) ? 0 : 1,
			'batch_size'     => isset( $_POST['batch_size'] ) ? (int) $_POST['batch_size'] : 25,
			'ig_scan_24_7'   => empty( $_POST['ig_scan_24_7'] ) ? 0 : 1,
			'ig_urls'        => isset( $_POST['ig_urls'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['ig_urls'] ) ) : '',
			'ig_notes'       => isset( $_POST['ig_notes'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['ig_notes'] ) ) : '',
		);
		update_option( self::OPTION, wp_parse_args( $next, self::defaults() ), false );
		if ( class_exists( 'LAPS_Learning' ) ) {
			if ( ! empty( $next['learning_24_7'] ) ) {
				LAPS_Learning::activate();
			} else {
				LAPS_Learning::deactivate();
			}
		}
		if ( class_exists( 'LAPS_Instagram' ) ) {
			$urls = LAPS_Instagram::extract_urls( $next['ig_urls'] . "\n" . $next['ig_notes'] );
			update_option( LAPS_Instagram::OPTION_QUEUE, $urls, false );
		}
		add_settings_error(
			'laps',
			'saved',
			__( 'Product Scout settings saved.', 'luxe-affiliate-product-scout' ),
			'updated'
		);
	}
}
