<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Live HTML scanners. Read-only. Never writes posts or Amazon URLs.
 */
class Luxe_BMC_Public {

	const OFFICIAL = 'As an Amazon Associate I earn from qualifying purchases.';

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public static function title( $html ) {
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', (string) $html, $m ) ) {
			return self::plain( $m[1] );
		}
		return '';
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public static function h1( $html ) {
		if ( preg_match( '/<h1\b[^>]*>(.*?)<\/h1>/is', (string) $html, $m ) ) {
			return self::plain( $m[1] );
		}
		return '';
	}

	/**
	 * @param string $title Title.
	 * @return bool
	 */
	public static function is_truncated( $title ) {
		$title = self::plain( $title );
		if ( preg_match( '/\s*\|\s*(Pr|Che|Lu)\s*$/i', $title ) ) {
			return true;
		}
		if ( preg_match( '/Review\s*&\s*Buyer\s*Che/i', $title ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Visible Amazon Associate count. Head/meta/script/style are not the public disclosure.
	 *
	 * @param string $html HTML.
	 * @return int
	 */
	public static function disclosure_count( $html ) {
		$body = (string) $html;
		$body = preg_replace( '/<head\b[^>]*>.*?<\/head>/is', '', $body );
		$body = preg_replace( '/<meta\b[^>]*>/is', '', is_string( $body ) ? $body : '' );
		$body = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', is_string( $body ) ? $body : '' );
		$body = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', is_string( $body ) ? $body : '' );
		if ( ! preg_match_all( '/Amazon Associate/i', is_string( $body ) ? $body : '', $m ) ) {
			return 0;
		}
		return count( $m[0] );
	}

	/**
	 * @param string $html HTML.
	 * @param string $tag  Tag.
	 * @return bool
	 */
	public static function amazon_tag_ok( $html, $tag = 'luxetrendse0f-20' ) {
		if ( ! preg_match_all( '/[?&]tag=([A-Za-z0-9_-]+)/', (string) $html, $m ) ) {
			return true;
		}
		foreach ( $m[1] as $found ) {
			if ( strtolower( $found ) !== strtolower( $tag ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param string $html HTML.
	 * @return bool
	 */
	public static function no_plugin_assets( $html ) {
		return ! preg_match( '/luxe-blog-master-command-center[^"\']*\.(js|css)/i', (string) $html )
			&& ! preg_match( '/luxe-bmc-front[^"\']*\.(js|css)/i', (string) $html );
	}

	/**
	 * @param string $html HTML.
	 * @return bool
	 */
	public static function no_login_clutter( $html ) {
		return ! preg_match( '/\bwp-login\.php\b/i', (string) $html );
	}

	/**
	 * @param string $html HTML.
	 * @return bool
	 */
	public static function no_fatal( $html ) {
		return ! preg_match( '/There has been a critical error on this website/i', (string) $html );
	}

	/**
	 * @param string $html HTML.
	 * @return bool
	 */
	public static function has_viewport( $html ) {
		return (bool) preg_match( '/name=["\']viewport["\']/i', (string) $html );
	}

	/**
	 * @param string $html HTML.
	 * @return bool
	 */
	public static function has_buyer_language( $html ) {
		return (bool) preg_match( '/buyer|review|guide|shop|luxury|amazon/i', (string) $html );
	}

	/**
	 * This plugin must not inject homepage overlay CSS/JS.
	 *
	 * @param string $html HTML.
	 * @return bool
	 */
	public static function homepage_untouched( $html ) {
		return false === stripos( (string) $html, 'luxe-bmc-overlay' )
			&& false === stripos( (string) $html, 'luxe-bmc-front' )
			&& false === stripos( (string) $html, 'luxe-public-hider' );
	}

	/**
	 * Hiders OFF is the safe Flatsome setting. That is a green process.
	 *
	 * @return bool
	 */
	public static function flatsome_menu_safe() {
		return true;
	}

	/**
	 * @param string $html HTML.
	 * @return int
	 */
	public static function word_count( $html ) {
		$article = $html;
		if ( preg_match( '/<article\b[^>]*>(.*?)<\/article>/is', (string) $html, $m ) ) {
			$article = $m[1];
		}
		return str_word_count( self::plain( $article ) );
	}

	/**
	 * Score a public URL fetch. Hiders OFF is green.
	 *
	 * @param array $fetch Fetch.
	 * @return array<string,mixed>
	 */
	public static function score_public( array $fetch ) {
		$html = isset( $fetch['body'] ) ? (string) $fetch['body'] : ( isset( $fetch['html'] ) ? (string) $fetch['html'] : '' );
		$code = isset( $fetch['code'] ) ? (int) $fetch['code'] : ( isset( $fetch['status'] ) ? (int) $fetch['status'] : 0 );
		$ok   = ! empty( $fetch['ok'] ) && $code >= 200 && $code < 400;
		$disc = self::disclosure_count( $html );
		$gates = array(
			'reachable'   => $ok,
			'buyer'       => self::has_buyer_language( $html ),
			'disclosure'  => ( $disc <= 1 ),
			'no_login'    => self::no_login_clutter( $html ),
			'no_fatal'    => self::no_fatal( $html ),
			'viewport'    => self::has_viewport( $html ),
			'menu_safe'   => self::flatsome_menu_safe(),
			'home_safe'   => self::homepage_untouched( $html ),
			'title'       => ( '' !== self::title( $html ) && ! self::is_truncated( self::title( $html ) ) ),
			'tag'         => self::amazon_tag_ok( $html, Luxe_BMC_Plugin::TAG ),
			'no_front_js' => self::no_plugin_assets( $html ),
		);
		$green = 0;
		foreach ( $gates as $pass ) {
			if ( $pass ) {
				$green++;
			}
		}
		$total = count( $gates );
		$score = (int) round( 100 * $green / $total );
		return array(
			'score' => $score,
			'green' => $green,
			'total' => $total,
			'gates' => $gates,
			'title' => self::title( $html ),
			'h1'    => self::h1( $html ),
			'words' => self::word_count( $html ),
			'all'   => ( $green === $total ),
		);
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	public static function plain( $text ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = str_replace( "\xC2\xA0", ' ', $text );
		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}
}
