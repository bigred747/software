<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exact-path 301s. Runs on init so Rank Math cannot hop /blog/ first.
 * Learned paths are exact only and must land on an allowlisted destination.
 */
class Luxe_Score_Repair_Redirects {

	const LEARNED = 'luxe_score_repair_learned_redirects';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var bool
	 */
	private $did = false;

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
	 * Register redirect hooks before Rank Math.
	 */
	public function boot() {
		add_action( 'init', array( $this, 'maybe_redirect' ), 0 );
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 0 );
	}

	/**
	 * Built-in exact map.
	 *
	 * @return array<string,string>
	 */
	public static function base_map() {
		return array(
			'/blog'                             => '/blogs/',
			'/blog/'                            => '/blogs/',
			'/high-end-blogging-luxury-brands'  => '/blogs/',
			'/high-end-blogging-luxury-brands/' => '/blogs/',
			'/contact'                          => '/welcome-to-our-contact-us-page/',
			'/contact/'                         => '/welcome-to-our-contact-us-page/',
			'/privacy'                          => '/privacy-policy/',
			'/privacy/'                         => '/privacy-policy/',
			'/terms'                            => '/terms-and-conditions/',
			'/terms/'                           => '/terms-and-conditions/',
		);
	}

	/**
	 * Destinations learning is allowed to use.
	 *
	 * @return string[]
	 */
	public static function allowlist() {
		return array(
			'/blogs/',
			'/privacy-policy/',
			'/terms-and-conditions/',
			'/welcome-to-our-contact-us-page/',
			'/',
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function map() {
		$learned = get_option( self::LEARNED, array() );
		if ( ! is_array( $learned ) ) {
			$learned = array();
		}
		return array_merge( self::base_map(), $learned );
	}

	/**
	 * Store one exact learned hop. Never Amazon, never product permalinks.
	 *
	 * @param string $from Source path.
	 * @param string $to   Destination path.
	 * @return bool
	 */
	public static function learn( $from, $to ) {
		$from = self::normalize_path( $from );
		$to   = self::normalize_path( $to );
		if ( '' === $from || '' === $to ) {
			return false;
		}
		if ( ! in_array( trailingslashit( $to ), self::allowlist(), true ) && '/' !== $to ) {
			return false;
		}
		if ( false !== strpos( $from, '/product/' ) || false !== strpos( $from, 'amazon' ) ) {
			return false;
		}
		$learned = get_option( self::LEARNED, array() );
		if ( ! is_array( $learned ) ) {
			$learned = array();
		}
		if ( count( $learned ) >= 40 ) {
			return false;
		}
		$learned[ $from ]                    = trailingslashit( $to );
		$learned[ trailingslashit( $from ) ] = trailingslashit( $to );
		update_option( self::LEARNED, $learned, false );
		return true;
	}

	/**
	 * 301 only the exact map.
	 */
	public function maybe_redirect() {
		if ( $this->did || is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'repair_known_404s' ) ) {
			return;
		}
		$path = Luxe_Score_Repair_Plugin::request_path();
		if ( '/robots.txt' === $path ) {
			return;
		}
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
		$this->did = true;
		wp_safe_redirect( $target, 301 );
		exit;
	}

	/**
	 * @param string $path Path.
	 * @return string
	 */
	private static function normalize_path( $path ) {
		$path = (string) $path;
		if ( 0 === strpos( $path, 'http' ) ) {
			$parsed = wp_parse_url( $path, PHP_URL_PATH );
			$path   = is_string( $parsed ) ? $parsed : '';
		}
		$path = '/' . ltrim( $path, '/' );
		if ( '/' === $path ) {
			return '/';
		}
		return untrailingslashit( $path );
	}
}
