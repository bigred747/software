<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Qualify Amazon anchors for Google without changing the href.
 * The associate tag stays on the existing URL. media-amazon.com images stay images.
 */
class Luxe_Google_Presence_Anchors {

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public static function qualify( $html ) {
		if ( ! is_string( $html ) || '' === $html || false === stripos( $html, '<a' ) ) {
			return is_string( $html ) ? $html : '';
		}
		$out = preg_replace_callback( '/<a\b[^>]*>/i', array( __CLASS__, 'qualify_tag' ), $html );
		return is_string( $out ) ? $out : $html;
	}

	/**
	 * @param array $match Match.
	 * @return string
	 */
	public static function qualify_tag( $match ) {
		$tag = isset( $match[0] ) ? $match[0] : '';
		if ( ! preg_match( '/\bhref\s*=\s*(\'|")(.*?)\1/i', $tag, $href_match ) ) {
			return $tag;
		}
		$href = html_entity_decode( $href_match[2], ENT_QUOTES, 'UTF-8' );
		if ( ! self::is_amazon_href( $href ) ) {
			return $tag;
		}
		if ( preg_match( '/\brel\s*=\s*(\'|")(.*?)\1/i', $tag, $rel_match ) ) {
			$rel = $rel_match[2];
			if ( preg_match( '/(^|\s)sponsored(\s|$)/i', $rel ) ) {
				return $tag;
			}
			$quote   = $rel_match[1];
			$updated = trim( $rel . ' sponsored' );
			$pos     = strpos( $tag, $rel_match[0] );
			if ( false === $pos ) {
				return $tag;
			}
			return substr( $tag, 0, $pos ) . 'rel=' . $quote . $updated . $quote . substr( $tag, $pos + strlen( $rel_match[0] ) );
		}
		$trimmed = rtrim( $tag );
		if ( '>' !== substr( $trimmed, -1 ) ) {
			return $tag;
		}
		return substr( $trimmed, 0, -1 ) . ' rel="sponsored">';
	}

	/**
	 * @param string $href Href.
	 * @return bool
	 */
	public static function is_amazon_href( $href ) {
		if ( ! is_string( $href ) || '' === $href ) {
			return false;
		}
		$host = self::host( $href );
		if ( '' === $host ) {
			return false;
		}
		if ( 'www.' === substr( $host, 0, 4 ) ) {
			$host = substr( $host, 4 );
		}
		$roots = self::roots();
		if ( in_array( $host, $roots, true ) ) {
			return true;
		}
		foreach ( $roots as $root ) {
			$suffix = '.' . $root;
			$len    = strlen( $suffix );
			if ( strlen( $host ) > $len && substr( $host, -$len ) === $suffix ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	/**
	 * @param string $token Token or a verification meta tag.
	 * @return string
	 */
	public static function verification_tag( $token ) {
		$token = trim( (string) $token );
		if ( preg_match( '/content\s*=\s*(\'|")([A-Za-z0-9_-]+)\1/i', $token, $match ) ) {
			$token = $match[2];
		}
		if ( ! preg_match( '/^[A-Za-z0-9_-]{10,128}$/', $token ) ) {
			return '';
		}
		return '<meta name="google-site-verification" content="' . htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ) . '" />';
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public static function ensure_viewport( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return is_string( $html ) ? $html : '';
		}
		if ( preg_match( '/<meta\b[^>]*name\s*=\s*(\'|")viewport\1/i', $html ) ) {
			return $html;
		}
		$meta = '<meta name="viewport" content="width=device-width, initial-scale=1" />';
		$out  = preg_replace( '/<head\b[^>]*>/i', '$0' . $meta, $html, 1 );
		return is_string( $out ) ? $out : $html;
	}

	/**
	 * @return string[]
	 */
	public static function roots() {
		return array(
			'amazon.com',
			'amazon.co.uk',
			'amazon.ca',
			'amazon.de',
			'amazon.fr',
			'amazon.it',
			'amazon.es',
			'amazon.co.jp',
			'amazon.in',
			'amazon.com.au',
			'amazon.com.br',
			'amazon.com.mx',
			'amazon.nl',
			'amazon.sg',
			'amazon.ae',
			'amazon.sa',
			'amazon.pl',
			'amazon.se',
			'amazon.eg',
			'amazon.com.tr',
			'amazon.com.be',
			'amzn.to',
			'amzn.eu',
			'a.co',
		);
	}

	/**
	 * @param string $href Href.
	 * @return string
	 */
	private static function host( $href ) {
		$href = trim( $href );
		if ( 0 === stripos( $href, 'javascript:' ) || 0 === stripos( $href, 'data:' ) ) {
			return '';
		}
		$parts = parse_url( $href );
		$host  = is_array( $parts ) && isset( $parts['host'] ) ? $parts['host'] : '';
		return strtolower( (string) $host );
	}
}
