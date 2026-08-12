<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keep junk and noindex URLs out of Rank Math / core sitemaps.
 * Display-only XML filter. Does not write posts or Rank Math redirect rows.
 */
class Luxe_Score_Repair_Sitemaps {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var bool
	 */
	private $buffering = false;

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
	 * Register sitemap filters.
	 */
	public function boot() {
		add_filter( 'rank_math/sitemap/entry', array( $this, 'rank_math_entry' ), 99, 3 );
		add_filter( 'rank_math/sitemap/exclude_post', array( $this, 'exclude_post' ), 99, 2 );
		add_filter( 'rank_math/sitemap/enable_authors', '__return_false', 99 );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'core_query_args' ), 99, 2 );
		add_filter( 'wp_sitemaps_add_provider', array( $this, 'core_providers' ), 99, 2 );
		add_action( 'template_redirect', array( $this, 'start_xml_buffer' ), 0 );
	}

	/**
	 * @param mixed  $url    Entry.
	 * @param string $type   Type.
	 * @param mixed  $object Object.
	 * @return mixed
	 */
	public function rank_math_entry( $url, $type, $object ) {
		unset( $object );
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_sitemaps' ) ) {
			return $url;
		}
		if ( ! is_array( $url ) || empty( $url['loc'] ) ) {
			return $url;
		}
		$loc = (string) $url['loc'];
		if ( self::is_blocked_loc( $loc ) ) {
			return false;
		}
		if ( 'post' === $type && self::is_page_sitemap_request() && self::is_shop_loc( $loc ) ) {
			return false;
		}
		return $url;
	}

	/**
	 * @param bool $exclude Exclude.
	 * @param int  $post_id Post ID.
	 * @return bool
	 */
	public function exclude_post( $exclude, $post_id ) {
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_sitemaps' ) ) {
			return $exclude;
		}
		$post = get_post( (int) $post_id );
		if ( ! $post ) {
			return $exclude;
		}
		$slug = (string) $post->post_name;
		if ( in_array( $slug, Luxe_Score_Repair_Plugin::noindex_slugs(), true ) ) {
			return true;
		}
		if ( 0 === strpos( $slug, 'test-' ) ) {
			return true;
		}
		return $exclude;
	}

	/**
	 * @param array  $args      Query args.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public function core_query_args( $args, $post_type ) {
		unset( $post_type );
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_sitemaps' ) || ! is_array( $args ) ) {
			return $args;
		}
		$blocked = get_posts(
			array(
				'post_type'              => array( 'page', 'post' ),
				'post_status'            => 'publish',
				'posts_per_page'         => 40,
				'post_name__in'          => Luxe_Score_Repair_Plugin::noindex_slugs(),
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( is_array( $blocked ) && $blocked ) {
			$existing              = isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array();
			$args['post__not_in']  = array_values( array_unique( array_merge( $existing, $blocked ) ) );
		}
		return $args;
	}

	/**
	 * Drop WP core users sitemap.
	 *
	 * @param mixed  $provider Provider.
	 * @param string $name     Name.
	 * @return mixed
	 */
	public function core_providers( $provider, $name ) {
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_sitemaps' ) ) {
			return $provider;
		}
		if ( 'users' === $name ) {
			return false;
		}
		return $provider;
	}

	/**
	 * Last-pass strip of junk <url> rows Rank Math already printed.
	 */
	public function start_xml_buffer() {
		if ( $this->buffering || is_admin() ) {
			return;
		}
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_sitemaps' ) ) {
			return;
		}
		if ( ! self::is_sitemap_request() ) {
			return;
		}
		if ( headers_sent() ) {
			return;
		}
		$this->buffering = true;
		ob_start( array( $this, 'strip_xml' ) );
	}

	/**
	 * @param string $xml XML.
	 * @return string
	 */
	public function strip_xml( $xml ) {
		if ( ! is_string( $xml ) || false === strpos( $xml, '<url>' ) ) {
			return $xml;
		}
		$page_map = self::is_page_sitemap_request();
		$xml      = preg_replace_callback(
			'/<url\b[^>]*>.*?<\/url>/is',
			function ( $m ) use ( $page_map ) {
				$chunk = $m[0];
				if ( ! preg_match( '/<loc>\s*([^<]+)\s*<\/loc>/i', $chunk, $lm ) ) {
					return $chunk;
				}
				$loc = html_entity_decode( trim( $lm[1] ), ENT_QUOTES, 'UTF-8' );
				if ( self::is_blocked_loc( $loc ) ) {
					return '';
				}
				if ( $page_map && self::is_shop_loc( $loc ) ) {
					return '';
				}
				return $chunk;
			},
			$xml
		);
		return is_string( $xml ) ? $xml : '';
	}

	/**
	 * @param string $loc URL.
	 * @return bool
	 */
	public static function is_blocked_loc( $loc ) {
		$loc  = strtolower( (string) $loc );
		$path = (string) wp_parse_url( $loc, PHP_URL_PATH );
		$tail = trim( basename( $path ), '/' );
		foreach ( Luxe_Score_Repair_Plugin::noindex_slugs() as $slug ) {
			if ( $tail === $slug || false !== strpos( $path, '/' . $slug ) ) {
				return true;
			}
		}
		if ( 0 === strpos( $tail, 'test-' ) ) {
			return true;
		}
		if ( false !== strpos( $path, '/author/' ) || false !== strpos( $path, '/search' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $loc URL.
	 * @return bool
	 */
	public static function is_shop_loc( $loc ) {
		$loc = strtolower( (string) $loc );
		if ( false !== strpos( $loc, 'shop-shopping-guide-for-top-products' ) ) {
			return true;
		}
		if ( function_exists( 'wc_get_page_id' ) ) {
			$shop = wc_get_page_id( 'shop' );
			if ( $shop && $shop > 0 ) {
				$permalink = get_permalink( $shop );
				if ( is_string( $permalink ) && $permalink && untrailingslashit( $permalink ) === untrailingslashit( $loc ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @return bool
	 */
	public static function is_sitemap_request() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		return (bool) preg_match( '#/(sitemap_index\.xml|[^/]*sitemap[^/]*\.xml)(\?|$)#i', $uri );
	}

	/**
	 * @return bool
	 */
	public static function is_page_sitemap_request() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		return ( false !== strpos( $uri, 'page-sitemap' ) );
	}
}
