<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tools → Luxe Google Presence.
 */
class Luxe_Google_Presence_Admin {

	const SCAN = 'luxe_google_presence_last_scan';

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
	 * Register the tools page.
	 */
	public function boot() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
		add_action( 'admin_init', array( $this, 'maybe_scan' ) );
		add_filter( 'plugin_action_links_' . LUXE_GOOGLE_PRESENCE_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Settings page.
	 */
	public function menu() {
		add_management_page(
			__( 'Luxe Google Presence', 'luxe-google-presence' ),
			__( 'Luxe Google Presence', 'luxe-google-presence' ),
			'manage_options',
			'luxe-google-presence',
			array( $this, 'render' )
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( 'tools_page_luxe-google-presence' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'luxe-google-presence-admin',
			LUXE_GOOGLE_PRESENCE_URL . 'assets/admin.css',
			array(),
			LUXE_GOOGLE_PRESENCE_VERSION
		);
	}

	/**
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'tools.php?page=luxe-google-presence' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'luxe-google-presence' ) . '</a>' );
		return $links;
	}

	/**
	 * Save switches and the Search Console token.
	 */
	public function maybe_save() {
		if ( empty( $_POST['luxe_google_presence_save'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_google_presence_save' );
		$current = Luxe_Google_Presence_Plugin::instance()->settings();
		$next    = array();
		foreach ( Luxe_Google_Presence_Plugin::defaults() as $key => $default ) {
			if ( 'verification' === $key ) {
				$raw = isset( $_POST['verification'] ) ? sanitize_text_field( wp_unslash( $_POST['verification'] ) ) : '';
				$tag = Luxe_Google_Presence_Anchors::verification_tag( $raw );
				if ( preg_match( '/content="([A-Za-z0-9_-]+)"/', $tag, $match ) ) {
					$next[ $key ] = $match[1];
				} else {
					$next[ $key ] = '';
				}
				continue;
			}
			unset( $default );
			$next[ $key ] = empty( $_POST[ $key ] ) ? 0 : 1;
		}
		if ( ! isset( $next['verification'] ) && isset( $current['verification'] ) ) {
			$next['verification'] = $current['verification'];
		}
		update_option( Luxe_Google_Presence_Plugin::OPTION, $next, false );
		add_settings_error(
			'luxe_google_presence',
			'saved',
			__( 'Google Presence settings saved. Score Repair, Product Scout, Product Integrity, and Rank Math were not overwritten.', 'luxe-google-presence' ),
			'updated'
		);
	}

	/**
	 * Fetch the live homepage as a computer and as a phone.
	 */
	public function maybe_scan() {
		if ( empty( $_POST['luxe_google_presence_scan'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_google_presence_scan' );
		$desktop = $this->fetch( home_url( '/' ), 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36' );
		$mobile  = $this->fetch( home_url( '/' ), 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Mobile Safari/537.36' );
		$robots  = $this->fetch( home_url( '/robots.txt' ), 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' );
		$result  = Luxe_Google_Presence_Scan::evaluate(
			isset( $desktop['body'] ) ? $desktop['body'] : '',
			isset( $mobile['body'] ) ? $mobile['body'] : '',
			isset( $robots['body'] ) ? $robots['body'] : ''
		);
		$result['at'] = time();
		update_option( self::SCAN, $result, false );
		add_settings_error(
			'luxe_google_presence',
			'scanned',
			sprintf(
				/* translators: 1: green count, 2: total, 3: score */
				__( 'Live scan finished. %1$d / %2$d signals green. Readiness %3$d.', 'luxe-google-presence' ),
				isset( $result['green'] ) ? (int) $result['green'] : 0,
				isset( $result['total'] ) ? (int) $result['total'] : 0,
				isset( $result['score'] ) ? (int) $result['score'] : 0
			),
			! empty( $result['score'] ) && (int) $result['score'] >= 95 ? 'updated' : 'notice-warning'
		);
	}

	/**
	 * Render the board.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = Luxe_Google_Presence_Plugin::instance()->settings();
		$contract = Luxe_Google_Presence_Contract::evaluate();
		$live     = get_option( self::SCAN, array() );
		if ( ! is_array( $live ) ) {
			$live = array();
		}
		$siblings = Luxe_Google_Presence_Compat::siblings();
		settings_errors( 'luxe_google_presence' );
		include LUXE_GOOGLE_PRESENCE_DIR . 'templates/admin.php';
	}

	/**
	 * @param string $url URL.
	 * @param string $agent User agent.
	 * @return array
	 */
	private function fetch( $url, $agent ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 12,
				'redirection' => 3,
				'sslverify'   => true,
				'user-agent'  => $agent,
				'headers'     => array(
					'Cache-Control' => 'no-cache',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array(
				'body' => '',
			);
		}
		return array(
			'body' => (string) wp_remote_retrieve_body( $response ),
		);
	}
}
