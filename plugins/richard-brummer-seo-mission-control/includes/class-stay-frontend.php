<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public stay repairs. Does not write post_content or change post status.
 */
class RBSMC_Stay_Frontend {

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
		add_action( 'wp_head', array( $this, 'guest_vary_shield' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'the_content', array( $this, 'filter_content' ), 32 );
		add_action( 'wp_footer', array( $this, 'reading_progress_markup' ), 5 );
	}

	/**
	 * Skip LiteSpeed Guest Mode first-visit reload.
	 */
	public function guest_vary_shield() {
		if ( is_admin() || ! RBSMC_Plugin::instance()->enabled( 'guest_vary_shield' ) ) {
			return;
		}
		echo "<script data-no-optimize=\"1\">(function(){try{if(!document.cookie.match(/(?:^|;\\s*)_lscache_vary=/)){sessionStorage.setItem('litespeed_reloaded','1');}}catch(e){}})();</script>\n";
	}

	/**
	 * Front assets.
	 */
	public function enqueue() {
		if ( is_admin() ) {
			return;
		}
		$plugin = RBSMC_Plugin::instance();
		wp_enqueue_style( 'rbsmc-front', RBSMC_URL . 'assets/front.css', array(), RBSMC_VERSION );
		wp_enqueue_script( 'rbsmc-front', RBSMC_URL . 'assets/front.js', array(), RBSMC_VERSION, true );
		wp_localize_script(
			'rbsmc-front',
			'rbsmcStay',
			array(
				'ajax'       => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'rbsmc_dwell' ),
				'beacon'     => $plugin->enabled( 'dwell_beacon' ) ? 1 : 0,
				'filler'     => $plugin->enabled( 'hide_utility_ai_filler' ) ? 1 : 0,
				'progress'   => $plugin->enabled( 'reading_progress' ) ? 1 : 0,
				'neutralize' => $plugin->enabled( 'neutralize_history_traps' ) ? 1 : 0,
				'utility'    => $this->is_utility_page() ? 1 : 0,
			)
		);
	}

	/**
	 * @param string $content Content.
	 * @return string
	 */
	public function filter_content( $content ) {
		if ( is_admin() || is_feed() || ! is_string( $content ) ) {
			return $content;
		}
		$plugin = RBSMC_Plugin::instance();
		if ( $plugin->enabled( 'hide_leaked_shortcodes' ) ) {
			$content = preg_replace( '/\[(\/?ez-?toc|toc)[^\]]*\]/i', '', $content );
		}
		if ( $plugin->enabled( 'hide_utility_ai_filler' ) && $this->is_utility_page() ) {
			$content = preg_replace( '/This section has been expanded automatically[\s\S]{0,900}/i', '', $content );
		}
		if ( $plugin->enabled( 'related_rail' ) && $this->is_reading_page() ) {
			$content .= $this->related_rail_html();
		}
		return $content;
	}

	/**
	 * Progress bar.
	 */
	public function reading_progress_markup() {
		if ( ! RBSMC_Plugin::instance()->enabled( 'reading_progress' ) || ! $this->is_reading_page() ) {
			return;
		}
		echo '<div class="rbsmc-stay-progress" aria-hidden="true"><span></span></div>';
	}

	/**
	 * @return bool
	 */
	private function is_utility_page() {
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			return true;
		}
		return is_page( array( 'cart', 'checkout', 'my-account', 'wish-list', 'wishlist', 'client-portal' ) );
	}

	/**
	 * @return bool
	 */
	private function is_reading_page() {
		return is_singular( array( 'post', 'page', 'product' ) ) && ! $this->is_utility_page();
	}

	/**
	 * @return string
	 */
	private function related_rail_html() {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '';
		}
		$type  = get_post_type( $post_id );
		$args  = array(
			'post_type'           => $type ? $type : 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 4,
			'post__not_in'        => array( $post_id ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'orderby'             => 'date',
		);
		$query = new WP_Query( $args );
		if ( ! $query->have_posts() ) {
			wp_reset_postdata();
			return '';
		}
		$html = '<aside class="rbsmc-stay-rail"><h2>Keep comparing</h2><ul>';
		while ( $query->have_posts() ) {
			$query->the_post();
			$html .= '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
		}
		$html .= '</ul></aside>';
		wp_reset_postdata();
		return $html;
	}
}
