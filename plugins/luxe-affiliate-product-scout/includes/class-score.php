<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LAPS_Score' ) ) {
	return;
}

/**
 * Honest 95–100 Product Integrity / Catalog Readiness.
 *
 * A 95–100 score requires supported brand evidence, ASIN, price, a primary
 * image, and no hard risk flags. Missing ratings, seller, or warranty data
 * never creates fake evidence and does not lower an otherwise valid product.
 * Taxonomy/brand-attribute leftovers are repaired; they do not cap a product
 * that already meets those gates.
 */
class LAPS_Score {

	const META_READINESS = '_laps_readiness';
	const META_INTEGRITY = '_laps_integrity';
	const META_STATUS    = '_laps_status';
	const META_READY     = '_laps_homepage_ready';
	const META_REASONS   = '_laps_reasons';
	const META_BRAND     = '_laps_brand';
	const META_ASIN      = '_laps_asin';
	const META_AT        = '_laps_audited_at';
	const META_CONFLICTS = '_laps_conflicts';

	/**
	 * Alias keys written so leftover 5.6.4 readers still see scores.
	 *
	 * @return string[][]
	 */
	public static function aliases() {
		return array(
			self::META_READINESS => array(
				'_luxe_aps_readiness',
				'laps_readiness_score',
				'_laps_readiness_score',
				'_luxe_catalog_readiness',
				'_luxe_aps_catalog_readiness',
			),
			self::META_INTEGRITY => array(
				'_luxe_aps_integrity',
				'laps_integrity_score',
				'_laps_integrity_score',
				'_luxe_product_integrity',
				'_luxe_aps_product_integrity',
			),
			self::META_STATUS    => array( '_luxe_aps_status', 'laps_status' ),
			self::META_READY     => array( '_luxe_aps_homepage_ready', 'laps_homepage_ready', '_luxe_homepage_ready' ),
			self::META_BRAND     => array( '_luxe_aps_brand' ),
			self::META_ASIN      => array( '_luxe_aps_asin' ),
		);
	}

	/**
	 * Pure 95–100 decision used by evaluate() and tests.
	 *
	 * @param array $facts Brand/ASIN/price/image/risk facts.
	 * @return array
	 */
	public static function decide( $facts ) {
		$brand     = ! empty( $facts['brand'] ) ? (string) $facts['brand'] : '';
		$asin      = ! empty( $facts['asin'] );
		$price     = ! empty( $facts['price'] );
		$image     = ! empty( $facts['image'] );
		$hard      = ! empty( $facts['hard_risk'] );
		$images    = isset( $facts['image_count'] ) ? (int) $facts['image_count'] : ( $image ? 1 : 0 );
		$model     = ! empty( $facts['model'] );
		$category  = ! empty( $facts['category'] );
		$tags      = ! empty( $facts['tags'] );
		$reasons   = array();
		$status    = 'REVIEW';

		if ( $hard ) {
			$reasons[] = 'used-refurbished-or-parts';
			$status    = 'HOLD';
		}
		if ( ! $brand ) {
			$reasons[] = 'unknown-brand';
			$status    = 'HOLD';
		}
		if ( ! $asin ) {
			$reasons[] = 'missing-asin';
		}
		if ( ! $price ) {
			$reasons[] = 'missing-price';
		}
		if ( ! $image ) {
			$reasons[] = 'missing-image';
		}
		if ( ! empty( $facts['taxonomy_conflict'] ) ) {
			$reasons[] = 'taxonomy-conflict';
		}
		if ( ! empty( $facts['attribute_conflict'] ) ) {
			$reasons[] = 'brand-attribute-conflict';
		}
		if ( ! empty( $facts['seo_conflict'] ) ) {
			$reasons[] = 'rank-math-brand-conflict';
		}
		if ( ! empty( $facts['public_marker'] ) ) {
			$reasons[] = 'public-internal-marker';
		}
		if ( $images > 0 && $images < 3 ) {
			$reasons[] = 'low-image-count';
		}
		if ( $brand && ! $model ) {
			$reasons[] = 'model-not-clear';
		}

		$readiness = 0;
		if ( $brand ) {
			$readiness += 20;
		}
		if ( $asin ) {
			$readiness += 20;
		}
		if ( $price ) {
			$readiness += 20;
		}
		if ( $image ) {
			$readiness += 20;
		}
		if ( $category ) {
			$readiness += 5;
		}
		if ( $tags ) {
			$readiness += 5;
		}
		if ( $images >= 3 ) {
			$readiness += 10;
		} elseif ( $image ) {
			$readiness += 6;
		}

		$core_ok = ( '' !== $brand ) && $asin && $price && $image && 'HOLD' !== $status;

		if ( 'HOLD' === $status ) {
			$integrity = min( 70, $readiness );
			$readiness = min( 94, $readiness );
		} elseif ( ! $core_ok ) {
			$integrity = min( 94, $readiness );
			$readiness = min( 94, $readiness );
			$status    = 'REVIEW';
		} else {
			$readiness = max( 95, min( 100, $readiness ) );
			$integrity = $model ? 100 : 95;
			$status    = 'READY';
		}

		$ready = ( $readiness >= 95 && $integrity >= 95 && 'READY' === $status );

		return array(
			'readiness' => (int) $readiness,
			'integrity' => (int) $integrity,
			'status'    => $status,
			'ready'     => $ready ? 'yes' : 'no',
			'reasons'   => array_values( array_unique( $reasons ) ),
			'core_ok'   => $core_ok,
		);
	}

	/**
	 * @param WC_Product $product Product.
	 * @param array      $repair  Optional repair result flags. Live data wins.
	 * @return array
	 */
	public static function evaluate( $product, $repair = array() ) {
		$title = LAPS_Catalog::plain( $product->get_name() );
		$sku   = LAPS_Catalog::plain( $product->get_sku() );
		$blob  = $title . ' ' . $sku;
		$brand = LAPS_Catalog::product_brand( $product );
		if ( ! $brand ) {
			$brand = LAPS_Catalog::title_brand( $title );
		}
		$asin   = LAPS_Catalog::product_asin( $product );
		$price  = LAPS_Catalog::has_price( $product );
		$image  = LAPS_Catalog::has_image( $product );
		$opt    = LAPS_Catalog::optional_evidence( $product );
		$images = LAPS_Catalog::image_count( $product );
		$model  = LAPS_Catalog::has_model_signal( $title, $sku );

		$live = self::live_conflicts( $product, $brand, $title );
		$facts = array(
			'brand'               => $brand,
			'asin'                => (bool) $asin,
			'price'               => $price,
			'image'               => $image,
			'hard_risk'           => LAPS_Catalog::is_hard_risk( $blob ),
			'image_count'         => $images,
			'model'               => $model,
			'category'            => self::has_terms( (int) $product->get_id(), 'product_cat' ),
			'tags'                => self::has_terms( (int) $product->get_id(), 'product_tag' ),
			'taxonomy_conflict'   => $live['taxonomy'],
			'attribute_conflict'  => $live['attribute'],
			'seo_conflict'        => $live['seo'],
			'public_marker'       => $live['marker'],
		);
		unset( $repair );

		$score = self::decide( $facts );
		$score['id']        = (int) $product->get_id();
		$score['title']     = $title;
		$score['brand']     = $brand ? $brand : 'Unknown';
		$score['asin']      = $asin;
		$score['conflicts'] = $live['labels'];
		$score['optional']  = $opt;
		$score['bucket']    = LAPS_Catalog::bucket( $title, $brand );
		return $score;
	}

	/**
	 * @param int    $product_id Product ID.
	 * @param string $taxonomy   Taxonomy.
	 * @return bool
	 */
	private static function has_terms( $product_id, $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return false;
		}
		$terms = wp_get_object_terms( $product_id, $taxonomy, array( 'fields' => 'ids' ) );
		return ! is_wp_error( $terms ) && ! empty( $terms );
	}

	/**
	 * @param WC_Product $product Product.
	 * @param string     $brand   Brand.
	 * @param string     $title   Title.
	 * @return array
	 */
	public static function live_conflicts( $product, $brand, $title ) {
		$out = array(
			'taxonomy'  => false,
			'attribute' => false,
			'seo'       => false,
			'marker'    => false,
			'labels'    => array(),
		);
		$id = (int) $product->get_id();
		if ( taxonomy_exists( 'product_cat' ) ) {
			$terms = wp_get_object_terms( $id, 'product_cat' );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$label = $term->name . ' ' . $term->slug;
					if ( $brand && LAPS_Catalog::category_conflicts( $label, $brand, $title ) ) {
						$out['taxonomy'] = true;
						$out['labels'][] = 'cat:' . $term->name;
					}
				}
			}
		}
		foreach ( array( 'pa_brand', 'pa_manufacturer' ) as $tax ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$names = wp_get_object_terms( $id, $tax, array( 'fields' => 'names' ) );
			if ( is_wp_error( $names ) || empty( $names ) ) {
				continue;
			}
			foreach ( (array) $names as $name ) {
				if ( $brand && ! LAPS_Catalog::brand_matches( $name, $brand ) ) {
					$out['attribute'] = true;
					$out['labels'][]  = $tax . '=' . LAPS_Catalog::plain( $name );
				}
			}
		}
		$attrs = $product->get_attributes();
		if ( is_array( $attrs ) ) {
			foreach ( $attrs as $attr ) {
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
				if ( $brand && $first && ! LAPS_Catalog::brand_matches( $first, $brand ) ) {
					$out['attribute'] = true;
					$out['labels'][]  = 'custom-' . $name . '=' . $first;
				}
			}
		}
		$meta_brand = LAPS_Catalog::plain( (string) $product->get_meta( '_brand', true ) );
		if ( $brand && $meta_brand && ! LAPS_Catalog::brand_matches( $meta_brand, $brand ) ) {
			$out['attribute'] = true;
			$out['labels'][]  = '_brand=' . $meta_brand;
		}
		$rm_title = LAPS_Catalog::plain( (string) get_post_meta( $id, 'rank_math_title', true ) );
		$rm_desc  = LAPS_Catalog::plain( (string) get_post_meta( $id, 'rank_math_description', true ) );
		$rm_kw    = LAPS_Catalog::plain( (string) get_post_meta( $id, 'rank_math_focus_keyword', true ) );
		$blob     = $rm_title . ' ' . $rm_desc . ' ' . $rm_kw;
		if ( $brand && $blob ) {
			foreach ( LAPS_Catalog::brands() as $other ) {
				$other = LAPS_Catalog::canonical_brand( $other );
				if ( 0 === strcasecmp( $other, $brand ) ) {
					continue;
				}
				if ( preg_match( '/\b' . preg_quote( $other, '/' ) . '\b/i', $blob ) ) {
					$out['seo']      = true;
					$out['labels'][] = 'rank-math:' . $other;
					break;
				}
			}
		}
		$content = LAPS_Catalog::plain( $product->get_description() . ' ' . $product->get_short_description() );
		$data    = LAPS_Catalog::data();
		$markers = isset( $data['markers'] ) ? $data['markers'] : array();
		foreach ( $markers as $mark ) {
			if ( false !== stripos( $content, $mark ) ) {
				$out['marker']   = true;
				$out['labels'][] = 'marker:' . $mark;
			}
		}
		return $out;
	}

	/**
	 * Persist scores. Never changes post status.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $score      Score row.
	 */
	public static function write( $product_id, $score ) {
		$pairs = array(
			self::META_READINESS => (string) (int) $score['readiness'],
			self::META_INTEGRITY => (string) (int) $score['integrity'],
			self::META_STATUS    => (string) $score['status'],
			self::META_READY     => (string) $score['ready'],
			self::META_REASONS   => wp_json_encode( $score['reasons'] ),
			self::META_BRAND     => (string) $score['brand'],
			self::META_ASIN      => (string) $score['asin'],
			self::META_AT        => (string) time(),
			self::META_CONFLICTS => wp_json_encode( $score['conflicts'] ),
		);
		foreach ( $pairs as $key => $value ) {
			update_post_meta( $product_id, $key, $value );
			$aliases = self::aliases();
			if ( isset( $aliases[ $key ] ) ) {
				foreach ( $aliases[ $key ] as $alias ) {
					$stored = $value;
					if ( self::META_READY === $key && '_luxe_homepage_ready' === $alias ) {
						$stored = ( 'yes' === $score['ready'] ) ? '1' : '0';
					}
					update_post_meta( $product_id, $alias, $stored );
				}
			}
		}
	}

	/**
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public static function read( $product_id ) {
		$readiness = (int) get_post_meta( $product_id, self::META_READINESS, true );
		$integrity = (int) get_post_meta( $product_id, self::META_INTEGRITY, true );
		if ( ! $readiness ) {
			$readiness = (int) get_post_meta( $product_id, '_luxe_aps_readiness', true );
		}
		if ( ! $integrity ) {
			$integrity = (int) get_post_meta( $product_id, '_luxe_aps_integrity', true );
		}
		$ready = (string) get_post_meta( $product_id, self::META_READY, true );
		if ( '' === $ready ) {
			$ready = (string) get_post_meta( $product_id, '_luxe_homepage_ready', true );
		}
		return array(
			'readiness' => $readiness,
			'integrity' => $integrity,
			'status'    => (string) get_post_meta( $product_id, self::META_STATUS, true ),
			'ready'     => $ready,
			'brand'     => (string) get_post_meta( $product_id, self::META_BRAND, true ),
			'asin'      => (string) get_post_meta( $product_id, self::META_ASIN, true ),
			'reasons'   => json_decode( (string) get_post_meta( $product_id, self::META_REASONS, true ), true ),
		);
	}
}
