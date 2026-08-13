<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce related products stay in the same real product bucket.
 * Audited products below 95 Readiness or Integrity are excluded.
 */
class LAPS_Related {

	/**
	 * Register filters.
	 */
	public function boot() {
		add_filter( 'woocommerce_related_products', array( $this, 'filter_ids' ), 40, 3 );
		add_filter( 'woocommerce_product_related_posts_query', array( $this, 'filter_query' ), 40, 2 );
	}

	/**
	 * @param array $related Related IDs.
	 * @param int   $product_id Product ID.
	 * @param array $args Args.
	 * @return array
	 */
	public function filter_ids( $related, $product_id, $args ) {
		unset( $args );
		if ( ! is_array( $related ) ) {
			return array();
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return $related;
		}
		$bucket = LAPS_Catalog::bucket( $product->get_name(), LAPS_Catalog::title_brand( $product->get_name() ) );
		$keep   = array();
		foreach ( $related as $id ) {
			$id   = (int) $id;
			$item = wc_get_product( $id );
			if ( ! $item ) {
				continue;
			}
			$score = LAPS_Score::read( $id );
			if ( (int) $score['readiness'] < 95 || (int) $score['integrity'] < 95 ) {
				continue;
			}
			$other = LAPS_Catalog::bucket( $item->get_name(), LAPS_Catalog::title_brand( $item->get_name() ) );
			if ( $other !== $bucket ) {
				continue;
			}
			$keep[] = $id;
		}
		return $keep;
	}

	/**
	 * @param array $query Query.
	 * @param int   $product_id Product ID.
	 * @return array
	 */
	public function filter_query( $query, $product_id ) {
		unset( $product_id );
		return $query;
	}
}
