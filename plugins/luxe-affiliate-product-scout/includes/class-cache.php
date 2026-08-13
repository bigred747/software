<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LAPS_Cache' ) ) {
	return;
}

/**
 * LiteSpeed / WooCommerce cache flush only. Does not call Hostinger CDN.
 */
class LAPS_Cache {

	/**
	 * Flush product caches after repair or audit.
	 */
	public static function flush() {
		if ( class_exists( 'WC_Cache_Helper' ) && method_exists( 'WC_Cache_Helper', 'invalidate_cache_group' ) ) {
			WC_Cache_Helper::invalidate_cache_group( 'product' );
		}
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
		}
		if ( class_exists( '\LiteSpeed\Purge' ) && method_exists( '\LiteSpeed\Purge', 'purge_all' ) ) {
			\LiteSpeed\Purge::purge_all();
		}
	}

	/**
	 * @param int $product_id Product ID.
	 */
	public static function product( $product_id ) {
		clean_post_cache( $product_id );
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $product_id );
		}
	}
}
