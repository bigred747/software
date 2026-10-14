<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only speed notes. Does not minify, dequeue, or purge on its own.
 */
class LUAE_Speed {

	/**
	 * @param string $cache_control Cache-Control header.
	 * @param bool   $litespeed_hit LiteSpeed hit.
	 * @return array{level:string,detail:string}
	 */
	public static function cache( $cache_control, $litespeed_hit ) {
		$cache = strtolower( (string) $cache_control );
		if ( false !== strpos( $cache, 'no-store' ) || false !== strpos( $cache, 'no-cache' ) ) {
			return array(
				'level'  => 'yellow',
				'detail' => 'The homepage is sending no-store or no-cache, so visitors download it again instead of a cached copy.',
			);
		}
		if ( $litespeed_hit ) {
			return array(
				'level'  => 'green',
				'detail' => 'LiteSpeed served a cached homepage.',
			);
		}
		if ( '' === trim( $cache ) ) {
			return array(
				'level'  => 'yellow',
				'detail' => 'No homepage cache sample yet.',
			);
		}
		return array(
			'level'  => 'green',
			'detail' => 'The homepage cache header allows a public copy.',
		);
	}

	/**
	 * Keep a tiny sample. The full homepage is never stored.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function snippet( $html ) {
		if ( ! preg_match_all( '/<img\b[^>]*>/i', (string) $html, $matches ) ) {
			return '';
		}
		$tags = array_slice( $matches[0], 0, 20 );
		$text = implode( "\n", $tags );
		if ( strlen( $text ) > 8000 ) {
			$text = substr( $text, 0, 8000 );
		}
		return $text;
	}

	/**
	 * @param string $html HTML sample.
	 * @return array{level:string,detail:string}
	 */
	public static function images( $html ) {
		if ( '' === trim( (string) $html ) ) {
			return array(
				'level'  => 'yellow',
				'detail' => 'No page sample yet. Image checks run when you refresh signals.',
			);
		}
		$images = LUAE_Text::images( $html );
		if ( $images['count'] < 1 ) {
			return array(
				'level'  => 'yellow',
				'detail' => 'The sample had no images to check.',
			);
		}
		if ( $images['missing_alt'] > 0 || $images['missing_dimensions'] > 0 ) {
			return array(
				'level'  => 'yellow',
				'detail' => $images['missing_dimensions'] . ' images lack width or height, and ' . $images['missing_alt'] . ' lack alt text. That is a layout-shift risk. LiteSpeed still owns optimization.',
			);
		}
		return array(
			'level'  => 'green',
			'detail' => $images['count'] . ' sampled images have alt text and dimensions.',
		);
	}
}
