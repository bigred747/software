<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LAH_Links' ) ) {
	return;
}

/**
 * Pure helpers for tracking IDs, ASINs, and tagged amazon.com links.
 * No database writes and no requests to Amazon.
 */
class LAH_Links {

	const DISCLOSURE = 'As an Amazon Associate I earn from qualifying purchases.';

	const SITE_DISCLOSURE = 'As an Amazon Associate, LuxeTrendsetters earns from qualifying purchases. Prices and availability can change on Amazon.';

	const REL = 'sponsored noopener nofollow';

	const PRICE_NOTE = 'Price and availability are set by Amazon and can change. LuxeTrendsetters does not sell this product, and Amazon does not endorse this site.';

	/**
	 * @param string $raw Raw tracking ID.
	 * @return string
	 */
	public static function tracking_id( $raw ) {
		$raw = trim( (string) $raw );
		$raw = preg_replace( '/^tag=/i', '', $raw );
		if ( ! is_string( $raw ) ) {
			return '';
		}
		if ( ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9-]{1,48}[A-Za-z0-9]$/', $raw ) ) {
			return '';
		}
		if ( ! preg_match( '/-[0-9]{2}$/', $raw ) ) {
			return '';
		}
		return $raw;
	}

	/**
	 * @param string $text ASIN or Amazon product URL.
	 * @return string
	 */
	public static function asin( $text ) {
		$text = strtoupper( self::plain( $text ) );
		if ( preg_match( '~/(?:DP|GP/PRODUCT)/([A-Z0-9]{10})(?:[/?&#]|$)~', $text, $m ) ) {
			return self::confirm_asin( $m[1] );
		}
		if ( preg_match( '/\b(B0[A-Z0-9]{8})\b/', $text, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '/^[A-Z0-9]{10}$/', $text ) ) {
			return self::confirm_asin( $text );
		}
		return '';
	}

	/**
	 * @param string $asin ASIN.
	 * @return string
	 */
	public static function confirm_asin( $asin ) {
		$asin = strtoupper( (string) $asin );
		if ( ! preg_match( '/^[A-Z0-9]{10}$/', $asin ) ) {
			return '';
		}
		if ( ! preg_match( '/[0-9]/', $asin ) || ! preg_match( '/[A-Z]/', $asin ) ) {
			return '';
		}
		return $asin;
	}

	/**
	 * Standard product Special Link. Empty unless both parts validate.
	 *
	 * @param string $asin        ASIN or product URL.
	 * @param string $tracking_id Tracking ID.
	 * @return string
	 */
	public static function product_url( $asin, $tracking_id ) {
		$asin = self::asin( $asin );
		$tag  = self::tracking_id( $tracking_id );
		if ( '' === $asin || '' === $tag ) {
			return '';
		}
		return 'https://www.amazon.com/dp/' . $asin . '?tag=' . rawurlencode( $tag );
	}

	/**
	 * Tag a whitelisted Amazon page. Best Sellers is public; Associates "Trending Now" stays behind login.
	 *
	 * @param string $path        Path.
	 * @param string $tracking_id Tracking ID.
	 * @return string
	 */
	public static function tagged_page( $path, $tracking_id ) {
		$allowed = array(
			'/gp/bestsellers',
		);
		if ( ! in_array( $path, $allowed, true ) ) {
			return '';
		}
		$base = 'https://www.amazon.com' . $path;
		$tag  = self::tracking_id( $tracking_id );
		if ( '' === $tag ) {
			return $base;
		}
		return $base . '?tag=' . rawurlencode( $tag );
	}

	/**
	 * Compliant block for a post or page. Empty unless the ASIN and tracking ID both validate.
	 * The href is the direct amazon.com Special Link. It is not cloaked or redirected.
	 *
	 * @param string $asin        ASIN or product URL.
	 * @param string $tracking_id Tracking ID.
	 * @param string $label       Link text.
	 * @return string
	 */
	public static function block_html( $asin, $tracking_id, $label = '' ) {
		$url = self::product_url( $asin, $tracking_id );
		if ( '' === $url || ! self::allowed_url( $url ) ) {
			return '';
		}
		$label = self::link_label( $label );
		return '<aside class="luxe-amazon-link">'
			. '<p class="luxe-amazon-disclosure">' . self::esc( self::DISCLOSURE ) . '</p>'
			. '<p><a class="luxe-amazon-anchor" href="' . self::esc( $url ) . '" rel="' . self::esc( self::REL ) . '" target="_blank">' . self::esc( $label ) . '</a></p>'
			. '<p class="luxe-amazon-note">' . self::esc( self::PRICE_NOTE ) . '</p>'
			. '</aside>';
	}

	/**
	 * @param string $label Requested anchor text.
	 * @return string
	 */
	public static function link_label( $label ) {
		$label = self::plain( $label );
		if ( function_exists( 'mb_substr' ) ) {
			$cut   = mb_substr( $label, 0, 120 );
			$label = is_string( $cut ) ? $cut : '';
		} elseif ( strlen( $label ) > 120 ) {
			$label = substr( $label, 0, 120 );
		}
		if ( '' === $label || preg_match( '/official amazon|endors|sponsor|guaranteed|lowest price|limited time/i', $label ) ) {
			return 'View on Amazon';
		}
		return $label;
	}

	/**
	 * True for an Amazon or amzn.to offer link. Image CDNs are not offer links.
	 *
	 * @param string $href Href.
	 * @return bool
	 */
	public static function is_amazon_offer_link( $href ) {
		$href = trim( html_entity_decode( (string) $href, ENT_QUOTES, 'UTF-8' ) );
		if ( 0 === strpos( $href, '//' ) ) {
			$href = 'https:' . $href;
		}
		if ( ! preg_match( '#^https?://#i', $href ) ) {
			return false;
		}
		$host = parse_url( $href, PHP_URL_HOST );
		if ( ! is_string( $host ) ) {
			return false;
		}
		$host = strtolower( $host );
		return in_array( $host, array( 'amazon.com', 'www.amazon.com', 'amzn.to', 'www.amzn.to' ), true );
	}

	/**
	 * Add Google's sponsored rel to Amazon offer anchors. Does not change href.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function qualify_amazon_anchors( $html ) {
		if ( ! is_string( $html ) || '' === $html || false === stripos( $html, '<a' ) ) {
			return is_string( $html ) ? $html : '';
		}
		$out = preg_replace_callback(
			'/<a\b[^>]*>/i',
			array( __CLASS__, 'qualify_anchor_tag' ),
			$html
		);
		return is_string( $out ) ? $out : $html;
	}

	/**
	 * @param array $matches Match.
	 * @return string
	 */
	public static function qualify_anchor_tag( $matches ) {
		$tag = $matches[0];
		if ( ! preg_match( '/\bhref\s*=\s*(["\'])([^"\']+)\1/i', $tag, $href_match ) ) {
			return $tag;
		}
		$href = $href_match[2];
		if ( ! self::is_amazon_offer_link( $href ) ) {
			return $tag;
		}
		$need = array( 'sponsored', 'noopener', 'nofollow' );
		if ( preg_match( '/\brel\s*=\s*(["\'])([^"\']*)\1/i', $tag, $rel_match ) ) {
			$have = preg_split( '/\s+/', strtolower( trim( $rel_match[2] ) ) );
			if ( ! is_array( $have ) ) {
				$have = array();
			}
			foreach ( $need as $token ) {
				if ( ! in_array( $token, $have, true ) ) {
					$have[] = $token;
				}
			}
			$rel = trim( implode( ' ', array_filter( $have ) ) );
			$updated = preg_replace( '/\brel\s*=\s*(["\'])([^"\']*)\1/i', 'rel="' . $rel . '"', $tag, 1 );
			return is_string( $updated ) ? $updated : $tag;
		}
		$updated = preg_replace( '/>$/', ' rel="' . self::REL . '">', $tag, 1 );
		return is_string( $updated ) ? $updated : $tag;
	}

	/**
	 * The live catalog sometimes says "use the DJI button" on a page whose button goes to Amazon.
	 * Only rewrite that sentence when an Amazon product link is already on the page.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function honest_retailer_phrase( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, 'www.amazon.com/dp/' ) ) {
			return is_string( $html ) ? $html : '';
		}
		$out = preg_replace(
			'/then use the (?!Amazon\b)([A-Za-z0-9][A-Za-z0-9 .&-]{0,40}) button/i',
			'then use the Amazon button',
			$html
		);
		return is_string( $out ) ? $out : $html;
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	public static function esc( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * @param string $url URL.
	 * @return bool
	 */
	public static function allowed_url( $url ) {
		if ( ! is_string( $url ) || ! preg_match( '#^https://#', $url ) ) {
			return false;
		}
		$host = parse_url( $url, PHP_URL_HOST );
		$ok   = array(
			'affiliate-program.amazon.com',
			'www.amazon.com',
			'contentpartners.amazon.com',
			'www.aboutamazon.com',
		);
		return is_string( $host ) && in_array( strtolower( $host ), $ok, true );
	}

	/**
	 * @param mixed $log   Log.
	 * @param array $row   Row.
	 * @param int   $limit Limit.
	 * @return array
	 */
	public static function push_log( $log, $row, $limit = 30 ) {
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift( $log, $row );
		$limit = (int) $limit;
		if ( $limit < 1 ) {
			$limit = 1;
		}
		if ( count( $log ) > $limit ) {
			$log = array_slice( $log, 0, $limit );
		}
		return $log;
	}

	/**
	 * @param int   $started_at Started.
	 * @param mixed $log        Log.
	 * @param int   $now        Now.
	 * @return array{open:bool,shared:bool}
	 */
	public static function week_status( $started_at, $log, $now ) {
		$started_at = (int) $started_at;
		$now        = (int) $now;
		$open       = ( $started_at <= 0 ) || ( ( $now - $started_at ) < 604800 );
		$shared     = false;
		if ( is_array( $log ) ) {
			foreach ( $log as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$at = isset( $row['at'] ) ? (int) $row['at'] : 0;
				if ( $at <= 0 ) {
					continue;
				}
				if ( $started_at > 0 && $at < $started_at ) {
					continue;
				}
				if ( $started_at > 0 && $at >= ( $started_at + 604800 ) ) {
					continue;
				}
				$shared = true;
				break;
			}
		}
		return array(
			'open'   => $open,
			'shared' => $shared,
		);
	}

	/**
	 * Local reminder only. Reject long digit strings so tax IDs and account numbers are not stored.
	 *
	 * @param string $text Note.
	 * @return string
	 */
	public static function note( $text ) {
		$text = self::plain( $text );
		if ( function_exists( 'mb_substr' ) ) {
			$cut  = mb_substr( $text, 0, 500 );
			$text = is_string( $cut ) ? $cut : '';
		} elseif ( strlen( $text ) > 500 ) {
			$text = substr( $text, 0, 500 );
		}
		if ( preg_match( '/\d{3}-\d{2}-\d{4}/', $text ) ) {
			return '';
		}
		if ( preg_match( '/\d{8,}/', $text ) ) {
			return '';
		}
		return $text;
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	public static function plain( $text ) {
		$text = trim( strip_tags( (string) $text ) );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$spaced = preg_replace( '/\s+/', ' ', $text );
		return trim( is_string( $spaced ) ? $spaced : $text );
	}
}
