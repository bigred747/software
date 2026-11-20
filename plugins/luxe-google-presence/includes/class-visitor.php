<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display-only repair for public HTML.
 * Filter archives such as item weight are not departments. Image labels that are Amazon file names are not product names.
 * Amazon hrefs, product permalinks, and post content are not rewritten.
 */
class Luxe_Google_Presence_Visitor {

	/**
	 * @return array<int,string>
	 */
	public static function junk_bases() {
		return array(
			'item-weight',
			'date-first-available',
			'product-shipping-class',
			'item-dimensions',
			'item-display-dimensions',
			'package-dimensions',
			'package-weight',
			'item-package-quantity',
			'part-number',
			'model-number',
			'warranty',
			'product-group',
		);
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public static function repair( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return is_string( $html ) ? $html : '';
		}
		$html = self::repair_disclosure( $html );
		$html = self::repair_note( $html );
		$html = self::repair_cards( $html );
		$html = self::repair_junk_links( $html );
		return $html;
	}

	/**
	 * @param string $base First path segment.
	 * @return bool
	 */
	public static function is_junk_base( $base ) {
		$base = strtolower( trim( (string) $base, '/' ) );
		if ( 0 === strpos( $base, 'pa_' ) ) {
			$base = substr( $base, 3 );
		}
		return in_array( $base, self::junk_bases(), true );
	}

	/**
	 * @param string $path URL or path.
	 * @return bool
	 */
	public static function is_junk_archive_path( $path ) {
		$only = self::path_only( $path );
		if ( '' === $only ) {
			return false;
		}
		$bits = explode( '/', $only );
		return self::is_junk_base( $bits[0] );
	}

	/**
	 * @param string $path  Request path.
	 * @param array  $rows  Department rows with url, depth, and hits.
	 * @param string $shop  Shop URL on this host.
	 * @param bool   $force Redirect even when the path is not a known filter.
	 * @return string
	 */
	public static function redirect_url( $path, $rows, $shop, $force = false ) {
		$force = (bool) $force;
		if ( ! $force && ! self::is_junk_archive_path( $path ) ) {
			return '';
		}
		$shop = is_string( $shop ) ? $shop : '';
		if ( '' === $shop ) {
			return '';
		}
		$choice = self::choose_department( $rows );
		$target = ( is_array( $choice ) && ! empty( $choice['url'] ) ) ? (string) $choice['url'] : $shop;
		if ( self::is_junk_archive_path( $target ) || self::is_amazon_url( $target ) || ! self::same_host( $target, $shop ) ) {
			$target = $shop;
		}
		if ( self::same_destination( $target, $path ) ) {
			return '';
		}
		return $target;
	}

	/**
	 * Prefer the department every product on the filter shares, then the deepest one.
	 *
	 * @param array $rows Rows.
	 * @return array<string,mixed>|null
	 */
	public static function choose_department( $rows ) {
		$best = null;
		if ( ! is_array( $rows ) ) {
			return null;
		}
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['url'] ) || ! is_string( $row['url'] ) ) {
				continue;
			}
			if ( self::is_junk_archive_path( $row['url'] ) || self::is_amazon_url( $row['url'] ) ) {
				continue;
			}
			$hits  = isset( $row['hits'] ) ? (int) $row['hits'] : 0;
			$depth = isset( $row['depth'] ) ? (int) $row['depth'] : 0;
			if ( null === $best || $hits > $best['hits'] || ( $hits === $best['hits'] && $depth > $best['depth'] ) ) {
				$best = array(
					'url'   => $row['url'],
					'name'  => isset( $row['name'] ) ? (string) $row['name'] : '',
					'depth' => $depth,
					'hits'  => $hits,
				);
			}
		}
		return $best;
	}

	/**
	 * @param string $html HTML.
	 * @return array<string,mixed>
	 */
	public static function check( $html ) {
		$fixed = self::repair( $html );
		return array(
			'html'           => $fixed,
			'bad_disclosure' => false !== stripos( $fixed, 'may contain affiliate links' ),
			'official'       => false !== strpos( $fixed, Luxe_Google_Presence_Copy::DISCLOSURE ),
			'price'          => false !== strpos( $fixed, Luxe_Google_Presence_Copy::PRICE ),
			'image_id'       => (bool) preg_match( '/\balt="[0-9]{2}[A-Za-z0-9]{6,14}"/', $fixed ),
			'smartwatche'    => (bool) preg_match( '/\balt="[^"]*smartwatche/i', $fixed ),
			'slug_alt'       => (bool) preg_match( '/\balt="[^"]*[-–—]\s*[A-Za-z0-9]+(?:-[A-Za-z0-9]+){2,}/', $fixed ),
			'hrefs'          => self::hrefs( $html, 'amazon' ) === self::hrefs( $fixed, 'amazon' ),
			'products'       => self::hrefs( $html, 'product' ) === self::hrefs( $fixed, 'product' ),
			'stable'         => $fixed === self::repair( $fixed ),
			'junk_anchors'   => self::junk_anchor_count( $fixed ),
			'amazon_count'   => count( self::hrefs( $html, 'amazon' ) ),
			'variants'       => substr_count( $fixed, 'class="lgp-variant"' ),
		);
	}

	/**
	 * @param string $alt Alt text.
	 * @param string $src Image URL.
	 * @return bool
	 */
	public static function is_broken_alt( $alt, $src ) {
		$alt = trim( (string) $alt );
		$src = (string) $src;
		if ( '' === $alt ) {
			return false !== stripos( $src, 'media-amazon.com' ) || false !== strpos( $src, '/images/I/' );
		}
		if ( preg_match( '/^[0-9]{2}[A-Za-z0-9]{6,14}$/', $alt ) ) {
			return false !== strpos( $src, $alt ) || false !== strpos( $src, '/images/I/' );
		}
		if ( false !== stripos( $alt, 'smartwatche' ) ) {
			return true;
		}
		if ( preg_match( '/\bSpor$/', $alt ) || preg_match( '/\bSpor\s/', $alt ) ) {
			return true;
		}
		if ( preg_match( '/[-–—]\s*[A-Za-z0-9]+(?:-[A-Za-z0-9]+){2,}/', $alt ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $url   Product URL.
	 * @param string $plain Visible title.
	 * @return string
	 */
	public static function variant_label( $url, $plain ) {
		$path  = self::path_only( $url );
		$slug  = strtolower( basename( $path ) );
		$title = strtolower( html_entity_decode( (string) $plain, ENT_QUOTES, 'UTF-8' ) );
		$bits  = array();
		if ( preg_match_all( '/(?:^|-)(64gb|128gb|256gb|512gb|1tb|2tb)(?:-|$)/', $slug, $caps ) && ! empty( $caps[1] ) ) {
			$cap = strtoupper( $caps[1][ count( $caps[1] ) - 1 ] );
			if ( false === strpos( $title, strtolower( $cap ) ) ) {
				$bits[] = $cap;
			}
		}
		$color = self::color_label( $slug );
		if ( '' !== $color && false === strpos( $title, strtolower( $color ) ) ) {
			$bits[] = $color;
		}
		return implode( ' · ', $bits );
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public static function repair_disclosure( $html ) {
		$out = preg_replace_callback(
			'/<p\s+class=("|\')luxe-affiliate-disclosure\1[^>]*>.*?<\/p>/s',
			array( __CLASS__, 'disclosure_paragraph' ),
			$html
		);
		if ( ! is_string( $out ) ) {
			$out = $html;
		}
		$bad = 'Affiliate disclosure: This guide may contain affiliate links. We may earn a commission at no extra cost to you when you buy through qualifying links.';
		if ( false !== strpos( $out, $bad ) ) {
			$out = str_replace( $bad, Luxe_Google_Presence_Copy::DISCLOSURE . ' ' . Luxe_Google_Presence_Copy::PRICE, $out );
		}
		return $out;
	}

	/**
	 * @param array $match Match.
	 * @return string
	 */
	public static function disclosure_paragraph( $match ) {
		$chunk = isset( $match[0] ) ? $match[0] : '';
		if ( false !== strpos( $chunk, Luxe_Google_Presence_Copy::DISCLOSURE ) && false !== strpos( $chunk, Luxe_Google_Presence_Copy::PRICE ) ) {
			return $chunk;
		}
		return self::official_disclosure_html();
	}

	/**
	 * @return string
	 */
	public static function official_disclosure_html() {
		return '<p class="luxe-affiliate-disclosure"><strong>' . self::esc( Luxe_Google_Presence_Copy::DISCLOSURE ) . '</strong> ' . self::esc( Luxe_Google_Presence_Copy::PRICE ) . '</p>';
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public static function repair_note( $html ) {
		$filled = self::associate_html();
		$out    = preg_replace( '/<p class="lgp-stay-note">\s*<\/p>/', $filled, $html );
		if ( ! is_string( $out ) ) {
			$out = $html;
		}
		$again = preg_replace( '/<p class="lgp-associate">\s*<\/p>/', $filled, $out );
		if ( ! is_string( $again ) ) {
			$again = $out;
		}
		if ( false === strpos( $again, Luxe_Google_Presence_Copy::PRICE ) && false !== strpos( $again, 'class="lgp-stay"' ) ) {
			$appended = preg_replace( '/<\/aside>/', $filled . '</aside>', $again, 1 );
			if ( is_string( $appended ) ) {
				$again = $appended;
			}
		}
		return $again;
	}

	/**
	 * @return string
	 */
	public static function associate_html() {
		return '<p class="lgp-associate">' . self::esc( Luxe_Google_Presence_Copy::DISCLOSURE ) . ' ' . self::esc( Luxe_Google_Presence_Copy::PRICE ) . '</p>';
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public static function repair_cards( $html ) {
		$parts = preg_split( '/(?=<div class="product-small col\b)/', $html );
		if ( ! is_array( $parts ) || count( $parts ) < 2 ) {
			return $html;
		}
		$out = $parts[0];
		$n   = count( $parts );
		for ( $i = 1; $i < $n; $i++ ) {
			$out .= self::repair_one_card( $parts[ $i ] );
		}
		return $out;
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public static function repair_junk_links( $html ) {
		$out = preg_replace_callback( '/<a\b[^>]*>/i', array( __CLASS__, 'repair_anchor' ), $html );
		return is_string( $out ) ? $out : $html;
	}

	/**
	 * @param array $match Match.
	 * @return string
	 */
	public static function repair_anchor( $match ) {
		$tag = isset( $match[0] ) ? $match[0] : '';
		if ( ! preg_match( '/\bhref\s*=\s*("|\')([^"\']+)\1/i', $tag, $href_match ) ) {
			return $tag;
		}
		$href = html_entity_decode( $href_match[2], ENT_QUOTES, 'UTF-8' );
		if ( ! self::is_junk_archive_path( $href ) ) {
			return $tag;
		}
		$shop = self::shop_from_href( $href );
		if ( '' === $shop ) {
			return $tag;
		}
		$pos = strpos( $tag, $href_match[0] );
		if ( false === $pos ) {
			return $tag;
		}
		return substr( $tag, 0, $pos ) . 'href=' . $href_match[1] . $shop . $href_match[1] . substr( $tag, $pos + strlen( $href_match[0] ) );
	}

	/**
	 * @param string $html HTML.
	 * @param string $kind amazon or product.
	 * @return array<int,string>
	 */
	public static function hrefs( $html, $kind ) {
		$out = array();
		if ( ! is_string( $html ) || ! preg_match_all( '/<a\b[^>]*\bhref\s*=\s*("|\')([^"\']+)\1/i', $html, $matches ) ) {
			return $out;
		}
		foreach ( $matches[2] as $href ) {
			if ( 'amazon' === $kind ) {
				$decoded = html_entity_decode( $href, ENT_QUOTES, 'UTF-8' );
				if ( self::is_amazon_url( $decoded ) ) {
					$out[] = $href;
				}
				continue;
			}
			if ( 'product' === $kind && false !== strpos( $href, '/product/' ) ) {
				$out[] = $href;
			}
		}
		return $out;
	}

	/**
	 * @param string $html HTML.
	 * @return int
	 */
	public static function junk_anchor_count( $html ) {
		$count = 0;
		if ( ! is_string( $html ) || ! preg_match_all( '/<a\b[^>]*\bhref\s*=\s*("|\')([^"\']+)\1/i', $html, $matches ) ) {
			return 0;
		}
		foreach ( $matches[2] as $href ) {
			$decoded = html_entity_decode( $href, ENT_QUOTES, 'UTF-8' );
			if ( self::is_junk_archive_path( $decoded ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	public static function shop_from_href( $url ) {
		$parts = parse_url( (string) $url );
		if ( ! is_array( $parts ) ) {
			return '';
		}
		if ( empty( $parts['host'] ) ) {
			$path = isset( $parts['path'] ) ? (string) $parts['path'] : '';
			return self::is_junk_archive_path( $path ) ? '/shop/' : '';
		}
		$scheme = ! empty( $parts['scheme'] ) ? (string) $parts['scheme'] : 'https';
		if ( 'https' !== $scheme && 'http' !== $scheme ) {
			return '';
		}
		$host = (string) $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$host .= ':' . (int) $parts['port'];
		}
		return $scheme . '://' . $host . '/shop/';
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	public static function path_only( $url ) {
		$parts = parse_url( (string) $url );
		$path  = ( is_array( $parts ) && isset( $parts['path'] ) ) ? (string) $parts['path'] : (string) $url;
		return trim( strtolower( $path ), '/' );
	}

	/**
	 * @param string $a URL.
	 * @param string $b URL.
	 * @return bool
	 */
	public static function same_destination( $a, $b ) {
		$left  = self::path_only( $a );
		$right = self::path_only( $b );
		return '' !== $left && $left === $right;
	}

	/**
	 * @param string $a URL.
	 * @param string $b URL.
	 * @return bool
	 */
	public static function same_host( $a, $b ) {
		$left  = self::host_of( $a );
		$right = self::host_of( $b );
		if ( '' === $left || '' === $right ) {
			return '' === $left && '' === $right;
		}
		return $left === $right;
	}

	/**
	 * @param string $html Card HTML.
	 * @return string
	 */
	private static function repair_one_card( $html ) {
		$url   = '';
		$inner = '';
		if ( preg_match( '/<a\s+href=("|\')([^"\']+)\1[^>]*class="[^"]*woocommerce-loop-product__link[^"]*"[^>]*>(.*?)<\/a>/s', $html, $match ) ) {
			$url   = $match[2];
			$inner = $match[3];
		}
		$plain = trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( $inner ), ENT_QUOTES, 'UTF-8' ) ) );
		$alt   = self::replacement_alt( $url, $plain );
		if ( '' !== $alt ) {
			$html = self::repair_images( $html, $alt );
		}
		return self::repair_variant( $html, $url, $inner, $plain );
	}

	/**
	 * @param string $html HTML.
	 * @param string $alt  Replacement alt.
	 * @return string
	 */
	private static function repair_images( $html, $alt ) {
		$out = preg_replace_callback(
			'/<img\b[^>]*>/i',
			function ( $match ) use ( $alt ) {
				$tag = isset( $match[0] ) ? $match[0] : '';
				$current = '';
				$src     = '';
				if ( preg_match( '/\balt\s*=\s*("|\')(.*?)\1/s', $tag, $alt_match ) ) {
					$current = html_entity_decode( $alt_match[2], ENT_QUOTES, 'UTF-8' );
				}
				if ( preg_match( '/\bsrc\s*=\s*("|\')(.*?)\1/s', $tag, $src_match ) ) {
					$src = $src_match[2];
				}
				if ( ! self::is_broken_alt( $current, $src ) ) {
					return $tag;
				}
				return self::set_alt( $tag, $alt );
			},
			$html
		);
		return is_string( $out ) ? $out : $html;
	}

	/**
	 * @param string $html  HTML.
	 * @param string $url   Product URL.
	 * @param string $inner Raw title HTML.
	 * @param string $plain Plain title.
	 * @return string
	 */
	private static function repair_variant( $html, $url, $inner, $plain ) {
		if ( '' === $url || ! self::title_is_truncated( $inner ) ) {
			return $html;
		}
		$label = self::variant_label( $url, $plain );
		if ( '' === $label ) {
			return $html;
		}
		$safe    = self::esc( $label );
		$pattern = '/(<p class="[^"]*\bwoocommerce-loop-product__title\b[^"]*">\s*<a\s+href=("|\')' . preg_quote( $url, '/' ) . '\2[^>]*>.*?<\/a>\s*<\/p>)(?!\s*<p class="lgp-variant")/s';
		$out     = preg_replace( $pattern, '$1<p class="lgp-variant">' . $safe . '</p>', $html, 1 );
		return is_string( $out ) ? $out : $html;
	}

	/**
	 * @param string $url   Product URL.
	 * @param string $plain Visible title.
	 * @return string
	 */
	private static function replacement_alt( $url, $plain ) {
		$plain = trim( (string) preg_replace( '/\s+/', ' ', (string) $plain ) );
		if ( self::title_is_truncated( $plain ) ) {
			$from = self::label_from_slug( $url );
			if ( strlen( $from ) > strlen( $plain ) ) {
				return $from;
			}
		}
		if ( strlen( $plain ) >= 12 ) {
			return $plain;
		}
		$from = self::label_from_slug( $url );
		return '' !== $from ? $from : $plain;
	}

	/**
	 * @param string $url Product URL.
	 * @return string
	 */
	private static function label_from_slug( $url ) {
		$path = self::path_only( $url );
		$slug = strtolower( basename( $path ) );
		if ( '' === $slug ) {
			return '';
		}
		$words = explode( '-', $slug );
		$out   = array();
		foreach ( $words as $word ) {
			if ( '' === $word ) {
				continue;
			}
			if ( preg_match( '/^[0-9]+(gb|tb)$/', $word ) ) {
				$out[] = strtoupper( $word );
				continue;
			}
			if ( in_array( $word, array( 'ai', 'ssd', 'gps', 'hd', 'usb' ), true ) ) {
				$out[] = strtoupper( $word );
				continue;
			}
			$out[] = ucfirst( $word );
		}
		$text = implode( ' ', $out );
		if ( strlen( $text ) <= 160 ) {
			return $text;
		}
		$text = substr( $text, 0, 157 );
		$text = (string) preg_replace( '/\s+\S*$/', '', $text );
		return $text . '…';
	}

	/**
	 * @param string $text Title HTML or text.
	 * @return bool
	 */
	private static function title_is_truncated( $text ) {
		return (bool) preg_match( '/(&#8230;|&hellip;|\.\.\.|…)/u', (string) $text );
	}

	/**
	 * @param string $slug Product slug.
	 * @return string
	 */
	private static function color_label( $slug ) {
		$map     = array(
			'space-gray'  => 'Space Gray',
			'space-grey'  => 'Space Gray',
			'rose-gold'   => 'Rose Gold',
			'jet-black'   => 'Jet Black',
			'light-blush' => 'Light Blush',
			'sky-blue'    => 'Sky Blue',
			'starlight'   => 'Starlight',
			'midnight'    => 'Midnight',
			'citrus'      => 'Citrus',
			'blush'       => 'Blush',
			'indigo'      => 'Indigo',
			'graphite'    => 'Graphite',
			'silver'      => 'Silver',
			'gold'        => 'Gold',
			'blue'        => 'Blue',
			'black'       => 'Black',
			'pink'        => 'Pink',
			'green'       => 'Green',
			'white'       => 'White',
			'red'         => 'Red',
			'purple'      => 'Purple',
		);
		$best    = '';
		$best_end = -1;
		$best_len = -1;
		foreach ( $map as $key => $label ) {
			$offset = 0;
			$len    = strlen( $slug );
			while ( false !== ( $pos = strpos( $slug, $key, $offset ) ) ) {
				$end    = $pos + strlen( $key );
				$before = ( 0 === $pos ) ? '-' : $slug[ $pos - 1 ];
				$after  = ( $end >= $len ) ? '-' : $slug[ $end ];
				$bound  = ( '-' === $before || 0 === $pos ) && ( '-' === $after || $end === $len );
				if ( $bound && ( $end > $best_end || ( $end === $best_end && strlen( $key ) > $best_len ) ) ) {
					$best_end = $end;
					$best_len = strlen( $key );
					$best     = $label;
				}
				$offset = $pos + 1;
			}
		}
		return $best;
	}

	/**
	 * @param string $tag Image tag.
	 * @param string $alt Alt text.
	 * @return string
	 */
	private static function set_alt( $tag, $alt ) {
		$safe = self::esc( $alt );
		if ( preg_match( '/\balt\s*=\s*("|\')(.*?)\1/s', $tag ) ) {
			$updated = preg_replace( '/\balt\s*=\s*("|\')(.*?)\1/s', 'alt="' . $safe . '"', $tag, 1 );
			return is_string( $updated ) ? $updated : $tag;
		}
		$trim = rtrim( $tag );
		if ( '>' !== substr( $trim, -1 ) ) {
			return $tag;
		}
		if ( strlen( $trim ) > 1 && '/>' === substr( $trim, -2 ) ) {
			return substr( $trim, 0, -2 ) . ' alt="' . $safe . '" />';
		}
		return substr( $trim, 0, -1 ) . ' alt="' . $safe . '">';
	}

	/**
	 * @param string $url URL.
	 * @return bool
	 */
	private static function is_amazon_url( $url ) {
		return class_exists( 'Luxe_Google_Presence_Anchors' ) && Luxe_Google_Presence_Anchors::is_amazon_href( $url );
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	private static function host_of( $url ) {
		$parts = parse_url( (string) $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}
		$host = strtolower( (string) $parts['host'] );
		if ( 0 === strpos( $host, 'www.' ) ) {
			return substr( $host, 4 );
		}
		return $host;
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	private static function esc( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
