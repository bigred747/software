<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared options. Public HTML polish only. Never writes posts.
 */
class Luxe_Public_Finish_Plugin {

	const OPTION = 'luxe_public_finish_settings';
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
			'polish_titles'     => 1,
			'unique_listings'   => 1,
			'disclosure_once'   => 1,
			'schema_org_once'   => 1,
			'buffer_html'       => 1,
			'process_learning'  => 1,
			'auto_purge'        => 1,
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
		add_option( 'luxe_public_finish_activated_at', time(), '', false );
		Luxe_Public_Finish_Learning::activate();
	}

	/**
	 * Deactivation leaves options so a re-activate keeps choices.
	 */
	public static function deactivate() {
		Luxe_Public_Finish_Learning::deactivate();
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	/**
	 * Register runtime modules.
	 */
	public function boot() {
		add_action( 'admin_init', array( $this, 'maybe_save_settings' ) );

		Luxe_Public_Finish_Titles::instance()->boot();
		Luxe_Public_Finish_Buffer::instance()->boot();
		Luxe_Public_Finish_Learning::instance()->boot();

		if ( is_admin() ) {
			Luxe_Public_Finish_Admin::instance()->boot();
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
		if ( empty( $_POST['luxe_public_finish_save'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_public_finish_save' );

		$next = array();
		foreach ( self::defaults() as $key => $default ) {
			unset( $default );
			$next[ $key ] = empty( $_POST[ $key ] ) ? 0 : 1;
		}
		update_option( self::OPTION, $next, false );
		Luxe_Public_Finish_Learning::purge_caches();
		add_settings_error(
			'luxe_public_finish',
			'saved',
			__( 'Finish settings saved. Caches purged. Open the green signal board below.', 'luxe-public-finish' ),
			'updated'
		);
	}

	/**
	 * @return string
	 */
	public static function brand_name() {
		if ( function_exists( 'get_bloginfo' ) ) {
			$name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			if ( $name ) {
				return $name;
			}
		}
		return 'LuxeTrendsetters';
	}

	/**
	 * Public HTML only. Never admin, cron, AJAX, REST, or feeds.
	 *
	 * @return bool
	 */
	public static function is_public_html_request() {
		if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			return false;
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return false;
		}
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri = (string) $_SERVER['REQUEST_URI'];
			if ( false !== strpos( $uri, '/wp-json/' ) || false !== strpos( $uri, 'admin-ajax.php' ) ) {
				return false;
			}
		}
		return true;
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
	 * Locked Associates tag. Never rewritten on the public site.
	 *
	 * @return string
	 */
	public static function amazon_tag() {
		return self::TAG;
	}
}
