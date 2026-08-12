<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public cache and security headers. Does not alter cookies for carts.
 */
class Luxe_Score_Repair_Headers {

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
	 * Register header filters.
	 */
	public function boot() {
		add_filter( 'wp_headers', array( $this, 'wp_headers' ), 999 );
		add_action( 'send_headers', array( $this, 'send_headers' ), 20 );
		add_filter( 'nocache_headers', array( $this, 'nocache_headers' ), 999 );
	}

	/**
	 * @param array $headers Headers.
	 * @return array
	 */
	public function wp_headers( $headers ) {
		if ( ! is_array( $headers ) ) {
			return $headers;
		}
		$plugin = Luxe_Score_Repair_Plugin::instance();
		if ( $plugin->enabled( 'noindex_utility' ) && Luxe_Score_Repair_SEO::instance()->should_noindex() ) {
			$headers['X-Robots-Tag'] = 'noindex, follow';
		}
		if ( ! $plugin->enabled( 'fix_headers' ) ) {
			return $headers;
		}
		if ( ! $this->is_cacheable_public() ) {
			return $headers;
		}
		$headers['Cache-Control'] = 'public, max-age=600, s-maxage=600';
		unset( $headers['Expires'], $headers['Pragma'] );
		if ( is_ssl() ) {
			$headers['Strict-Transport-Security'] = 'max-age=15552000; includeSubDomains';
		}
		if ( empty( $headers['X-Content-Type-Options'] ) ) {
			$headers['X-Content-Type-Options'] = 'nosniff';
		}
		if ( empty( $headers['Referrer-Policy'] ) ) {
			$headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
		}
		if ( empty( $headers['X-Frame-Options'] ) ) {
			$headers['X-Frame-Options'] = 'SAMEORIGIN';
		}
		return $headers;
	}

	/**
	 * Replace leftover session no-cache headers and stamp HSTS / X-Robots-Tag.
	 */
	public function send_headers() {
		if ( is_admin() || headers_sent() ) {
			return;
		}
		$plugin = Luxe_Score_Repair_Plugin::instance();
		if ( $plugin->enabled( 'noindex_utility' ) && Luxe_Score_Repair_SEO::instance()->should_noindex() ) {
			header( 'X-Robots-Tag: noindex, follow', true );
		}
		if ( ! $plugin->enabled( 'fix_headers' ) ) {
			return;
		}
		if ( $this->is_cacheable_public() ) {
			if ( function_exists( 'header_remove' ) ) {
				header_remove( 'Expires' );
				header_remove( 'Pragma' );
			}
			header( 'Cache-Control: public, max-age=600, s-maxage=600', true );
		}
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=15552000; includeSubDomains', true );
		}
	}

	/**
	 * Stop WordPress from forcing no-store on public catalog pages.
	 *
	 * @param array $headers Headers.
	 * @return array
	 */
	public function nocache_headers( $headers ) {
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_headers' ) ) {
			return $headers;
		}
		if ( ! $this->is_cacheable_public() ) {
			return $headers;
		}
		return array(
			'Cache-Control' => 'public, max-age=600, s-maxage=600',
		);
	}

	/**
	 * @return bool
	 */
	private function is_cacheable_public() {
		if ( is_admin() || is_user_logged_in() || wp_doing_ajax() ) {
			return false;
		}
		if ( $_POST ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return false;
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return false;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return false;
		}
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return false;
		}
		if ( is_preview() ) {
			return false;
		}
		return true;
	}
}
