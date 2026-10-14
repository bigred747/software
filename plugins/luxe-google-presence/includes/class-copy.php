<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public copy required by Amazon Associates and the FTC, plus on-site paths.
 * The block does not create an Amazon URL. The shopper uses the button already on the page.
 */
class Luxe_Google_Presence_Copy {

	const DISCLOSURE = 'As an Amazon Associate I earn from qualifying purchases.';

	const PRICE = 'Product prices and availability are accurate as of the date/time indicated and are subject to change. Any price and availability information displayed on Amazon.com at the time of purchase will apply to the purchase of this product.';

	const PAID = '(paid link)';

	/**
	 * @param string $home        Home URL.
	 * @param array  $candidates  Optional link rows with url and label.
	 * @return string
	 */
	public static function aside( $home, $candidates = array() ) {
		$links = self::internal_links( $home, $candidates );
		$items = '';
		foreach ( $links as $link ) {
			$items .= '<li><a href="' . self::esc( $link['url'] ) . '">' . self::esc( $link['label'] ) . '</a></li>';
		}
		$html  = '<aside class="lgp-stay" aria-label="Compare on LuxeTrendsetters">';
		$html .= '<p class="lgp-stay-kicker">On this site first</p>';
		$html .= '<h2>Compare on LuxeTrendsetters, then buy on Amazon</h2>';
		$html .= '<p>Stay here and open another guide. The Amazon button on this page is a paid link. ' . self::esc( self::PAID ) . '</p>';
		$html .= '<ul class="lgp-stay-links">' . $items . '</ul>';
		$html .= '<p class="lgp-associate">' . self::esc( self::DISCLOSURE ) . ' ' . self::esc( self::PRICE ) . ' When you are ready, use the Amazon button on this page to buy on Amazon.</p>';
		$html .= '</aside>';
		return $html;
	}

	/**
	 * @param string $home       Home URL.
	 * @param array  $candidates Candidates.
	 * @return array<int,array{url:string,label:string}>
	 */
	public static function internal_links( $home, $candidates = array() ) {
		$home = is_string( $home ) ? $home : '';
		$host = self::host( $home );
		$rows = array();
		$list = is_array( $candidates ) ? $candidates : array();
		if ( ! $list ) {
			$list = self::defaults( $home );
		}
		foreach ( $list as $row ) {
			if ( ! is_array( $row ) || empty( $row['url'] ) || empty( $row['label'] ) ) {
				continue;
			}
			$url = (string) $row['url'];
			if ( self::is_rejected_url( $url, $host ) ) {
				continue;
			}
			$rows[] = array(
				'url'   => $url,
				'label' => (string) $row['label'],
			);
			if ( count( $rows ) >= 6 ) {
				break;
			}
		}
		return $rows;
	}

	/**
	 * @param string $home Home URL.
	 * @return array<int,array{url:string,label:string}>
	 */
	public static function defaults( $home ) {
		$base = rtrim( (string) $home, '/' );
		return array(
			array(
				'url'   => $base . '/shop/',
				'label' => 'Shop the catalog',
			),
			array(
				'url'   => $base . '/blogs/',
				'label' => 'Buyer guides',
			),
			array(
				'url'   => $base . '/shopping-resources/',
				'label' => 'Shopping resources',
			),
			array(
				'url'   => $base . '/about/',
				'label' => 'About LuxeTrendsetters',
			),
		);
	}

	/**
	 * @param string $url  URL.
	 * @param string $host Expected host.
	 * @return bool
	 */
	public static function is_rejected_url( $url, $host ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return true;
		}
		$trim = strtolower( trim( $url ) );
		if ( 0 === strpos( $trim, 'javascript:' ) || 0 === strpos( $trim, 'data:' ) ) {
			return true;
		}
		if ( class_exists( 'Luxe_Google_Presence_Anchors' ) && Luxe_Google_Presence_Anchors::is_amazon_href( $url ) ) {
			return true;
		}
		$link_host = self::host( $url );
		if ( '' === $link_host || '' === $host ) {
			return true;
		}
		return strtolower( $link_host ) !== strtolower( $host );
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	private static function host( $url ) {
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
