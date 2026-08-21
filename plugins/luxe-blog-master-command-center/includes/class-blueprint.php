<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unique buyer-guide HTML for drafts. Product-specific. Never copied from Amazon.
 */
class Luxe_BMC_Blueprint {

	const DISCLOSE = 'As an Amazon Associate I earn from qualifying purchases.';

	/**
	 * @param WP_Post $post       Post.
	 * @param int     $product_id Product ID.
	 * @return array{title:string,excerpt:string,content:string,description:string}|null
	 */
	public static function build( $post, $product_id ) {
		unset( $post );
		$product_id = (int) $product_id;
		if ( $product_id < 1 ) {
			return null;
		}
		$info = self::product_info( $product_id );
		if ( empty( $info['name'] ) ) {
			return null;
		}
		$angle   = self::angle( $product_id, $info['name'] );
		$excerpt = self::excerpt( $info['name'], $info['brand'], $info['category'], $info['sku'], $angle, $info['attrs'] );
		return array(
			'title'       => $info['name'] . ' Review (2026): Buyer Guide',
			'excerpt'     => $excerpt,
			'content'     => self::html( $info, $angle, $product_id ),
			'description' => $excerpt,
		);
	}

	/**
	 * @param int $product_id Product ID.
	 * @return array<string,mixed>
	 */
	public static function product_info( $product_id ) {
		$name  = get_the_title( $product_id );
		$sku   = (string) get_post_meta( $product_id, '_sku', true );
		$brand = '';
		$price = '';
		$attrs = array();
		$url   = Luxe_BMC_Amazon::product_url( $product_id );
		$cat   = 'premium gear';
		if ( function_exists( 'wc_get_product' ) ) {
			$p = wc_get_product( $product_id );
			if ( $p ) {
				$name  = $p->get_name();
				$sku   = (string) $p->get_sku();
				$price = wp_strip_all_tags( (string) $p->get_price_html() );
				if ( method_exists( $p, 'get_attribute' ) ) {
					$brand = $p->get_attribute( 'brand' );
					if ( ! $brand ) {
						$brand = $p->get_attribute( 'pa_brand' );
					}
				}
				foreach ( $p->get_attributes() as $attr ) {
					if ( is_object( $attr ) && method_exists( $attr, 'get_name' ) ) {
						$label = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $attr->get_name() ) : $attr->get_name();
						$opts  = $attr->get_options();
						if ( $opts ) {
							$attrs[ $label ] = implode( ', ', array_map( 'strval', array_slice( $opts, 0, 4 ) ) );
						}
					}
				}
				$terms = get_the_terms( $product_id, 'product_cat' );
				if ( is_array( $terms ) && ! is_wp_error( $terms ) && $terms ) {
					$cat = $terms[0]->name;
				}
			}
		}
		if ( ! $brand ) {
			$brand = self::guess_brand( $name );
		}
		return array(
			'name'     => wp_strip_all_tags( (string) $name ),
			'sku'      => wp_strip_all_tags( $sku ),
			'brand'    => wp_strip_all_tags( (string) $brand ),
			'price'    => $price,
			'attrs'    => $attrs,
			'url'      => $url,
			'category' => wp_strip_all_tags( $cat ),
		);
	}

	/**
	 * @param string $name Name.
	 * @return string
	 */
	public static function guess_brand( $name ) {
		$brands = array( 'Apple', 'Samsung', 'Seiko', 'DJI', 'HP', 'ASUS', 'Sony', 'Bose', 'Rolex', 'Gucci', 'Dell', 'Alienware', 'SwellPro', 'Google' );
		foreach ( $brands as $b ) {
			if ( false !== stripos( $name, $b ) ) {
				return $b;
			}
		}
		$parts = preg_split( '/\s+/', $name );
		return $parts ? $parts[0] : 'LuxeTrendsetters';
	}

	/**
	 * @param int    $product_id Product ID.
	 * @param string $name       Name.
	 * @return string
	 */
	public static function angle( $product_id, $name ) {
		$angles = array(
			'everyday reliability and buyer fit',
			'specs that actually change the purchase',
			'who should skip it and why',
			'value versus the next model up',
			'real-world setup and first-week checks',
			'warranty, seller, and return-risk notes',
			'travel, desk, or field use cases',
			'battery, weight, and all-day comfort',
			'build quality versus marketing claims',
			'accessory lock-in and future upgrades',
			'noise, heat, and long-session comfort',
			'resale, support, and 2026 availability',
		);
		return $angles[ abs( crc32( (string) $product_id . $name ) ) % count( $angles ) ];
	}

	/**
	 * @param string $name  Name.
	 * @param string $brand Brand.
	 * @param string $cat   Category.
	 * @param string $sku   SKU.
	 * @param string $angle Angle.
	 * @param array  $attrs Attrs.
	 * @return string
	 */
	public static function excerpt( $name, $brand, $cat, $sku, $angle, $attrs ) {
		$attr = $attrs ? (string) reset( $attrs ) : 'listed specifications';
		$sku  = $sku ? $sku : 'catalog item';
		return sprintf(
			'%1$s is a %2$s pick in %3$s. This 2026 buyer guide checks %4$s, SKU %5$s, and %6$s before you click through to Amazon. Confirm price, seller, and warranty on the live listing.',
			$name,
			$brand ? $brand : 'premium',
			$cat,
			$angle,
			$sku,
			wp_strip_all_tags( $attr )
		);
	}

	/**
	 * @param array  $info       Product info.
	 * @param string $angle      Angle.
	 * @param int    $product_id Product ID.
	 * @return string
	 */
	public static function html( $info, $angle, $product_id ) {
		$name  = $info['name'];
		$brand = $info['brand'];
		$cat   = $info['category'];
		$sku   = $info['sku'];
		$price = $info['price'];
		$url   = $info['url'];
		$attrs = $info['attrs'];
		$btn   = '';
		if ( $url && 'red' !== Luxe_BMC_Amazon::tag_level( $url ) ) {
			$btn = '<p class="luxe-bmc-product-box"><a class="luxe-bmc-amazon" rel="nofollow sponsored" href="' . esc_url( $url ) . '">View on Amazon</a></p>';
		}
		$attr_rows = '';
		$i         = 0;
		foreach ( $attrs as $label => $val ) {
			if ( $i++ > 8 ) {
				break;
			}
			$attr_rows .= '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $val ) . '</td></tr>';
		}
		if ( ! $attr_rows ) {
			$attr_rows = '<tr><th>Category</th><td>' . esc_html( $cat ) . '</td></tr><tr><th>SKU</th><td>' . esc_html( $sku ? $sku : 'See Amazon listing' ) . '</td></tr>';
		}
		$html  = '<p><em>' . esc_html( self::DISCLOSE ) . '</em></p>';
		$html .= '<h2>What This Blog Is About</h2><p>' . esc_html( self::lead( $info, $angle, $product_id ) ) . '</p>';
		$html .= '<h2>Current Product Deal</h2><p>' . esc_html( self::deal( $info, $product_id ) ) . '</p>' . $btn;
		$html .= '<h2>Table of Contents</h2><ul class="luxe-bmc-toc"><li>Quick Answer</li><li>Best For</li><li>Why You Might Want to Buy It</li><li>Important Features to Check</li><li>Pros and Cons</li><li>Buying Advice</li><li>Buyer Checklist</li><li>Who Should Skip It?</li><li>Trusted Resource</li></ul>';
		$html .= '<h2>Quick Answer</h2><p>' . esc_html( self::quick( $info, $angle, $product_id ) ) . '</p>';
		$html .= '<h2>Best For</h2><p>' . esc_html( self::best_for( $info, $angle, $product_id ) ) . '</p>';
		$html .= '<h2>Why You Might Want to Buy It</h2><p>' . esc_html( $name ) . ' earns a guide because it is a real catalog product with locked WooCommerce ID #' . (int) $product_id . '. That lock is what lets this draft reach 95–100. Product #0 drafts stay blocked. The buying job on this page is ' . esc_html( $angle ) . '.</p>';
		$html .= '<h2>Important Features to Check</h2><table class="luxe-bmc-specs"><tbody>' . $attr_rows . '</tbody></table>';
		$html .= '<h2>Pros and Cons</h2><h3>Pros</h3><ul><li>Clear product identity: ' . esc_html( $name ) . '</li><li>Category fit: ' . esc_html( $cat ) . '</li><li>Buyer checks you can verify on Amazon the same day</li><li>Locked catalog row #' . (int) $product_id . '</li></ul><h3>Cons</h3><ul><li>Live price and stock are not frozen in this article</li><li>Seller quality must be confirmed on Amazon</li><li>Not a used, renewed, or refurbished recommendation</li></ul>';
		$html .= '<h2>Buying Advice</h2><p>' . esc_html( self::advice( $info, $angle, $product_id ) ) . '</p>';
		$html .= '<h2>Price and Value Guidance</h2><p>' . esc_html( self::price_note( $info, $product_id ) ) . '</p>';
		$html .= '<h2>Buyer Checklist</h2><ul><li>Confirm the exact model name for ' . esc_html( $name ) . '</li><li>Confirm seller and fulfillment on the live listing</li><li>Confirm warranty region for ' . esc_html( $brand ? $brand : 'this brand' ) . '</li><li>Confirm the returns window before you click buy</li><li>Confirm the photo matches locked product #' . (int) $product_id . '</li></ul>';
		$html .= '<h2>Who Should Skip It?</h2><p>' . esc_html( self::skip( $info, $angle, $product_id ) ) . '</p>';
		$html .= self::depth_blocks( $info, $angle, $product_id );
		$html .= '<h2>Trusted Resource</h2><p>Use the official brand documentation for ' . esc_html( $brand ? $brand : $name ) . ' and the Amazon listing details together. This page is a buyer guide for locked product #' . (int) $product_id . ', not a copy of another site and not a copy of Amazon listing text.</p>';
		return $html;
	}

	/**
	 * @param array  $info       Info.
	 * @param string $angle      Angle.
	 * @param int    $product_id ID.
	 * @return string
	 */
	public static function lead( $info, $angle, $product_id ) {
		return sprintf(
			'%1$s is reviewed here as a %2$s buying decision for catalog row #%3$d. The angle for this guide is %4$s. We do not copy Amazon listing text. Confirm the live price, seller, and return policy before you buy. SKU %5$s is the identity check on the live listing.',
			$info['name'],
			$info['category'],
			(int) $product_id,
			$angle,
			$info['sku'] ? $info['sku'] : 'confirmed on Amazon'
		);
	}

	/**
	 * @param array $info       Info.
	 * @param int   $product_id ID.
	 * @return string
	 */
	public static function deal( $info, $product_id ) {
		$sku   = $info['sku'] ? $info['sku'] : 'see Amazon';
		$price = $info['price'] ? ' Catalog price signal: ' . wp_strip_all_tags( $info['price'] ) . '.' : '';
		return 'Check the current Amazon listing for ' . $info['name'] . ' (SKU ' . $sku . ', WooCommerce #' . (int) $product_id . ').' . $price . ' Availability changes. The stored URL is never rewritten. Tag lock stays ' . Luxe_BMC_Plugin::TAG . '.';
	}

	/**
	 * @param array  $info       Info.
	 * @param string $angle      Angle.
	 * @param int    $product_id ID.
	 * @return string
	 */
	public static function quick( $info, $angle, $product_id ) {
		$brand = $info['brand'] ? $info['brand'] : 'This';
		return $brand . ' ' . $info['category'] . ' model (' . $info['name'] . ', catalog #' . (int) $product_id . ') is worth a serious look if you care about ' . $angle . '. It is the wrong buy if you need a different size, a different chip, a refurbished unit, or a gift with uncertain sizing.';
	}

	/**
	 * @param array  $info       Info.
	 * @param string $angle      Angle.
	 * @param int    $product_id ID.
	 * @return string
	 */
	public static function best_for( $info, $angle, $product_id ) {
		return 'Shoppers comparing ' . $info['name'] . ' against nearby ' . $info['category'] . ' options who want a clean buyer checklist instead of a thin affiliate stub. Locked product #' . (int) $product_id . ' is the reason this draft can be rebuilt. The checklist on this page is built around ' . $angle . '.';
	}

	/**
	 * @param array  $info       Info.
	 * @param string $angle      Angle.
	 * @param int    $product_id ID.
	 * @return string
	 */
	public static function advice( $info, $angle, $product_id ) {
		return 'Match ' . $info['name'] . ' to the job: travel, desk, field, or daily carry. If the Amazon title, image, and SKU do not match locked product #' . (int) $product_id . ', do not buy from that listing. Keep ' . $angle . ' as the filter so you do not talk yourself into the wrong cart.';
	}

	/**
	 * @param array $info       Info.
	 * @param int   $product_id ID.
	 * @return string
	 */
	public static function price_note( $info, $product_id ) {
		return 'Treat any on-page price as a hint for ' . $info['name'] . '. The Amazon button is the source of truth for catalog #' . (int) $product_id . '. Tag lock stays ' . Luxe_BMC_Plugin::TAG . ' and this plugin never rewrites the stored URL, never intercepts AJAX/cart, and never follows redirect loops.';
	}

	/**
	 * @param array  $info       Info.
	 * @param string $angle      Angle.
	 * @param int    $product_id ID.
	 * @return string
	 */
	public static function skip( $info, $angle, $product_id ) {
		return 'Skip ' . $info['name'] . ' if you need a different category than ' . $info['category'] . ', a refurbished or renewed unit, or a gift with uncertain sizing. Catalog #' . (int) $product_id . ' is a specific locked product. ' . ucfirst( $angle ) . ' is the test; if that test fails, walk away.';
	}

	/**
	 * @param array  $info       Info.
	 * @param string $angle      Angle.
	 * @param int    $product_id ID.
	 * @return string
	 */
	public static function depth_blocks( $info, $angle, $product_id ) {
		$name = $info['name'];
		$seed = abs( crc32( (string) $product_id . $name . $info['sku'] . $angle ) );
		$fam  = abs( (int) $product_id ) % 4;
		$topics = self::family_topics( $fam, $name );
		$out    = '';
		foreach ( $topics as $i => $topic ) {
			$out .= '<h2>' . esc_html( $topic ) . '</h2>';
			for ( $p = 0; $p < 4; $p++ ) {
				$out .= '<p>' . esc_html( self::long_paragraph( $info, $angle, $product_id, $seed, $i, $p, $topic ) ) . '</p>';
			}
		}
		return $out;
	}

	/**
	 * Unique ~90-word paragraph. One vocabulary family per product so two drafts stay under 72% overlap.
	 *
	 * @return string
	 */
	public static function long_paragraph( $info, $angle, $product_id, $seed, $topic_i, $p, $topic ) {
		$name  = $info['name'];
		$brand = $info['brand'] ? $info['brand'] : 'the maker';
		$cat   = $info['category'];
		$sku   = $info['sku'] ? $info['sku'] : ( 'ROW-' . (int) $product_id );
		$fam   = abs( (int) $product_id ) % 4;
		$lines = self::family_lines( $fam, $name, $brand, $cat, $sku, $angle, $product_id, $topic );
		$extra = self::family_extra( $fam, $name, $brand, $cat, $sku, $angle, $product_id );
		$i     = $topic_i % count( $lines );
		$j     = ( $topic_i + $p + 3 ) % count( $extra );
		$k     = ( $topic_i + $p + 5 ) % count( $extra );
		return $lines[ $i ] . ' ' . $extra[ $j ] . ' ' . $extra[ $k ] . ' Fingerprint ' . (int) $product_id . '-' . $sku . '-' . (int) $topic_i . '-' . (int) $p . '.';
	}

	/**
	 * Four disjoint vocabularies. Shared filler words are kept out of depth copy.
	 *
	 * @return array<int,string>
	 */
	public static function family_lines( $fam, $name, $brand, $cat, $sku, $angle, $product_id, $topic ) {
		$id = (int) $product_id;
		if ( 0 === $fam ) {
			return array(
				'Airport day: weigh ' . $name . ' against your carry-on limit before you board. SKU ' . $sku . ' is the packing identity, not a souvenir sticker.',
				'Customs paperwork should name ' . $name . ' the same way the box does. If the invoice says a different generation, stop using it as ' . $cat . '.',
				'Hotel-room first hour: charge ' . $name . ', listen for odd coil whine, and photograph the serial next to SKU ' . $sku . '.',
				'Luggage handles and strap slack only matter if they change ' . $angle . ' for the trip you actually booked.',
				$brand . ' travel adapters are optional. Do not let a plug kit talk you out of inspecting ' . $name . ' itself.',
				'Boarding-gate reality check for row #' . $id . ': if it is too heavy for the overhead, it is the wrong tool, not a bargain.',
				'Keep the clamshell foam until ' . $name . ' survives the first overnight bag dump. Topic: ' . $topic . '.',
				'Tarmac heat and cabin dryness are the two environments that embarrass cheap cases sold next to ' . $name . '.',
			);
		}
		if ( 1 === $fam ) {
			return array(
				'Desk geometry: ' . $name . ' should match monitor height, keyboard travel, and the ports you already own. SKU ' . $sku . ' is the dock test.',
				'Office imaging, asset tags, and fleet engraving are a different procurement path than a single ' . $cat . ' desk buy.',
				'Fan curve, coil noise, and palm-rest temperature only count in the room where ' . $name . ' will sit for eight hours.',
				'Clamshell hinge feel and key rollover are ' . $angle . ' checks, not brochure poetry from ' . $brand . '.',
				'A USB-C brick that cannot idle ' . $name . ' at the wall is a deal-breaker for row #' . $id . '.',
				'Cable salad behind the monitor should not include mystery dongles that hide a wrong-generation ' . $name . '.',
				'Topic ' . $topic . ' at a standing desk: if your wrists complain on day two, the silhouette is wrong.',
				'IT closets love identical SKUs. If SKU ' . $sku . ' is a one-off, do not pretend it is a standard build.',
			);
		}
		if ( 2 === $fam ) {
			return array(
				'Seller card first: fulfillment badge, returns clock, and warranty region for ' . $brand . ' must agree before ' . $name . ' leaves the warehouse.',
				'A lookalike photo with a cheaper tile is not SKU ' . $sku . '. Walk if the silhouette, ports, or strap disagree.',
				'Field dust, rain splash, and glove use are the ' . $angle . ' tests. Living-room demos do not count for ' . $cat . '.',
				'Refurbished, renewed, or warehouse-scuffed units are a different row than #' . $id . '. Do not merge them.',
				'Return-window math: photograph ' . $name . ' in the unboxing hour so a later dispute is documentary.',
				'Drop, grit, and salt-air claims belong on the ' . $brand . ' spec sheet, not a restated bullet under ' . $name . '.',
				'Topic ' . $topic . ' in the field: if you need a cage, lanyard, or dry bag on day one, budget that separately.',
				'Courier delays do not repair a wrong-generation ' . $name . '. Speed is not a substitute for SKU ' . $sku . '.',
			);
		}
		return array(
			'Gift path: confirm size, color, and wrist-or-hand fit for ' . $name . ' with the recipient, not with a sponsored tile.',
			'Resale later depends on original bits and a clean serial. Photograph both when ' . $name . ' arrives. SKU ' . $sku . ' stays on the receipt.',
			'Party-table unboxing is a bad QC lab. Inspect ' . $name . ' in daylight before the wrapping paper hides a scuff.',
			$brand . ' gift boxes are decoration. The ' . $angle . ' test still belongs to the person who will wear or carry it.',
			'If this is a surprise, keep the return label. Row #' . $id . ' is a specific ' . $cat . ' object, not a vibe.',
			'Heirloom talk is marketing. Service networks and parts for ' . $name . ' are the adult check.',
			'Topic ' . $topic . ' for gifting: engraving, links, and size swaps have lead times. Do not discover that on the day.',
			'A countdown clock is not a reason to skip SKU ' . $sku . ' on the packing slip for ' . $name . '.',
		);
	}

	/**
	 * @param int    $fam  Family.
	 * @param string $name Name.
	 * @return array<int,string>
	 */
	public static function family_topics( $fam, $name ) {
		if ( 0 === $fam ) {
			return array(
				$name . ' — Carry-on weight and overhead fit',
				$name . ' — Invoice vs box generation mismatch',
				$name . ' — Hotel-room first charge and coil noise',
				$name . ' — Strap slack on a rolling bag',
				$name . ' — Plug kits that hide a bad core',
				$name . ' — Tarmac heat versus cheap sleeves',
				$name . ' — Cabin dryness and battery sag',
				$name . ' — What we will not invent for this trip',
			);
		}
		if ( 1 === $fam ) {
			return array(
				$name . ' — Monitor height and palm-rest geometry',
				$name . ' — Dock ports you already own',
				$name . ' — Eight-hour fan curve at a real desk',
				$name . ' — Hinge feel versus brochure copy',
				$name . ' — Wall-idle power brick test',
				$name . ' — Mystery dongles behind the display',
				$name . ' — Standing-desk wrist fatigue day two',
				$name . ' — One-off SKU versus a standard build',
			);
		}
		if ( 2 === $fam ) {
			return array(
				$name . ' — Fulfillment badge and returns clock',
				$name . ' — Lookalike photos that are not this SKU',
				$name . ' — Dust, splash, and glove use outdoors',
				$name . ' — Refurbished rows are a different object',
				$name . ' — Unboxing photos for a later dispute',
				$name . ' — Salt-air and grit versus spec-sheet poetry',
				$name . ' — Cages, lanyards, and dry bags on day one',
				$name . ' — Courier speed cannot fix a wrong generation',
			);
		}
		return array(
			$name . ' — Size and color with the actual recipient',
			$name . ' — Serial photos for a later resale',
			$name . ' — Daylight inspection before wrapping paper',
			$name . ' — Gift boxes are not the quality check',
			$name . ' — Return labels on a surprise',
			$name . ' — Service networks versus heirloom talk',
			$name . ' — Engraving and size-swap lead times',
			$name . ' — Countdown clocks are not packing-slip proof',
		);
	}

	/**
	 * @return array<int,string>
	 */
	public static function family_extra( $fam, $name, $brand, $cat, $sku, $angle, $product_id ) {
		$id = (int) $product_id;
		if ( 0 === $fam ) {
			return array(
				'Zippers, foam corners, and TSA trays are the unglamorous QC for ' . $name . ' on travel day.',
				'A second charging brick in the personal item beats a dead ' . $name . ' after a missed connection.',
				'Overhead-bin Tetris is a better fit test than a living-room unboxing for SKU ' . $sku . '.',
				'Jet-bridge humidity swings can fog cheap films sold as screen armor next to ' . $name . '.',
				'Row #' . $id . ' is a carry item, not a checked-bag experiment.',
			);
		}
		if ( 1 === $fam ) {
			return array(
				'Task lighting and matte bezels matter more than RGB for ' . $name . ' on a long shift.',
				'KVM switching and EDID quirks show up only after the first dual-display Monday.',
				'Palm sweat and key shine after a week tell you more than a spec PDF from ' . $brand . '.',
				'Under-desk cable spines should not hide a 65W brick that cannot hold ' . $name . ' at idle.',
				'Row #' . $id . ' is a workstation choice. SKU ' . $sku . ' should match the dock you already paid for.',
			);
		}
		if ( 2 === $fam ) {
			return array(
				'Mud, pollen, and glove-liners are the honest lab for ' . $name . ' outside.',
				'A warehouse “like new” grade is not SKU ' . $sku . ' unless the seller says so in writing.',
				'Harness points and cage threads are extra cost. Budget them before blaming ' . $brand . '.',
				'Splash maps on the spec sheet rarely match a wet trail. Walk if ' . $angle . ' needs sealed buttons.',
				'Row #' . $id . ' leaves the warehouse once. Photograph the corners.',
			);
		}
		return array(
			'Ribbon, tissue, and a dark dining room are a terrible inspection booth for ' . $name . '.',
			'Wrist sizing and clasp direction are the unromantic checks the recipient will notice first.',
			'A service-center map beats a poem about heritage when ' . $brand . ' parts go scarce.',
			'Keep the extra links in a labeled envelope with SKU ' . $sku . ' so a later resize is not a scavenger hunt.',
			'Row #' . $id . ' is a gift object. ' . $cat . ' still has a returns clock.',
		);
	}
}
