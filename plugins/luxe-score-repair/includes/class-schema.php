<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Valid JSON-LD only. Quarantines truncated FAQ and utility ItemLists.
 */
class Luxe_Score_Repair_Schema {

	/**
	 * @var self|null
	 */
	private static $instance = null;

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
	 * Rank Math graph filter plus wp_head injection.
	 */
	public function boot() {
		add_filter( 'rank_math/json_ld', array( $this, 'rank_math_graph' ), 99, 2 );
		add_action( 'wp_head', array( $this, 'print_home_schema' ), 92 );
	}

	/**
	 * Repair Rank Math graph in place.
	 *
	 * @param array $data   Graph.
	 * @param mixed $jsonld Rank Math helper.
	 * @return array
	 */
	public function rank_math_graph( $data, $jsonld ) {
		unset( $jsonld );
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_schema' ) || ! is_array( $data ) ) {
			return $data;
		}
		$home = home_url( '/' );
		$brand = Luxe_Score_Repair_Plugin::brand_name();
		$logo  = Luxe_Score_Repair_Plugin::brand_image();
		$seo   = Luxe_Score_Repair_SEO::instance();

		foreach ( $data as $key => $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$type = isset( $node['@type'] ) ? $node['@type'] : '';
			if ( is_array( $type ) ) {
				$type = implode( ',', $type );
			}
			if ( false !== strpos( (string) $type, 'WebPage' ) && $seo->is_front() ) {
				$data[ $key ]['name']        = Luxe_Score_Repair_Plugin::home_title();
				$data[ $key ]['description'] = Luxe_Score_Repair_Plugin::home_description();
				$data[ $key ]['url']         = $home;
				if ( $logo ) {
					$data[ $key ]['primaryImageOfPage'] = array(
						'@type' => 'ImageObject',
						'url'   => $logo,
					);
				}
			}
			if ( false !== strpos( (string) $type, 'WebSite' ) ) {
				$data[ $key ]['name'] = $brand;
				$data[ $key ]['url']  = $home;
				$data[ $key ]['potentialAction'] = array(
					'@type'       => 'SearchAction',
					'target'      => array(
						'@type'       => 'EntryPoint',
						'urlTemplate' => $home . '?s={search_term_string}',
					),
					'query-input' => 'required name=search_term_string',
				);
			}
			if ( false !== strpos( (string) $type, 'Organization' ) ) {
				$data[ $key ]['name'] = $brand;
				$data[ $key ]['url']  = $home;
				if ( $logo ) {
					$data[ $key ]['logo'] = array(
						'@type' => 'ImageObject',
						'url'   => $logo,
					);
				}
			}
			if ( false !== strpos( (string) $type, 'ImageObject' ) && $seo->is_front() && $logo ) {
				$url = isset( $node['url'] ) ? (string) $node['url'] : '';
				if ( false !== strpos( $url, 'media-amazon.com' ) ) {
					$data[ $key ]['url']     = $logo;
					$data[ $key ]['caption'] = $brand;
					unset( $data[ $key ]['width'], $data[ $key ]['height'] );
				}
			}
		}
		return $data;
	}

	/**
	 * Homepage Organization + complete FAQ. Rank Math still emits WebSite.
	 */
	public function print_home_schema() {
		if ( is_admin() || ! Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_schema' ) ) {
			return;
		}
		if ( ! Luxe_Score_Repair_SEO::instance()->is_front() ) {
			return;
		}
		$payload = $this->home_graph();
		echo '<script type="application/ld+json" id="luxe-score-repair-schema">' . $this->encode( $payload ) . "</script>\n";
	}

	/**
	 * @return array
	 */
	public function home_graph() {
		$home  = home_url( '/' );
		$brand = Luxe_Score_Repair_Plugin::brand_name();
		$logo  = Luxe_Score_Repair_Plugin::brand_image();
		$org   = array(
			'@type' => 'Organization',
			'@id'   => $home . '#luxe-organization',
			'name'  => $brand,
			'url'   => $home,
			'email' => 'contact@luxetrendsetters.com',
		);
		if ( $logo ) {
			$org['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $logo,
			);
		}
		return array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				$org,
				array(
					'@type'      => 'WebSite',
					'@id'        => $home . '#luxe-website',
					'name'       => $brand,
					'url'        => $home,
					'publisher'  => array( '@id' => $home . '#luxe-organization' ),
					'inLanguage' => 'en-US',
					'potentialAction' => array(
						'@type'       => 'SearchAction',
						'target'      => array(
							'@type'       => 'EntryPoint',
							'urlTemplate' => $home . '?s={search_term_string}',
						),
						'query-input' => 'required name=search_term_string',
					),
				),
				array(
					'@type'      => 'WebPage',
					'@id'        => $home . '#luxe-webpage',
					'url'        => $home,
					'name'       => Luxe_Score_Repair_Plugin::home_title(),
					'description'=> Luxe_Score_Repair_Plugin::home_description(),
					'isPartOf'   => array( '@id' => $home . '#luxe-website' ),
					'about'      => array( '@id' => $home . '#luxe-organization' ),
					'inLanguage' => 'en-US',
				),
				array(
					'@type'      => 'FAQPage',
					'@id'        => $home . '#luxe-faq',
					'isPartOf'   => array( '@id' => $home . '#luxe-webpage' ),
					'mainEntity' => $this->faq_entities(),
				),
			),
		);
	}

	/**
	 * Complete FAQ answers that match the visible homepage copy.
	 *
	 * @return array
	 */
	public function faq_entities() {
		return array(
			array(
				'@type'          => 'Question',
				'name'           => 'How are products selected?',
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => 'Products are chosen for category relevance, clear presentation, useful specifications, current price visibility, premium appeal, and buyer value.',
				),
			),
			array(
				'@type'          => 'Question',
				'name'           => 'Why compare more than one product?',
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => 'The best choice balances price, build quality, features, compatibility, warranty, reviews, and long-term usefulness instead of relying on price alone.',
				),
			),
			array(
				'@type'          => 'Question',
				'name'           => 'Are prices and availability final?',
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => 'No. Prices, stock, sellers, shipping, and warranties can change on Amazon. Always confirm the live listing before you buy.',
				),
			),
			array(
				'@type'          => 'Question',
				'name'           => 'Does LuxeTrendsetters earn commissions?',
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => 'Yes. As an Amazon Associate, LuxeTrendsetters earns from qualifying purchases at no extra cost to you.',
				),
			),
		);
	}

	/**
	 * Drop invalid JSON-LD script tags. Repair FAQ / utility ItemList when possible.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public function sanitize_html( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		$saw_faq = false;
		$html    = preg_replace_callback(
			'/<script([^>]*type=["\']application\/ld\+json["\'][^>]*)>(.*?)<\/script>/is',
			function ( $m ) use ( &$saw_faq ) {
				$raw = $this->normalize( $m[2] );
				if ( '' === $raw ) {
					return '';
				}
				$decoded = json_decode( $raw, true );
				if ( JSON_ERROR_NONE !== json_last_error() || null === $decoded ) {
					return '<!-- luxe-score-repair quarantined invalid json-ld -->';
				}
				$decoded = $this->scrub_node( $decoded );
				if ( $this->contains_type( $decoded, 'FAQPage' ) ) {
					$saw_faq = true;
					if ( $this->faq_is_incomplete( $decoded ) ) {
						return '';
					}
					if ( Luxe_Score_Repair_SEO::instance()->is_front() && false === strpos( $m[1], 'luxe-score-repair-schema' ) ) {
						return '';
					}
				}
				if ( $this->contains_type( $decoded, 'ItemList' ) ) {
					$decoded = $this->scrub_item_list( $decoded );
					if ( empty( $decoded['itemListElement'] ) ) {
						return '';
					}
				}
				$json = $this->encode( $decoded );
				if ( ! $json ) {
					return '<!-- luxe-score-repair quarantined invalid json-ld -->';
				}
				return '<script' . $m[1] . '>' . $json . '</script>';
			},
			$html
		);
		unset( $saw_faq );
		return is_string( $html ) ? $html : '';
	}

	/**
	 * @param mixed $node Node.
	 * @return mixed
	 */
	private function scrub_node( $node ) {
		if ( ! is_array( $node ) ) {
			return $node;
		}
		if ( isset( $node['name'] ) && is_string( $node['name'] ) && 'HOME: Features, Use Cases and Buyer Fit' === $node['name'] ) {
			$node['name'] = Luxe_Score_Repair_Plugin::home_title();
		}
		if ( isset( $node['description'] ) && is_string( $node['description'] ) && Luxe_Score_Repair_SEO::instance()->is_weak_description( $node['description'] ) ) {
			$node['description'] = Luxe_Score_Repair_Plugin::home_description();
		}
		foreach ( $node as $k => $v ) {
			if ( is_array( $v ) ) {
				$node[ $k ] = $this->scrub_node( $v );
			}
		}
		return $node;
	}

	/**
	 * Remove Client Portal / cart / test pages from related ItemLists.
	 *
	 * @param array $node ItemList.
	 * @return array
	 */
	private function scrub_item_list( $node ) {
		if ( empty( $node['itemListElement'] ) || ! is_array( $node['itemListElement'] ) ) {
			return $node;
		}
		$blocked = array( 'client-portal', 'cart', 'checkout', 'my-account', 'test-blog-page', 'wish-list', 'wishlist' );
		$keep    = array();
		$pos     = 1;
		foreach ( $node['itemListElement'] as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$url  = isset( $item['url'] ) ? strtolower( (string) $item['url'] ) : '';
			$name = isset( $item['name'] ) ? strtolower( (string) $item['name'] ) : '';
			$bad  = false;
			foreach ( $blocked as $slug ) {
				if ( false !== strpos( $url, '/' . $slug ) || false !== strpos( $name, str_replace( '-', ' ', $slug ) ) ) {
					$bad = true;
					break;
				}
			}
			if ( $bad ) {
				continue;
			}
			$item['position'] = $pos;
			$keep[]           = $item;
			$pos++;
		}
		$node['itemListElement'] = $keep;
		return $node;
	}

	/**
	 * @param mixed  $node Node.
	 * @param string $type Type.
	 * @return bool
	 */
	private function contains_type( $node, $type ) {
		$blob = wp_json_encode( $node );
		return is_string( $blob ) && false !== strpos( $blob, '"' . $type . '"' );
	}

	/**
	 * @param array $node FAQ node.
	 * @return bool
	 */
	private function faq_is_incomplete( $node ) {
		$blob = wp_json_encode( $node );
		if ( ! is_string( $blob ) ) {
			return true;
		}
		if ( false !== strpos( $blob, '"text":""' ) || false !== strpos( $blob, '"text": ""' ) ) {
			return true;
		}
		return substr_count( $blob, '"Question"' ) < 2;
	}

	/**
	 * @param string $raw Raw JSON.
	 * @return string
	 */
	public function normalize( $raw ) {
		$raw = html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$raw = preg_replace( '/^\s*<!--/', '', $raw );
		$raw = preg_replace( '/-->\s*$/', '', $raw );
		$raw = preg_replace( '/^\s*<!\[CDATA\[/', '', $raw );
		$raw = preg_replace( '/\]\]>\s*$/', '', $raw );
		return trim( $raw );
	}

	/**
	 * @param mixed $data Data.
	 * @return string
	 */
	public function encode( $data ) {
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $json ) ? $json : '';
	}
}
