<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Natural phrase sets taken from the item title. No invented products and no Amazon URLs.
 */
class Luxe_KIA_Phrases {

	const PRIMARY_MAX = 48;

	/**
	 * @param string $title Title.
	 * @param int    $limit Phrase count.
	 * @return array<int,string>
	 */
	public static function build( $title, $limit = 5 ) {
		$limit = max( 1, min( 5, (int) $limit ) );
		$clean = self::clean( $title );
		if ( self::is_junk( $clean ) ) {
			return array();
		}
		$words = preg_split( '/\s+/', $clean );
		if ( ! is_array( $words ) ) {
			return array();
		}
		$words = array_values( array_filter( $words ) );
		if ( count( $words ) < 2 ) {
			return array();
		}
		$primary = self::clip( implode( ' ', array_slice( $words, 0, 8 ) ), self::PRIMARY_MAX );
		if ( self::confidence( $primary ) < 80 ) {
			return array();
		}
		$out        = array( $primary );
		$candidates = array(
			self::clip( implode( ' ', array_slice( $words, 0, 4 ) ), self::PRIMARY_MAX ),
			self::clip( implode( ' ', array_slice( $words, 0, 3 ) ), self::PRIMARY_MAX ),
			self::clip( implode( ' ', array_slice( $words, 1, 5 ) ), self::PRIMARY_MAX ),
			self::clip( $primary . ' specs', self::PRIMARY_MAX ),
		);
		foreach ( $candidates as $candidate ) {
			if ( self::is_junk( $candidate ) || self::has_phrase( $out, $candidate ) ) {
				continue;
			}
			$out[] = $candidate;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * @param string $title Title.
	 * @return string
	 */
	public static function clean( $title ) {
		$title = html_entity_decode( strip_tags( (string) $title ), ENT_QUOTES, 'UTF-8' );
		$title = preg_replace( '#https?://\S+#i', ' ', $title );
		$title = preg_replace( '/\s+/', ' ', (string) $title );
		$title = preg_replace( '/^sale!\s*/i', '', trim( (string) $title ) );
		$title = preg_replace( '/(\.\.\.|…).*$/u', '', (string) $title );
		return trim( (string) $title, " \t\n\r\0\x0B-|–—,;:" );
	}

	/**
	 * @param string $text Text.
	 * @return bool
	 */
	public static function is_junk( $text ) {
		$text = trim( (string) $text );
		if ( strlen( $text ) < 8 ) {
			return true;
		}
		if ( preg_match( '/^[0-9]{2}[A-Za-z0-9]{6,14}$/', $text ) ) {
			return true;
		}
		if ( preg_match( '#^https?://#i', $text ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $primary Primary phrase.
	 * @return int
	 */
	public static function confidence( $primary ) {
		$words = preg_split( '/\s+/', trim( (string) $primary ) );
		$count = 0;
		if ( is_array( $words ) ) {
			foreach ( $words as $word ) {
				if ( strlen( $word ) >= 2 ) {
					$count++;
				}
			}
		}
		if ( $count >= 3 ) {
			return 90;
		}
		if ( 2 === $count ) {
			return 80;
		}
		return 40;
	}

	/**
	 * @param string $primary Primary phrase.
	 * @return string
	 */
	public static function seo_title( $primary ) {
		$primary = trim( (string) $primary );
		$suffix  = ' | LuxeTrendsetters';
		if ( strlen( $primary ) + strlen( $suffix ) <= 60 ) {
			return $primary . $suffix;
		}
		return self::clip( $primary, 60 );
	}

	/**
	 * @param string $primary Primary phrase.
	 * @return string
	 */
	public static function description( $primary ) {
		$tail = ' Compare it on LuxeTrendsetters, then use the Amazon button. Prices can change.';
		$base = rtrim( trim( (string) $primary ), '.' );
		$text = $base . '.' . $tail;
		if ( strlen( $text ) > 160 ) {
			$room = 159 - strlen( '.' . $tail );
			$base = self::clip( $base, max( 12, $room ) );
			$text = rtrim( $base, '.' ) . '.' . $tail;
		}
		if ( strlen( $text ) < 70 ) {
			$text .= ' Confirm the price on the retailer page.';
		}
		if ( strlen( $text ) > 160 ) {
			$text = self::clip( $text, 160 );
		}
		return trim( $text );
	}

	/**
	 * @param string $text Text.
	 * @param int    $max  Max bytes.
	 * @return string
	 */
	public static function clip( $text, $max ) {
		$text = trim( (string) $text );
		$max  = (int) $max;
		if ( $max < 8 || strlen( $text ) <= $max ) {
			return $text;
		}
		$cut = substr( $text, 0, $max );
		$cut = preg_replace( '/\s+\S*$/', '', (string) $cut );
		$cut = trim( (string) $cut, " \t,;:-" );
		if ( strlen( $cut ) < 8 ) {
			return trim( substr( $text, 0, $max ) );
		}
		return $cut;
	}

	/**
	 * @param array  $have Existing phrases.
	 * @param string $candidate Candidate.
	 * @return bool
	 */
	private static function has_phrase( $have, $candidate ) {
		foreach ( $have as $phrase ) {
			if ( 0 === strcasecmp( (string) $phrase, (string) $candidate ) ) {
				return true;
			}
		}
		return false;
	}
}
