<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computer and mobile CSS. Public HTML is not branched by user agent.
 */
class Luxe_Google_Presence_Device {

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
	 * Register front styles and a late viewport fill.
	 */
	public function boot() {
		add_action( 'wp_enqueue_scripts', array( $this, 'styles' ), 20 );
		add_action( 'wp_head', array( $this, 'verification' ), 2 );
		add_action( 'template_redirect', array( $this, 'start_buffer' ), 1 );
	}

	/**
	 * Enqueue the shared stylesheet.
	 */
	public function styles() {
		if ( is_admin() || ! Luxe_Google_Presence_Plugin::instance()->enabled( 'device_css' ) ) {
			return;
		}
		wp_enqueue_style(
			'luxe-google-presence-device',
			LUXE_GOOGLE_PRESENCE_URL . 'assets/device.css',
			array(),
			LUXE_GOOGLE_PRESENCE_VERSION
		);
	}

	/**
	 * Print a Search Console verification meta when a safe token is saved.
	 */
	public function verification() {
		if ( is_admin() ) {
			return;
		}
		$settings = Luxe_Google_Presence_Plugin::instance()->settings();
		$token    = isset( $settings['verification'] ) ? (string) $settings['verification'] : '';
		$meta     = Luxe_Google_Presence_Anchors::verification_tag( $token );
		if ( '' === $meta ) {
			return;
		}
		echo $meta . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Fill a missing viewport after Score Repair starts its own buffer.
	 */
	public function start_buffer() {
		if ( ! Luxe_Google_Presence_Plugin::instance()->enabled( 'qualify_links' ) && ! Luxe_Google_Presence_Plugin::instance()->enabled( 'device_css' ) ) {
			return;
		}
		if ( ! Luxe_Google_Presence_Plugin::is_public_html_request() ) {
			return;
		}
		if ( headers_sent() ) {
			return;
		}
		ob_start( array( $this, 'rewrite' ) );
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public function rewrite( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) < 32 || false === stripos( $html, '<html' ) ) {
			return is_string( $html ) ? $html : '';
		}
		$plugin = Luxe_Google_Presence_Plugin::instance();
		if ( $plugin->enabled( 'device_css' ) ) {
			$html = Luxe_Google_Presence_Anchors::ensure_viewport( $html );
		}
		if ( $plugin->enabled( 'qualify_links' ) ) {
			$html = Luxe_Google_Presence_Anchors::qualify( $html );
		}
		return $html;
	}
}
