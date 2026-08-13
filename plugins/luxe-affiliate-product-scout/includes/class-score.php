<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Honest 95–100 Product Integrity / Catalog Readiness.
 * Missing ratings, seller, or warranty are reported unavailable and never invented.
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
			self::META_READINESS => array( '_luxe_aps_readiness', 'laps_readiness_score', '_laps_readiness_score' ),
			self::META_INTEGRITY => array( '_luxe_aps_integrity', 'laps_integrity_score', '_laps_integrity_score' ),
			self::META_STATUS    => array( '_luxe_aps_status', 'laps_status' ),
			self::META_READY     => array( '_luxe_aps_homepage_ready', 'laps_homepage_ready' ),
		);
	}

	/**
	 * @param WC_Product $product Product.
	 * @param array      $repair  Optional repair result.
	 * @return array
	 */
	public static function evaluate( $product, $repair = array() ) {
		$title = LAPS_Catalog::plain( $product->get_name() );
		$sku   = LAPS_Catalog::plain( $product->get_sku() );
		$blob  = $title . ' ' . $sku;
		$brand = LAPS_Catalog::title_brand( $title );
		$asin  = LAPS_Catalog::product_asin( $product );
		$price = LAPS_Catalog::has_price( $product );
		$image = LAPS_Catalog::has_image( $product );
		$opt   = LAPS_Catalog::optional_evidence( $product );

		$reasons   = array();
		$conflicts = array();
		$status    = 'REVIEW';

		if ( LAPS_Catalog::is_hard_risk( $blob ) ) {
			$reasons[] = 'used-refurbished-or-parts';
			$status    = 'HOLD';
		}
		if ( ! $brand ) {
			$reasons[] = 'unknown-brand';
			$status    = 'HOLD';
		}

		$tax_conflict = ! empty( $repair['taxonomy_conflict'] );
		$attr_conflict = ! empty( $repair['attribute_conflict'] );
		$seo_conflict  = ! empty( $repair['seo_conflict'] );
		$marker        = ! empty( $repair['public_marker'] );

		if ( ! $tax_conflict || ! $attr_conflict ) {
			$live = self::live_conflicts( $product, $brand, $title );
			$tax_conflict  = $tax_conflict || $live['taxonomy'];
			$attr_conflict = $attr_conflict || $live['attribute'];
			$seo_conflict  = $seo_conflict || $live['seo'];
			$marker        = $marker || $live['marker'];
			$conflicts     = $live['labels'];
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
		if ( $tax_conflict ) {
			$reasons[] = 'taxonomy-conflict';
		}
		if ( $attr_conflict ) {
			$reasons[] = 'brand-attribute-conflict';
		}
		if ( $seo_conflict ) {
			$reasons[] = 'rank-math-brand-conflict';
		}
		if ( $marker ) {
			$reasons[] = 'public-internal-marker';
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
		if ( ! $tax_conflict ) {
			$readiness += 10;
		}
		if ( ! $attr_conflict ) {
			$readiness += 10;
		}

		$integrity = $readiness;
		$gates_ok  = $brand && $asin && $price && $image && ! $tax_conflict && ! $attr_conflict && ! $seo_conflict && ! $marker && 'HOLD' !== $status;

		if ( 'HOLD' === $status ) {
			$integrity = min( 70, $readiness );
			$readiness = min( 94, $readiness );
		} elseif ( ! $gates_ok ) {
			$integrity = min( 94, $readiness );
			$readiness = min( 94, $readiness );
			$status    = 'REVIEW';
		} else {
			$integrity = 95;
			if ( $opt['rating'] ) {
				$integrity++;
			}
			if ( $opt['seller'] ) {
				$integrity++;
			}
			if ( $opt['warranty'] ) {
				$integrity++;
			}
			if ( $brand && $asin && $price && $image && ! $tax_conflict && ! $attr_conflict ) {
				$integrity++;
			}
			$integrity = min( 100, $integrity );
			$readiness = min( 100, max( 95, $readiness ) );
			$status    = 'READY';
		}

		$ready = ( $readiness >= 95 && $integrity >= 95 && 'READY' === $status );

		return array(
			'id'         => (int) $product->get_id(),
			'title'      => $title,
			'brand'      => $brand ? $brand : 'Unknown',
			'asin'       => $asin,
			'readiness'  => (int) $readiness,
			'integrity'  => (int) $integrity,
			'status'     => $status,
			'ready'      => $ready ? 'yes' : 'no',
			'reasons'    => array_values( array_unique( $reasons ) ),
			'conflicts'  => $conflicts,
			'optional'   => $opt,
			'bucket'     => LAPS_Catalog::bucket( $title, $brand ),
		);
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
						$out['taxonomy']  = true;
						$out['labels'][]  = 'cat:' . $term->name;
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
				if ( 'pa_brand' === $tax && $brand ) {
					$out['attribute'] = true;
					$out['labels'][]  = 'missing:' . $tax;
				}
				continue;
			}
			$plain = array_map( array( 'LAPS_Catalog', 'plain' ), (array) $names );
			if ( $brand && ( count( $plain ) > 1 || 0 !== strcasecmp( $plain[0], $brand ) ) ) {
				$out['attribute'] = true;
				$out['labels'][]  = $tax . '=' . implode( ',', $plain );
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
				if ( $brand && $first && 0 !== strcasecmp( $first, $brand ) ) {
					$out['attribute'] = true;
					$out['labels'][]  = 'custom-' . $name . '=' . $first;
				}
			}
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
					update_post_meta( $product_id, $alias, $value );
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
		return array(
			'readiness' => $readiness,
			'integrity' => $integrity,
			'status'    => (string) get_post_meta( $product_id, self::META_STATUS, true ),
			'ready'     => (string) get_post_meta( $product_id, self::META_READY, true ),
			'brand'     => (string) get_post_meta( $product_id, self::META_BRAND, true ),
			'asin'      => (string) get_post_meta( $product_id, self::META_ASIN, true ),
			'reasons'   => json_decode( (string) get_post_meta( $product_id, self::META_REASONS, true ), true ),
		);
	}
}
