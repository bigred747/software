<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Live taxonomy, brand-attribute, marker, copy, and Rank Math conflict repair.
 * Never publishes, never deletes, never rewrites Amazon URLs, never invents brands.
 */
class LAPS_Repair {

	/**
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public function repair_product( $product ) {
		$product_id = (int) $product->get_id();
		$title      = LAPS_Catalog::plain( $product->get_name() );
		$sku        = LAPS_Catalog::plain( $product->get_sku() );
		$blob       = $title . ' ' . $sku;
		$did        = array();
		$flags      = array(
			'taxonomy_conflict'  => false,
			'attribute_conflict' => false,
			'seo_conflict'       => false,
			'public_marker'      => false,
			'held'               => false,
		);

		if ( LAPS_Catalog::is_hard_risk( $blob ) ) {
			$flags['held'] = true;
			return array(
				'id'     => $product_id,
				'title'  => $title,
				'brand'  => LAPS_Catalog::title_brand( $title ) ? LAPS_Catalog::title_brand( $title ) : 'Unknown',
				'status' => 'held',
				'reason' => 'used-refurbished-or-parts',
				'did'    => array(),
				'flags'  => $flags,
			);
		}

		$brand = LAPS_Catalog::title_brand( $title );
		if ( $brand ) {
			$did = array_merge( $did, $this->sync_brand_attributes( $product, $brand ) );
			$did = array_merge( $did, $this->sync_brand_taxonomies( $product_id, $brand ) );
			$did = array_merge( $did, $this->repair_categories( $product_id, $brand, $title ) );
			$did = array_merge( $did, $this->repair_stale_tags( $product_id, $brand ) );
			$did = array_merge( $did, $this->repair_rank_math( $product_id, $brand, $title ) );
			$did = array_merge( $did, $this->repair_copy( $product, $brand, $title ) );
		}
		$did = array_merge( $did, $this->strip_markers( $product ) );
		$did = array_merge( $did, $this->strip_machine_tags( $product_id ) );

		$live = LAPS_Score::live_conflicts( $product, $brand, $title );
		$flags['taxonomy_conflict']  = $live['taxonomy'];
		$flags['attribute_conflict'] = $live['attribute'];
		$flags['seo_conflict']       = $live['seo'];
		$flags['public_marker']      = $live['marker'];

		$did = array_values( array_unique( array_filter( $did ) ) );
		if ( $did ) {
			LAPS_Cache::product( $product_id );
		}

		return array(
			'id'     => $product_id,
			'title'  => $title,
			'brand'  => $brand ? $brand : 'Unknown',
			'status' => $did ? 'repaired' : 'clean',
			'did'    => $did,
			'flags'  => $flags,
		);
	}

	/**
	 * @param WC_Product $product Product.
	 * @param string     $brand   Brand.
	 * @return string[]
	 */
	private function sync_brand_attributes( $product, $brand ) {
		$did = array();
		foreach ( array( 'pa_brand', 'pa_manufacturer' ) as $tax ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$current = wp_get_object_terms( $product->get_id(), $tax, array( 'fields' => 'names' ) );
			if ( is_wp_error( $current ) ) {
				continue;
			}
			$names = array_map( array( 'LAPS_Catalog', 'plain' ), (array) $current );
			if ( in_array( $brand, $names, true ) && 1 === count( $names ) ) {
				continue;
			}
			if ( $this->set_term_attribute( $product, $tax, $brand ) ) {
				$did[] = 'attribute:' . $tax . '=' . $brand;
			}
		}

		$attrs   = $product->get_attributes();
		$changed = false;
		if ( is_array( $attrs ) ) {
			foreach ( $attrs as $key => $attr ) {
				if ( ! is_object( $attr ) || ! method_exists( $attr, 'get_name' ) ) {
					continue;
				}
				if ( method_exists( $attr, 'is_taxonomy' ) && $attr->is_taxonomy() ) {
					continue;
				}
				$name = strtolower( LAPS_Catalog::plain( $attr->get_name() ) );
				if ( ! in_array( $name, array( 'brand', 'manufacturer' ), true ) ) {
					continue;
				}
				$options = $attr->get_options();
				$first   = LAPS_Catalog::plain( is_array( $options ) && $options ? (string) reset( $options ) : '' );
				if ( 0 === strcasecmp( $first, $brand ) ) {
					continue;
				}
				$attr->set_options( array( $brand ) );
				$attrs[ $key ] = $attr;
				$changed       = true;
				$did[]         = 'custom-attribute:' . $name . '=' . $brand;
			}
		}
		if ( $changed ) {
			$product->set_attributes( $attrs );
			$product->save();
		}

		$ensure = $this->ensure_brand_attribute( $product, $brand );
		if ( $ensure ) {
			$did[] = $ensure;
		}
		return $did;
	}

	/**
	 * @param WC_Product $product Product.
	 * @param string     $brand   Brand.
	 * @return string
	 */
	private function ensure_brand_attribute( $product, $brand ) {
		if ( taxonomy_exists( 'pa_brand' ) ) {
			$names = wp_get_object_terms( $product->get_id(), 'pa_brand', array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $names ) && $names ) {
				return '';
			}
		}
		$attrs = $product->get_attributes();
		if ( is_array( $attrs ) ) {
			foreach ( $attrs as $attr ) {
				if ( ! is_object( $attr ) || ! method_exists( $attr, 'get_name' ) ) {
					continue;
				}
				$name = strtolower( LAPS_Catalog::plain( $attr->get_name() ) );
				if ( 'brand' === $name || 'pa_brand' === $name ) {
					return '';
				}
			}
		}
		if ( ! class_exists( 'WC_Product_Attribute' ) ) {
			return '';
		}
		if ( ! is_array( $attrs ) ) {
			$attrs = array();
		}
		$attribute = new WC_Product_Attribute();
		$attribute->set_id( 0 );
		$attribute->set_name( 'Brand' );
		$attribute->set_options( array( $brand ) );
		$attribute->set_visible( true );
		$attribute->set_variation( false );
		$attrs['brand'] = $attribute;
		$product->set_attributes( $attrs );
		$product->save();
		return 'added-brand-attribute=' . $brand;
	}

	/**
	 * @param WC_Product $product  Product.
	 * @param string     $taxonomy Taxonomy.
	 * @param string     $value    Value.
	 * @return bool
	 */
	private function set_term_attribute( $product, $taxonomy, $value ) {
		$term = term_exists( $value, $taxonomy );
		if ( ! $term ) {
			$term = wp_insert_term( $value, $taxonomy );
		}
		if ( is_wp_error( $term ) ) {
			return false;
		}
		$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
		$set     = wp_set_object_terms( $product->get_id(), array( $term_id ), $taxonomy, false );
		if ( is_wp_error( $set ) ) {
			return false;
		}
		$attrs = $product->get_attributes();
		if ( ! is_array( $attrs ) ) {
			$attrs = array();
		}
		if ( empty( $attrs[ $taxonomy ] ) && class_exists( 'WC_Product_Attribute' ) ) {
			$attribute = new WC_Product_Attribute();
			$attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
			$attribute->set_name( $taxonomy );
			$attribute->set_options( array( $term_id ) );
			$attribute->set_visible( true );
			$attribute->set_variation( false );
			$attrs[ $taxonomy ] = $attribute;
			$product->set_attributes( $attrs );
			$product->save();
		}
		return true;
	}

	/**
	 * @param int    $product_id Product ID.
	 * @param string $brand      Brand.
	 * @return string[]
	 */
	private function sync_brand_taxonomies( $product_id, $brand ) {
		$did  = array();
		$taxs = array( 'product_brand', 'pwb-brand', 'brand' );
		foreach ( $taxs as $tax ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$current = wp_get_object_terms( $product_id, $tax, array( 'fields' => 'names' ) );
			if ( is_wp_error( $current ) ) {
				continue;
			}
			$names = array_map( array( 'LAPS_Catalog', 'plain' ), (array) $current );
			if ( in_array( $brand, $names, true ) && 1 === count( $names ) ) {
				continue;
			}
			$term = term_exists( $brand, $tax );
			if ( ! $term ) {
				$term = wp_insert_term( $brand, $tax );
			}
			if ( is_wp_error( $term ) ) {
				continue;
			}
			$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
			$set     = wp_set_object_terms( $product_id, array( $term_id ), $tax, false );
			if ( ! is_wp_error( $set ) ) {
				$did[] = 'taxonomy:' . $tax . '=' . $brand;
			}
		}
		return $did;
	}

	/**
	 * @param int    $product_id Product ID.
	 * @param string $brand      Brand.
	 * @param string $title      Title.
	 * @return string[]
	 */
	private function repair_categories( $product_id, $brand, $title ) {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}
		$terms = wp_get_object_terms( $product_id, 'product_cat' );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		$remove = array();
		foreach ( $terms as $term ) {
			$label = $term->name . ' ' . $term->slug;
			if ( LAPS_Catalog::category_conflicts( $label, $brand, $title ) ) {
				$remove[] = (int) $term->term_id;
			}
		}
		$remove = array_values( array_unique( $remove ) );
		if ( ! $remove ) {
			return array();
		}
		$left = array();
		foreach ( $terms as $term ) {
			if ( ! in_array( (int) $term->term_id, $remove, true ) ) {
				$left[] = (int) $term->term_id;
			}
		}
		if ( ! $left ) {
			return array();
		}
		$set = wp_set_object_terms( $product_id, $left, 'product_cat', false );
		if ( is_wp_error( $set ) ) {
			return array();
		}
		return array( 'removed-conflicting-categories:' . implode( ',', $remove ) );
	}

	/**
	 * @param int    $product_id Product ID.
	 * @param string $brand      Brand.
	 * @return string[]
	 */
	private function repair_stale_tags( $product_id, $brand ) {
		if ( ! taxonomy_exists( 'product_tag' ) ) {
			return array();
		}
		$tags = wp_get_object_terms( $product_id, 'product_tag' );
		if ( is_wp_error( $tags ) || empty( $tags ) ) {
			return array();
		}
		$brands  = LAPS_Catalog::brands();
		$keep    = array();
		$removed = array();
		foreach ( $tags as $tag ) {
			$name = LAPS_Catalog::plain( $tag->name );
			$hit  = '';
			foreach ( $brands as $other ) {
				$canon = LAPS_Catalog::canonical_brand( $other );
				if ( 0 === strcasecmp( $name, $other ) || 0 === strcasecmp( $tag->slug, sanitize_title( $other ) ) ) {
					$hit = $canon;
					break;
				}
			}
			if ( $hit && 0 !== strcasecmp( $hit, $brand ) ) {
				$removed[] = $name;
				continue;
			}
			$keep[] = (int) $tag->term_id;
		}
		if ( ! $removed ) {
			return array();
		}
		wp_set_object_terms( $product_id, $keep, 'product_tag', false );
		return array( 'removed-stale-brand-tags:' . implode( ',', $removed ) );
	}

	/**
	 * Deterministic Rank Math brand conflicts only. Keyword Intelligence owns ongoing titles.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $brand      Brand.
	 * @param string $title      Title.
	 * @return string[]
	 */
	private function repair_rank_math( $product_id, $brand, $title ) {
		$did  = array();
		$keys = array( 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword' );
		foreach ( $keys as $key ) {
			$current = (string) get_post_meta( $product_id, $key, true );
			if ( '' === $current ) {
				continue;
			}
			$next = $current;
			foreach ( LAPS_Catalog::brands() as $other ) {
				$other = LAPS_Catalog::canonical_brand( $other );
				if ( 0 === strcasecmp( $other, $brand ) ) {
					continue;
				}
				if ( preg_match( '/\b' . preg_quote( $other, '/' ) . '\b/i', $next ) ) {
					$next = preg_replace( '/\b' . preg_quote( $other, '/' ) . '\b/i', $brand, $next );
				}
			}
			$data = LAPS_Catalog::data();
			foreach ( $data['generic_seo'] as $generic ) {
				if ( false !== stripos( $next, $generic ) ) {
					$next = ( 'rank_math_description' === $key )
						? $title . ' — buyer notes from LuxeTrendsetters.'
						: $title . ' | LuxeTrendsetters';
				}
			}
			if ( $next !== $current ) {
				update_post_meta( $product_id, $key, $next );
				$did[] = 'rank-math:' . $key;
			}
		}
		return $did;
	}

	/**
	 * Strip known internal markers and deterministic generated-copy mismatches.
	 * Preserves post status. Does not rewrite Amazon URLs.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $brand   Brand.
	 * @param string     $title   Title.
	 * @return string[]
	 */
	private function repair_copy( $product, $brand, $title ) {
		$did     = array();
		$post_id = (int) $product->get_id();
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return $did;
		}
		$content = (string) $post->post_content;
		$next    = $content;
		$data    = LAPS_Catalog::data();
		foreach ( $data['markers'] as $mark ) {
			$next = str_ireplace( $mark, '', $next );
		}
		if ( LAPS_Catalog::is_accessory( $title ) ) {
			$next = preg_replace( '/\bthis laptop\b/i', 'this laptop accessory', $next );
			$next = preg_replace( '/\bthe laptop is\b/i', 'this accessory is', $next );
		}
		if ( 0 === strcasecmp( $brand, 'Samsung' ) ) {
			$next = preg_replace( '/\bApple Watches\b/i', 'Samsung Watches', $next );
		}
		if ( 0 === strcasecmp( $brand, 'Apple' ) ) {
			$next = preg_replace( '/\bSamsung Watches\b/i', 'Apple Watches', $next );
		}
		if ( $next !== $content ) {
			$next = $this->preserve_amazon_urls( $content, $next );
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $next,
					'post_status'  => $post->post_status,
				)
			);
			$did[] = 'copy-mismatch-cleanup';
		}
		return $did;
	}

	/**
	 * @param WC_Product $product Product.
	 * @return string[]
	 */
	private function strip_markers( $product ) {
		return array();
	}

	/**
	 * @param int $product_id Product ID.
	 * @return string[]
	 */
	private function strip_machine_tags( $product_id ) {
		if ( ! taxonomy_exists( 'product_tag' ) ) {
			return array();
		}
		$data = LAPS_Catalog::data();
		$bad  = isset( $data['machine_tags'] ) ? $data['machine_tags'] : array();
		$tags = wp_get_object_terms( $product_id, 'product_tag' );
		if ( is_wp_error( $tags ) || empty( $tags ) ) {
			return array();
		}
		$keep    = array();
		$removed = array();
		foreach ( $tags as $tag ) {
			$slug = strtolower( $tag->slug );
			$name = strtolower( LAPS_Catalog::plain( $tag->name ) );
			$hit  = false;
			foreach ( $bad as $mark ) {
				if ( $slug === $mark || $name === $mark || false !== strpos( $slug, $mark ) ) {
					$hit = true;
					break;
				}
			}
			if ( $hit ) {
				$removed[] = $tag->name;
				continue;
			}
			$keep[] = (int) $tag->term_id;
		}
		if ( ! $removed ) {
			return array();
		}
		wp_set_object_terms( $product_id, $keep, 'product_tag', false );
		return array( 'removed-machine-tags:' . implode( ',', $removed ) );
	}

	/**
	 * If copy cleanup accidentally touched an Amazon href, restore original hrefs.
	 *
	 * @param string $original Original HTML.
	 * @param string $next     New HTML.
	 * @return string
	 */
	private function preserve_amazon_urls( $original, $next ) {
		if ( ! preg_match_all( '/https?:\/\/(?:www\.)?amazon\.[^"\'\s<]+/i', $original, $m ) ) {
			return $next;
		}
		$orig_urls = $m[0];
		if ( ! preg_match_all( '/https?:\/\/(?:www\.)?amazon\.[^"\'\s<]+/i', $next, $n ) ) {
			return $original;
		}
		if ( $orig_urls === $n[0] ) {
			return $next;
		}
		return $original;
	}

	/**
	 * Neutral homepage SEO shell when the stored title is still a generic placeholder.
	 * Does not publish. Does not create redirects.
	 *
	 * @return string[]
	 */
	public function repair_homepage_shell() {
		$did     = array();
		$front   = (int) get_option( 'page_on_front' );
		if ( ! $front ) {
			return $did;
		}
		$title = (string) get_post_meta( $front, 'rank_math_title', true );
		$desc  = (string) get_post_meta( $front, 'rank_math_description', true );
		$data  = LAPS_Catalog::data();
		$bad   = false;
		foreach ( $data['generic_seo'] as $generic ) {
			if ( false !== stripos( $title, $generic ) || false !== stripos( $desc, $generic ) ) {
				$bad = true;
				break;
			}
		}
		if ( ! $bad ) {
			return $did;
		}
		update_post_meta( $front, 'rank_math_title', 'LuxeTrendsetters | Luxury Tech, Watches & Drones' );
		update_post_meta( $front, 'rank_math_description', 'Curated luxury tech, watches, drones, audio, and smart gear. Compare premium Amazon picks with clear buyer guides from LuxeTrendsetters.' );
		return array( 'homepage-seo-shell' );
	}

	/**
	 * Create an unpublished About draft when none exists. Never publishes it.
	 *
	 * @return string[]
	 */
	public function maybe_about_draft() {
		$existing = get_page_by_path( 'about' );
		if ( $existing ) {
			return array();
		}
		$q = new WP_Query(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'title'          => 'About',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( $q->have_posts() ) {
			return array();
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => 'About LuxeTrendsetters (draft for review)',
				'post_name'    => 'about-luxetrendsetters-draft',
				'post_content' => "This About draft was created by Product Scout for human review. It is not published.\n\nLuxeTrendsetters is an Amazon Associate. We earn from qualifying purchases.",
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			return array();
		}
		return array( 'about-draft:' . (int) $id );
	}
}
