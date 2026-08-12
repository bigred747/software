<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin-stack inventory against the 38-item catalog.
 */
class RBSMC_Compat {

	/**
	 * @return array
	 */
	public static function inventory() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all     = get_plugins();
		$active  = (array) get_option( 'active_plugins', array() );
		$catalog = rbsmc_plugin_catalog();
		$known   = array();
		foreach ( $catalog as $row ) {
			$known[ $row['slug'] ] = $row;
		}

		$mu      = array();
		$dropins = array();
		if ( function_exists( 'get_mu_plugins' ) ) {
			$mu = get_mu_plugins();
		}
		if ( function_exists( 'get_dropins' ) ) {
			$dropins = get_dropins();
		}

		$rows = array();
		foreach ( $all as $slug => $plugin ) {
			$in_catalog = isset( $known[ $slug ] );
			$is_active  = in_array( $slug, $active, true );
			$level      = 'info';
			$note       = 'Unclassified regular plugin requires MANUAL review.';
			if ( $in_catalog ) {
				$level = 'green';
				$note  = 'Catalog owner: ' . $known[ $slug ]['owner'];
				if ( 'overlap-with-rank-math' === $known[ $slug ]['owner'] && $is_active ) {
					$level = 'manual';
					$note  = 'Broad plugin overlap is MANUAL review, not a proven conflict.';
				}
			} elseif ( $is_active ) {
				$level = 'manual';
			}
			$rows[] = array(
				'slug'    => $slug,
				'name'    => $plugin['Name'],
				'version' => $plugin['Version'],
				'active'  => $is_active,
				'catalog' => $in_catalog,
				'level'   => $level,
				'note'    => $note,
			);
		}

		return array(
			'catalog_size' => count( $catalog ),
			'active'       => count( $active ),
			'mu'           => array_keys( $mu ),
			'dropins'      => array_keys( $dropins ),
			'rows'         => $rows,
			'signals'      => array(
				array(
					'level'  => 'info',
					'title'  => 'Must-use and drop-in components are inventory INFO',
					'detail' => 'MU: ' . count( $mu ) . ' Drop-ins: ' . count( $dropins ),
					'scored' => false,
				),
			),
		);
	}
}
