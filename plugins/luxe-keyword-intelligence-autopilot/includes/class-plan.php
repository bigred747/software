<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decide Rank Math writes. Post content, excerpts, and status are never in the plan.
 */
class Luxe_KIA_Plan {

	/**
	 * @param array $item Item.
	 * @param array $settings Settings.
	 * @return array<string,mixed>
	 */
	public static function build( $item, $settings = array() ) {
		$item     = is_array( $item ) ? $item : array();
		$settings = is_array( $settings ) ? $settings : array();
		$green    = isset( $settings['green'] ) ? (int) $settings['green'] : 95;
		$need     = isset( $settings['confidence'] ) ? (int) $settings['confidence'] : 80;
		$limit    = isset( $settings['phrases'] ) ? (int) $settings['phrases'] : 5;
		$alt_on   = ! isset( $settings['repair_alt'] ) || ! empty( $settings['repair_alt'] );
		$meta_on  = ! isset( $settings['repair_meta'] ) || ! empty( $settings['repair_meta'] );
		$status   = isset( $item['status'] ) ? (string) $item['status'] : 'publish';
		$built    = Luxe_KIA_Phrases::build( isset( $item['title'] ) ? $item['title'] : '', $limit );
		$primary  = self::primary( $item, $built );
		$phrases  = self::phrase_set( $primary, $built, $limit );
		$before    = Luxe_KIA_Score::evaluate( $item, self::stored_phrases( $item ), false );
		$writes    = array();
		$scan_only = ( 'future' === $status ) || ! $meta_on;
		$confident = Luxe_KIA_Phrases::confidence( $primary ) >= $need;

		if ( $confident && ! $scan_only && '' !== $primary && $before['score'] < $green ) {
			if ( self::focus_needs_write( $item, $phrases ) ) {
				$writes[] = array(
					'key'   => 'rank_math_focus_keyword',
					'value' => implode( ', ', $phrases ),
				);
			}
			$seo = Luxe_KIA_Phrases::seo_title( $primary );
			if ( self::title_needs_write( $item, $primary ) ) {
				$writes[] = array(
					'key'   => 'rank_math_title',
					'value' => $seo,
				);
			}
			$description = Luxe_KIA_Phrases::description( $primary );
			if ( self::description_needs_write( $item, $primary ) && false !== stripos( $description, $primary ) ) {
				$writes[] = array(
					'key'   => 'rank_math_description',
					'value' => $description,
				);
			}
		}
		if ( $confident && ! $scan_only && '' !== $primary && $alt_on && ! empty( $item['has_image'] ) && ! empty( $item['alt_broken'] ) ) {
			$writes[] = array(
				'key'        => '_wp_attachment_image_alt',
				'value'      => $primary,
				'attachment' => 1,
			);
		}

		$preview = self::preview( $item, $writes );
		$after   = Luxe_KIA_Score::evaluate( $preview, $phrases, false );
		if ( $after['score'] < $before['score'] ) {
			$writes = array();
			$after  = $before;
		}
		return array(
			'writes'     => $writes,
			'phrases'    => $phrases,
			'before'     => $before,
			'after'      => $writes ? $after : $before,
			'scan_only'  => $scan_only,
			'confidence' => Luxe_KIA_Phrases::confidence( $primary ),
		);
	}

	/**
	 * @param string $alt Alt text.
	 * @param string $src Optional image URL.
	 * @return bool
	 */
	public static function alt_is_broken( $alt, $src = '' ) {
		$alt = trim( (string) $alt );
		$src = (string) $src;
		if ( '' === $alt ) {
			return true;
		}
		if ( preg_match( '/^[0-9]{2}[A-Za-z0-9]{6,14}$/', $alt ) ) {
			return true;
		}
		if ( false !== stripos( $alt, 'smartwatche' ) ) {
			return true;
		}
		if ( preg_match( '/[-–—]\s*[A-Za-z0-9]+(?:-[A-Za-z0-9]+){2,}/', $alt ) ) {
			return true;
		}
		if ( '' !== $src && false !== strpos( $src, $alt ) && preg_match( '/^[A-Za-z0-9]{8,16}$/', $alt ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @return array<int,string>
	 */
	public static function allowed_keys() {
		return array(
			'rank_math_focus_keyword',
			'rank_math_title',
			'rank_math_description',
			'_wp_attachment_image_alt',
		);
	}

	/**
	 * @param array $item Item.
	 * @param array $built Built phrases.
	 * @return string
	 */
	private static function primary( $item, $built ) {
		$stored = self::stored_phrases( $item );
		$first  = $stored ? (string) $stored[0] : '';
		$title  = isset( $item['title'] ) ? (string) $item['title'] : '';
		if ( '' !== $first && ! Luxe_KIA_Phrases::is_junk( $first ) && false !== stripos( $title, $first ) && Luxe_KIA_Phrases::confidence( $first ) >= 80 ) {
			return Luxe_KIA_Phrases::clip( $first, Luxe_KIA_Phrases::PRIMARY_MAX );
		}
		return $built ? (string) $built[0] : '';
	}

	/**
	 * @param string $primary Primary.
	 * @param array  $built Built phrases.
	 * @param int    $limit Limit.
	 * @return array<int,string>
	 */
	private static function phrase_set( $primary, $built, $limit ) {
		if ( '' === $primary ) {
			return array();
		}
		$out = array( $primary );
		foreach ( $built as $phrase ) {
			if ( 0 === strcasecmp( (string) $phrase, $primary ) ) {
				continue;
			}
			$out[] = (string) $phrase;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * @param array $item Item.
	 * @return array<int,string>
	 */
	private static function stored_phrases( $item ) {
		$focus = isset( $item['focus'] ) ? (string) $item['focus'] : '';
		$parts = array_filter( array_map( 'trim', explode( ',', $focus ) ) );
		return array_values( $parts );
	}

	/**
	 * @param array $item Item.
	 * @param array $phrases Phrases.
	 * @return bool
	 */
	private static function focus_needs_write( $item, $phrases ) {
		$stored = self::stored_phrases( $item );
		if ( count( $stored ) >= 3 && 0 === strcasecmp( (string) $stored[0], (string) $phrases[0] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @param array  $item Item.
	 * @param string $primary Primary.
	 * @return bool
	 */
	private static function title_needs_write( $item, $primary ) {
		$seo   = isset( $item['seo_title'] ) ? trim( (string) $item['seo_title'] ) : '';
		$title = isset( $item['title'] ) ? trim( (string) $item['title'] ) : '';
		$shown = ( '' === $seo || false !== strpos( $seo, '%' ) ) ? $title : $seo;
		$length = strlen( $shown );
		if ( $length < 15 || $length > 60 ) {
			return true;
		}
		return false === stripos( $shown, $primary );
	}

	/**
	 * @param array  $item Item.
	 * @param string $primary Primary.
	 * @return bool
	 */
	private static function description_needs_write( $item, $primary ) {
		$desc = isset( $item['description'] ) ? trim( (string) $item['description'] ) : '';
		$length = strlen( $desc );
		if ( $length < 70 || $length > 160 ) {
			return true;
		}
		return false === stripos( $desc, $primary );
	}

	/**
	 * @param array $item Item.
	 * @param array $writes Writes.
	 * @return array
	 */
	private static function preview( $item, $writes ) {
		$next = $item;
		foreach ( $writes as $write ) {
			if ( ! is_array( $write ) || empty( $write['key'] ) ) {
				continue;
			}
			if ( 'rank_math_focus_keyword' === $write['key'] ) {
				$next['focus'] = $write['value'];
			} elseif ( 'rank_math_title' === $write['key'] ) {
				$next['seo_title'] = $write['value'];
			} elseif ( 'rank_math_description' === $write['key'] ) {
				$next['description'] = $write['value'];
			} elseif ( '_wp_attachment_image_alt' === $write['key'] ) {
				$next['alt']        = $write['value'];
				$next['alt_broken'] = false;
			}
		}
		return $next;
	}
}
