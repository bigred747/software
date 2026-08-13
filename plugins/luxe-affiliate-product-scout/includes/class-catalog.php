<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LAPS_Catalog' ) ) {
	return;
}

/**
 * Brand, ASIN, bucket, and conflict helpers. No database writes.
 */
class LAPS_Catalog {

	/**
	 * @return array<string,mixed>
	 */
	public static function data() {
		static $data = null;
		if ( null === $data ) {
			require_once LAPS_DIR . 'data/catalog.php';
			$data = laps_catalog_data();
		}
		return $data;
	}

	/**
	 * @return string[]
	 */
	public static function brands() {
		$data = self::data();
		return isset( $data['brands'] ) ? $data['brands'] : array();
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	public static function plain( $text ) {
		$text = (string) $text;
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$text = wp_strip_all_tags( $text );
		} else {
			$text = strip_tags( $text );
		}
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$converted = @mb_convert_encoding( $text, 'UTF-8', 'UTF-8' );
			if ( is_string( $converted ) ) {
				$text = $converted;
			}
		}
		$stripped = @preg_replace( '/[\x{200B}-\x{200F}\x{FEFF}]/u', '', $text );
		if ( is_string( $stripped ) ) {
			$text = $stripped;
		}
		$text = str_replace( "\xC2\xA0", ' ', $text );
		$spaced = preg_replace( '/\s+/', ' ', $text );
		return trim( is_string( $spaced ) ? $spaced : $text );
	}

	/**
	 * @param string $brand Brand.
	 * @return string
	 */
	public static function canonical_brand( $brand ) {
		$brand   = self::plain( $brand );
		$data    = self::data();
		$aliases = isset( $data['aliases'] ) ? $data['aliases'] : array();
		if ( isset( $aliases[ $brand ] ) ) {
			return $aliases[ $brand ];
		}
		foreach ( $aliases as $from => $to ) {
			if ( 0 === strcasecmp( $from, $brand ) ) {
				return $to;
			}
		}
		foreach ( self::brands() as $known ) {
			if ( 0 === strcasecmp( $known, $brand ) ) {
				if ( isset( $aliases[ $known ] ) ) {
					return $aliases[ $known ];
				}
				return $known;
			}
		}
		return $brand;
	}

	/**
	 * True when a stored brand value is the same manufacturer as the title brand.
	 * "Bose Corporation" matches "Bose". Missing values are not a match.
	 *
	 * @param string $value Stored brand/manufacturer.
	 * @param string $brand Canonical title brand.
	 * @return bool
	 */
	public static function brand_matches( $value, $brand ) {
		$value = self::plain( $value );
		$brand = self::plain( $brand );
		if ( '' === $value || '' === $brand ) {
			return false;
		}
		$value = self::normalize_visit_store( $value );
		$hit   = self::title_brand( $value );
		if ( $hit && 0 === strcasecmp( $hit, $brand ) ) {
			return true;
		}
		$canon_v = self::canonical_brand( $value );
		$canon_b = self::canonical_brand( $brand );
		if ( $canon_v && $canon_b && 0 === strcasecmp( $canon_v, $canon_b ) ) {
			return true;
		}
		if ( preg_match( '/\b' . preg_quote( $brand, '/' ) . '\b/i', $value ) ) {
			return true;
		}
		if ( preg_match( '/\b' . preg_quote( $canon_b, '/' ) . '\b/i', $value ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Supported brand from title, then local product metadata. Never invents a brand.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function product_brand( $product ) {
		$title = is_object( $product ) && method_exists( $product, 'get_name' ) ? self::plain( $product->get_name() ) : '';
		$hit   = self::title_brand( $title );
		if ( $hit ) {
			return $hit;
		}
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return '';
		}
		$keys = array( '_brand', 'brand', '_wzone_brand', '_amz_brand', 'attribute_pa_brand', '_laps_brand' );
		foreach ( $keys as $key ) {
			$val = self::plain( (string) $product->get_meta( $key, true ) );
			$hit = self::title_brand( $val );
			if ( $hit ) {
				return $hit;
			}
		}
		foreach ( array( 'pa_brand', 'pa_manufacturer', 'product_brand', 'pwb-brand' ) as $tax ) {
			if ( ! function_exists( 'taxonomy_exists' ) || ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$names = wp_get_object_terms( (int) $product->get_id(), $tax, array( 'fields' => 'names' ) );
			if ( is_wp_error( $names ) || empty( $names ) ) {
				continue;
			}
			foreach ( (array) $names as $name ) {
				$hit = self::title_brand( self::plain( (string) $name ) );
				if ( $hit ) {
					return $hit;
				}
			}
		}
		return '';
	}

	/**
	 * Local model evidence. Marketing-only names without a series/SKU stay unclear.
	 *
	 * @param string $title Title.
	 * @param string $sku   SKU.
	 * @return bool
	 */
	public static function has_model_signal( $title, $sku = '' ) {
		$blob = self::plain( $title . ' ' . $sku );
		if ( '' === $blob ) {
			return false;
		}
		if ( preg_match( '/\b(series|gen|generation)\s*\d+/i', $blob ) ) {
			return true;
		}
		if ( preg_match( '/\b(2nd|3rd|4th|5th|6th)\s*gen/i', $blob ) ) {
			return true;
		}
		if ( preg_match( '/\b[A-Z]{1,4}[-_]?[A-Z0-9]{3,}\b/', $blob ) ) {
			return true;
		}
		if ( preg_match( '/\b(xm\d+|wh-\d+|wf-\d+|qc\s?\d+)\b/i', $blob ) ) {
			return true;
		}
		if ( preg_match( '/\b(watch|buds|airpods|iphone|galaxy)\s+\d+/i', $blob ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param WC_Product $product Product.
	 * @return int
	 */
	public static function image_count( $product ) {
		if ( ! is_object( $product ) ) {
			return 0;
		}
		$count = 0;
		if ( method_exists( $product, 'get_image_id' ) && (int) $product->get_image_id() > 0 ) {
			$count++;
		}
		if ( method_exists( $product, 'get_gallery_image_ids' ) ) {
			$gallery = $product->get_gallery_image_ids();
			if ( is_array( $gallery ) ) {
				$count += count( array_filter( array_map( 'intval', $gallery ) ) );
			}
		}
		return $count;
	}

	/**
	 * Title-leading supported brand. Unknown returns empty string.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function title_brand( $title ) {
		$title = self::plain( $title );
		$title = self::normalize_visit_store( $title );
		if ( '' === $title ) {
			return '';
		}
		$brands = self::brands();
		usort(
			$brands,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		foreach ( $brands as $brand ) {
			if ( preg_match( '/^' . preg_quote( $brand, '/' ) . '\b/i', $title ) ) {
				$hit = self::canonical_brand( $brand );
				if ( self::is_blocked_word( $hit ) || self::is_feature_not_brand( $hit, $title ) ) {
					continue;
				}
				return $hit;
			}
		}
		$head = substr( $title, 0, 56 );
		foreach ( $brands as $brand ) {
			if ( preg_match( '/\b' . preg_quote( $brand, '/' ) . '\b/i', $head ) ) {
				$hit = self::canonical_brand( $brand );
				if ( self::is_blocked_word( $hit ) || self::is_feature_not_brand( $hit, $title ) ) {
					continue;
				}
				return $hit;
			}
		}
		return '';
	}

	/**
	 * “Visit the Apple Store” → Apple.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function normalize_visit_store( $text ) {
		if ( preg_match( '/visit the\s+([A-Za-z0-9][A-Za-z0-9.&+\- ]{1,40})\s+store/i', $text, $m ) ) {
			$maybe = trim( $m[1] );
			$hit   = self::title_brand( $maybe );
			if ( $hit ) {
				return $hit . ' ' . $text;
			}
		}
		return $text;
	}

	/**
	 * "Google TV" / "AppleCare" in a title are features, not the product brand.
	 *
	 * @param string $brand Brand.
	 * @param string $title Title.
	 * @return bool
	 */
	public static function is_feature_not_brand( $brand, $title ) {
		if ( 0 === strcasecmp( $brand, 'Google' ) && preg_match( '/\bgoogle\s+tv\b/i', $title ) && ! preg_match( '/^google\b/i', $title ) ) {
			return true;
		}
		if ( 0 === strcasecmp( $brand, 'Apple' ) && preg_match( '/\bapplecare\b/i', $title ) && ! preg_match( '/^apple\b/i', $title ) ) {
			return true;
		}
		if ( 0 === strcasecmp( $brand, 'Amazon' ) && preg_match( '/\bamazon\s+(availability|associate)/i', $title ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $word Word.
	 * @return bool
	 */
	public static function is_blocked_word( $word ) {
		$data    = self::data();
		$blocked = isset( $data['blocked'] ) ? $data['blocked'] : array();
		return in_array( strtolower( $word ), array_map( 'strtolower', $blocked ), true );
	}

	/**
	 * @param string $text Text.
	 * @return bool
	 */
	public static function is_hard_risk( $text ) {
		$data = self::data();
		$rx   = isset( $data['hard_risk'] ) ? $data['hard_risk'] : '/\b(renewed|refurbished|used\b)\b/i';
		return (bool) preg_match( $rx, $text );
	}

	/**
	 * @param string $title Title.
	 * @return bool
	 */
	public static function is_watch( $title ) {
		$data = self::data();
		$yes  = isset( $data['watch'] ) ? $data['watch'] : '/\bwatch\b/i';
		$no   = isset( $data['not_watch'] ) ? $data['not_watch'] : '/\b(earbuds?|headphones?)\b/i';
		return (bool) preg_match( $yes, $title ) && ! preg_match( $no, $title );
	}

	/**
	 * @param string $title Title.
	 * @return bool
	 */
	public static function is_accessory( $title ) {
		$data = self::data();
		$rx   = isset( $data['accessory'] ) ? $data['accessory'] : '/\b(stand|hub|dock)\b/i';
		return (bool) preg_match( $rx, $title );
	}

	/**
	 * @param string $title Title.
	 * @param string $brand Brand.
	 * @return bool
	 */
	public static function is_mac( $title, $brand ) {
		$data = self::data();
		$rx   = isset( $data['mac'] ) ? $data['mac'] : '/\bmacbook\b/i';
		return (bool) preg_match( $rx, $title ) && ( 0 === strcasecmp( $brand, 'Apple' ) );
	}

	/**
	 * @param string $title Title.
	 * @return bool
	 */
	public static function is_laptop( $title ) {
		$data = self::data();
		$rx   = isset( $data['laptop'] ) ? $data['laptop'] : '/\b(laptop|notebook|macbook)\b/i';
		return (bool) preg_match( $rx, $title ) && ! self::is_accessory( $title );
	}

	/**
	 * Same-family bucket for related products.
	 *
	 * @param string $title Title.
	 * @param string $brand Brand.
	 * @return string
	 */
	public static function bucket( $title, $brand = '' ) {
		$title = self::plain( $title );
		$brand = $brand ? $brand : self::title_brand( $title );
		if ( self::is_watch( $title ) ) {
			if ( 0 === strcasecmp( $brand, 'Apple' ) ) {
				return 'apple-watch';
			}
			if ( 0 === strcasecmp( $brand, 'Samsung' ) ) {
				return 'samsung-watch';
			}
			if ( 0 === strcasecmp( $brand, 'Seiko' ) || 0 === strcasecmp( $brand, 'Rolex' ) || 0 === strcasecmp( $brand, 'Omega' ) ) {
				return 'luxury-watch';
			}
			return 'watch';
		}
		if ( preg_match( '/\b(airpods|earbuds?|buds)\b/i', $title ) ) {
			return 'earbuds';
		}
		if ( preg_match( '/\b(headphones?|headset)\b/i', $title ) ) {
			return 'headphones';
		}
		if ( preg_match( '/\bdrone|quadcopter|fpv\b/i', $title ) ) {
			if ( preg_match( '/\bfish/i', $title ) ) {
				return 'fishing-drone';
			}
			return 'drone';
		}
		if ( preg_match( '/\b(projector|proyector)\b/i', $title ) ) {
			return 'projector';
		}
		if ( preg_match( '/\b(tv|television|soundbar)\b/i', $title ) && ! preg_match( '/\b(google tv|android tv)\b/i', $title ) ) {
			return 'tv';
		}
		if ( self::is_accessory( $title ) ) {
			return 'laptop-accessory';
		}
		if ( self::is_mac( $title, $brand ) || self::is_laptop( $title ) ) {
			return 'laptop';
		}
		if ( preg_match( '/\b(iphone|galaxy s\d|smartphone|pixel )\b/i', $title ) ) {
			return 'phone';
		}
		if ( preg_match( '/\b(action camera|gopro|insta360)\b/i', $title ) ) {
			return 'action-camera';
		}
		if ( preg_match( '/\b(all-in-one|aio|desktop)\b/i', $title ) ) {
			return 'desktop';
		}
		return 'general';
	}

	/**
	 * @param string $label Category name + slug.
	 * @param string $brand Brand.
	 * @param string $title Title.
	 * @return bool True when this category should be removed.
	 */
	public static function category_conflicts( $label, $brand, $title ) {
		$label      = strtolower( str_replace( array( '&amp;', '+' ), ' ', self::plain( $label ) ) );
		$brand      = self::canonical_brand( $brand );
		$is_watch   = self::is_watch( $title );
		$is_apple_w = $is_watch && ( 0 === strcasecmp( $brand, 'Apple' ) );
		$is_sam_w   = $is_watch && ( 0 === strcasecmp( $brand, 'Samsung' ) );
		$is_mac     = self::is_mac( $title, $brand );
		$is_laptop  = self::is_laptop( $title );
		if ( ! $is_apple_w && preg_match( '/apple[- ]watches?\b/', $label ) ) {
			return true;
		}
		if ( ! $is_sam_w && preg_match( '/samsung[- ]watches?\b/', $label ) ) {
			return true;
		}
		if ( ! $is_mac && ! $is_laptop && preg_match( '/\bmacbooks?\b|apple[- ]laptops?\b/', $label ) ) {
			return true;
		}
		if ( '' === $brand ) {
			return false;
		}
		foreach ( self::brands() as $other ) {
			$other = self::canonical_brand( $other );
			if ( 0 === strcasecmp( $other, $brand ) ) {
				continue;
			}
			$needle = strtolower( $other );
			if ( preg_match( '/^' . preg_quote( $needle, '/' ) . 's?(\s|$)/', $label ) ) {
				return true;
			}
			if ( preg_match( '/\b' . preg_quote( $needle, '/' ) . '[- ](watches?|laptops?|macbooks?|phones?|earbuds?|headphones?)\b/', $label ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $text Text that may contain an ASIN.
	 * @return string
	 */
	public static function extract_asin( $text ) {
		$text = strtoupper( self::plain( $text ) );
		if ( preg_match( '/\b(B0[A-Z0-9]{8})\b/', $text, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '#/(?:DP|GP/PRODUCT)/([A-Z0-9]{10})(?:[/?]|$)#', $text, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '/\b([A-Z0-9]{10})\b/', $text, $m ) && preg_match( '/[0-9]/', $m[1] ) && preg_match( '/[A-Z]/', $m[1] ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function product_asin( $product ) {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return '';
		}
		$data = self::data();
		$keys = isset( $data['asin_keys'] ) ? $data['asin_keys'] : array();
		foreach ( $keys as $key ) {
			$val = self::plain( (string) $product->get_meta( $key, true ) );
			$hit = self::extract_asin( $val );
			if ( $hit ) {
				return $hit;
			}
		}
		$sku = self::extract_asin( (string) $product->get_sku() );
		if ( $sku ) {
			return $sku;
		}
		$url_keys = isset( $data['url_keys'] ) ? $data['url_keys'] : array();
		foreach ( $url_keys as $key ) {
			$val = (string) $product->get_meta( $key, true );
			$hit = self::extract_asin( $val );
			if ( $hit ) {
				return $hit;
			}
		}
		if ( method_exists( $product, 'get_product_url' ) ) {
			$hit = self::extract_asin( (string) $product->get_product_url() );
			if ( $hit ) {
				return $hit;
			}
		}
		$desc = self::plain( $product->get_description() . ' ' . $product->get_short_description() );
		return self::extract_asin( $desc );
	}

	/**
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public static function has_price( $product ) {
		if ( ! is_object( $product ) ) {
			return false;
		}
		$price = method_exists( $product, 'get_price' ) ? $product->get_price() : '';
		if ( '' === $price || null === $price ) {
			$price = method_exists( $product, 'get_regular_price' ) ? $product->get_regular_price() : '';
		}
		return is_numeric( $price ) && (float) $price > 0;
	}

	/**
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public static function has_image( $product ) {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_image_id' ) ) {
			return false;
		}
		return (int) $product->get_image_id() > 0;
	}

	/**
	 * Optional local evidence. Never invented.
	 *
	 * @param WC_Product $product Product.
	 * @return array<string,bool>
	 */
	public static function optional_evidence( $product ) {
		$out = array(
			'rating'   => false,
			'seller'   => false,
			'warranty' => false,
		);
		if ( ! is_object( $product ) ) {
			return $out;
		}
		if ( method_exists( $product, 'get_average_rating' ) && (float) $product->get_average_rating() > 0 ) {
			$out['rating'] = true;
		}
		$seller_keys = array( '_seller', 'seller', '_amazon_seller', '_laps_seller' );
		foreach ( $seller_keys as $key ) {
			$val = self::plain( (string) $product->get_meta( $key, true ) );
			if ( $val && ! preg_match( '/^(n\/?a|unknown|unavailable)$/i', $val ) ) {
				$out['seller'] = true;
				break;
			}
		}
		$warr_keys = array( '_warranty', 'warranty', '_laps_warranty', 'pa_warranty' );
		foreach ( $warr_keys as $key ) {
			$val = self::plain( (string) $product->get_meta( $key, true ) );
			if ( $val && ! preg_match( '/^(n\/?a|unknown|unavailable)$/i', $val ) ) {
				$out['warranty'] = true;
				break;
			}
		}
		$title = method_exists( $product, 'get_name' ) ? self::plain( $product->get_name() ) : '';
		if ( preg_match( '/\b(\d+\s*-?\s*year|applecare|2-yr warranty|1-year warranty)\b/i', $title ) ) {
			$out['warranty'] = true;
		}
		return $out;
	}
}
