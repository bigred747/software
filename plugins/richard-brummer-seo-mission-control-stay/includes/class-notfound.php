<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 404 logging and a helpful recovery screen. No public redirects unless opted in.
 */
class RBSMC_Stay_NotFound {

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
	 * Register 404 hooks.
	 */
	public function boot() {
		add_action( 'init', array( $this, 'serve_bot_placeholders' ), 0 );
		add_action( 'wp', array( $this, 'maybe_redirect_legacy' ), 9 );
		add_action( 'wp', array( $this, 'on_wp' ), 10 );
		add_filter( '404_template', array( $this, 'template' ) );
	}

	/**
	 * Exact-path map of deleted content to live catalog pages. No fuzzy matching.
	 *
	 * @return array<string,string>
	 */
	public static function known_dead_urls() {
		return array(
			'/the-apple-watch-the-perfect-blend-of-technology-and-fashion/' => '/product-tag/apple/',
			'/the-best-smartwatches-for-a-luxurious-lifestyle/'             => '/product-category/wearables/',
			'/the-best-drones-for-capturing-stunning-aerial-footage/'       => '/product-tag/dji/',
			'/the-best-smart-home-gadgets-for-a-luxurious-lifestyle/'       => '/shop/',
			'/shopping-guide-for-top-products/'                            => '/shop/',
			'/product-category/computers/'                                 => '/shop/',
		);
	}

	/**
	 * Answer bot probes that currently 404: /meta.json and /.well-known/agents.js.
	 */
	public function serve_bot_placeholders() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( ! RBSMC_Stay_Plugin::instance()->enabled( 'serve_bot_placeholders' ) ) {
			return;
		}
		$path = $this->request_path();
		if ( '/meta.json' === $path ) {
			if ( is_file( ABSPATH . 'meta.json' ) ) {
				return;
			}
			status_header( 200 );
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Cache-Control: public, max-age=3600' );
			echo wp_json_encode(
				array(
					'name'        => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
					'url'         => home_url( '/' ),
					'description' => wp_specialchars_decode( get_bloginfo( 'description' ), ENT_QUOTES ),
				)
			);
			exit;
		}
		if ( '/.well-known/agents.js' === $path ) {
			if ( is_file( ABSPATH . '.well-known/agents.js' ) ) {
				return;
			}
			status_header( 200 );
			header( 'Content-Type: application/javascript; charset=utf-8' );
			header( 'Cache-Control: public, max-age=3600' );
			echo "/* LuxeTrendsetters agent discovery placeholder. Publication status unchanged. */\n";
			exit;
		}
	}

	/**
	 * 301 only the known deleted URLs. Does not invent Rank Math rules or change post status.
	 */
	public function maybe_redirect_legacy() {
		if ( is_admin() || wp_doing_ajax() || ! is_404() ) {
			return;
		}
		if ( ! RBSMC_Stay_Plugin::instance()->enabled( 'repair_known_dead_urls' ) ) {
			return;
		}
		$path = $this->normalized_content_path();
		$map  = self::known_dead_urls();
		if ( ! isset( $map[ $path ] ) ) {
			return;
		}
		$target = $map[ $path ];
		wp_safe_redirect( home_url( $target ), 301 );
		exit;
	}

	/**
	 * Current request path without query string.
	 *
	 * @return string
	 */
	private function request_path() {
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
		if ( preg_match( '/\.[a-z0-9]{1,8}$/i', $path ) ) {
			return $path;
		}
		return untrailingslashit( $path );
	}

	/**
	 * Content URL path with trailing slash for map lookup.
	 *
	 * @return string
	 */
	private function normalized_content_path() {
		$path = $this->request_path();
		if ( '/' === $path ) {
			return '/';
		}
		return trailingslashit( $path );
	}

	/**
	 * Log 404 paths.
	 */
	public function on_wp() {
		if ( is_admin() || ! is_404() ) {
			return;
		}
		$plugin = RBSMC_Stay_Plugin::instance();
		if ( $plugin->enabled( 'log_404' ) ) {
			$this->log_current();
		}
	}

	/**
	 * Optional plugin 404 template.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public function template( $template ) {
		if ( ! RBSMC_Stay_Plugin::instance()->enabled( 'helpful_404' ) ) {
			return $template;
		}
		$custom = RBSMC_STAY_DIR . 'templates/404.php';
		return file_exists( $custom ) ? $custom : $template;
	}

	/**
	 * Store a bounded 404 log.
	 */
	private function log_current() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$uri = strtok( $uri, '?' );
		$uri = substr( $uri, 0, 180 );
		if ( '' === $uri || 0 === strpos( $uri, '/wp-admin' ) || 0 === strpos( $uri, '/wp-json' ) || 0 === strpos( $uri, '/wp-content/' ) || 0 === strpos( $uri, '/wp-includes/' ) ) {
			return;
		}
		$request_path = $this->request_path();
		$normalized   = $this->normalized_content_path();
		if ( '/meta.json' === $request_path || '/.well-known/agents.js' === $request_path ) {
			return;
		}
		$map = self::known_dead_urls();
		if ( isset( $map[ $normalized ] ) ) {
			return;
		}
		$log = get_option( RBSMC_Stay_Plugin::OPTION_404, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		if ( ! isset( $log[ $uri ] ) ) {
			$log[ $uri ] = array(
				'hits' => 0,
				'last' => 0,
			);
		}
		$log[ $uri ]['hits'] = absint( $log[ $uri ]['hits'] ) + 1;
		$log[ $uri ]['last'] = time();
		if ( count( $log ) > 200 ) {
			uasort(
				$log,
				function ( $a, $b ) {
					return $b['hits'] - $a['hits'];
				}
			);
			$log = array_slice( $log, 0, 200, true );
		}
		update_option( RBSMC_Stay_Plugin::OPTION_404, $log, false );
	}
}
