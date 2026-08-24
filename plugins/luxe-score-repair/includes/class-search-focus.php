<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Point Google at products and published buyer guides.
 * Never publishes, never writes post_content, never rewrites Amazon URLs,
 * never fakes Analytics or Search Console traffic.
 */
class Luxe_Score_Repair_Search_Focus {

	const SITEMAP_PATH = '/luxe-search-sitemap.xml';

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
	 * Register sitemap and taxonomy filters.
	 */
	public function boot() {
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'search_focus' ) ) {
			return;
		}
		add_action( 'init', array( $this, 'maybe_serve_sitemap' ), 0 );
		add_filter( 'rank_math/sitemap/exclude_taxonomy', array( $this, 'exclude_taxonomy' ), 99, 2 );
		add_filter( 'wpseo_sitemap_exclude_taxonomy', array( $this, 'exclude_taxonomy_yoast' ), 99, 2 );
		add_filter( 'wp_sitemaps_taxonomies', array( $this, 'core_sitemaps_taxonomies' ), 99 );
		add_filter( 'rank_math/sitemap/entry', array( $this, 'sitemap_entry' ), 99, 3 );
		add_action( 'send_headers', array( $this, 'robots_header' ), 30 );
	}

	/**
	 * Product tags, WooCommerce attributes, and other thin listing archives.
	 * Product categories stay indexable.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return bool
	 */
	public static function is_thin_taxonomy_name( $taxonomy ) {
		$taxonomy = strtolower( trim( (string) $taxonomy ) );
		if ( '' === $taxonomy ) {
			return false;
		}
		if ( 0 === strpos( $taxonomy, 'pa_' ) ) {
			return true;
		}
		$thin = array(
			'product_tag',
			'product_shipping_class',
			'product_visibility',
			'date-first-available',
		);
		return in_array( $taxonomy, $thin, true );
	}

	/**
	 * Path-only check so learning and sitemaps work without the WP query.
	 *
	 * @param string $path Request path.
	 * @return bool
	 */
	public static function is_thin_taxonomy_path( $path ) {
		$path = strtolower( (string) $path );
		$path = '/' . trim( $path, '/' );
		if ( '/' === $path ) {
			return false;
		}
		if ( preg_match( '#^/(product-tag|date-first-available|product-shipping-class)(/|$)#', $path ) ) {
			return true;
		}
		if ( preg_match( '#^/pa-[a-z0-9-]+(/|$)#', $path ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Utility or thin URLs that must not appear in sitemaps.
	 *
	 * @param string $path Request path.
	 * @return bool
	 */
	public static function is_junk_sitemap_path( $path ) {
		$path = '/' . trim( strtolower( (string) $path ), '/' );
		if ( '/' === $path ) {
			return false;
		}
		if ( self::is_thin_taxonomy_path( $path ) ) {
			return true;
		}
		$slug = basename( $path );
		if ( 0 === strpos( $slug, 'test-' ) ) {
			return true;
		}
		return in_array( $slug, Luxe_Score_Repair_Plugin::noindex_slugs(), true );
	}

	/**
	 * Current public request is a thin archive Google should not rank.
	 *
	 * @return bool
	 */
	public function is_thin_request() {
		if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
			return true;
		}
		if ( function_exists( 'is_tax' ) && is_tax() ) {
			$obj = get_queried_object();
			$tax = ( $obj && isset( $obj->taxonomy ) ) ? (string) $obj->taxonomy : '';
			if ( self::is_thin_taxonomy_name( $tax ) ) {
				return true;
			}
		}
		if ( self::is_thin_taxonomy_path( Luxe_Score_Repair_Plugin::request_path() ) ) {
			return true;
		}
		return $this->is_filtered_listing();
	}

	/**
	 * Layered-nav / price-filter URLs on shop and tax archives.
	 *
	 * @return bool
	 */
	private function is_filtered_listing() {
		if ( function_exists( 'is_filtered' ) && is_filtered() ) {
			return true;
		}
		if ( empty( $_GET ) || ! is_array( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		$on_listing = ( function_exists( 'is_shop' ) && is_shop() )
			|| ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() );
		if ( ! $on_listing ) {
			return false;
		}
		foreach ( array_keys( $_GET ) as $key ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$key = strtolower( (string) $key );
			if ( 0 === strpos( $key, 'filter_' ) || 0 === strpos( $key, 'query_type_' ) ) {
				return true;
			}
			if ( in_array( $key, array( 'min_price', 'max_price' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param bool   $exclude  Current exclude.
	 * @param string $taxonomy Taxonomy.
	 * @return bool
	 */
	public function exclude_taxonomy( $exclude, $taxonomy ) {
		if ( self::is_thin_taxonomy_name( $taxonomy ) ) {
			return true;
		}
		return (bool) $exclude;
	}

	/**
	 * @param bool   $exclude  Current exclude.
	 * @param string $taxonomy Taxonomy.
	 * @return bool
	 */
	public function exclude_taxonomy_yoast( $exclude, $taxonomy ) {
		return $this->exclude_taxonomy( $exclude, $taxonomy );
	}

	/**
	 * @param array $taxonomies Taxonomies.
	 * @return array
	 */
	public function core_sitemaps_taxonomies( $taxonomies ) {
		if ( ! is_array( $taxonomies ) ) {
			return $taxonomies;
		}
		foreach ( array_keys( $taxonomies ) as $name ) {
			if ( self::is_thin_taxonomy_name( $name ) ) {
				unset( $taxonomies[ $name ] );
			}
		}
		return $taxonomies;
	}

	/**
	 * Drop thin tags and utility pages from Rank Math sitemaps.
	 *
	 * @param array|false $url    Entry.
	 * @param string      $type   Type.
	 * @param mixed       $object Object.
	 * @return array|false
	 */
	public function sitemap_entry( $url, $type, $object ) {
		unset( $type, $object );
		if ( false === $url || ! is_array( $url ) ) {
			return $url;
		}
		$loc  = isset( $url['loc'] ) ? (string) $url['loc'] : '';
		$path = wp_parse_url( $loc, PHP_URL_PATH );
		if ( is_string( $path ) && self::is_junk_sitemap_path( $path ) ) {
			return false;
		}
		return $url;
	}

	/**
	 * Extra noindex header so cached HTML still tells Google not to rank tags.
	 */
	public function robots_header() {
		if ( is_admin() || headers_sent() ) {
			return;
		}
		if ( ! $this->is_thin_request() ) {
			return;
		}
		header( 'X-Robots-Tag: noindex, follow', false );
	}

	/**
	 * Serve a small sitemap of money pages. No rewrite flush required.
	 */
	public function maybe_serve_sitemap() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		$path = Luxe_Score_Repair_Plugin::request_path();
		if ( self::SITEMAP_PATH !== $path ) {
			return;
		}
		nocache_headers();
		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, follow', true );
		header( 'Cache-Control: public, max-age=300' );
		status_header( 200 );
		echo $this->sitemap_xml(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Homepage, shop, published guides, product categories. Not tags.
	 *
	 * @return string
	 */
	public function sitemap_xml() {
		$urls = $this->sitemap_urls();
		$out  = array(
			'<?xml version="1.0" encoding="UTF-8"?>',
			'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
		);
		foreach ( $urls as $url ) {
			$out[] = '  <url>';
			$out[] = '    <loc>' . esc_url( $url ) . '</loc>';
			$out[] = '  </url>';
		}
		$out[] = '</urlset>';
		$out[] = '';
		return implode( "\n", $out );
	}

	/**
	 * @return string[]
	 */
	public function sitemap_urls() {
		$urls = array(
			home_url( '/' ),
			home_url( '/blogs/' ),
		);
		if ( function_exists( 'wc_get_page_id' ) ) {
			$shop_id = (int) wc_get_page_id( 'shop' );
			if ( $shop_id > 0 ) {
				$shop = get_permalink( $shop_id );
				if ( is_string( $shop ) && $shop ) {
					$urls[] = $shop;
				}
			}
		}
		foreach ( self::published_guides( 40 ) as $guide ) {
			$urls[] = $guide['url'];
		}
		if ( function_exists( 'taxonomy_exists' ) && taxonomy_exists( 'product_cat' ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => true,
					'number'     => 200,
				)
			);
			if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$link = get_term_link( $term );
					if ( is_string( $link ) && $link ) {
						$urls[] = $link;
					}
				}
			}
		}
		$clean = array();
		$seen  = array();
		foreach ( $urls as $url ) {
			$url = esc_url_raw( $url );
			if ( ! $url ) {
				continue;
			}
			$path = wp_parse_url( $url, PHP_URL_PATH );
			if ( is_string( $path ) && self::is_junk_sitemap_path( $path ) ) {
				continue;
			}
			$key = untrailingslashit( strtolower( $url ) );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$clean[]      = $url;
		}
		return array_slice( $clean, 0, 400 );
	}

	/**
	 * Published buyer guides only. Never drafts.
	 *
	 * @param int $limit Max posts.
	 * @return array<int,array{url:string,title:string}>
	 */
	public static function published_guides( $limit = 24 ) {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}
		$posts = get_posts(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'numberposts'         => max( 1, (int) $limit ),
				'orderby'             => 'date',
				'order'               => 'DESC',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			)
		);
		$out = array();
		foreach ( $posts as $post ) {
			$url   = get_permalink( $post );
			$title = get_the_title( $post );
			$title = Luxe_Score_Repair_Content::clean_title_text( is_string( $title ) ? $title : '' );
			if ( ! is_string( $url ) || ! $url || ! $title ) {
				continue;
			}
			$out[] = array(
				'url'   => $url,
				'title' => $title,
			);
		}
		return $out;
	}
}
