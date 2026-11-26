<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * On-site compare block. Printed once per document, never instead of the Amazon button.
 */
class Luxe_Google_Presence_Engage {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var bool
	 */
	private $printed = false;

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
	 * Register placement hooks. WooCommerce's own button is not removed.
	 */
	public function boot() {
		add_action( 'woocommerce_single_product_summary', array( $this, 'product_summary' ), 25 );
		add_filter( 'the_content', array( $this, 'prepend_guides' ), 12 );
		add_action( 'wp_footer', array( $this, 'footer' ), 25 );
	}

	/**
	 * Place guides and the disclosure just before the product button.
	 */
	public function product_summary() {
		if ( ! $this->allowed() ) {
			return;
		}
		$this->printed = true;
		echo $this->markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Buyer guides get the same block ahead of in-post Amazon links.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public function prepend_guides( $content ) {
		if ( ! is_string( $content ) || ! $this->allowed() ) {
			return $content;
		}
		if ( is_admin() || is_feed() || ! in_the_loop() || ! is_main_query() || ! is_singular( 'post' ) ) {
			return $content;
		}
		$this->printed = true;
		return $this->markup() . $content;
	}

	/**
	 * Archives and the homepage still get one in-flow block.
	 */
	public function footer() {
		if ( ! $this->allowed() ) {
			return;
		}
		$this->printed = true;
		echo $this->markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @return bool
	 */
	private function allowed() {
		if ( $this->printed || ! Luxe_Google_Presence_Plugin::instance()->enabled( 'onsite_compare' ) ) {
			return false;
		}
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}
		if ( is_feed() ) {
			return false;
		}
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			return false;
		}
		return ! Luxe_Google_Presence_Plugin::is_utility_request();
	}

	/**
	 * @return string
	 */
	private function markup() {
		$links = Luxe_Google_Presence_Copy::defaults( home_url( '/' ) );
		if ( function_exists( 'is_product' ) && is_product() ) {
			$terms = get_the_terms( get_the_ID(), 'product_cat' );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$link = get_term_link( $term );
					if ( is_wp_error( $link ) || ! is_string( $link ) ) {
						continue;
					}
					array_unshift(
						$links,
						array(
							'url'   => $link,
							'label' => 'More in ' . $term->name,
						)
					);
					break;
				}
			}
		}
		/**
		 * Extra on-site links. Amazon URLs are ignored.
		 *
		 * @param array $links Link rows.
		 */
		$links = apply_filters( 'luxe_google_presence_links', $links );
		return Luxe_Google_Presence_Copy::aside( home_url( '/' ), $links );
	}
}
