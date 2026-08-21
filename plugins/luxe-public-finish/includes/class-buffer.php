<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outer HTML pass. Starts before other Luxe buffers so this rewrite runs last.
 * Adds zero frontend JavaScript or CSS.
 */
class Luxe_Public_Finish_Buffer {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var bool
	 */
	private $started = false;

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
	 * Buffer public HTML only.
	 */
	public function boot() {
		add_action( 'template_redirect', array( $this, 'start' ), -1 );
		add_action( 'wp_head', array( $this, 'meta_marker' ), 2 );
	}

	/**
	 * Tiny head marker so learning can confirm the pass ran. No JS. No CSS.
	 */
	public function meta_marker() {
		if ( is_admin() || ! Luxe_Public_Finish_Plugin::is_public_html_request() ) {
			return;
		}
		echo '<meta name="luxe-public-finish" content="' . esc_attr( LUXE_PUBLIC_FINISH_VERSION ) . '" />' . "\n";
	}

	/**
	 * Start the outer output buffer.
	 */
	public function start() {
		if ( $this->started ) {
			return;
		}
		if ( ! Luxe_Public_Finish_Plugin::instance()->enabled( 'buffer_html' ) ) {
			return;
		}
		if ( ! Luxe_Public_Finish_Plugin::is_public_html_request() ) {
			return;
		}
		if ( headers_sent() ) {
			return;
		}
		$this->started = true;
		ob_start( array( $this, 'rewrite' ) );
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public function rewrite( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) < 32 ) {
			return $html;
		}
		if ( false === stripos( $html, '<html' ) ) {
			return $html;
		}

		$plugin = Luxe_Public_Finish_Plugin::instance();

		if ( $plugin->enabled( 'polish_titles' ) ) {
			$html = $this->polish_head_titles( $html );
		}
		if ( $plugin->enabled( 'disclosure_once' ) ) {
			$html = Luxe_Public_Finish_Titles::collapse_disclosures( $html );
		}
		if ( $plugin->enabled( 'schema_org_once' ) ) {
			$html = Luxe_Public_Finish_Titles::collapse_organization( $html );
		}

		return $html;
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	private function polish_head_titles( $html ) {
		$current   = Luxe_Public_Finish_Titles::html_title( $html );
		$h1        = $this->first_h1( $html );
		$preferred = $h1;
		if ( function_exists( 'is_singular' ) && is_singular() && function_exists( 'get_post_field' ) ) {
			$raw = Luxe_Public_Finish_Titles::plain( (string) get_post_field( 'post_title', get_queried_object_id() ) );
			if ( $raw && ! Luxe_Public_Finish_Titles::is_truncated( $raw ) ) {
				$preferred = $raw;
			}
		}
		$fixed = Luxe_Public_Finish_Titles::polish( $current, $preferred );
		if ( ! $fixed ) {
			$fixed = $current;
		}
		if ( $fixed ) {
			$html = Luxe_Public_Finish_Titles::replace_document_title( $html, $fixed );
		}
		return $html;
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	private function first_h1( $html ) {
		if ( preg_match( '/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $m ) ) {
			return Luxe_Public_Finish_Titles::plain( $m[1] );
		}
		return '';
	}
}
