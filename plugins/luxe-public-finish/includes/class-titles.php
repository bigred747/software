<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display-only title polish. Never writes post_title or post_content.
 */
class Luxe_Public_Finish_Titles {

	const MAX_CHARS = 70;

	/**
	 * Official required Amazon Associates identification.
	 */
	const OFFICIAL_ID = 'As an Amazon Associate I earn from qualifying purchases.';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var array<string,int>
	 */
	private $seen = array();

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register late title filters. Runs after Rank Math and Luxe SEO.
	 */
	public function boot() {
		add_action( 'wp', array( $this, 'reset_seen' ), 1 );
		add_filter( 'pre_get_document_title', array( $this, 'document_title' ), 99999 );
		add_filter( 'wp_title', array( $this, 'wp_title' ), 99999, 2 );
		add_filter( 'document_title_parts', array( $this, 'title_parts' ), 99999 );
		add_filter( 'rank_math/frontend/title', array( $this, 'rank_math_title' ), 99999 );
		add_filter( 'wpseo_title', array( $this, 'rank_math_title' ), 99999 );
		add_filter( 'rank_math/opengraph/facebook/og_title', array( $this, 'rank_math_title' ), 99999 );
		add_filter( 'rank_math/opengraph/twitter/title', array( $this, 'rank_math_title' ), 99999 );
		add_filter( 'the_title', array( $this, 'filter_title' ), 99999, 2 );
		add_filter( 'woocommerce_product_title', array( $this, 'filter_product_title' ), 99999 );
	}

	/**
	 * New request, new uniqueness map.
	 */
	public function reset_seen() {
		$this->seen = array();
	}

	/**
	 * @param string $title Title.
	 * @return string
	 */
	public function document_title( $title ) {
		if ( ! Luxe_Public_Finish_Plugin::instance()->enabled( 'polish_titles' ) ) {
			return $title;
		}
		$preferred = $this->preferred_source();
		$fixed     = self::polish( is_string( $title ) ? $title : '', $preferred );
		return $fixed ? $fixed : $title;
	}

	/**
	 * @param string $title Title.
	 * @param string $sep   Separator.
	 * @return string
	 */
	public function wp_title( $title, $sep ) {
		unset( $sep );
		return $this->document_title( $title );
	}

	/**
	 * @param array $parts Title parts.
	 * @return array
	 */
	public function title_parts( $parts ) {
		if ( ! is_array( $parts ) || ! Luxe_Public_Finish_Plugin::instance()->enabled( 'polish_titles' ) ) {
			return $parts;
		}
		$joined = isset( $parts['title'] ) ? (string) $parts['title'] : '';
		$fixed  = self::polish( $joined, $this->preferred_source() );
		if ( $fixed ) {
			return array(
				'title' => $fixed,
			);
		}
		return $parts;
	}

	/**
	 * @param string $title Title.
	 * @return string
	 */
	public function rank_math_title( $title ) {
		return $this->document_title( $title );
	}

	/**
	 * Unique listing labels only. The queried product keeps its own H1.
	 *
	 * @param string $title Title.
	 * @param int    $id    Post ID.
	 * @return string
	 */
	public function filter_title( $title, $id ) {
		if ( is_admin() || ! is_string( $title ) ) {
			return $title;
		}
		if ( ! Luxe_Public_Finish_Plugin::instance()->enabled( 'unique_listings' ) ) {
			return $title;
		}
		$id = (int) $id;
		if ( $id < 1 ) {
			return $title;
		}
		if ( function_exists( 'get_post_type' ) && 'product' !== get_post_type( $id ) ) {
			return $title;
		}
		if ( function_exists( 'is_singular' ) && is_singular( 'product' ) && function_exists( 'get_queried_object_id' ) && $id === (int) get_queried_object_id() ) {
			return $title;
		}
		$sku = function_exists( 'get_post_meta' ) ? (string) get_post_meta( $id, '_sku', true ) : '';
		return self::unique_label( $title, $id, $sku, $this->seen );
	}

	/**
	 * @param string $title Title.
	 * @return string
	 */
	public function filter_product_title( $title ) {
		if ( is_admin() || ! is_string( $title ) ) {
			return $title;
		}
		$id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
		return $this->filter_title( $title, $id );
	}

	/**
	 * @return string
	 */
	private function preferred_source() {
		if ( ! function_exists( 'is_singular' ) || ! is_singular() ) {
			return '';
		}
		$id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
		if ( $id < 1 || ! function_exists( 'get_post_field' ) ) {
			return '';
		}
		$raw = (string) get_post_field( 'post_title', $id );
		return self::plain( $raw );
	}

	/**
	 * Restore clipped document titles. Word-boundary cap. No mid-word stubs.
	 *
	 * @param string $title     Incoming title.
	 * @param string $preferred Longer source (post_title / H1).
	 * @return string
	 */
	public static function polish( $title, $preferred = '' ) {
		$title     = self::plain( $title );
		$preferred = self::plain( $preferred );
		$stripped  = self::strip_stubs( $title );

		if ( self::is_truncated( $title ) || self::is_truncated( $stripped ) ) {
			if ( $preferred && ! self::is_truncated( $preferred ) && strlen( $preferred ) >= strlen( $stripped ) ) {
				$stripped = $preferred;
			} elseif ( $preferred && self::is_prefix_of( $stripped, $preferred ) ) {
				$stripped = self::strip_stubs( $preferred );
			}
		}

		$stripped = self::strip_stubs( $stripped );
		if ( '' === $stripped ) {
			return $title;
		}

		$brand = 'LuxeTrendsetters';
		if ( function_exists( 'luxe_public_finish_boot' ) && class_exists( 'Luxe_Public_Finish_Plugin' ) ) {
			$brand = Luxe_Public_Finish_Plugin::brand_name();
		}
		if ( false === stripos( $stripped, $brand ) ) {
			$with = $stripped . ' | ' . $brand;
			if ( strlen( $with ) <= self::MAX_CHARS ) {
				$stripped = $with;
			}
		}

		return self::finish_clip( self::word_clip( $stripped, self::MAX_CHARS ) );
	}

	/**
	 * @param string $title Title.
	 * @return bool
	 */
	public static function is_truncated( $title ) {
		$title = self::plain( $title );
		if ( '' === $title ) {
			return false;
		}
		if ( preg_match( '/\s*\|\s*(Pr|Che|Lu|Luxe\s*T[a-z]{0,12})\s*$/i', $title ) ) {
			return true;
		}
		if ( preg_match( '/Review\s*&\s*Buyer\s*Che\w*\s*$/i', $title ) ) {
			return true;
		}
		if ( preg_match( '/Review 2026:\s*Best Buyer\s*$/i', $title ) ) {
			return true;
		}
		if ( preg_match( '/Review 2026:\s*Best\s*$/i', $title ) ) {
			return true;
		}
		if ( preg_match( '/\s[A-Z][a-z]{0,2}$/', $title ) && strlen( $title ) >= 48 ) {
			return true;
		}
		if ( false !== strpos( $title, '…' ) && preg_match( '/…\s+\S+/', $title ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $title Title.
	 * @return string
	 */
	public static function strip_stubs( $title ) {
		$title = self::plain( $title );
		$title = preg_replace( '/\s*[….]+\s*Review\s*&\s*Buyer\s*Che\w*\s*$/i', '', $title );
		$title = preg_replace( '/\s+Review\s*&\s*Buyer\s*Che\w*\s*$/i', '', $title );
		$title = preg_replace( '/\s*\|\s*(Pr|Che|Lu|Luxe\s*T[a-z]{0,12}|luxetrendsetters\.com\.?)\s*$/i', '', $title );
		$title = preg_replace( '/\s*[….]+\s*$/u', '', $title );
		return self::plain( $title );
	}

	/**
	 * @param string $short Short.
	 * @param string $long  Long.
	 * @return bool
	 */
	public static function is_prefix_of( $short, $long ) {
		$short = strtolower( rtrim( self::plain( $short ), ' .…' ) );
		$long  = strtolower( self::plain( $long ) );
		if ( strlen( $short ) < 12 ) {
			return false;
		}
		return 0 === strpos( $long, $short );
	}

	/**
	 * Clip on a word boundary. Never append an ellipsis stub.
	 *
	 * @param string $text Text.
	 * @param int    $max  Max chars.
	 * @return string
	 */
	public static function word_clip( $text, $max = 70 ) {
		$text = self::plain( $text );
		$max  = (int) $max;
		if ( $max < 24 || strlen( $text ) <= $max ) {
			return self::finish_clip( $text );
		}
		$cut = substr( $text, 0, $max );
		$sp  = strrpos( $cut, ' ' );
		if ( false !== $sp && $sp > 24 ) {
			$cut = substr( $cut, 0, $sp );
		}
		return self::finish_clip( rtrim( $cut, " |,;:-" ) );
	}

	/**
	 * Do not leave a 70-char cut on “Best Buyer” / “Best”. Snap to Review 2026.
	 *
	 * @param string $cut Clipped title.
	 * @return string
	 */
	public static function finish_clip( $cut ) {
		$cut = self::plain( $cut );
		if ( preg_match( '/^(.*\bReview 2026):?\s*Best( Buyer)?$/i', $cut, $m ) ) {
			return rtrim( $m[1] );
		}
		return $cut;
	}

	/**
	 * Append SKU or #ID when this exact listing title already appeared.
	 *
	 * @param string             $title Title.
	 * @param int                $id    Post ID.
	 * @param string             $sku   SKU.
	 * @param array<string,int>  $seen  Seen map (by ref).
	 * @return string
	 */
	public static function unique_label( $title, $id, $sku, array &$seen ) {
		$plain = self::plain( $title );
		$key   = strtolower( rtrim( $plain, " .…" ) );
		if ( '' === $key ) {
			return $title;
		}
		if ( ! isset( $seen[ $key ] ) ) {
			$seen[ $key ] = (int) $id;
			return $title;
		}
		if ( (int) $seen[ $key ] === (int) $id ) {
			return $title;
		}
		$suffix = self::plain( $sku );
		if ( '' === $suffix ) {
			$suffix = '#' . (int) $id;
		}
		if ( false !== stripos( $plain, $suffix ) ) {
			return $title;
		}
		return rtrim( $plain, " .…" ) . ' · ' . $suffix;
	}

	/**
	 * Keep official Associate sentences (header + article). Drop unofficial extras.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function collapse_disclosures( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		$parked = self::park_blocks( $html );
		$html   = $parked['html'];
		$html   = preg_replace_callback(
			'/<(p|div|span|small|em|section)(\b[^>]*)>([^<]{0,400}Amazon Associate[^<]{0,400})<\/\1>/i',
			function ( $m ) {
				$inner = $m[3];
				if ( false !== stripos( $inner, self::OFFICIAL_ID ) ) {
					return $m[0];
				}
				if ( ! preg_match( '/Amazon Associate/i', $inner ) ) {
					return $m[0];
				}
				return '';
			},
			$html
		);
		if ( ! is_string( $html ) ) {
			$html = $parked['html'];
		}
		$html = preg_replace_callback(
			'/As an Amazon Associate[^.]*\./i',
			function ( $m ) {
				return self::is_official_sentence( $m[0] ) ? $m[0] : '';
			},
			$html
		);
		if ( ! is_string( $html ) ) {
			$html = $parked['html'];
		}
		return self::unpark_blocks( $html, $parked['blocks'] );
	}

	/**
	 * Official header + article sentences count as one identification.
	 * Unofficial extras still add to the count.
	 *
	 * @param string $html HTML.
	 * @return int
	 */
	public static function disclosure_count( $html ) {
		$parked   = self::park_blocks( is_string( $html ) ? $html : '' );
		$text     = $parked['html'];
		$official = (int) preg_match_all( '/As an Amazon Associate I earn from qualifying purchases/i', $text );
		$any      = (int) preg_match_all( '/Amazon Associate/i', $text );
		$extra    = max( 0, $any - $official );
		if ( $official < 1 && $any < 1 ) {
			return 0;
		}
		if ( $official > 0 ) {
			return 1 + $extra;
		}
		return $any;
	}

	/**
	 * @param string $sentence Sentence.
	 * @return bool
	 */
	public static function is_official_sentence( $sentence ) {
		$plain = rtrim( self::plain( $sentence ), '.' ) . '.';
		return 0 === strcasecmp( $plain, self::OFFICIAL_ID );
	}

	/**
	 * Replace the document title and keep og/twitter titles in lockstep.
	 *
	 * @param string $html  HTML.
	 * @param string $fixed Polished title.
	 * @return string
	 */
	public static function replace_document_title( $html, $fixed ) {
		if ( ! is_string( $html ) || '' === $fixed ) {
			return $html;
		}
		if ( preg_match( '/<title[^>]*>.*?<\/title>/is', $html ) ) {
			$replaced = preg_replace( '/<title[^>]*>.*?<\/title>/is', '<title>' . esc_html( $fixed ) . '</title>', $html, 1 );
			if ( is_string( $replaced ) ) {
				$html = $replaced;
			}
		}
		$html = self::upsert_meta( $html, 'property', 'og:title', $fixed );
		$html = self::upsert_meta( $html, 'name', 'twitter:title', $fixed );
		return $html;
	}

	/**
	 * @param string $html    HTML.
	 * @param string $attr    name or property.
	 * @param string $key     Key.
	 * @param string $content Content.
	 * @return string
	 */
	public static function upsert_meta( $html, $attr, $key, $content ) {
		$tag = '<meta ' . $attr . '="' . esc_attr( $key ) . '" content="' . esc_attr( $content ) . '" />';
		$re  = '/<meta[^>]+' . preg_quote( $attr, '/' ) . '=["\']' . preg_quote( $key, '/' ) . '["\'][^>]*>/i';
		if ( preg_match( $re, $html ) ) {
			$replaced = preg_replace( $re, $tag, $html, 1 );
			return is_string( $replaced ) ? $replaced : $html;
		}
		if ( preg_match( '/<\/head>/i', $html ) ) {
			$replaced = preg_replace( '/<\/head>/i', $tag . "\n</head>", $html, 1 );
			return is_string( $replaced ) ? $replaced : $html;
		}
		return $html;
	}

	/**
	 * Keep the first Organization JSON-LD node. Drop later duplicates.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function collapse_organization( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, 'application/ld+json' ) ) {
			return $html;
		}
		$seen = false;
		return preg_replace_callback(
			'/<script\b([^>]*)\btype=(["\'])application\/ld\+json\2([^>]*)>(.*?)<\/script>/is',
			function ( $m ) use ( &$seen ) {
				$raw  = trim( html_entity_decode( $m[4], ENT_QUOTES, 'UTF-8' ) );
				$data = json_decode( $raw, true );
				if ( ! is_array( $data ) ) {
					return $m[0];
				}
				$next = self::filter_org_node( $data, $seen );
				if ( null === $next ) {
					return '';
				}
				$json = wp_json_encode( $next, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				if ( ! is_string( $json ) || '' === $json ) {
					return $m[0];
				}
				return '<script type="application/ld+json">' . $json . '</script>';
			},
			$html
		);
	}

	/**
	 * @param array $data Graph.
	 * @param bool  $seen Seen Organization.
	 * @return array|null
	 */
	public static function filter_org_node( array $data, &$seen ) {
		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			$keep = array();
			foreach ( $data['@graph'] as $node ) {
				if ( ! is_array( $node ) ) {
					$keep[] = $node;
					continue;
				}
				if ( self::is_organization( $node ) ) {
					if ( $seen ) {
						continue;
					}
					$seen = true;
				}
				$keep[] = $node;
			}
			if ( empty( $keep ) ) {
				return null;
			}
			$data['@graph'] = array_values( $keep );
			return $data;
		}
		if ( self::is_organization( $data ) ) {
			if ( $seen ) {
				return null;
			}
			$seen = true;
		}
		return $data;
	}

	/**
	 * @param array $node Node.
	 * @return bool
	 */
	public static function is_organization( array $node ) {
		$type = isset( $node['@type'] ) ? $node['@type'] : '';
		if ( is_array( $type ) ) {
			$type = implode( ',', $type );
		}
		return false !== strpos( (string) $type, 'Organization' );
	}

	/**
	 * @param string $html HTML.
	 * @return int
	 */
	public static function organization_count( $html ) {
		if ( ! is_string( $html ) || ! preg_match_all( '/<script\b[^>]*type=(["\'])application\/ld\+json\1[^>]*>(.*?)<\/script>/is', $html, $matches, PREG_SET_ORDER ) ) {
			return 0;
		}
		$count = 0;
		foreach ( $matches as $m ) {
			$data = json_decode( trim( html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' ) ), true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$count += self::count_org_nodes( $data );
		}
		return $count;
	}

	/**
	 * @param array $data Graph.
	 * @return int
	 */
	private static function count_org_nodes( array $data ) {
		$n = 0;
		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			foreach ( $data['@graph'] as $node ) {
				if ( is_array( $node ) && self::is_organization( $node ) ) {
					$n++;
				}
			}
			return $n;
		}
		return self::is_organization( $data ) ? 1 : 0;
	}

	/**
	 * @param string $html HTML.
	 * @return array{html:string,blocks:array<int,string>}
	 */
	public static function park_blocks( $html ) {
		$blocks = array();
		$html   = preg_replace_callback(
			'/<script\b[^>]*>.*?<\/script>/is',
			function ( $m ) use ( &$blocks ) {
				$blocks[] = $m[0];
				return '<!--LPF_BLOCK_' . ( count( $blocks ) - 1 ) . '-->';
			},
			$html
		);
		$html = preg_replace_callback(
			'/<style\b[^>]*>.*?<\/style>/is',
			function ( $m ) use ( &$blocks ) {
				$blocks[] = $m[0];
				return '<!--LPF_BLOCK_' . ( count( $blocks ) - 1 ) . '-->';
			},
			is_string( $html ) ? $html : ''
		);
		return array(
			'html'   => is_string( $html ) ? $html : '',
			'blocks' => $blocks,
		);
	}

	/**
	 * @param string             $html   HTML.
	 * @param array<int,string>  $blocks Blocks.
	 * @return string
	 */
	public static function unpark_blocks( $html, array $blocks ) {
		foreach ( $blocks as $i => $block ) {
			$html = str_replace( '<!--LPF_BLOCK_' . $i . '-->', $block, $html );
		}
		return $html;
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public static function html_title( $html ) {
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', (string) $html, $m ) ) {
			return self::plain( $m[1] );
		}
		return '';
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	public static function plain( $text ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = str_replace( "\xC2\xA0", ' ', $text );
		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * Locked Associates tag present; no foreign tags.
	 *
	 * @param string $html HTML.
	 * @param string $tag  Wanted tag.
	 * @return bool
	 */
	public static function amazon_tag_ok( $html, $tag = 'luxetrendse0f-20' ) {
		if ( ! is_string( $html ) ) {
			return false;
		}
		if ( ! preg_match_all( '/[?&]tag=([A-Za-z0-9_-]+)/', $html, $m ) ) {
			return true;
		}
		foreach ( $m[1] as $found ) {
			if ( strtolower( $found ) !== strtolower( $tag ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * This plugin must not enqueue public JS or CSS.
	 *
	 * @param string $html HTML.
	 * @return bool
	 */
	public static function no_frontend_assets( $html ) {
		if ( ! is_string( $html ) ) {
			return false;
		}
		if ( preg_match( '/luxe-public-finish[^"\']*\.(js|css)/i', $html ) ) {
			return false;
		}
		return true;
	}
}
