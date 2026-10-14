<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This plugin never takes work that belongs to a sibling plugin.
 * Titles, robots files, schema, product scores, and Rank Math sitemaps stay put.
 */
class Luxe_Google_Presence_Compat {

	/**
	 * @param string $feature Feature key.
	 * @return bool
	 */
	public static function we_write( $feature ) {
		$never = array(
			'product_scores',
			'taxonomy_repair',
			'amazon_href',
			'post_content',
			'post_status',
			'rank_math_sitemap',
			'titles',
			'robots_file',
			'schema',
			'related_products',
			'indexing_api',
			'frontend_purge',
		);
		return ! in_array( (string) $feature, $never, true );
	}

	/**
	 * @return array<string,string>
	 */
	public static function siblings() {
		$found = array();
		$map   = array(
			'Luxe Score Repair'             => 'Luxe_Score_Repair_Plugin',
			'Luxe Affiliate Product Scout'  => 'LAPS_Plugin',
			'Luxe Product Integrity Repair' => 'Luxe_PIR_Plugin',
			'WooCommerce'                   => 'WooCommerce',
		);
		foreach ( $map as $label => $class_name ) {
			if ( class_exists( $class_name ) ) {
				$found[ $label ] = 'active';
			}
		}
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
			$found['Rank Math'] = 'active';
		}
		if ( defined( 'LSCWP_V' ) ) {
			$found['LiteSpeed Cache'] = 'active';
		}
		return $found;
	}
}
