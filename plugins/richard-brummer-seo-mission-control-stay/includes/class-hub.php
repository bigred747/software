<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Archive compare hub markup. Query happens in the frontend class.
 */
class RBSMC_Stay_Hub {

	/**
	 * @param string $label Collection label.
	 * @param array  $products List of url/title.
	 * @param array  $guides   List of url/title.
	 * @param array  $siblings List of url/title.
	 * @return string
	 */
	public static function markup( $label, $products, $guides, $siblings ) {
		$label = self::h( $label ? $label : 'this collection' );
		$html  = '<aside class="rbsmc-stay-hub" data-rbsmc-stay-hub="1">';
		$html .= '<h2>Compare ' . $label . ' for about five minutes</h2>';
		$html .= '<p>The 1-second Site Kit number is a bounce, not a reading session. Stay on LuxeTrendsetters, open two or three pages in this collection, and only then click through to Amazon.</p>';
		$html .= '<ol class="rbsmc-stay-checks">';
		$html .= '<li>Confirm the exact model, size, and year on the product page.</li>';
		$html .= '<li>Compare at least one alternative in this collection.</li>';
		$html .= '<li>Read the affiliate disclosure and the live Amazon listing before you buy.</li>';
		$html .= '<li>If you are still deciding, open a related guide instead of leaving.</li>';
		$html .= '</ol>';
		$html .= self::list_block( 'Products to compare', $products );
		$html .= self::list_block( 'Buyer guides to read next', $guides );
		$html .= self::list_block( 'Other collections', $siblings );
		$html .= '</aside>';
		return $html;
	}

	/**
	 * @param string $heading Heading.
	 * @param array  $items   url/title rows.
	 * @return string
	 */
	private static function list_block( $heading, $items ) {
		if ( ! is_array( $items ) || ! $items ) {
			return '';
		}
		$html = '<h3>' . self::h( $heading ) . '</h3><ul>';
		foreach ( $items as $item ) {
			$url   = isset( $item['url'] ) ? $item['url'] : '';
			$title = isset( $item['title'] ) ? $item['title'] : '';
			if ( '' === $url || '' === $title ) {
				continue;
			}
			$html .= '<li><a href="' . self::h( $url ) . '">' . self::h( $title ) . '</a></li>';
		}
		$html .= '</ul>';
		return $html;
	}

	/**
	 * @param string $value Raw.
	 * @return string
	 */
	private static function h( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}
