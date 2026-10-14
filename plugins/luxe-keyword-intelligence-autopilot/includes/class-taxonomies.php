<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Low-value attribute archives. item-weight and date-first-available are always included.
 */
class Luxe_KIA_Taxonomies {

	/**
	 * @return array<int,string>
	 */
	public static function required() {
		return array(
			'item-weight',
			'date-first-available',
			'product-shipping-class',
			'item-dimensions',
			'package-dimensions',
			'package-weight',
			'item-package-quantity',
			'part-number',
			'model-number',
			'warranty',
			'pa_item-weight',
			'pa_date-first-available',
			'pa_product-shipping-class',
			'pa_item-dimensions',
			'pa_package-dimensions',
			'pa_package-weight',
			'pa_item-package-quantity',
			'pa_part-number',
			'pa_model-number',
			'pa_warranty',
		);
	}

	/**
	 * @param string $configured Saved slug list.
	 * @return array<int,string>
	 */
	public static function merge( $configured ) {
		$extra = preg_split( '/[\s,]+/', strtolower( (string) $configured ) );
		if ( ! is_array( $extra ) ) {
			$extra = array();
		}
		$all = array_merge( self::required(), $extra );
		$out = array();
		foreach ( $all as $slug ) {
			$slug = trim( (string) $slug );
			if ( '' === $slug || ! preg_match( '/^[a-z0-9_-]{2,64}$/', $slug ) ) {
				continue;
			}
			if ( in_array( $slug, array( 'product_cat', 'product_tag', 'category', 'post_tag' ), true ) ) {
				continue;
			}
			$out[] = $slug;
		}
		return array_values( array_unique( $out ) );
	}
}
