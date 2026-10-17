<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings and boot. Public page views do not flush caches or write posts.
 */
class Luxe_Google_Presence_Plugin {

	const OPTION = 'luxe_google_presence_settings';

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
			'device_css'      => 1,
			'qualify_links'   => 1,
			'onsite_compare'  => 1,
			'visitor_repair'  => 1,
			'junk_redirect'   => 1,
			'public_cache'    => 1,
			'verification'    => '',
		);
	}

	/**
	 * Store defaults. Do not fetch Google and do not write content.
	 */
	public static function activate() {
		$settings = get_option( self::OPTION );
		if ( ! is_array( $settings ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		} else {
			update_option( self::OPTION, wp_parse_args( $settings, self::defaults() ), false );
		}
	}

	/**
	 * Leave options in place for the next activation.
	 */
	public static function deactivate() {
	}

	/**
	 * Register modules.
	 */
	public function boot() {
		Luxe_Google_Presence_Cache::instance()->boot();
		Luxe_Google_Presence_Device::instance()->boot();
		Luxe_Google_Presence_Engage::instance()->boot();
		if ( is_admin() ) {
			Luxe_Google_Presence_Admin::instance()->boot();
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
	 * @return bool
	 */
	public static function is_public_html_request() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( preg_match( '#/(robots\.txt|sitemap[^/]*\.xml|wp-cron\.php|xmlrpc\.php|wp-json/)#i', $uri ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Cart, checkout, account, and other utility paths stay free of the compare block.
	 *
	 * @return bool
	 */
	public static function is_utility_request() {
		$slugs = array(
			'cart',
			'checkout',
			'my-account',
			'wishlist',
			'wish-list',
			'client-portal',
			'test-blog-page',
		);
		if ( class_exists( 'Luxe_Score_Repair_Plugin' ) && method_exists( 'Luxe_Score_Repair_Plugin', 'noindex_slugs' ) ) {
			$shared = Luxe_Score_Repair_Plugin::noindex_slugs();
			if ( is_array( $shared ) && $shared ) {
				$slugs = $shared;
			}
		}
		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$only = wp_parse_url( $path, PHP_URL_PATH );
		$only = is_string( $only ) ? trim( $only, '/' ) : '';
		if ( '' === $only ) {
			return false;
		}
		$first = strtok( $only, '/' );
		return is_string( $first ) && in_array( $first, $slugs, true );
	}
}
