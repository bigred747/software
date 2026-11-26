<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Approval gate. Drafts stay off the public page until these checks pass.
 */
class LUAE_Approve {

	/**
	 * @param string $text Verdict.
	 * @return string[]
	 */
	public static function verdict( $text ) {
		$text = trim( LUAE_Text::plain( $text ) );
		$errors = array();
		if ( LUAE_Text::has_placeholder( $text ) ) {
			$errors[] = 'Replace the verdict note with your own sentences.';
		}
		if ( strlen( $text ) < 40 ) {
			$errors[] = 'The verdict needs at least 40 characters in your own words.';
		}
		return $errors;
	}

	/**
	 * @param string $text One item per line.
	 * @param int    $min  Minimum rows.
	 * @param string $label Label.
	 * @return string[]
	 */
	public static function lines( $text, $min, $label ) {
		$rows   = self::clean_lines( $text );
		$errors = array();
		if ( count( $rows ) < $min ) {
			$errors[] = $label . ' needs at least ' . (int) $min . ' lines.';
		}
		foreach ( $rows as $row ) {
			if ( LUAE_Text::has_placeholder( $row ) || strlen( $row ) < 8 ) {
				$errors[] = $label . ' still has an unfinished line.';
				break;
			}
		}
		return $errors;
	}

	/**
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function clean_lines( $text ) {
		$parts = preg_split( '/\r\n|\r|\n/', (string) $text );
		if ( ! is_array( $parts ) ) {
			return array();
		}
		$out = array();
		foreach ( $parts as $part ) {
			$part = trim( LUAE_Text::plain( $part ) );
			$part = preg_replace( '/^[✓✔✗✘xX\-\*]+\s*/u', '', $part );
			$part = trim( (string) $part );
			if ( '' !== $part ) {
				$out[] = $part;
			}
		}
		return $out;
	}

	/**
	 * @param array<string,string> $scores Scores.
	 * @return string[]
	 */
	public static function scorecard( $scores ) {
		$errors = array();
		$keys   = array( 'design', 'performance', 'battery', 'value', 'overall' );
		if ( ! is_array( $scores ) ) {
			return array( 'Enter all five scores.' );
		}
		foreach ( $keys as $key ) {
			$value = isset( $scores[ $key ] ) ? trim( (string) $scores[ $key ] ) : '';
			if ( ! preg_match( '/^(10(\.0)?|[0-9](\.[0-9])?)$/', $value ) ) {
				$errors[] = ucfirst( $key ) . ' needs a score from 0 to 10.';
			}
		}
		return $errors;
	}

	/**
	 * @param array<int,array{title:string,url:string}> $items Items.
	 * @param string                                    $home  Home URL.
	 * @return string[]
	 */
	public static function compare( $items, $home ) {
		$errors = array();
		$good   = 0;
		if ( ! is_array( $items ) ) {
			return array( 'Add at least one real guide to compare.' );
		}
		foreach ( $items as $item ) {
			$title = isset( $item['title'] ) ? trim( LUAE_Text::plain( $item['title'] ) ) : '';
			$url   = isset( $item['url'] ) ? trim( (string) $item['url'] ) : '';
			if ( '' === $title && '' === $url ) {
				continue;
			}
			if ( strlen( $title ) < 3 || ! LUAE_Text::is_local_url( $url, $home ) ) {
				$errors[] = 'Comparison links must stay on this site and cannot be Amazon URLs.';
				continue;
			}
			$good++;
		}
		if ( $good < 1 ) {
			$errors[] = 'Add at least one real guide to compare.';
		}
		if ( $good > 3 ) {
			$errors[] = 'Keep the comparison to three guides.';
		}
		return $errors;
	}

	/**
	 * @param string $text FAQ.
	 * @return string[]
	 */
	public static function faq( $text ) {
		$text = trim( LUAE_Text::plain( $text ) );
		if ( strlen( $text ) < 40 || LUAE_Text::has_placeholder( $text ) ) {
			return array( 'Write at least one real question and answer.' );
		}
		return array();
	}
}
