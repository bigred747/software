<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Anonymous catalog pages should be cacheable.
 * An empty PHP session cookie forces a LiteSpeed miss and a multi-second wait.
 * Cart, checkout, account, and a shopper who already has session data keep the cookie.
 */
class Luxe_Google_Presence_Cache {

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
	 * Close an empty session before the response is sent.
	 */
	public function boot() {
		$this->release();
		add_action( 'init', array( $this, 'release' ), 9999 );
		add_action( 'wp', array( $this, 'release' ), 9999 );
		add_action( 'send_headers', array( $this, 'release' ), 9999 );
		add_action( 'template_redirect', array( $this, 'release' ), 0 );
		add_action( 'shutdown', array( $this, 'release' ), 0 );
	}

	/**
	 * Drop a brand-new PHP session on a public read.
	 */
	public function release() {
		if ( ! Luxe_Google_Presence_Plugin::instance()->enabled( 'public_cache' ) ) {
			return;
		}
		$method  = isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET';
		$uri     = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$logged  = function_exists( 'is_user_logged_in' ) && is_user_logged_in();
		$cookie  = isset( $_SERVER['HTTP_COOKIE'] ) ? (string) $_SERVER['HTTP_COOKIE'] : '';
		$session = null;
		if ( function_exists( 'session_status' ) && PHP_SESSION_ACTIVE === session_status() && isset( $_SESSION ) && is_array( $_SESSION ) ) {
			$session = $_SESSION;
		} elseif ( ! self::client_has_session( $cookie ) ) {
			$session = array();
		}
		if ( ! self::can_release( $method, $uri, $logged, $cookie, $session ) ) {
			return;
		}
		if ( function_exists( 'session_status' ) && PHP_SESSION_NONE === session_status() ) {
			@ini_set( 'session.use_cookies', '0' );
			@ini_set( 'session.cache_limiter', '' );
		}
		if ( function_exists( 'session_status' ) && PHP_SESSION_ACTIVE === session_status() ) {
			session_write_close();
		}
		if ( function_exists( 'headers_sent' ) && ! headers_sent() && function_exists( 'headers_list' ) ) {
			self::replace_headers( headers_list() );
		}
	}

	/**
	 * A public read with no shopper session can be cached.
	 *
	 * @param string     $method Method.
	 * @param string     $uri Request URI.
	 * @param bool       $logged_in Logged in.
	 * @param string     $cookie_header Cookie header.
	 * @param array|null $session Session data. Null means unknown.
	 * @return bool
	 */
	public static function can_release( $method, $uri, $logged_in, $cookie_header, $session = null ) {
		$method = strtoupper( (string) $method );
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return false;
		}
		if ( $logged_in ) {
			return false;
		}
		if ( self::is_private_request( $uri ) ) {
			return false;
		}
		if ( self::client_has_session( $cookie_header ) && ( null === $session || ! self::session_is_empty( $session ) ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $cookie_header Cookie header.
	 * @return bool
	 */
	public static function client_has_session( $cookie_header ) {
		return false !== stripos( (string) $cookie_header, 'PHPSESSID=' );
	}

	/**
	 * @param mixed $session Session.
	 * @return bool
	 */
	public static function session_is_empty( $session ) {
		if ( ! is_array( $session ) || ! $session ) {
			return true;
		}
		foreach ( $session as $value ) {
			if ( is_array( $value ) && $value ) {
				return false;
			}
			if ( is_string( $value ) && '' !== $value ) {
				return false;
			}
			if ( is_numeric( $value ) && 0 != $value ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
				return false;
			}
			if ( is_bool( $value ) && $value ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Cart, checkout, account, and add-to-cart requests keep their session.
	 *
	 * @param string $uri URI.
	 * @return bool
	 */
	public static function is_private_request( $uri ) {
		$parts = parse_url( (string) $uri );
		$path  = isset( $parts['path'] ) ? strtolower( (string) $parts['path'] ) : '';
		$query = isset( $parts['query'] ) ? (string) $parts['query'] : '';
		if ( preg_match( '#/(?:cart|checkout|my-account|wishlist|wish-list)(?:/|$)#', $path ) ) {
			return true;
		}
		if ( preg_match( '#/(?:wp-admin|wp-login\.php|admin-ajax\.php|wc-ajax|wp-json|xmlrpc\.php|wp-cron\.php)(?:/|$)#', $path ) ) {
			return true;
		}
		if ( '' !== $query ) {
			$args = array();
			parse_str( $query, $args );
			foreach ( array( 'add-to-cart', 'remove_item', 'undo_item', 'wc-ajax' ) as $key ) {
				if ( isset( $args[ $key ] ) && '' !== (string) $args[ $key ] ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Remove the PHP session cookie and the 1981 no-cache headers. Keep every other cookie.
	 *
	 * @param array $headers Header lines.
	 * @return array<int,string>
	 */
	public static function public_headers( $headers ) {
		$kept      = array();
		$saw_cache = false;
		if ( ! is_array( $headers ) ) {
			$headers = array();
		}
		foreach ( $headers as $header ) {
			if ( ! is_string( $header ) || false === strpos( $header, ':' ) ) {
				continue;
			}
			list( $name, $value ) = explode( ':', $header, 2 );
			$name  = trim( $name );
			$value = trim( $value );
			$lower = strtolower( $name );
			if ( 'set-cookie' === $lower && 0 === stripos( $value, 'PHPSESSID=' ) ) {
				continue;
			}
			if ( 'pragma' === $lower && 'no-cache' === strtolower( $value ) ) {
				continue;
			}
			if ( 'expires' === $lower && false !== strpos( $value, '1981' ) ) {
				continue;
			}
			if ( 'cache-control' === $lower ) {
				if ( false !== stripos( $value, 'no-cache' ) || false !== stripos( $value, 'no-store' ) || false !== stripos( $value, 'private' ) ) {
					continue;
				}
				$saw_cache = true;
			}
			$kept[] = $name . ': ' . $value;
		}
		if ( ! $saw_cache ) {
			$kept[] = 'Cache-Control: public, max-age=600, s-maxage=600';
		}
		return $kept;
	}

	/**
	 * @param array $current Current header lines.
	 */
	public static function replace_headers( $current ) {
		if ( ! function_exists( 'header_remove' ) || ! function_exists( 'header' ) ) {
			return;
		}
		$clean = self::public_headers( $current );
		header_remove( 'Set-Cookie' );
		header_remove( 'Pragma' );
		header_remove( 'Expires' );
		header_remove( 'Cache-Control' );
		foreach ( $clean as $line ) {
			header( $line, false );
		}
	}
}
