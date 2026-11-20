<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LAH_Public' ) ) {
	return;
}

/**
 * Public Amazon blocks. Direct tagged links, required disclosure, Google sponsored rel.
 * Does not log into Amazon and does not change an existing href.
 */
class LAH_Public {

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
	 * Register the shortcode and the public HTML pass.
	 */
	public function boot() {
		add_shortcode( 'luxe_amazon', array( $this, 'shortcode' ) );
		add_filter( 'the_content', array( $this, 'qualify_content' ), 42 );
		add_action( 'template_redirect', array( $this, 'start_buffer' ), 3 );
	}

	/**
	 * [luxe_amazon asin="B0ABCDEF12" title="View the Seiko on Amazon"]
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'asin'  => '',
				'title' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'luxe_amazon'
		);
		$settings = LAH_Plugin::instance()->settings();
		return LAH_Links::block_html( $atts['asin'], $settings['tracking_id'], $atts['title'] );
	}

	/**
	 * @param string $content Content.
	 * @return string
	 */
	public function qualify_content( $content ) {
		if ( ! is_string( $content ) || is_admin() ) {
			return $content;
		}
		$content = LAH_Links::qualify_amazon_anchors( $content );
		return LAH_Links::honest_retailer_phrase( $content );
	}

	/**
	 * Last pass so buttons outside post content get the same rel.
	 */
	public function start_buffer() {
		if ( $this->started || headers_sent() ) {
			return;
		}
		if ( is_admin() || wp_doing_ajax() || is_feed() ) {
			return;
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
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
		if ( ! is_string( $html ) || strlen( $html ) < 32 || false === stripos( $html, '<html' ) ) {
			return is_string( $html ) ? $html : '';
		}
		$html = LAH_Links::qualify_amazon_anchors( $html );
		return LAH_Links::honest_retailer_phrase( $html );
	}
}
