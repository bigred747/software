<?php
/**
 * Pure repairs for Mission Control's scored gaps.
 *
 * Amazon.com and amzn.to URLs are never rewritten. WooCommerce @version
 * is updated only after the WooCommerce 11.2 behavior is actually in the file.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Luxe_MSR_Repair {

	const MERGED_VERSION = '11.2.0';

	/**
	 * @return string[]
	 */
	public static function template_files() {
		return array(
			'cart/cart.php',
			'cart/mini-cart.php',
			'cart/cart-shipping.php',
			'checkout/thankyou.php',
			'single-product-reviews.php',
		);
	}

	/**
	 * @return string[]
	 */
	public static function homepage_script_ids() {
		return array(
			'wflsi18njs-js-extra',
			'wordfence-ls-login-js-extra',
			'wordfence-ls-login-js',
			'thickbox-js',
			'thickbox-js-extra',
			'contact-form-7-js',
			'contact-form-7-js-extra',
			'contact-form-7-js-before',
		);
	}

	/**
	 * @return string[]
	 */
	public static function homepage_style_ids() {
		return array(
			'wp-block-library-inline-css',
			'global-styles-inline-css',
			'classic-theme-styles-inline-css',
			'dashicons-inline-css',
			'luxe-archive-card-lock-css',
			'luxe-green-signals-mobile-tables',
			'luxe-amazon-bridge-orange-button',
		);
	}

	/**
	 * @param string $relative Theme override path.
	 * @param string $code     File contents.
	 * @param string $core_version Installed WooCommerce template @version.
	 * @return array{code:string,changed:bool,ready:bool}
	 */
	public static function patch_template( $relative, $code, $core_version ) {
		$relative = str_replace( '\\', '/', (string) $relative );
		$ready    = false;
		$next     = (string) $code;

		if ( 'cart/cart.php' === $relative || 'cart/mini-cart.php' === $relative ) {
			$next  = self::patch_cart_names( $next );
			$ready = self::cart_ready( $next );
		} elseif ( 'cart/cart-shipping.php' === $relative ) {
			$next  = self::patch_shipping_label( $next );
			$ready = self::shipping_ready( $next );
		} elseif ( 'checkout/thankyou.php' === $relative ) {
			$next  = self::patch_thankyou( $next );
			$ready = self::thankyou_ready( $next );
		} elseif ( 'single-product-reviews.php' === $relative ) {
			$next  = self::patch_reviews( $next );
			$ready = self::reviews_ready( $next );
		}

		if ( ! $ready ) {
			return array(
				'code'    => (string) $code,
				'changed' => false,
				'ready'   => false,
			);
		}

		$stamped = self::stamp_version( $next, (string) $core_version );
		return array(
			'code'    => $stamped,
			'changed' => $stamped !== (string) $code,
			'ready'   => true,
		);
	}

	/**
	 * @param string $html HTML document.
	 * @return string
	 */
	public static function repair_links( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, 'amazon' ) ) {
			return (string) $html;
		}
		$out = preg_replace_callback(
			'/<a\b[^>]*>/i',
			array( __CLASS__, 'repair_anchor' ),
			$html
		);
		return is_string( $out ) ? $out : $html;
	}

	/**
	 * Homepage-only trim of assets that have no storefront job.
	 *
	 * @param string $html HTML document.
	 * @return string
	 */
	public static function trim_homepage( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return (string) $html;
		}
		foreach ( array_merge( self::homepage_script_ids(), self::homepage_style_ids() ) as $id ) {
			$html = self::strip_tag_id( $html, 'script', $id );
			$html = self::strip_tag_id( $html, 'style', $id );
		}
		$html = self::strip_tab_image_noop( $html );
		$html = self::strip_unused_gallery_style( $html );
		if ( false !== strpos( $html, 'screen-reader-text' ) && false === strpos( $html, 'id="luxe-msr-skip-link"' ) ) {
			$rule = '<style id="luxe-msr-skip-link">.screen-reader-text{border:0;clip:rect(1px,1px,1px,1px);clip-path:inset(50%);height:1px;margin:-1px;overflow:hidden;padding:0;position:absolute;width:1px;word-wrap:normal!important}</style>';
			$html = preg_replace( '/<head\b[^>]*>/i', '$0' . $rule, $html, 1 );
		}
		return $html;
	}

	/**
	 * @param string $html HTML.
	 * @param bool   $homepage Apply the homepage asset trim.
	 * @return string
	 */
	public static function repair_document( $html, $homepage ) {
		$html = self::repair_links( $html );
		if ( $homepage ) {
			$html = self::trim_homepage( $html );
		}
		return $html;
	}

	/**
	 * @param string $html HTML.
	 * @return array{bytes:int,scripts:int,styles:int}
	 */
	public static function budget( $html ) {
		$html = (string) $html;
		$scripts = preg_match_all( '/<script\b/i', $html );
		$styles  = preg_match_all( '/<link\b[^>]*rel=["\'][^"\']*stylesheet/i', $html );
		return array(
			'bytes'   => strlen( $html ),
			'scripts' => false === $scripts ? 0 : (int) $scripts,
			'styles'  => false === $styles ? 0 : (int) $styles,
		);
	}

	/**
	 * @param string $host Hostname.
	 * @return bool
	 */
	public static function is_amazon_retail_host( $host ) {
		$host = strtolower( (string) $host );
		$host = preg_replace( '/^www\./', '', $host );
		if ( 'amzn.to' === $host || 'amzn.com' === $host ) {
			return true;
		}
		return (bool) preg_match( '/(^|\.)amazon\.(com|ca|co\.uk|de|fr|it|es|co\.jp|in|com\.au|com\.br|com\.mx|nl|se|pl|sg|ae|sa|eg|com\.tr)$/', $host );
	}

	/**
	 * @param string $host Hostname.
	 * @return bool
	 */
	public static function is_amazon_image_host( $host ) {
		$host = strtolower( (string) $host );
		return (bool) preg_match( '/(^|\.)(media-amazon\.com|ssl-images-amazon\.com|images-amazon\.com)$/', $host );
	}

	/**
	 * @param string $tag Opening anchor tag.
	 * @return string
	 */
	public static function repair_anchor( $tag ) {
		if ( ! is_array( $tag ) || empty( $tag[0] ) ) {
			return '';
		}
		$full = $tag[0];
		if ( ! preg_match( '/\shref\s*=\s*(["\'])(.*?)\1/i', $full, $m ) && ! preg_match( '/href\s*=\s*(["\'])(.*?)\1/i', $full, $m ) ) {
			return $full;
		}
		$quote = $m[1];
		$raw   = html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' );
		$fixed = self::repair_href( $raw );
		if ( $fixed === $raw ) {
			return $full;
		}
		$encoded = htmlspecialchars( $fixed, ENT_QUOTES, 'UTF-8' );
		$pos     = strpos( $full, $m[0] );
		if ( false === $pos ) {
			return $full;
		}
		$replacement = 'href=' . $quote . $encoded . $quote;
		return substr_replace( $full, $replacement, $pos, strlen( $m[0] ) );
	}

	/**
	 * @param string $href Decoded href.
	 * @return string
	 */
	public static function repair_href( $href ) {
		$href  = (string) $href;
		$parts = luxe_msr_parse_url( $href );
		if ( empty( $parts['host'] ) ) {
			return $href;
		}
		$host = $parts['host'];
		if ( self::is_amazon_retail_host( $host ) ) {
			return $href;
		}
		if ( self::is_amazon_image_host( $host ) ) {
			$path = isset( $parts['path'] ) ? $parts['path'] : '';
			if ( preg_match( '/\.(?:jpe?g|png|gif|webp|avif)$/i', $path ) ) {
				return '#';
			}
			return $href;
		}
		if ( empty( $parts['query'] ) ) {
			return $href;
		}
		$kept    = array();
		$changed = false;
		foreach ( explode( '&', $parts['query'] ) as $pair ) {
			$bits = explode( '=', $pair, 2 );
			$val = isset( $bits[1] ) ? rawurldecode( $bits[1] ) : '';
			if ( self::value_embeds_amazon( $val ) ) {
				$changed = true;
				continue;
			}
			$kept[] = $pair;
		}
		if ( ! $changed ) {
			return $href;
		}
		$parts['query'] = implode( '&', $kept );
		if ( '' === $parts['query'] ) {
			unset( $parts['query'] );
		}
		return self::build_url( $parts );
	}

	/**
	 * @param string $value Query value.
	 * @return bool
	 */
	public static function value_embeds_amazon( $value ) {
		if ( ! is_string( $value ) || false === stripos( $value, 'amazon' ) ) {
			return false;
		}
		if ( ! preg_match( '#https?://[^/\s]+#i', $value, $m ) ) {
			return false;
		}
		$host = parse_url( $m[0], PHP_URL_HOST );
		if ( ! is_string( $host ) ) {
			return false;
		}
		return self::is_amazon_retail_host( $host ) || self::is_amazon_image_host( $host );
	}

	/**
	 * @param array $parts parse_url parts.
	 * @return string
	 */
	public static function build_url( $parts ) {
		$url = '';
		if ( isset( $parts['scheme'] ) ) {
			$url .= $parts['scheme'] . '://';
		}
		if ( isset( $parts['user'] ) ) {
			$url .= $parts['user'];
			if ( isset( $parts['pass'] ) ) {
				$url .= ':' . $parts['pass'];
			}
			$url .= '@';
		}
		if ( isset( $parts['host'] ) ) {
			$url .= $parts['host'];
		}
		if ( isset( $parts['port'] ) ) {
			$url .= ':' . $parts['port'];
		}
		$url .= isset( $parts['path'] ) ? $parts['path'] : '';
		if ( isset( $parts['query'] ) && '' !== $parts['query'] ) {
			$url .= '?' . $parts['query'];
		}
		if ( isset( $parts['fragment'] ) ) {
			$url .= '#' . $parts['fragment'];
		}
		return $url;
	}

	/**
	 * @param string $code Template source.
	 * @return string
	 */
	public static function patch_cart_names( $code ) {
		if ( false === strpos( $code, 'get_item_product_name' ) && preg_match( '/\$product_name\s*=\s*apply_filters\(\s*[\'"]woocommerce_cart_item_name[\'"]\s*,\s*\$_product->get_name\(\)/', $code ) ) {
			$code = preg_replace(
				'/\$product_name\s*=\s*apply_filters\(\s*[\'"]woocommerce_cart_item_name[\'"]\s*,\s*\$_product->get_name\(\)/',
				"\$cart_item_name = WC()->cart->get_item_product_name( \$cart_item, \$_product );\n\t\t\t\t\t\$product_name      = apply_filters( 'woocommerce_cart_item_name', \$cart_item_name",
				$code,
				1
			);
		}
		$code = preg_replace(
			'/apply_filters\(\s*([\'"])woocommerce_cart_item_name\1\s*,\s*\$_product->get_name\(\)/',
			'apply_filters( $1woocommerce_cart_item_name$1, $cart_item_name',
			$code
		);
		$code = preg_replace(
			'/sprintf\(\s*([\'"])<a href="%s">%s<\/a>\1\s*,\s*esc_url\(\s*\$product_permalink\s*\)\s*,\s*\$_product->get_name\(\)\s*\)/',
			'sprintf( $1<a href="%s">%s</a>$1, esc_url( $product_permalink ), $cart_item_name )',
			$code
		);
		$code = preg_replace(
			'/wc_get_formatted_cart_item_data\(\s*\$cart_item\s*\)/',
			'wc_get_formatted_cart_item_data( $cart_item, false, $cart_item_name )',
			$code
		);
		return is_string( $code ) ? $code : '';
	}

	/**
	 * @param string $code Template source.
	 * @return bool
	 */
	public static function cart_ready( $code ) {
		if ( false === strpos( $code, 'get_item_product_name' ) ) {
			return false;
		}
		if ( ! preg_match( '/wc_get_formatted_cart_item_data\(\s*\$cart_item\s*,\s*false\s*,\s*\$cart_item_name\s*\)/', $code ) ) {
			return false;
		}
		if ( preg_match( '/woocommerce_cart_item_name[\'"]\s*,\s*\$_product->get_name\(\)/', $code ) ) {
			return false;
		}
		return false === strpos( $code, "sprintf( '<a href=\"%s\">%s</a>', esc_url( \$product_permalink ), \$_product->get_name()" )
			&& false === strpos( $code, 'sprintf( \'<a href="%s">%s</a>\', esc_url( $product_permalink ), $_product->get_name()' );
	}

	/**
	 * @param string $code Template source.
	 * @return string
	 */
	public static function patch_shipping_label( $code ) {
		$next = str_replace(
			array(
				'for="shipping_method_%1$s_%2$s"',
				"for='shipping_method_%1\$s_%2\$s'",
			),
			array(
				'for="shipping_method_%1$d_%2$s"',
				"for='shipping_method_%1\$d_%2\$s'",
			),
			$code
		);
		return $next;
	}

	/**
	 * @param string $code Template source.
	 * @return bool
	 */
	public static function shipping_ready( $code ) {
		$has_new = false !== strpos( $code, 'for="shipping_method_%1$d_%2$s"' ) || false !== strpos( $code, "for='shipping_method_%1\$d_%2\$s'" );
		$has_old = false !== strpos( $code, 'for="shipping_method_%1$s_%2$s"' ) || false !== strpos( $code, "for='shipping_method_%1\$s_%2\$s'" );
		return $has_new && ! $has_old;
	}

	/**
	 * @param string $code Template source.
	 * @return string
	 */
	public static function patch_thankyou( $code ) {
		if ( false !== strpos( $code, 'woocommerce_thankyou_order_failed_text' ) ) {
			return $code;
		}
		$pattern = '/<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed">\s*<\?php\s+esc_html_e\(\s*([\'"])(.*?)\1\s*,\s*[\'"]woocommerce[\'"]\s*\)\s*;\s*\?>\s*<\/p>/s';
		$next    = preg_replace_callback(
			$pattern,
			array( __CLASS__, 'thankyou_replacement' ),
			$code,
			1
		);
		return is_string( $next ) ? $next : $code;
	}

	/**
	 * @param array $m Match.
	 * @return string
	 */
	public static function thankyou_replacement( $m ) {
		$message = $m[2];
		return '<?php
			$default_order_failed_text = \'' . str_replace( "'", "\\'", $message ) . '\';
			/**
			 * Filters the message shown when an order has failed.
			 *
			 * @param string   $message The failed order message.
			 * @param WC_Order $order   The failed order.
			 * @since 11.2.0
			 */
			$order_failed_text = apply_filters( \'woocommerce_thankyou_order_failed_text\', $default_order_failed_text, $order );
			$order_failed_text = is_string( $order_failed_text ) ? $order_failed_text : $default_order_failed_text;
			?>
			<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed"><?php echo wp_kses_post( $order_failed_text ); ?></p>';
	}

	/**
	 * @param string $code Template source.
	 * @return bool
	 */
	public static function thankyou_ready( $code ) {
		return false !== strpos( $code, 'woocommerce_thankyou_order_failed_text' )
			&& false !== strpos( $code, 'wp_kses_post( $order_failed_text )' );
	}

	/**
	 * @param string $code Template source.
	 * @return string
	 */
	public static function patch_reviews( $code ) {
		if ( false !== strpos( $code, 'wp_kses_post( get_the_title() )' ) ) {
			return $code;
		}
		$next = str_replace(
			"'<span>' . get_the_title() . '</span>'",
			"'<span>' . wp_kses_post( get_the_title() ) . '</span>'",
			$code
		);
		$next = str_replace(
			'"<span>" . get_the_title() . "</span>"',
			'"<span>" . wp_kses_post( get_the_title() ) . "</span>"',
			$next
		);
		return $next;
	}

	/**
	 * @param string $code Template source.
	 * @return bool
	 */
	public static function reviews_ready( $code ) {
		return false !== strpos( $code, 'wp_kses_post( get_the_title() )' );
	}

	/**
	 * Stamp @version only when this file contains the 11.2 behavior and the
	 * installed core template is exactly that merge.
	 *
	 * @param string $code Template source.
	 * @param string $core_version Core @version.
	 * @return string
	 */
	public static function stamp_version( $code, $core_version ) {
		if ( self::MERGED_VERSION !== $core_version ) {
			return $code;
		}
		if ( ! preg_match( '/@version\s+([0-9.]+)/', $code, $m ) ) {
			return $code;
		}
		if ( version_compare( $m[1], self::MERGED_VERSION, '>=' ) ) {
			return $code;
		}
		$stamped = preg_replace( '/@version\s+[0-9.]+/', '@version ' . self::MERGED_VERSION, $code, 1 );
		return is_string( $stamped ) ? $stamped : $code;
	}

	/**
	 * @param string $html HTML.
	 * @param string $tag script|style.
	 * @param string $id Element id.
	 * @return string
	 */
	public static function strip_tag_id( $html, $tag, $id ) {
		$q    = preg_quote( $id, '/' );
		$next = preg_replace(
			'/<' . $tag . '\b[^>]*\bid=["\']' . $q . '["\'][^>]*>.*?<\/' . $tag . '>/is',
			'',
			$html
		);
		return is_string( $next ) ? $next : $html;
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public static function strip_tab_image_noop( $html ) {
		$next = preg_replace_callback(
			'/<script\b([^>]*)>(.*?)<\/script>/is',
			array( __CLASS__, 'maybe_drop_tab_script' ),
			$html
		);
		return is_string( $next ) ? $next : $html;
	}

	/**
	 * @param array $m Match.
	 * @return string
	 */
	public static function maybe_drop_tab_script( $m ) {
		$source = $m[2];
		if ( preg_match( '/src=(["\'])data:text\/javascript;base64,([^"\']+)\1/i', $m[1], $src ) ) {
			$decoded = base64_decode( $src[2], true );
			if ( is_string( $decoded ) ) {
				$source .= $decoded;
			}
		}
		if ( false !== strpos( $source, '.woocommerce-tabs #tab-description' ) ) {
			return '';
		}
		return $m[0];
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public static function strip_unused_gallery_style( $html ) {
		if ( false !== strpos( $html, 'woocommerce-product-gallery' ) ) {
			$body = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', $html );
			$body = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', (string) $body );
			if ( is_string( $body ) && false !== strpos( $body, 'woocommerce-product-gallery' ) ) {
				return $html;
			}
		}
		$next = preg_replace(
			'/<style\b[^>]*>\s*\.woocommerce-product-gallery\{\s*opacity:\s*1\s*!important;\s*\}\s*<\/style>/i',
			'',
			$html
		);
		return is_string( $next ) ? $next : $html;
	}
}

/**
 * parse_url wrapper so the repair class does not require WordPress.
 *
 * @param string $url URL.
 * @return array
 */
function luxe_msr_parse_url( $url ) {
	$parts = parse_url( $url );
	return is_array( $parts ) ? $parts : array();
}
