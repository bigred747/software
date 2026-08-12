<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 404 log and helpful template.
 */
class RBSMC_NotFound {

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
	 * Hooks.
	 */
	public function boot() {
		add_action( 'wp', array( $this, 'on_wp' ) );
		add_filter( '404_template', array( $this, 'template' ) );
	}

	/**
	 * Log 404s.
	 */
	public function on_wp() {
		if ( is_admin() || ! is_404() || ! RBSMC_Plugin::instance()->enabled( 'log_404' ) ) {
			return;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$uri = substr( strtok( $uri, '?' ), 0, 180 );
		if ( '' === $uri || 0 === strpos( $uri, '/wp-admin' ) || 0 === strpos( $uri, '/wp-json' ) ) {
			return;
		}
		$log = get_option( RBSMC_Plugin::OPTION_404, array() );
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
		update_option( RBSMC_Plugin::OPTION_404, $log, false );
	}

	/**
	 * @param string $template Template.
	 * @return string
	 */
	public function template( $template ) {
		if ( ! RBSMC_Plugin::instance()->enabled( 'helpful_404' ) ) {
			return $template;
		}
		$custom = RBSMC_DIR . 'templates/404.php';
		return file_exists( $custom ) ? $custom : $template;
	}
}
