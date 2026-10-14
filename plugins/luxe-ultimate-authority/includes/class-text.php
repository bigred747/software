<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plain-text helpers. No network. No post writes.
 */
class LUAE_Text {

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public static function plain( $html ) {
		$text = preg_replace( '/(?is)<(script|style)\b[^>]*>.*?<\/\1>/', ' ', (string) $html );
		$text = preg_replace( '/(?is)<[^>]+>/', ' ', (string) $text );
		$text = html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
		$text = str_replace( "\xc2\xa0", ' ', $text );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return trim( (string) $text );
	}

	/**
	 * @param string $html HTML.
	 * @return int
	 */
	public static function words( $html ) {
		$plain = self::plain( $html );
		if ( '' === $plain ) {
			return 0;
		}
		$parts = preg_split( '/\s+/u', $plain );
		return is_array( $parts ) ? count( $parts ) : 0;
	}

	/**
	 * @param string $html HTML.
	 * @return string[]
	 */
	public static function sentences( $html ) {
		$plain = self::plain( $html );
		$parts = preg_split( '/(?<=[.!?])\s+/u', $plain );
		if ( ! is_array( $parts ) ) {
			return array();
		}
		$out = array();
		foreach ( $parts as $part ) {
			$part = trim( (string) $part );
			if ( strlen( $part ) >= 40 ) {
				$out[] = $part;
			}
		}
		return $out;
	}

	/**
	 * Template lines seen on the live buyer guides.
	 *
	 * @return string[]
	 */
	public static function template_phrases() {
		return array(
			'item weight',
			'worth considering if the live product page confirms',
			'what this blog is about',
			'verify live price before checkout',
			'use this guide as a final checkpoint',
			'the most important decision is not only whether the product looks premium',
			'exact listing matches the configuration',
			'confirm the live retailer page matches the exact product',
		);
	}

	/**
	 * @param string $html HTML.
	 * @return string[]
	 */
	public static function template_hits( $html ) {
		$plain = strtolower( self::plain( $html ) );
		$hits  = array();
		foreach ( self::template_phrases() as $phrase ) {
			if ( false !== strpos( $plain, $phrase ) ) {
				$hits[] = $phrase;
			}
		}
		return $hits;
	}

	/**
	 * @param string $html  HTML.
	 * @param string $title Title.
	 * @return int
	 */
	public static function title_repeats( $html, $title ) {
		$title = strtolower( trim( self::plain( $title ) ) );
		if ( strlen( $title ) < 20 ) {
			return 0;
		}
		$stem = substr( $title, 0, 48 );
		return substr_count( strtolower( self::plain( $html ) ), $stem );
	}

	/**
	 * @param string $html HTML.
	 * @return string[] Repeated sentences.
	 */
	public static function duplicate_sentences( $html ) {
		$counts = array();
		foreach ( self::sentences( $html ) as $sentence ) {
			$key = strtolower( $sentence );
			if ( ! isset( $counts[ $key ] ) ) {
				$counts[ $key ] = 0;
			}
			$counts[ $key ]++;
		}
		$dupes = array();
		foreach ( $counts as $sentence => $count ) {
			if ( $count >= 2 ) {
				$dupes[] = $sentence;
			}
		}
		return $dupes;
	}

	/**
	 * @param string $html HTML.
	 * @return string[]
	 */
	public static function amazon_urls( $html ) {
		$found = array();
		if ( ! preg_match_all( '/https?:\/\/[^\s"\'<>]+/i', (string) $html, $matches ) ) {
			return $found;
		}
		foreach ( $matches[0] as $url ) {
			$url = rtrim( $url, '.,);' );
			$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
			if ( false !== strpos( $host, 'amazon.' ) || false !== strpos( $host, 'amzn.to' ) ) {
				$found[] = $url;
			}
		}
		return array_values( array_unique( $found ) );
	}

	/**
	 * 0 when the button is at the top, 1 when it is at the end. Null when missing.
	 *
	 * @param string $html HTML.
	 * @return float|null
	 */
	public static function amazon_ratio( $html ) {
		$html = (string) $html;
		if ( '' === $html ) {
			return null;
		}
		$pos = stripos( $html, 'amazon.' );
		if ( false === $pos ) {
			$pos = stripos( $html, 'amzn.to' );
		}
		if ( false === $pos ) {
			$pos = stripos( $html, 'view on amazon' );
		}
		if ( false === $pos ) {
			return null;
		}
		$len = strlen( $html );
		if ( $len < 1 ) {
			return null;
		}
		return round( $pos / $len, 4 );
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	public static function amazon_tag( $url ) {
		$query = parse_url( (string) $url, PHP_URL_QUERY );
		if ( ! is_string( $query ) || '' === $query ) {
			return '';
		}
		$args = array();
		parse_str( $query, $args );
		if ( isset( $args['tag'] ) && is_string( $args['tag'] ) ) {
			return trim( $args['tag'] );
		}
		return '';
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	public static function asin( $url ) {
		if ( preg_match( '#/(?:dp|gp/product)/([A-Z0-9]{10})#i', (string) $url, $match ) ) {
			return strtoupper( $match[1] );
		}
		return '';
	}

	/**
	 * @param string $html HTML.
	 * @return int
	 */
	public static function h1_count( $html ) {
		if ( ! preg_match_all( '/(?is)<h1\b[^>]*>.*?<\/h1>/', (string) $html, $matches ) ) {
			return 0;
		}
		return count( $matches[0] );
	}

	/**
	 * @param string $html HTML.
	 * @return bool
	 */
	public static function has_faq( $html ) {
		return (bool) preg_match( '/(?is)<h[23][^>]*>\s*faq\b/', (string) $html );
	}

	/**
	 * @param string $text Text.
	 * @return bool
	 */
	public static function has_placeholder( $text ) {
		return false !== strpos( (string) $text, '[[' );
	}

	/**
	 * @param string $url  URL.
	 * @param string $home Site home.
	 * @return bool
	 */
	public static function is_local_url( $url, $home ) {
		$url = trim( (string) $url );
		if ( '' === $url || preg_match( '#^\s*(javascript|data):#i', $url ) ) {
			return false;
		}
		$lower = strtolower( $url );
		if ( false !== strpos( $lower, 'amazon.' ) || false !== strpos( $lower, 'amzn.to' ) ) {
			return false;
		}
		if ( '/' === substr( $url, 0, 1 ) && '//' !== substr( $url, 0, 2 ) ) {
			return true;
		}
		$host      = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
		$home_host = strtolower( (string) parse_url( (string) $home, PHP_URL_HOST ) );
		if ( '' === $host || '' === $home_host ) {
			return false;
		}
		return $host === $home_host;
	}

	/**
	 * @param string $html HTML.
	 * @param string $home Home.
	 * @return array{category:bool,guide:bool,comparison:bool}
	 */
	public static function link_kinds( $html, $home ) {
		$kinds = array(
			'category'   => false,
			'guide'      => false,
			'comparison' => false,
		);
		if ( ! preg_match_all( '/href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', (string) $html, $matches, PREG_SET_ORDER ) ) {
			return $kinds;
		}
		foreach ( $matches as $match ) {
			$url   = html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' );
			$label = strtolower( self::plain( $match[2] ) );
			if ( ! self::is_local_url( $url, $home ) ) {
				continue;
			}
			$path = strtolower( (string) parse_url( $url, PHP_URL_PATH ) );
			if ( false !== strpos( $path, '/product-category/' ) ) {
				$kinds['category'] = true;
			}
			if ( false !== strpos( $label, 'compare' ) || false !== strpos( $path, 'compare' ) ) {
				$kinds['comparison'] = true;
			}
			if ( self::is_guide_path( $path ) ) {
				$kinds['guide'] = true;
			}
		}
		return $kinds;
	}

	/**
	 * @param string $path Path.
	 * @return bool
	 */
	public static function is_guide_path( $path ) {
		$path = '/' . trim( strtolower( (string) $path ), '/' ) . '/';
		if ( '/' === $path || '/blogs/' === $path || '/shop/' === $path || '/about/' === $path ) {
			return false;
		}
		$blocked = array( '/product/', '/product-category/', '/product-tag/', '/item-', '/pa_', '/cart/', '/checkout/', '/wp-' );
		foreach ( $blocked as $needle ) {
			if ( false !== strpos( $path, $needle ) ) {
				return false;
			}
		}
		$parts = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
		return 1 === count( $parts );
	}

	/**
	 * @param string $html HTML.
	 * @return array{count:int,missing_alt:int,missing_dimensions:int}
	 */
	public static function images( $html ) {
		$result = array(
			'count'              => 0,
			'missing_alt'        => 0,
			'missing_dimensions' => 0,
		);
		if ( ! preg_match_all( '/<img\b[^>]*>/i', (string) $html, $matches ) ) {
			return $result;
		}
		$result['count'] = count( $matches[0] );
		foreach ( $matches[0] as $tag ) {
			if ( ! preg_match( '/\balt\s*=\s*"[^"]+"/i', $tag ) || preg_match( '/\balt\s*=\s*"\s*"/i', $tag ) ) {
				$result['missing_alt']++;
			}
			$width  = preg_match( '/\bwidth\s*=\s*"[^"]+"/i', $tag );
			$height = preg_match( '/\bheight\s*=\s*"[^"]+"/i', $tag );
			if ( ! $width || ! $height ) {
				$result['missing_dimensions']++;
			}
		}
		return $result;
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	public static function esc( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
