<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LAH_Suite' ) ) {
	return;
}

/**
 * Read-only view of the Luxe master set.
 * Product Scout, Score Repair, and Product Integrity Repair.
 */
class LAH_Suite {

	/**
	 * @return array<int,array<string,string>>
	 */
	public static function signals() {
		$scout = defined( 'LAPS_VERSION' );
		$score = defined( 'LUXE_SCORE_REPAIR_VERSION' );
		$pir   = defined( 'LUXE_PIR_VERSION' );
		$rows  = array();

		$rows[] = array(
			'tone'   => $scout ? 'ok' : 'bad',
			'title'  => $scout ? 'Product Scout ' . LAPS_VERSION . ' is active' : 'Product Scout is not active',
			'detail' => $scout
				? 'ASINs already stored on products can be used below. This hub does not rewrite Amazon URLs and does not publish products.'
				: 'Activate Luxe Affiliate Product Scout to pull ASINs from the catalog. You can still paste an ASIN by hand.',
			'url'    => $scout ? admin_url( 'admin.php?page=luxe-affiliate-product-scout' ) : '',
			'action' => $scout ? 'Open Product Scout' : '',
		);

		$disclosure_on = false;
		if ( $score ) {
			$stored = get_option( 'luxe_score_repair_settings', array() );
			$disclosure_on = is_array( $stored ) && ! empty( $stored['affiliate_disclosure'] );
		}
		if ( $score && $disclosure_on ) {
			$rows[] = array(
				'tone'   => 'ok',
				'title'  => 'Score Repair ' . LUXE_SCORE_REPAIR_VERSION . ' prints the homepage disclosure',
				'detail' => LAH_Links::SITE_DISCLOSURE,
				'url'    => admin_url( 'tools.php?page=luxe-score-repair' ),
				'action' => 'Open Score Repair',
			);
		} elseif ( $score ) {
			$rows[] = array(
				'tone'   => 'bad',
				'title'  => 'Homepage disclosure is turned off',
				'detail' => 'Turn on Homepage Amazon disclosure in Score Repair before you send people to Amazon.',
				'url'    => admin_url( 'tools.php?page=luxe-score-repair' ),
				'action' => 'Open Score Repair',
			);
		} else {
			$rows[] = array(
				'tone'   => 'bad',
				'title'  => 'Score Repair is not active',
				'detail' => 'The master set uses Score Repair to print the homepage associate disclosure. Activate it, or place the disclosure on the page yourself.',
				'url'    => '',
				'action' => '',
			);
		}

		$rows[] = array(
			'tone'   => $pir ? 'ok' : 'bad',
			'title'  => $pir ? 'Product Integrity Repair ' . LUXE_PIR_VERSION . ' is active' : 'Product Integrity Repair is not active',
			'detail' => $pir
				? 'It repairs brand and category conflicts for Product Scout. It does not change affiliate URLs.'
				: 'Integrity Repair is the catalog companion. The hub still works without it.',
			'url'    => $pir ? admin_url( 'tools.php?page=luxe-product-integrity-repair' ) : '',
			'action' => $pir ? 'Open Product Integrity' : '',
		);

		return $rows;
	}

	/**
	 * Products that already have an ASIN. Read only.
	 *
	 * @param int $limit Limit.
	 * @return array<int,array<string,string>>
	 */
	public static function catalog( $limit = 24 ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}
		$limit    = max( 1, (int) $limit );
		$products = wc_get_products(
			array(
				'limit'   => 80,
				'status'  => array( 'publish', 'draft', 'pending', 'private' ),
				'orderby' => 'modified',
				'order'   => 'DESC',
				'return'  => 'objects',
			)
		);
		if ( ! is_array( $products ) ) {
			return array();
		}
		$rows = array();
		foreach ( $products as $product ) {
			if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
				continue;
			}
			$asin = self::asin_from_product( $product );
			if ( '' === $asin ) {
				continue;
			}
			$title = method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '';
			$status = method_exists( $product, 'get_status' ) ? (string) $product->get_status() : '';
			$rows[] = array(
				'id'     => (string) $product->get_id(),
				'title'  => $title,
				'asin'   => $asin,
				'status' => $status,
			);
			if ( count( $rows ) >= $limit ) {
				break;
			}
		}
		return $rows;
	}

	/**
	 * @param object $product Product.
	 * @return string
	 */
	private static function asin_from_product( $product ) {
		if ( class_exists( 'LAPS_Catalog' ) ) {
			return LAPS_Catalog::product_asin( $product );
		}
		$keys = array(
			'_asin',
			'asin',
			'_amazon_asin',
			'amazon_asin',
			'_amz_asin',
			'_wzone_asin',
			'_wzae_asin',
			'_amazon_product_id',
			'_laps_asin',
			'_sku_asin',
			'pa_asin',
			'_product_url',
			'_amazon_url',
			'_amz_url',
			'_wzone_amazon_url',
			'_affiliate_url',
			'_laps_amazon_url',
		);
		foreach ( $keys as $key ) {
			if ( ! method_exists( $product, 'get_meta' ) ) {
				break;
			}
			$hit = LAH_Links::asin( (string) $product->get_meta( $key, true ) );
			if ( '' !== $hit ) {
				return $hit;
			}
		}
		if ( method_exists( $product, 'get_sku' ) ) {
			$hit = LAH_Links::asin( (string) $product->get_sku() );
			if ( '' !== $hit ) {
				return $hit;
			}
		}
		if ( method_exists( $product, 'get_product_url' ) ) {
			return LAH_Links::asin( (string) $product->get_product_url() );
		}
		return '';
	}
}
