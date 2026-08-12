<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Strict JSON-LD parse and rendered-output quarantine.
 * Does not write post_content or change post status.
 */
class RBSMC_Stay_Schema {

	/**
	 * Decode entities, strip CDATA/comments, trim.
	 *
	 * @param string $raw Raw JSON-LD.
	 * @return string
	 */
	public static function normalize( $raw ) {
		$raw = html_entity_decode( (string) $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$raw = preg_replace( '/^\s*<!--/', '', $raw );
		$raw = preg_replace( '/-->\s*$/', '', $raw );
		$raw = preg_replace( '/^\s*<!\[CDATA\[/', '', $raw );
		$raw = preg_replace( '/\]\]>\s*$/', '', $raw );
		return trim( $raw );
	}

	/**
	 * @param string $raw Raw JSON-LD body.
	 * @return array{ok:bool,error:string,hash:string,bytes:int,preview:string}
	 */
	public static function inspect( $raw ) {
		$normalized = self::normalize( $raw );
		$hash       = hash( 'sha256', $normalized );
		$decoded    = json_decode( $normalized, true );
		$error      = json_last_error();
		$ok         = ( JSON_ERROR_NONE === $error && null !== $decoded );
		return array(
			'ok'      => $ok,
			'error'   => $ok ? '' : json_last_error_msg(),
			'hash'    => $hash,
			'bytes'   => strlen( $normalized ),
			'preview' => substr( $normalized, 0, 180 ),
		);
	}

	/**
	 * Find application/ld+json blocks in HTML.
	 *
	 * @param string $html HTML.
	 * @return array
	 */
	public static function audit_html( $html ) {
		$signals = array();
		$invalid = 0;
		$blocks  = 0;
		if ( ! preg_match_all( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', (string) $html, $matches ) ) {
			return array(
				'signals' => $signals,
				'blocks'  => 0,
				'invalid' => 0,
				'items'   => array(),
			);
		}
		$items = array();
		foreach ( $matches[1] as $raw ) {
			$block = self::inspect( $raw );
			$blocks++;
			$items[] = $block;
			if ( $block['bytes'] && ! $block['ok'] ) {
				$invalid++;
				$signals[] = array(
					'level'  => 'red',
					'title'  => 'JSON-LD failed strict parsing after safe normalization',
					'detail' => 'error=' . $block['error'] . ' sha256=' . $block['hash'] . ' bytes=' . $block['bytes'],
					'scored' => true,
					'hash'   => $block['hash'],
				);
			}
		}
		return array(
			'signals' => $signals,
			'blocks'  => $blocks,
			'invalid' => $invalid,
			'items'   => $items,
		);
	}

	/**
	 * Replace only invalid JSON-LD script tags. Valid Rank Math / other blocks stay.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function quarantine_invalid( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, 'application/ld+json' ) ) {
			return $html;
		}
		return preg_replace_callback(
			'/<script([^>]*type=["\']application\/ld\+json["\'][^>]*)>(.*?)<\/script>/is',
			function ( $m ) {
				$block = RBSMC_Stay_Schema::inspect( $m[2] );
				if ( $block['bytes'] && ! $block['ok'] ) {
					return '<!-- rbsmc-stay quarantined invalid json-ld sha256=' . $block['hash'] . ' error=' . $block['error'] . ' -->';
				}
				return $m[0];
			},
			$html
		);
	}
}
