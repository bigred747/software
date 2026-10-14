<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computer and mobile CSS. Public HTML is not branched by user agent.
 */
class Luxe_Google_Presence_Device {

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
	 * Register front styles. The HTML buffer starts here so it runs after later buffers.
	 */
	public function boot() {
		add_action( 'wp_enqueue_scripts', array( $this, 'styles' ), 20 );
		add_action( 'wp_head', array( $this, 'verification' ), 2 );
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 0 );
		$this->start_buffer();
	}

	/**
	 * Enqueue the shared stylesheet.
	 */
	public function styles() {
		if ( is_admin() || ! Luxe_Google_Presence_Plugin::instance()->enabled( 'device_css' ) ) {
			return;
		}
		wp_enqueue_style(
			'luxe-google-presence-device',
			LUXE_GOOGLE_PRESENCE_URL . 'assets/device.css',
			array(),
			LUXE_GOOGLE_PRESENCE_VERSION
		);
	}

	/**
	 * Print a Search Console verification meta when a safe token is saved.
	 */
	public function verification() {
		if ( is_admin() ) {
			return;
		}
		$settings = Luxe_Google_Presence_Plugin::instance()->settings();
		$token    = isset( $settings['verification'] ) ? (string) $settings['verification'] : '';
		$meta     = Luxe_Google_Presence_Anchors::verification_tag( $token );
		if ( '' === $meta ) {
			return;
		}
		echo $meta . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Send item-weight and other filter archives to the department those products share.
	 * The target is always on this site. Amazon is never the redirect.
	 */
	public function maybe_redirect() {
		$plugin = Luxe_Google_Presence_Plugin::instance();
		if ( ! $plugin->enabled( 'junk_redirect' ) ) {
			return;
		}
		if ( ! Luxe_Google_Presence_Plugin::is_public_html_request() ) {
			return;
		}
		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return;
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return;
		}
		if ( headers_sent() ) {
			return;
		}
		$uri       = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$junk_path = Luxe_Google_Presence_Visitor::is_junk_archive_path( $uri );
		$junk_tax  = $this->is_junk_attribute_query();
		if ( ! $junk_path && ! $junk_tax ) {
			return;
		}
		$target = Luxe_Google_Presence_Visitor::redirect_url( $uri, $this->department_rows(), home_url( '/shop/' ), ! $junk_path && $junk_tax );
		if ( '' === $target ) {
			return;
		}
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		wp_safe_redirect( $target, 301 );
		exit;
	}

	/**
	 * @return bool
	 */
	private function is_junk_attribute_query() {
		if ( ! function_exists( 'get_queried_object' ) ) {
			return false;
		}
		$object = get_queried_object();
		if ( ! is_object( $object ) || ! isset( $object->taxonomy ) ) {
			return false;
		}
		$taxonomy = (string) $object->taxonomy;
		if ( 0 !== strpos( $taxonomy, 'pa_' ) ) {
			return false;
		}
		return Luxe_Google_Presence_Visitor::is_junk_base( substr( $taxonomy, 3 ) );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function department_rows() {
		$rows = array();
		if ( ! isset( $GLOBALS['wp_query'] ) || ! is_object( $GLOBALS['wp_query'] ) || ! function_exists( 'get_the_terms' ) ) {
			return $rows;
		}
		$posts = $GLOBALS['wp_query']->posts;
		if ( ! is_array( $posts ) ) {
			return $rows;
		}
		$hits = array();
		$meta = array();
		foreach ( $posts as $post ) {
			$id = ( is_object( $post ) && isset( $post->ID ) ) ? (int) $post->ID : 0;
			if ( $id < 1 ) {
				continue;
			}
			$terms = get_the_terms( $id, 'product_cat' );
			if ( ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				if ( ! is_object( $term ) || empty( $term->term_id ) ) {
					continue;
				}
				$term_id = (int) $term->term_id;
				if ( ! isset( $hits[ $term_id ] ) ) {
					$hits[ $term_id ] = 0;
				}
				$hits[ $term_id ]++;
				if ( isset( $meta[ $term_id ] ) || ! function_exists( 'get_term_link' ) ) {
					continue;
				}
				$link = get_term_link( $term );
				if ( is_wp_error( $link ) || ! is_string( $link ) ) {
					continue;
				}
				$meta[ $term_id ] = array(
					'url'   => $link,
					'name'  => isset( $term->name ) ? (string) $term->name : '',
					'depth' => $this->term_depth( $term ),
					'hits'  => 0,
				);
			}
		}
		foreach ( $meta as $term_id => $row ) {
			$row['hits'] = isset( $hits[ $term_id ] ) ? (int) $hits[ $term_id ] : 0;
			$rows[]      = $row;
		}
		return $rows;
	}

	/**
	 * @param object $term Term.
	 * @return int
	 */
	private function term_depth( $term ) {
		$depth  = 0;
		$parent = ( is_object( $term ) && isset( $term->parent ) ) ? (int) $term->parent : 0;
		$guard  = 0;
		while ( $parent > 0 && $guard < 8 && function_exists( 'get_term' ) ) {
			$depth++;
			$parent_term = get_term( $parent, 'product_cat' );
			if ( ! $parent_term || is_wp_error( $parent_term ) || ! is_object( $parent_term ) ) {
				break;
			}
			$parent = isset( $parent_term->parent ) ? (int) $parent_term->parent : 0;
			$guard++;
		}
		return $depth;
	}

	/**
	 * Fill a missing viewport and repair public labels after other buffers.
	 */
	public function start_buffer() {
		$plugin = Luxe_Google_Presence_Plugin::instance();
		if ( ! $plugin->enabled( 'qualify_links' ) && ! $plugin->enabled( 'device_css' ) && ! $plugin->enabled( 'visitor_repair' ) ) {
			return;
		}
		if ( ! Luxe_Google_Presence_Plugin::is_public_html_request() ) {
			return;
		}
		if ( headers_sent() ) {
			return;
		}
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
		$plugin = Luxe_Google_Presence_Plugin::instance();
		if ( $plugin->enabled( 'visitor_repair' ) ) {
			$html = Luxe_Google_Presence_Visitor::repair( $html );
		}
		if ( $plugin->enabled( 'device_css' ) ) {
			$html = Luxe_Google_Presence_Anchors::ensure_viewport( $html );
		}
		if ( $plugin->enabled( 'qualify_links' ) ) {
			$html = Luxe_Google_Presence_Anchors::qualify( $html );
		}
		return $html;
	}
}
