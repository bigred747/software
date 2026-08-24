<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only Amazon resolver. Never rewrites a live URL. Tag lock luxetrendse0f-20.
 */
class Luxe_BMC_Amazon {

	/**
	 * Existing product Amazon URL, unchanged.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function product_url( $product_id ) {
		$product_id = (int) $product_id;
		if ( $product_id < 1 ) {
			return '';
		}
		$keys = array( '_product_url', '_amazon_url', 'product_url', '_affiliate_url' );
		foreach ( $keys as $key ) {
			$url = trim( (string) get_post_meta( $product_id, $key, true ) );
			if ( self::is_amazon( $url ) ) {
				return $url;
			}
		}
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product && method_exists( $product, 'get_product_url' ) ) {
				$url = trim( (string) $product->get_product_url() );
				if ( self::is_amazon( $url ) ) {
					return $url;
				}
			}
		}
		return '';
	}

	/**
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_amazon( $url ) {
		return (bool) preg_match( '#https?://(www\.)?amazon\.[a-z.]+/#i', (string) $url );
	}

	/**
	 * Tag is locked. Foreign tags fail. Missing tag on an otherwise valid URL is yellow, not rewritten.
	 *
	 * @param string $url URL.
	 * @return string green|yellow|red
	 */
	public static function tag_level( $url ) {
		if ( ! self::is_amazon( $url ) ) {
			return 'red';
		}
		if ( preg_match( '/[?&]tag=([A-Za-z0-9_-]+)/', $url, $m ) ) {
			return ( strtolower( $m[1] ) === strtolower( Luxe_BMC_Plugin::TAG ) ) ? 'green' : 'red';
		}
		return 'yellow';
	}

	/**
	 * Direct /dp/ ASIN form without mutating the stored URL.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_direct_dp( $url ) {
		return (bool) preg_match( '#amazon\.[^/]+/dp/[A-Z0-9]{10}#i', (string) $url );
	}
}
