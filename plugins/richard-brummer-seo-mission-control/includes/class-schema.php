<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Strict JSON-LD parse, wrapper/entity normalization, and SHA-256 evidence.
 */
class RBSMC_Schema {

	/**
	 * @param string $html HTML.
	 * @return array
	 */
	public static function audit_html( $html ) {
		$blocks = array();
		if ( ! preg_match_all( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches ) ) {
			return array(
				'signals' => array(),
				'blocks'  => 0,
				'invalid' => 0,
			);
		}
		$invalid = 0;
		$signals = array();
		foreach ( $matches[1] as $raw ) {
			$normalized = self::normalize( $raw );
			$hash       = hash( 'sha256', $normalized );
			$decoded    = json_decode( $normalized, true );
			$error      = json_last_error();
			$block      = array(
				'hash'    => $hash,
				'bytes'   => strlen( $normalized ),
				'ok'      => ( JSON_ERROR_NONE === $error && null !== $decoded ),
				'error'   => JSON_ERROR_NONE === $error ? '' : json_last_error_msg(),
				'preview' => substr( $normalized, 0, 180 ),
			);
			$blocks[] = $block;
			if ( $normalized && ! $block['ok'] ) {
				$invalid++;
				$signals[] = array(
					'level'  => 'red',
					'title'  => 'JSON-LD failed strict parsing after safe normalization',
					'detail' => 'error=' . $block['error'] . ' sha256=' . $hash,
					'scored' => true,
					'hash'   => $hash,
				);
			}
		}
		return array(
			'signals' => $signals,
			'blocks'  => count( $blocks ),
			'invalid' => $invalid,
			'items'   => $blocks,
		);
	}

	/**
	 * Decode entities, strip CDATA/comments, trim.
	 *
	 * @param string $raw Raw JSON-LD.
	 * @return string
	 */
	public static function normalize( $raw ) {
		$raw = html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$raw = preg_replace( '/^\s*<!--/', '', $raw );
		$raw = preg_replace( '/-->\s*$/', '', $raw );
		$raw = preg_replace( '/^\s*<!\[CDATA\[/', '', $raw );
		$raw = preg_replace( '/\]\]>\s*$/', '', $raw );
		return trim( $raw );
	}

	/**
	 * Drop only invalid JSON-LD script blocks from rendered HTML.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function quarantine_invalid( $html ) {
		if ( ! RBSMC_Plugin::instance()->enabled( 'schema_quarantine' ) ) {
			return $html;
		}
		return preg_replace_callback(
			'/<script([^>]*type=["\']application\/ld\+json["\'][^>]*)>(.*?)<\/script>/is',
			function ( $m ) {
				$normalized = RBSMC_Schema::normalize( $m[2] );
				if ( '' === $normalized ) {
					return $m[0];
				}
				json_decode( $normalized, true );
				if ( JSON_ERROR_NONE !== json_last_error() ) {
					return '<!-- rbsmc quarantined invalid json-ld sha256=' . hash( 'sha256', $normalized ) . ' -->';
				}
				return $m[0];
			},
			$html
		);
	}
}
