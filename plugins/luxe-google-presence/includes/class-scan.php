<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read public HTML and robots.txt into green or red signals.
 * Computer and mobile documents are compared. Different copies would be cloaking.
 */
class Luxe_Google_Presence_Scan {

	/**
	 * @param string $desktop Desktop HTML.
	 * @param string $mobile  Mobile HTML.
	 * @param string $robots  robots.txt.
	 * @return array
	 */
	public static function evaluate( $desktop, $mobile, $robots ) {
		$desktop = is_string( $desktop ) ? $desktop : '';
		$mobile  = is_string( $mobile ) ? $mobile : '';
		$robots  = is_string( $robots ) ? $robots : '';
		$title_d = self::title( $desktop );
		$title_m = self::title( $mobile );
		$canon_d = self::canonical( $desktop );
		$canon_m = self::canonical( $mobile );
		$desc    = self::meta( $desktop, 'description' );
		$robots_meta = strtolower( self::meta( $desktop, 'robots' ) );
		$blob    = $desktop . "\n" . $mobile;

		$signals = array(
			Luxe_Google_Presence_Score::signal(
				'https',
				'HTTPS canonical',
				'' !== $canon_d && 0 === strpos( $canon_d, 'https://' ),
				$canon_d ? $canon_d : 'Canonical missing'
			),
			Luxe_Google_Presence_Score::signal(
				'googlebot',
				'Googlebot may crawl the homepage',
				self::googlebot_allowed( $robots ),
				self::googlebot_allowed( $robots ) ? 'robots.txt allows the homepage' : 'robots.txt blocks Googlebot'
			),
			Luxe_Google_Presence_Score::signal(
				'sitemap',
				'Sitemap listed for Google',
				(bool) preg_match( '/^Sitemap:\s*https:\/\//mi', $robots ),
				'Sitemap line in robots.txt'
			),
			Luxe_Google_Presence_Score::signal(
				'indexable',
				'Homepage is indexable',
				false === strpos( $robots_meta, 'noindex' ),
				$robots_meta ? $robots_meta : 'No noindex meta'
			),
			Luxe_Google_Presence_Score::signal(
				'viewport',
				'Mobile viewport',
				(bool) preg_match( '/<meta\b[^>]*name\s*=\s*(\'|")viewport\1/i', $desktop )
					&& (bool) preg_match( '/<meta\b[^>]*name\s*=\s*(\'|")viewport\1/i', $mobile ),
				'width=device-width on computer and mobile'
			),
			Luxe_Google_Presence_Score::signal(
				'title',
				'Homepage title length',
				self::length_ok( $title_d, 15, 70 ),
				$title_d ? $title_d : 'Title missing'
			),
			Luxe_Google_Presence_Score::signal(
				'description',
				'Meta description length',
				self::length_ok( $desc, 50, 180 ),
				$desc ? $desc : 'Description missing'
			),
			Luxe_Google_Presence_Score::signal(
				'same_title',
				'Same title on computer and mobile',
				'' !== $title_d && $title_d === $title_m,
				$title_d === $title_m ? 'One title for both devices' : 'Titles differ'
			),
			Luxe_Google_Presence_Score::signal(
				'same_canonical',
				'Same canonical on computer and mobile',
				'' !== $canon_d && $canon_d === $canon_m,
				$canon_d === $canon_m ? 'One canonical for both devices' : 'Canonicals differ'
			),
			Luxe_Google_Presence_Score::signal(
				'disclosure',
				'Amazon Associate disclosure',
				self::has_disclosure( $blob ),
				'Associate disclosure is on the page'
			),
			Luxe_Google_Presence_Score::signal(
				'price_note',
				'Price and availability note',
				self::has_price_note( $blob ),
				'Shoppers are told Amazon prices can change'
			),
			Luxe_Google_Presence_Score::signal(
				'no_refresh',
				'No automatic redirect',
				false === stripos( $blob, 'http-equiv="refresh"' ) && false === stripos( $blob, "http-equiv='refresh'" ),
				'No meta refresh'
			),
			Luxe_Google_Presence_Score::signal(
				'no_amazon_frame',
				'Amazon is not framed',
				! self::frames_amazon( $blob ),
				'No iframe pointed at Amazon'
			),
			Luxe_Google_Presence_Score::signal(
				'official_disclosure',
				'Official Associate sentence',
				false !== strpos( $blob, Luxe_Google_Presence_Copy::DISCLOSURE ),
				Luxe_Google_Presence_Copy::DISCLOSURE
			),
			Luxe_Google_Presence_Score::signal(
				'official_price',
				'Official price disclaimer',
				false !== strpos( $blob, Luxe_Google_Presence_Copy::PRICE ),
				'Full Amazon price and availability disclaimer'
			),
		);

		return Luxe_Google_Presence_Score::decide(
			$signals,
			array( 'googlebot', 'indexable', 'same_title', 'same_canonical', 'no_refresh', 'no_amazon_frame' ),
			array( 'official_disclosure', 'official_price' )
		);
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public static function title( $html ) {
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', (string) $html, $match ) ) {
			return self::clean( $match[1] );
		}
		return '';
	}

	/**
	 * @param string $html HTML.
	 * @param string $name Meta name.
	 * @return string
	 */
	public static function meta( $html, $name ) {
		$html = (string) $html;
		$name = preg_quote( (string) $name, '/' );
		if ( preg_match( '/<meta[^>]+name=["\']' . $name . '["\'][^>]+content=["\']([^"\']*)["\']/i', $html, $match ) ) {
			return self::clean( $match[1] );
		}
		if ( preg_match( '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']' . $name . '["\']/i', $html, $match ) ) {
			return self::clean( $match[1] );
		}
		return '';
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public static function canonical( $html ) {
		if ( preg_match( '/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']/i', (string) $html, $match ) ) {
			return self::clean( $match[1] );
		}
		if ( preg_match( '/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']canonical["\']/i', (string) $html, $match ) ) {
			return self::clean( $match[1] );
		}
		return '';
	}

	/**
	 * @param string $robots robots.txt.
	 * @return bool
	 */
	public static function googlebot_allowed( $robots ) {
		$group = self::agent_group( $robots, 'googlebot' );
		if ( ! $group ) {
			$group = self::agent_group( $robots, '*' );
		}
		if ( ! $group ) {
			return true;
		}
		$blocks_all = false;
		$allows_root = false;
		foreach ( $group as $rule ) {
			if ( 0 === strpos( $rule, 'disallow:' ) ) {
				$path = trim( substr( $rule, strlen( 'disallow:' ) ) );
				if ( '/' === $path ) {
					$blocks_all = true;
				}
			}
			if ( 0 === strpos( $rule, 'allow:' ) ) {
				$path = trim( substr( $rule, strlen( 'allow:' ) ) );
				if ( '/' === $path || '' === $path ) {
					$allows_root = true;
				}
			}
		}
		if ( $blocks_all && ! $allows_root ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $text Text.
	 * @param int    $min  Min.
	 * @param int    $max  Max.
	 * @return bool
	 */
	public static function length_ok( $text, $min, $max ) {
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text ) : strlen( (string) $text );
		return $length >= $min && $length <= $max;
	}

	/**
	 * @param string $html HTML.
	 * @return bool
	 */
	public static function has_disclosure( $html ) {
		$hay = strtolower( (string) $html );
		if ( false !== strpos( $hay, strtolower( Luxe_Google_Presence_Copy::DISCLOSURE ) ) ) {
			return true;
		}
		return false !== strpos( $hay, 'amazon associate' ) && false !== strpos( $hay, 'qualifying purchases' );
	}

	/**
	 * @param string $html HTML.
	 * @return bool
	 */
	public static function has_price_note( $html ) {
		$hay = strtolower( (string) $html );
		if ( false !== strpos( (string) $html, Luxe_Google_Presence_Copy::PRICE ) ) {
			return true;
		}
		return false !== strpos( $hay, 'subject to change' )
			|| ( false !== strpos( $hay, 'prices and availability' ) && false !== strpos( $hay, 'change' ) );
	}

	/**
	 * @param string $html HTML.
	 * @return bool
	 */
	public static function frames_amazon( $html ) {
		if ( ! preg_match_all( '/<iframe\b[^>]*>/i', (string) $html, $matches ) ) {
			return false;
		}
		foreach ( $matches[0] as $tag ) {
			if ( preg_match( '/\bsrc\s*=\s*(\'|")(.*?)\1/i', $tag, $src ) && Luxe_Google_Presence_Anchors::is_amazon_href( html_entity_decode( $src[2], ENT_QUOTES, 'UTF-8' ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	private static function clean( $text ) {
		$text = html_entity_decode( strip_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
		$text = str_replace( "\xC2\xA0", ' ', $text );
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		return is_string( $text ) ? $text : '';
	}

	/**
	 * @param string $robots robots.txt.
	 * @param string $agent  Agent.
	 * @return string[]
	 */
	private static function agent_group( $robots, $agent ) {
		$lines   = preg_split( '/\r\n|\n|\r/', (string) $robots );
		$groups  = array();
		$agents  = array();
		$rules   = array();
		$flush   = function () use ( &$groups, &$agents, &$rules ) {
			if ( $agents ) {
				$groups[] = array(
					'agents' => $agents,
					'rules'  => $rules,
				);
			}
			$agents = array();
			$rules  = array();
		};
		if ( ! is_array( $lines ) ) {
			return array();
		}
		foreach ( $lines as $line ) {
			$line = trim( (string) preg_replace( '/#.*/', '', (string) $line ) );
			if ( '' === $line ) {
				continue;
			}
			if ( 0 === stripos( $line, 'user-agent:' ) ) {
				if ( $rules ) {
					$flush();
				}
				$agents[] = strtolower( trim( substr( $line, strlen( 'user-agent:' ) ) ) );
				continue;
			}
			$rules[] = strtolower( $line );
		}
		$flush();
		$agent = strtolower( $agent );
		foreach ( $groups as $group ) {
			if ( in_array( $agent, $group['agents'], true ) ) {
				return $group['rules'];
			}
		}
		return array();
	}
}
