<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LDS_Picker' ) ) {
	return;
}

/**
 * Decides which pasted WZone rows are safe to download.
 *
 * A download must already show a supported brand and a real price, and it must
 * not be renewed, refurbished, used, for-parts, an accessory, already imported,
 * or the same model already stored in the catalog. Unknown brands stay off the
 * list. Nothing here writes products, URLs, or publication status.
 */
class LDS_Picker {

	/**
	 * @param string   $paste          WZone paste.
	 * @param string[] $catalog_titles Product names already in WooCommerce.
	 * @return array<string,mixed>
	 */
	public static function evaluate( $paste, $catalog_titles = array() ) {
		if ( ! class_exists( 'LAPS_Catalog' ) || ! class_exists( 'LDS_Parser' ) ) {
			return array(
				'ready'             => false,
				'downloads'         => array(),
				'skips'             => array(),
				'parsed'            => 0,
				'catalog_compared'  => 0,
			);
		}
		$rows    = LDS_Parser::parse( $paste );
		$covered = array();
		$catalog = array();
		foreach ( (array) $catalog_titles as $title ) {
			$title = LDS_Parser::clean_title( (string) $title );
			if ( '' === $title ) {
				continue;
			}
			$catalog[] = $title;
			self::cover( $covered, $title );
		}
		foreach ( $rows as $row ) {
			if ( ! empty( $row['imported'] ) ) {
				self::cover( $covered, $row['title'] );
			}
		}
		$downloads = array();
		$skips     = array();
		foreach ( $rows as $row ) {
			$decision = self::decide( $row, $covered );
			if ( ! empty( $decision['download'] ) ) {
				self::cover( $covered, $row['title'] );
				$downloads[] = $decision;
			} else {
				$skips[] = $decision;
			}
		}
		return array(
			'ready'            => true,
			'downloads'        => $downloads,
			'skips'            => $skips,
			'parsed'           => count( $rows ),
			'catalog_compared' => count( $catalog ),
		);
	}

	/**
	 * @param array<string,bool> $covered Covered keys.
	 * @param string             $title   Title.
	 */
	private static function cover( &$covered, $title ) {
		$id = self::identity( $title );
		$covered[ 'title:' . $id['fingerprint'] ] = true;
		if ( '' === $id['model'] ) {
			return;
		}
		if ( $id['family'] ) {
			$covered[ 'model:' . $id['model'] ] = true;
			return;
		}
		if ( '' !== $id['brand'] ) {
			$covered[ 'brand:' . strtolower( $id['brand'] ) . '|' . $id['model'] ] = true;
		}
	}

	/**
	 * @param array<string,mixed> $row     Parsed row.
	 * @param array<string,bool>  $covered Covered keys.
	 * @return array<string,mixed>
	 */
	private static function decide( $row, $covered ) {
		$title = isset( $row['title'] ) ? (string) $row['title'] : '';
		$price = isset( $row['price'] ) ? (float) $row['price'] : 0.0;
		$id    = self::identity( $title );
		$hard  = self::is_risky( $title );
		$reason = '';
		if ( ! empty( $row['imported'] ) ) {
			$reason = 'already-imported';
		} elseif ( $hard ) {
			$reason = 'used-refurbished-or-parts';
		} elseif ( self::is_primary_accessory( $title ) ) {
			$reason = 'accessory';
		} elseif ( '' === $id['brand'] ) {
			$reason = 'unknown-brand';
		} elseif ( $price <= 0 ) {
			$reason = 'missing-price';
		} elseif ( self::is_covered( $id, $covered ) ) {
			$reason = 'already-in-catalog';
		}
		$download = ( '' === $reason );
		$integrity = 0;
		if ( $download ) {
			$integrity = '' !== $id['model'] ? 100 : 95;
		}
		return array(
			'title'       => $title,
			'price'       => $price,
			'price_label' => LDS_Parser::money_label( $price ),
			'brand'       => '' !== $id['brand'] ? $id['brand'] : 'Unknown',
			'model'       => $id['model'],
			'integrity'   => $integrity,
			'download'    => $download,
			'reason'      => $reason,
			'why'         => self::why( $download, $reason, $id['model'] ),
		);
	}

	/**
	 * @param array<string,mixed> $id      Identity.
	 * @param array<string,bool>  $covered Covered keys.
	 * @return bool
	 */
	private static function is_covered( $id, $covered ) {
		if ( isset( $covered[ 'title:' . $id['fingerprint'] ] ) ) {
			return true;
		}
		if ( '' === $id['model'] ) {
			return false;
		}
		if ( $id['family'] && isset( $covered[ 'model:' . $id['model'] ] ) ) {
			return true;
		}
		if ( ! $id['family'] && '' !== $id['brand'] ) {
			$key = 'brand:' . strtolower( $id['brand'] ) . '|' . $id['model'];
			return isset( $covered[ $key ] );
		}
		return false;
	}

	/**
	 * @param string $title Title.
	 * @return array{brand:string,model:string,family:bool,fingerprint:string}
	 */
	public static function identity( $title ) {
		$title = LAPS_Catalog::plain( $title );
		$brand = LAPS_Catalog::title_brand( $title );
		if ( $brand && self::brand_is_component( $brand, $title ) ) {
			$brand = '';
		}
		$model = self::family_key( $title );
		$family = '' !== $model;
		if ( ! $family ) {
			$model = self::general_model( $title );
		}
		$finger = strtolower( $title );
		$finger = preg_replace( '/[^a-z0-9]+/', ' ', $finger );
		return array(
			'brand'       => $brand ? $brand : '',
			'model'       => $model,
			'family'      => $family,
			'fingerprint' => trim( is_string( $finger ) ? $finger : '' ),
		);
	}

	/**
	 * Drone families that already appear in this catalog. Longer names win.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function family_key( $title ) {
		$families = array(
			'mini 5 pro'       => '/\bmini\s*5\s*pro\b/i',
			'mini 4 pro'       => '/\bmini\s*4\s*pro\b/i',
			'mini 4k'          => '/\bmini\s*4k\b/i',
			'mini 3'           => '/\bmini\s*3(?!\s*pro)\b/i',
			'air 3s'           => '/\bair\s*3s\b/i',
			'air 3'            => '/\bair\s*3(?!s)\b/i',
			'mavic 4 pro'      => '/\bmavic\s*4\s*pro\b/i',
			'avata 2'          => '/\bavata\s*2\b/i',
			'neo 2'            => '/\bneo\s*2\b/i',
			'neo'              => '/\bneo(?!\s*2)\b/i',
			'u11mini'          => '/\bu11\s*mini\b/i',
			'f11pro 2'         => '/\bf11\s*pro\s*2\b/i',
			'f11pro'           => '/\bf11\s*pro(?!\s*2)\b/i',
			'f7gb2'            => '/\bf7\s*gb\s*2\b/i',
			'f7mini'           => '/\bf7\s*mini\b/i',
			'atom 2'           => '/\batom\s*2\b/i',
			'atom se'          => '/\batom\s*se\b/i',
			'evo lite'         => '/\bevo\s*lite\b/i',
			'evo ii dual 640t' => '/\bevo\s*ii\s*dual\s*640\s*t\b/i',
			'evo 2'            => '/\bevo\s*2\b/i',
			't60a'             => '/\bt60a\b/i',
			'hs360s'           => '/\bhs\s*360\s*s\b/i',
		);
		foreach ( $families as $key => $pattern ) {
			if ( preg_match( $pattern, $title ) ) {
				if ( preg_match( '/\benterprise\b/i', $title ) ) {
					return $key . '|enterprise';
				}
				return $key;
			}
		}
		return '';
	}

	/**
	 * Model signal for non-drone products. Empty means integrity can reach 95, not 100.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function general_model( $title ) {
		$tv = self::tv_model( $title );
		if ( '' !== $tv ) {
			return $tv;
		}
		$watch = self::watch_model( $title );
		if ( '' !== $watch ) {
			return $watch;
		}
		if ( preg_match( '/\bairpods\s+pro(?:\s+(\d+))?\b/i', $title, $m ) ) {
			return 'airpods pro' . ( ! empty( $m[1] ) ? ' ' . $m[1] : '' );
		}
		if ( preg_match( '/\bgalaxy\s+buds\s*(\d+)\b/i', $title, $m ) ) {
			return 'galaxy buds ' . $m[1];
		}
		if ( preg_match( '/\bquietcomfort(?:\s+ultra)?\b/i', $title, $m ) ) {
			return strtolower( $m[0] );
		}
		if ( preg_match( '/\b(?:wh|wf)[-\s]?\d{3,4}[a-z0-9]*\b/i', $title, $m ) ) {
			return strtolower( str_replace( array( ' ', '_' ), '-', $m[0] ) );
		}
		if ( preg_match( '/\b(series|gen|generation)\s*(\d+)\b/i', $title, $m ) ) {
			return 'series ' . $m[2];
		}
		if ( preg_match( '/\b(2nd|3rd|4th|5th|6th)\s+gen\b/i', $title, $m ) ) {
			return strtolower( $m[0] );
		}
		return '';
	}

	/**
	 * Glued “RenewedSony” is still a renewed set.
	 *
	 * @param string $title Title.
	 * @return bool
	 */
	public static function is_risky( $title ) {
		if ( LAPS_Catalog::is_hard_risk( $title ) ) {
			return true;
		}
		return (bool) preg_match( '/\b(renewed|refurbished|pre-?owned)(?=[a-z])/i', $title );
	}

	/**
	 * “with Qualcomm CPU” is a part, not the product brand.
	 *
	 * @param string $brand Brand.
	 * @param string $title Title.
	 * @return bool
	 */
	public static function brand_is_component( $brand, $title ) {
		if ( LAPS_Catalog::is_feature_not_brand( $brand, $title ) ) {
			return true;
		}
		if ( preg_match( '/^' . preg_quote( $brand, '/' ) . '\b/i', $title ) ) {
			return false;
		}
		return (bool) preg_match( '/\bwith\s+' . preg_quote( $brand, '/' ) . '\b/i', $title );
	}

	/**
	 * Sony K- codes and Samsung / TCL series codes. Size stays on the key.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function tv_model( $title ) {
		$key = '';
		if ( preg_match( '/\b\d{2}qm\d+l\b/i', $title, $m ) ) {
			$key = strtolower( $m[0] );
		} elseif ( preg_match( '/\bk-?\d{2}[a-z]{1,4}\d{0,4}[a-z]?\d?\b/i', $title, $m ) ) {
			$key = strtolower( str_replace( '-', '', $m[0] ) );
		} elseif ( preg_match( '/\b(u\d{4}[a-z]?|f\d{4}|m\d{2}h|qm\d+l|w\d{3}k)\b/i', $title, $m ) ) {
			$key = strtolower( $m[1] );
		}
		if ( '' === $key ) {
			return '';
		}
		if ( preg_match( '/\b(\d{2})\s*-?\s*inch\b/i', $title, $inch ) && 0 !== strpos( $key, $inch[1] ) ) {
			$key .= ' ' . $inch[1];
		}
		return $key;
	}

	/**
	 * Watch size and radio stay in the model so 42mm and 46mm are different products.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function watch_model( $title ) {
		$mm = '';
		if ( preg_match( '/\b(\d+)\s*mm\b/i', $title, $size ) ) {
			$mm = ' ' . $size[1] . 'mm';
		}
		if ( preg_match( '/\bpixel\s+watch\s+(\d+)\b/i', $title, $m ) ) {
			$key = 'pixel watch ' . $m[1];
			if ( preg_match( '/\blte\b/i', $title ) ) {
				$key .= ' lte';
			} elseif ( preg_match( '/\bwi-?fi\b/i', $title ) ) {
				$key .= ' wifi';
			}
			return $key . $mm;
		}
		if ( preg_match( '/\bultra\s+(\d+)\b/i', $title, $m ) ) {
			return 'ultra ' . $m[1] . $mm;
		}
		if ( preg_match( '/\bse\s+(\d+)\b/i', $title, $m ) ) {
			return 'se ' . $m[1] . $mm;
		}
		if ( preg_match( '/\bseries\s+(\d+)\b/i', $title, $m ) ) {
			return 'series ' . $m[1] . $mm;
		}
		return '';
	}

	/**
	 * A strap sold for a watch is an accessory. A watch that includes a band is not.
	 *
	 * @param string $title Title.
	 * @return bool
	 */
	public static function is_band_product( $title ) {
		if ( ! preg_match( '/\b(bands?|straps?)\b/i', $title ) ) {
			return false;
		}
		$watch = preg_match( '/\b(smartwatch|ultra\s+\d|series\s+\d+|se\s+\d+|pixel\s+watch)\b/i', $title );
		$radio = preg_match( '/\b(gps|cellular)\b/i', $title );
		if ( $watch && $radio ) {
			return false;
		}
		if ( preg_match( '/\b(band|strap)s?\s+for\b/i', $title ) ) {
			return true;
		}
		if ( preg_match( '/\b(for|compatible|replacement)\b/i', $title ) && preg_match( '/\bbands?\b/i', $title ) ) {
			return true;
		}
		if ( preg_match( '/\bwatch\s+bands?\b/i', $title ) ) {
			return true;
		}
		if ( preg_match( '/\bbands?\b/i', $title ) && ! $radio && ! preg_match( '/\bsmartwatch\b/i', $title ) ) {
			return true;
		}
		return false;
	}

	/**
	 * A landing pad or case is not a catalog product. A drone bundle that includes a pad still is.
	 *
	 * @param string $title Title.
	 * @return bool
	 */
	public static function is_primary_accessory( $title ) {
		if ( self::is_band_product( $title ) ) {
			return true;
		}
		if ( preg_match( '/\b(wall mount|tv mount)\b/i', $title ) ) {
			return true;
		}
		$kit = preg_match( '/\b(fly more|combo|bundle|with rc)\b/i', $title );
		if ( preg_match( '/\blanding\s+pad\b/i', $title ) && ! $kit ) {
			return true;
		}
		if ( preg_match( '/\b(screen protector|keyboard cover|lens cleaning kit)\b/i', $title ) && ! $kit ) {
			return true;
		}
		if ( preg_match( '/\b(drone|quadcopter|earbuds?|headphones?|laptop|macbook|projector|smartwatch|iphone|ultra\s+\d|series\s+\d+)\b/i', $title ) ) {
			return false;
		}
		return LAPS_Catalog::is_accessory( $title );
	}

	/**
	 * @param bool   $download Download?
	 * @param string $reason   Skip code.
	 * @param string $model    Model key.
	 * @return string
	 */
	private static function why( $download, $reason, $model ) {
		if ( $download && '' !== $model ) {
			return 'Supported brand, traceable model, and a real price. On the WZone card, confirm an ASIN and a primary image before you add it. This plugin does not import it.';
		}
		if ( $download ) {
			return 'Supported brand and a real price. The model name is not clear, so this can reach 95 once an ASIN and a primary image are stored. Confirm it is not a renamed copy of a model you already sell.';
		}
		$labels = array(
			'already-imported'          => 'Already imported. Leave it off the download list.',
			'unknown-brand'             => 'Unknown brand. It would stay HOLD and below 95.',
			'used-refurbished-or-parts' => 'Renewed, refurbished, used, or for-parts. Those stay HOLD.',
			'missing-price'             => 'No real price on the row.',
			'accessory'                 => 'Accessory. It is not a catalog product.',
			'already-in-catalog'        => 'That model is already in the catalog or already on this page. Downloading it again does not repair a HOLD row.',
		);
		return isset( $labels[ $reason ] ) ? $labels[ $reason ] : 'Skip.';
	}

	/**
	 * @param string $code Reason code.
	 * @return string
	 */
	public static function reason_label( $code ) {
		$labels = array(
			'already-imported'          => 'Already imported',
			'unknown-brand'             => 'Unknown brand',
			'used-refurbished-or-parts' => 'Renewed or used',
			'missing-price'             => 'Missing price',
			'accessory'                 => 'Accessory',
			'already-in-catalog'        => 'Already in catalog',
		);
		return isset( $labels[ $code ] ) ? $labels[ $code ] : $code;
	}
}
