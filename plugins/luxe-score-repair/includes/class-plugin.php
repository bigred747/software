<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared options. Safe public-output repairs default on.
 */
class Luxe_Score_Repair_Plugin {

	const OPTION = 'luxe_score_repair_settings';

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
			'fix_titles'            => 1,
			'fix_robots_txt'        => 1,
			'noindex_utility'       => 1,
			'fix_schema'            => 1,
			'fix_open_graph'        => 1,
			'clean_content'         => 1,
			'clean_titles'          => 1,
			'fix_headers'           => 1,
			'repair_known_404s'     => 1,
			'buffer_html'           => 1,
			'image_alts'            => 1,
			'hide_generator'        => 1,
			'affiliate_disclosure'  => 1,
			'process_learning'      => 1,
			'auto_purge'            => 1,
			'fix_sitemaps'          => 1,
		);
	}

	/**
	 * Store defaults. Do not write content.
	 */
	public static function activate() {
		$settings = get_option( self::OPTION );
		if ( ! is_array( $settings ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		} else {
			update_option( self::OPTION, wp_parse_args( $settings, self::defaults() ), false );
		}
		add_option( 'luxe_score_repair_activated_at', time(), '', false );
		Luxe_Score_Repair_Learning::activate();
	}

	/**
	 * Deactivation leaves options so a re-activate keeps choices.
	 */
	public static function deactivate() {
		Luxe_Score_Repair_Learning::deactivate();
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	/**
	 * Register runtime modules.
	 */
	public function boot() {
		add_action( 'admin_init', array( $this, 'maybe_save_settings' ) );

		Luxe_Score_Repair_SEO::instance()->boot();
		Luxe_Score_Repair_Schema::instance()->boot();
		Luxe_Score_Repair_Robots::instance()->boot();
		Luxe_Score_Repair_Content::instance()->boot();
		Luxe_Score_Repair_Headers::instance()->boot();
		Luxe_Score_Repair_Redirects::instance()->boot();
		Luxe_Score_Repair_Buffer::instance()->boot();
		Luxe_Score_Repair_Sitemaps::instance()->boot();
		Luxe_Score_Repair_Learning::instance()->boot();

		if ( is_admin() ) {
			Luxe_Score_Repair_Admin::instance()->boot();
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
		if ( empty( $_POST['luxe_score_repair_save'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_score_repair_save' );

		$next = array();
		foreach ( self::defaults() as $key => $default ) {
			$next[ $key ] = empty( $_POST[ $key ] ) ? 0 : 1;
		}
		update_option( self::OPTION, $next, false );
		Luxe_Score_Repair_Robots::heal_file();
		Luxe_Score_Repair_Learning::purge_caches();
		add_settings_error(
			'luxe_score_repair',
			'saved',
			__( 'Score Repair settings saved. robots.txt healed and caches purged. Open the green signal board below.', 'luxe-score-repair' ),
			'updated'
		);
	}

	/**
	 * Brand homepage title. 50–60 characters.
	 *
	 * @return string
	 */
	public static function home_title() {
		return 'LuxeTrendsetters | Luxury Tech, Watches & Drones';
	}

	/**
	 * Brand homepage meta description. 140–160 characters.
	 *
	 * @return string
	 */
	public static function home_description() {
		return 'Curated luxury tech, watches, drones, audio, and smart gear. Compare premium Amazon picks with clear buyer guides from LuxeTrendsetters.';
	}

	/**
	 * @return string
	 */
	public static function brand_name() {
		$name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		return $name ? $name : 'LuxeTrendsetters';
	}

	/**
	 * Best available brand image (site icon, then custom logo).
	 *
	 * @return string
	 */
	public static function brand_image() {
		$icon = get_site_icon_url( 512 );
		if ( is_string( $icon ) && $icon ) {
			return $icon;
		}
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( is_string( $url ) && $url ) {
				return $url;
			}
		}
		return '';
	}

	/**
	 * Utility / junk paths that must not rank.
	 *
	 * @return string[]
	 */
	public static function noindex_slugs() {
		return array(
			'cart',
			'checkout',
			'my-account',
			'wishlist',
			'wish-list',
			'client-portal',
			'test-blog-page',
			'thank-you-appointment',
			'thank-you-for-contacting-us',
		);
	}

	/**
	 * Current request path without query string, leading slash, no trailing slash except root.
	 *
	 * @return string
	 */
	public static function request_path() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			$path = '/';
		}
		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( is_string( $home_path ) && '/' !== $home_path && 0 === strpos( $path, rtrim( $home_path, '/' ) ) ) {
			$path = substr( $path, strlen( rtrim( $home_path, '/' ) ) );
			if ( ! is_string( $path ) || '' === $path ) {
				$path = '/';
			}
		}
		if ( '' === $path || '/' !== $path[0] ) {
			$path = '/' . ltrim( $path, '/' );
		}
		if ( '/' === $path ) {
			return '/';
		}
		return untrailingslashit( $path );
	}

	/**
	 * Whether this request is a public HTML document we may rewrite.
	 *
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
}
