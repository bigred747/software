<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LDS_Placer' ) ) {
	return;
}

/**
 * Creates unpublished WooCommerce drafts for 100-score rows only.
 * Never publishes, never invents an ASIN or affiliate URL, never edits an existing product.
 */
class LDS_Placer {

	const META_CANDIDATE   = '_lds_candidate';
	const META_FINGERPRINT = '_lds_fingerprint';

	/**
	 * Rows that can reach integrity 100. A 95 row stays off this list.
	 *
	 * @param array<int,array<string,mixed>> $downloads Download rows.
	 * @return array<int,array<string,mixed>>
	 */
	public static function very_best( $downloads ) {
		$out = array();
		foreach ( (array) $downloads as $row ) {
			if ( empty( $row['download'] ) ) {
				continue;
			}
			if ( (int) $row['integrity'] < 100 ) {
				continue;
			}
			$brand = isset( $row['brand'] ) ? (string) $row['brand'] : '';
			$model = isset( $row['model'] ) ? (string) $row['model'] : '';
			$price = isset( $row['price'] ) ? (float) $row['price'] : 0.0;
			if ( '' === $brand || 'Unknown' === $brand || '' === $model || $price <= 0 ) {
				continue;
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Category name for a product. Uses a live category when one already matches.
	 *
	 * @param string   $title    Title.
	 * @param string   $brand    Brand.
	 * @param string[] $existing Existing category names.
	 * @return string
	 */
	public static function category_for( $title, $brand, $existing = array() ) {
		if ( ! class_exists( 'LAPS_Catalog' ) ) {
			return '';
		}
		$title  = LAPS_Catalog::plain( $title );
		$brand  = LAPS_Catalog::plain( $brand );
		$bucket = LAPS_Catalog::bucket( $title, $brand );
		if ( 'general' === $bucket && class_exists( 'LDS_Picker' ) && '' !== LDS_Picker::family_key( $title ) ) {
			$bucket = preg_match( '/\bfish/i', $title ) ? 'fishing-drone' : 'drone';
		}
		if ( LAPS_Catalog::is_mac( $title, $brand ) ) {
			$bucket = 'macbook';
		}
		$aliases = self::aliases( $bucket );
		if ( ! $aliases ) {
			return '';
		}
		foreach ( (array) $existing as $name ) {
			$name = trim( (string) $name );
			foreach ( $aliases as $alias ) {
				if ( 0 === strcasecmp( $name, $alias ) ) {
					return $name;
				}
			}
		}
		return $aliases[0];
	}

	/**
	 * @param string $bucket Bucket key.
	 * @return string[]
	 */
	public static function aliases( $bucket ) {
		$map = array(
			'drone'            => array( 'Drones', 'Drone', 'Camera Drones' ),
			'fishing-drone'    => array( 'Fishing Drones', 'Fishing Drone' ),
			'earbuds'          => array( 'Earbuds', 'Earbud' ),
			'headphones'       => array( 'Headphones', 'Headphone' ),
			'apple-watch'      => array( 'Apple Watches', 'Apple Watch' ),
			'samsung-watch'    => array( 'Samsung Watches', 'Samsung Watch' ),
			'luxury-watch'     => array( 'Luxury Watches', 'Luxury Watch' ),
			'watch'            => array( 'Watches', 'Watch' ),
			'projector'        => array( 'Projectors', 'Projector' ),
			'tv'               => array( 'TVs', 'TV', 'Televisions' ),
			'laptop'           => array( 'Laptops', 'Laptop' ),
			'macbook'          => array( 'MacBooks', 'MacBook', 'Apple Laptops' ),
			'laptop-accessory' => array( 'Laptop Accessories', 'Laptop Accessory' ),
			'phone'            => array( 'Phones', 'Phone' ),
			'action-camera'    => array( 'Action Cameras', 'Action Camera' ),
			'desktop'          => array( 'Desktops', 'Desktop' ),
		);
		return isset( $map[ $bucket ] ) ? $map[ $bucket ] : array();
	}

	/**
	 * @param array<int,array<string,mixed>> $rows Very best rows.
	 * @return array<string,mixed>
	 */
	public static function place( $rows ) {
		$out = array(
			'created' => array(),
			'skipped' => array(),
			'error'   => '',
		);
		if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Product_Simple' ) ) {
			$out['error'] = 'woocommerce-missing';
			return $out;
		}
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			$out['error'] = 'categories-missing';
			return $out;
		}
		$existing = self::category_names();
		foreach ( (array) $rows as $row ) {
			$title = isset( $row['title'] ) ? LAPS_Catalog::plain( (string) $row['title'] ) : '';
			$brand = isset( $row['brand'] ) ? (string) $row['brand'] : '';
			$price = isset( $row['price'] ) ? (float) $row['price'] : 0.0;
			$model = isset( $row['model'] ) ? (string) $row['model'] : '';
			if ( '' === $title || '' === $brand || $price <= 0 || '' === $model ) {
				continue;
			}
			$identity = LDS_Picker::identity( $title );
			$found    = self::existing_id( $title, $identity['fingerprint'] );
			if ( $found ) {
				$out['skipped'][] = array(
					'title'  => $title,
					'reason' => 'already-in-catalog',
					'id'     => $found,
				);
				continue;
			}
			$category = self::category_for( $title, $brand, $existing );
			if ( '' === $category ) {
				$out['skipped'][] = array(
					'title'  => $title,
					'reason' => 'no-category',
					'id'     => 0,
				);
				continue;
			}
			$term_id = self::category_id( $category );
			if ( ! $term_id ) {
				$out['skipped'][] = array(
					'title'  => $title,
					'reason' => 'category-failed',
					'id'     => 0,
				);
				continue;
			}
			$product = new WC_Product_Simple();
			$product->set_name( $title );
			$product->set_status( 'draft' );
			$product->set_catalog_visibility( 'hidden' );
			$product->set_regular_price( self::decimal( $price ) );
			$product->set_reviews_allowed( false );
			$product->set_short_description( '' );
			$product->set_description( '' );
			$product_id = (int) $product->save();
			if ( $product_id < 1 ) {
				$out['skipped'][] = array(
					'title'  => $title,
					'reason' => 'save-failed',
					'id'     => 0,
				);
				continue;
			}
			wp_set_object_terms( $product_id, array( $term_id ), 'product_cat', false );
			update_post_meta( $product_id, '_brand', $brand );
			update_post_meta( $product_id, '_laps_brand', $brand );
			update_post_meta( $product_id, self::META_CANDIDATE, '1' );
			update_post_meta( $product_id, self::META_FINGERPRINT, $identity['fingerprint'] );
			update_post_meta( $product_id, '_lds_model', $model );
			$out['created'][] = array(
				'id'       => $product_id,
				'title'    => $title,
				'brand'    => $brand,
				'category' => $category,
				'price'    => $price,
			);
		}
		return $out;
	}

	/**
	 * Draft candidates stay off the Product Scout scoreboard until someone publishes them.
	 *
	 * @param array<string,mixed> $wp_query_args Query args.
	 * @param array<string,mixed> $query_vars    Original wc_get_products args.
	 * @return array<string,mixed>
	 */
	public static function exclude_candidates( $wp_query_args, $query_vars ) {
		if ( ! empty( $query_vars['lds_include_candidates'] ) ) {
			return $wp_query_args;
		}
		$guard = array(
			'relation' => 'OR',
			array(
				'key'     => self::META_CANDIDATE,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => self::META_CANDIDATE,
				'value'   => '1',
				'compare' => '!=',
			),
		);
		if ( empty( $wp_query_args['meta_query'] ) || ! is_array( $wp_query_args['meta_query'] ) ) {
			$wp_query_args['meta_query'] = $guard;
		} else {
			$wp_query_args['meta_query'] = array(
				'relation' => 'AND',
				$wp_query_args['meta_query'],
				$guard,
			);
		}
		return $wp_query_args;
	}

	/**
	 * Publishing is a manual step. Once published, Product Scout may score the product.
	 *
	 * @param string  $new  New status.
	 * @param string  $old  Old status.
	 * @param WP_Post $post Post.
	 */
	public static function release_on_publish( $new, $old, $post ) {
		unset( $old );
		if ( 'publish' !== $new || ! is_object( $post ) || 'product' !== $post->post_type ) {
			return;
		}
		if ( '1' === (string) get_post_meta( $post->ID, self::META_CANDIDATE, true ) ) {
			delete_post_meta( $post->ID, self::META_CANDIDATE );
		}
	}

	/**
	 * @param float $price Price.
	 * @return string
	 */
	private static function decimal( $price ) {
		if ( function_exists( 'wc_format_decimal' ) ) {
			return (string) wc_format_decimal( $price );
		}
		return number_format( (float) $price, 2, '.', '' );
	}

	/**
	 * @return string[]
	 */
	private static function category_names() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}
		$names = array();
		foreach ( $terms as $term ) {
			if ( is_object( $term ) && isset( $term->name ) ) {
				$names[] = (string) $term->name;
			}
		}
		return $names;
	}

	/**
	 * @param string $name Category name.
	 * @return int
	 */
	private static function category_id( $name ) {
		$term = term_exists( $name, 'product_cat' );
		if ( ! $term ) {
			$by_slug = get_term_by( 'slug', sanitize_title( $name ), 'product_cat' );
			if ( $by_slug && ! is_wp_error( $by_slug ) ) {
				return (int) $by_slug->term_id;
			}
			$term = wp_insert_term( $name, 'product_cat' );
		}
		if ( is_wp_error( $term ) || ! $term ) {
			return 0;
		}
		return is_array( $term ) ? (int) $term['term_id'] : (int) $term;
	}

	/**
	 * @param string $title       Title.
	 * @param string $fingerprint Fingerprint.
	 * @return int
	 */
	private static function existing_id( $title, $fingerprint ) {
		$by_meta = get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'meta_key'         => self::META_FINGERPRINT,
				'meta_value'       => $fingerprint,
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		if ( ! empty( $by_meta ) ) {
			return (int) $by_meta[0];
		}
		$by_title = get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'title'            => $title,
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		if ( ! empty( $by_title ) ) {
			return (int) $by_title[0];
		}
		return 0;
	}
}
