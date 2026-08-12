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
		add_action( 'wp', array( $this, 'on_wp' ) );
		add_filter( '404_template', array( $this, 'template' ) );
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
		if ( '' === $uri || 0 === strpos( $uri, '/wp-admin' ) || 0 === strpos( $uri, '/wp-json' ) ) {
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
