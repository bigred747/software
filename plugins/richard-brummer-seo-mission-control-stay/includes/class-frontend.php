<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public output repairs. Never writes post_content or changes post status.
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
	 * Register frontend hooks.
	 */
	public function boot() {
		add_action( 'template_redirect', array( $this, 'start_schema_buffer' ), 0 );
		add_action( 'wp_head', array( $this, 'guest_vary_shield' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'drop_missing_plugin_assets' ), 9999 );
		add_action( 'wp_print_styles', array( $this, 'drop_missing_plugin_assets' ), 1 );
		add_action( 'wp_print_scripts', array( $this, 'drop_missing_plugin_assets' ), 1 );
		add_filter( 'the_content', array( $this, 'filter_content' ), 32 );
		add_action( 'wp_footer', array( $this, 'reading_progress_markup' ), 5 );
	}

	/**
	 * Buffer public HTML so only invalid JSON-LD is stripped. Valid Rank Math schema stays.
	 */
	public function start_schema_buffer() {
		if ( is_admin() || wp_doing_ajax() || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( is_feed() || is_robots() || is_trackback() ) {
			return;
		}
		if ( ! RBSMC_Stay_Plugin::instance()->enabled( 'schema_quarantine' ) ) {
			return;
		}
		ob_start( array( $this, 'quarantine_buffer' ) );
	}

	/**
	 * @param string $html Buffered HTML.
	 * @return string
	 */
	public function quarantine_buffer( $html ) {
		return RBSMC_Stay_Schema::quarantine_invalid( $html );
	}

	/**
	 * Skip LiteSpeed Guest Mode's first-visit reload so analytics is not a 1-second session.
	 */
	public function guest_vary_shield() {
		if ( is_admin() || ! RBSMC_Stay_Plugin::instance()->enabled( 'guest_vary_shield' ) ) {
			return;
		}
		echo "<script data-no-optimize=\"1\">(function(){try{if(!document.cookie.match(/(?:^|;\\s*)_lscache_vary=/)){sessionStorage.setItem('litespeed_reloaded','1');}}catch(e){}})();</script>\n";
	}

	/**
	 * Front CSS/JS.
	 */
	public function enqueue() {
		if ( is_admin() ) {
			return;
		}
		$plugin = RBSMC_Stay_Plugin::instance();
		wp_enqueue_style(
			'rbsmc-stay-front',
			RBSMC_STAY_URL . 'assets/front.css',
			array(),
			RBSMC_STAY_VERSION
		);
		wp_enqueue_script(
			'rbsmc-stay-front',
			RBSMC_STAY_URL . 'assets/front.js',
			array(),
			RBSMC_STAY_VERSION,
			true
		);
		wp_script_add_data( 'rbsmc-stay-front', 'strategy', 'defer' );
		wp_localize_script(
			'rbsmc-stay-front',
			'rbsmcStay',
			array(
				'ajax'       => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'rbsmc_stay_dwell' ),
				'beacon'     => $plugin->enabled( 'dwell_beacon' ) ? 1 : 0,
				'filler'     => $plugin->enabled( 'hide_utility_ai_filler' ) ? 1 : 0,
				'progress'   => $plugin->enabled( 'reading_progress' ) ? 1 : 0,
				'neutralize' => $plugin->enabled( 'neutralize_history_traps' ) ? 1 : 0,
				'utility'    => $this->is_utility_page() ? 1 : 0,
			)
		);
	}

	/**
	 * Stop enqueueing plugin CSS/JS whose files are missing on disk (Link Guardian 404s).
	 */
	public function drop_missing_plugin_assets() {
		if ( is_admin() || ! RBSMC_Stay_Plugin::instance()->enabled( 'drop_missing_assets' ) ) {
			return;
		}
		global $wp_styles, $wp_scripts;
		$this->drop_missing_from_queue( $wp_styles, true );
		$this->drop_missing_from_queue( $wp_scripts, false );
	}

	/**
	 * @param WP_Dependencies|null $queue Style or script queue.
	 * @param bool                 $is_style Whether this is a style queue.
	 */
	private function drop_missing_from_queue( $queue, $is_style ) {
		if ( ! $queue || empty( $queue->registered ) || ! is_array( $queue->registered ) ) {
			return;
		}
		$content_url_path = wp_parse_url( content_url(), PHP_URL_PATH );
		if ( ! is_string( $content_url_path ) || '' === $content_url_path ) {
			return;
		}
		foreach ( $queue->registered as $handle => $obj ) {
			$src = isset( $obj->src ) ? (string) $obj->src : '';
			if ( '' === $src ) {
				continue;
			}
			$path = wp_parse_url( $src, PHP_URL_PATH );
			if ( ! is_string( $path ) || false === strpos( $path, '/plugins/' ) ) {
				continue;
			}
			if ( 0 !== strpos( $path, $content_url_path ) ) {
				continue;
			}
			$rel  = substr( $path, strlen( $content_url_path ) );
			$file = WP_CONTENT_DIR . $rel;
			if ( is_readable( $file ) ) {
				continue;
			}
			if ( $is_style ) {
				wp_dequeue_style( $handle );
				wp_deregister_style( $handle );
			} else {
				wp_dequeue_script( $handle );
				wp_deregister_script( $handle );
			}
		}
	}

	/**
	 * Hide leaked shortcodes and append related guides.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public function filter_content( $content ) {
		if ( is_admin() || is_feed() || ! is_string( $content ) ) {
			return $content;
		}
		$plugin = RBSMC_Stay_Plugin::instance();
		if ( $plugin->enabled( 'hide_leaked_shortcodes' ) ) {
			$content = preg_replace( '/\[(\/?ez-?toc|toc)[^\]]*\]/i', '', $content );
		}
		if ( $plugin->enabled( 'hide_utility_ai_filler' ) && $this->is_utility_page() ) {
			$content = preg_replace( '/<[^>]*>\s*Expanded Analysis:.*?<\/(div|section|article)>/is', '', $content );
			$content = preg_replace( '/This section has been expanded automatically[\s\S]{0,900}/i', '', $content );
		}
		if ( $plugin->enabled( 'related_rail' ) && $this->is_reading_page() ) {
			$content .= $this->related_rail_html();
		}
		return $content;
	}

	/**
	 * Reading progress bar markup.
	 */
	public function reading_progress_markup() {
		if ( ! RBSMC_Stay_Plugin::instance()->enabled( 'reading_progress' ) || ! $this->is_reading_page() ) {
			return;
		}
		echo '<div class="rbsmc-stay-progress" aria-hidden="true"><span></span></div>';
	}

	/**
	 * Cart, checkout, account, wishlist.
	 *
	 * @return bool
	 */
	private function is_utility_page() {
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			return true;
		}
		if ( is_page( array( 'cart', 'checkout', 'my-account', 'wish-list', 'wishlist', 'client-portal' ) ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Long-form public content.
	 *
	 * @return bool
	 */
	private function is_reading_page() {
		if ( is_singular( array( 'post', 'page', 'product' ) ) && ! $this->is_utility_page() ) {
			return true;
		}
		return false;
	}

	/**
	 * Related published items. Query only.
	 *
	 * @return string
	 */
	private function related_rail_html() {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '';
		}
		$type = get_post_type( $post_id );
		$args = array(
			'post_type'           => $type ? $type : 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 4,
			'post__not_in'        => array( $post_id ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
		);
		if ( 'product' === $type && taxonomy_exists( 'product_cat' ) ) {
			$terms = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) && $terms ) {
				$args['tax_query'] = array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $terms,
					),
				);
			}
		} else {
			$cats = wp_get_post_categories( $post_id );
			if ( $cats ) {
				$args['category__in'] = $cats;
			}
		}
		$query = new WP_Query( $args );
		if ( ! $query->have_posts() ) {
			wp_reset_postdata();
			return '';
		}
		$html  = '<aside class="rbsmc-stay-rail"><h2>Keep comparing</h2><p>Stay on LuxeTrendsetters and open another buyer guide instead of bouncing.</p><ul>';
		while ( $query->have_posts() ) {
			$query->the_post();
			$html .= '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
		}
		$html .= '</ul></aside>';
		wp_reset_postdata();
		return $html;
	}
}
