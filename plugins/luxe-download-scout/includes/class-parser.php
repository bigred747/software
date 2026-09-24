<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LDS_Parser' ) ) {
	return;
}

/**
 * Reads a pasted WZone result list. No network calls and no imports.
 */
class LDS_Parser {

	/**
	 * @param string $text Pasted WZone page text.
	 * @return array<int,array<string,mixed>>
	 */
	public static function parse( $text ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		$text = preg_replace( '/\x{00A0}/u', ' ', $text );
		$lines = explode( "\n", is_string( $text ) ? $text : '' );
		$rows  = array();
		$buf   = array();
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '/^(add to import list|already imported)$/i', $line ) ) {
				$row = self::block( $buf, $line );
				if ( $row ) {
					$rows[] = $row;
				}
				$buf = array();
				continue;
			}
			$buf[] = $line;
		}
		return $rows;
	}

	/**
	 * One title is the line directly above the price, so dashboard text above a paste is ignored.
	 *
	 * @param string[] $lines  Lines since the previous product.
	 * @param string   $action Add to import list or Already Imported.
	 * @return array<string,mixed>|null
	 */
	private static function block( $lines, $action ) {
		$price_at = null;
		for ( $i = count( $lines ) - 1; $i >= 0; $i-- ) {
			if ( preg_match( '/^\$[\d,]+$/', $lines[ $i ] ) ) {
				$price_at = $i;
				break;
			}
		}
		if ( null === $price_at ) {
			return null;
		}
		$title = '';
		for ( $i = $price_at - 1; $i >= 0; $i-- ) {
			$line = trim( $lines[ $i ] );
			if ( '' === $line || '...' === $line || preg_match( '/^\d{1,2}$/', $line ) ) {
				continue;
			}
			$title = $line;
			break;
		}
		$title = self::clean_title( $title );
		if ( '' === $title ) {
			return null;
		}
		$imported = (bool) preg_match( '/already imported/i', $action );
		return array(
			'title'    => $title,
			'price'    => self::money( $lines[ $price_at ] ),
			'imported' => $imported,
		);
	}

	/**
	 * @param string $title Title.
	 * @return string
	 */
	public static function clean_title( $title ) {
		$title = str_replace( "\xC2\xA0", ' ', (string) $title );
		$clean = preg_replace( '/\s*Global Recycled Standard\s*/i', ' ', $title );
		if ( is_string( $clean ) ) {
			$title = $clean;
		}
		$spaced = preg_replace( '/\s+/', ' ', $title );
		return trim( is_string( $spaced ) ? $spaced : $title );
	}

	/**
	 * WZone prints dollars and cents as one number: $1,82995 is $1,829.95.
	 *
	 * @param string $raw Price text.
	 * @return float
	 */
	public static function money( $raw ) {
		$digits = preg_replace( '/\D/', '', (string) $raw );
		if ( ! is_string( $digits ) || strlen( $digits ) < 3 ) {
			return 0.0;
		}
		$dollars = substr( $digits, 0, -2 );
		$cents   = substr( $digits, -2 );
		return (float) ( $dollars . '.' . $cents );
	}

	/**
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function money_label( $amount ) {
		return '$' . number_format( (float) $amount, 2, '.', ',' );
	}
}
