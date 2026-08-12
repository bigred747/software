<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exact-path 301s for known dead URLs only. No fuzzy matching. No Rank Math writes.
 */
class Luxe_Score_Repair_Redirects {

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
	 * Register redirect hook.
	 */
	public function boot() {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 8 );
	}

	/**
	 * @return array<string,string>
	 */
	public static function map() {
		return array(
			'/blog'                              => '/blogs/',
			'/blog/'                             => '/blogs/',
			'/high-end-blogging-luxury-brands'   => '/blogs/',
			'/high-end-blogging-luxury-brands/'  => '/blogs/',
			'/contact'                           => '/welcome-to-our-contact-us-page/',
			'/contact/'                          => '/welcome-to-our-contact-us-page/',
			'/privacy'                           => '/privacy-policy/',
			'/privacy/'                          => '/privacy-policy/',
			'/terms'                             => '/terms-and-conditions/',
			'/terms/'                            => '/terms-and-conditions/',
		);
	}

	/**
	 * 301 only the exact map. Never invents destinations.
	 */
	public function maybe_redirect() {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'repair_known_404s' ) ) {
			return;
		}
		$path = Luxe_Score_Repair_Plugin::request_path();
		$map  = self::map();
		$keys = array( $path, trailingslashit( $path ) );
		$dest = '';
		foreach ( $keys as $key ) {
			if ( isset( $map[ $key ] ) ) {
				$dest = $map[ $key ];
				break;
			}
		}
		if ( '' === $dest ) {
			return;
		}
		$target = home_url( $dest );
		$here   = home_url( trailingslashit( $path ) );
		if ( untrailingslashit( $target ) === untrailingslashit( $here ) ) {
			return;
		}
		wp_safe_redirect( $target, 301 );
		exit;
	}
}
