<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Amazon Associates marketplace allowlist, lookalike rejection, and disclosure evidence.
 */
class RBSMC_Amazon {

	/**
	 * Official Amazon hostnames.
	 *
	 * @return array
	 */
	public static function allowlist() {
		return array(
			'amazon.com',
			'www.amazon.com',
			'smile.amazon.com',
			'amazon.ca',
			'www.amazon.ca',
			'amazon.co.uk',
			'www.amazon.co.uk',
			'amazon.de',
			'www.amazon.de',
			'amazon.fr',
			'www.amazon.fr',
			'amazon.it',
			'www.amazon.it',
			'amazon.es',
			'www.amazon.es',
			'amazon.co.jp',
			'www.amazon.co.jp',
			'amazon.in',
			'www.amazon.in',
			'amazon.com.au',
			'www.amazon.com.au',
			'amazon.com.mx',
			'www.amazon.com.mx',
			'amazon.com.br',
			'www.amazon.com.br',
			'amazon.nl',
			'www.amazon.nl',
			'amazon.se',
			'www.amazon.se',
			'amazon.pl',
			'www.amazon.pl',
			'amazon.sg',
			'www.amazon.sg',
			'amazon.ae',
			'www.amazon.ae',
			'amazon.sa',
			'www.amazon.sa',
			'amazon.com.tr',
			'www.amazon.com.tr',
			'amazon.eg',
			'www.amazon.eg',
			'amazon.com.be',
			'www.amazon.com.be',
			'amzn.to',
			'amzn.com',
		);
	}

	/**
	 * @param string $host Hostname.
	 * @return bool
	 */
	public static function is_allowed_host( $host ) {
		$host = strtolower( preg_replace( '/:\d+$/', '', $host ) );
		return in_array( $host, self::allowlist(), true );
	}

	/**
	 * Lookalike / phishing Amazon domains.
	 *
	 * @param string $host Hostname.
	 * @return bool
	 */
	public static function is_lookalike( $host ) {
		$host = strtolower( $host );
		if ( self::is_allowed_host( $host ) ) {
			return false;
		}
		return (bool) preg_match( '/amaz[0o]n\.|amazon\.(cm|co|net|org)$|amazom\.|amzon\.|anazon\./i', $host );
	}

	/**
	 * Expected Associate tag from wp-config only.
	 *
	 * @return string
	 */
	public static function expected_tag() {
		if ( defined( 'RBSMC_AMAZON_TAG' ) && RBSMC_AMAZON_TAG ) {
			return sanitize_text_field( (string) RBSMC_AMAZON_TAG );
		}
		return '';
	}

	/**
	 * Audit Amazon anchors in rendered HTML. Does not rewrite URLs.
	 *
	 * @param string $html HTML.
	 * @param string $url  Page URL.
	 * @return array
	 */
	public static function audit_html( $html, $url ) {
		$signals = array();
		if ( ! preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches, PREG_SET_ORDER ) ) {
			return array(
				'status'  => 'CHECKS PASSED',
				'signals' => array(),
				'links'   => 0,
			);
		}
		$amazon_links = 0;
		$red          = 0;
		$manual       = 0;
		foreach ( $matches as $match ) {
			$href = html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' );
			$parts = wp_parse_url( $href );
			if ( empty( $parts['host'] ) ) {
				continue;
			}
			$host = strtolower( $parts['host'] );
			if ( self::is_lookalike( $host ) ) {
				$red++;
				$signals[] = array(
					'level'  => 'red',
					'title'  => 'Lookalike Amazon domain rejected',
					'detail' => $host . ' on ' . $url,
					'scored' => true,
				);
				continue;
			}
			if ( ! self::is_allowed_host( $host ) && false === strpos( $host, 'amazon.' ) ) {
				continue;
			}
			$amazon_links++;
			$tag = '';
			if ( ! empty( $parts['query'] ) ) {
				parse_str( str_replace( '&amp;', '&', $parts['query'] ), $query );
				if ( isset( $query['tag'] ) ) {
					$tag = is_array( $query['tag'] ) ? implode( ',', $query['tag'] ) : (string) $query['tag'] ;
				}
			}
			$rel = '';
			if ( preg_match( '/\brel=["\']([^"\']+)["\']/i', $match[0], $relm ) ) {
				$rel = strtolower( $relm[1] );
			}
			$ok_rel = ( false !== strpos( $rel, 'sponsored' ) || false !== strpos( $rel, 'nofollow' ) );
			if ( ! $ok_rel ) {
				$red++;
				$signals[] = array(
					'level'  => 'red',
					'title'  => 'Amazon link missing rel=sponsored or rel=nofollow',
					'detail' => $href,
					'scored' => true,
				);
			}
			$expected = self::expected_tag();
			if ( $expected && $tag && $tag !== $expected ) {
				$red++;
				$signals[] = array(
					'level'  => 'red',
					'title'  => 'Amazon tag mismatch',
					'detail' => 'detected=' . $tag . ' expected=' . $expected . ' url=' . $href,
					'scored' => true,
				);
			} elseif ( $expected && ! $tag ) {
				$manual++;
				$signals[] = array(
					'level'  => 'manual',
					'title'  => 'Amazon Associate tag missing on one link',
					'detail' => 'Sitewide review, not automatic RED. ' . $href,
					'scored' => false,
				);
			}
			if ( in_array( $host, array( 'amzn.to', 'amzn.com' ), true ) ) {
				$signals[] = array(
					'level'  => 'manual',
					'title'  => 'Shortened Amazon link is indeterminate',
					'detail' => $href,
					'scored' => false,
				);
			}
		}

		$has_id = (bool) preg_match( '/as an amazon associate/i', $html );
		if ( $amazon_links > 0 && ! $has_id ) {
			$signals[] = array(
				'level'  => 'manual',
				'title'  => 'Amazon Associate identification missing on this response',
				'detail' => 'MANUAL sitewide review, not automatic RED. Page: ' . $url,
				'scored' => false,
			);
			$manual++;
		}

		$status = 'CHECKS PASSED';
		if ( $red > 0 ) {
			$status = 'VERIFIED ISSUE';
		} elseif ( $manual > 0 ) {
			$status = 'REVIEW';
		}

		return array(
			'status'  => $status,
			'signals' => $signals,
			'links'   => $amazon_links,
		);
	}

	/**
	 * Optional rendered-only Associate identification. Does not write post_content.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function maybe_append_identification( $html ) {
		if ( ! RBSMC_Plugin::instance()->enabled( 'amazon_identification_output' ) ) {
			return $html;
		}
		if ( ! preg_match( '/amazon\.(com|ca|co\.uk|de|fr)/i', $html ) ) {
			return $html;
		}
		if ( preg_match( '/as an amazon associate/i', $html ) ) {
			return $html;
		}
		$statement = '<p class="rbsmc-amazon-id">As an Amazon Associate I earn from qualifying purchases.</p>';
		return $html . $statement;
	}
}
