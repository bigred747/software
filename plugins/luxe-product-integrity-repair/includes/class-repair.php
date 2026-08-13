<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repair live WooCommerce taxonomy / brand-attribute conflicts.
 * Does not write Product Scout scores. After this runs, Recalculate in Product Scout.
 */
class Luxe_PIR_Repair {

	const OPTION_LAST = 'luxe_pir_last_run';
	const OPTION_LOG  = 'luxe_pir_log';
	const OPTION_CURSOR = 'luxe_pir_cursor';
	const CRON        = 'luxe_pir_heal';
	const LOCK        = 'luxe_pir_lock';

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
	 * Register cron and admin run.
	 */
	public function boot() {
		add_filter( 'cron_schedules', array( $this, 'schedules' ) );
		add_action( self::CRON, array( $this, 'cron_batch' ) );
		add_action( 'admin_init', array( $this, 'ensure_cron' ) );
		add_action( 'admin_init', array( $this, 'maybe_manual' ) );
	}

	/**
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public function schedules( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}
		$schedules['luxe_pir_six'] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 6 hours', 'luxe-product-integrity-repair' ),
		);
		return $schedules;
	}

	/**
	 * Schedule bounded heal.
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 120, 'luxe_pir_six', self::CRON );
		}
		wp_schedule_single_event( time() + 20, self::CRON );
	}

	/**
	 * Clear cron.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * Keep cron alive after zip upload.
	 */
	public function ensure_cron() {
		if ( ! Luxe_PIR_Plugin::instance()->enabled( 'auto_repair' ) ) {
			return;
		}
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 180, 'luxe_pir_six', self::CRON );
		}
	}

	/**
	 * Admin button.
	 */
	public function maybe_manual() {
		if ( empty( $_POST['luxe_pir_run_now'] ) && empty( $_POST['luxe_pir_run_all'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_pir_run' );
		delete_transient( self::LOCK );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 180 );
		}
		$all = ! empty( $_POST['luxe_pir_run_all'] );
		if ( $all ) {
			update_option( self::OPTION_CURSOR, 0, false );
		}
		$result = $this->run_batch( $all ? 250 : Luxe_PIR_Plugin::instance()->batch_size() );
		$this->flush_catalog_caches();
		add_settings_error(
			'luxe_pir',
			'ran',
			sprintf(
				/* translators: 1: scanned, 2: repaired, 3: held, 4: skipped */
				__( 'Catalog repair finished. Scanned %1$d, repaired %2$d, held %3$d, already clean %4$d. Open Product Scout and click Run Full Catalog Audit.', 'luxe-product-integrity-repair' ),
				(int) $result['scanned'],
				(int) $result['repaired'],
				(int) $result['held'],
				(int) $result['clean']
			),
			'updated'
		);
	}

	/**
	 * Cron batch.
	 */
	public function cron_batch() {
		if ( ! Luxe_PIR_Plugin::instance()->enabled( 'auto_repair' ) ) {
			return;
		}
		$this->run_batch( Luxe_PIR_Plugin::instance()->batch_size() );
	}

	/**
	 * @param int $limit 0 = all remaining this request, capped at 250.
	 * @return array
	 */
	public function run_batch( $limit = 25 ) {
		if ( get_transient( self::LOCK ) ) {
			$last = get_option( self::OPTION_LAST, array() );
			return is_array( $last ) ? $last : array();
		}
		set_transient( self::LOCK, 1, 90 );

		if ( ! function_exists( 'wc_get_products' ) ) {
			delete_transient( self::LOCK );
			return array(
				'scanned'  => 0,
				'repaired' => 0,
				'held'     => 0,
				'clean'    => 0,
				'changes'  => array(),
				'at'       => time(),
				'error'    => 'woocommerce-missing',
			);
		}

		$limit  = (int) $limit;
		$cap    = ( $limit < 1 ) ? 250 : min( 250, $limit );
		$cursor = (int) get_option( self::OPTION_CURSOR, 0 );
		$ids    = wc_get_products(
			array(
				'status'  => array( 'publish', 'draft', 'pending', 'private' ),
				'limit'   => $cap,
				'offset'  => $cursor,
				'return'  => 'ids',
				'orderby' => 'ID',
				'order'   => 'DESC',
				'type'    => array( 'simple', 'variable', 'external', 'grouped' ),
			)
		);

		$scanned  = 0;
		$repaired = 0;
		$held     = 0;
		$clean    = 0;
		$changes  = array();

		foreach ( $ids as $id ) {
			$scanned++;
			$result = $this->repair_product( (int) $id );
			if ( 'held' === $result['status'] ) {
				$held++;
			} elseif ( 'repaired' === $result['status'] ) {
				$repaired++;
				$changes[] = $result;
			} else {
				$clean++;
			}
		}

		$next = $cursor + $scanned;
		if ( $scanned < $cap ) {
			$next = 0;
		}
		update_option( self::OPTION_CURSOR, $next, false );

		$out = array(
			'at'       => time(),
			'scanned'  => $scanned,
			'repaired' => $repaired,
			'held'     => $held,
			'clean'    => $clean,
			'cursor'   => $next,
			'changes'  => array_slice( $changes, 0, 40 ),
		);
		update_option( self::OPTION_LAST, $out, false );
		$this->push_log( $out );
		delete_transient( self::LOCK );
		return $out;
	}

	/**
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public function repair_product( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array(
				'id'     => $product_id,
				'status' => 'clean',
				'did'    => array(),
			);
		}

		$title = $this->plain( $product->get_name() );
		$sku   = $this->plain( $product->get_sku() );
		$blob  = $title . ' ' . $sku;

		if ( $this->is_hard_risk( $blob ) ) {
			return array(
				'id'     => $product_id,
				'title'  => $title,
				'status' => 'held',
				'reason' => 'used-refurbished-or-parts',
				'did'    => array(),
			);
		}

		$brand = $this->title_brand( $title );
		$did   = array();

		if ( $brand ) {
			$attr = $this->sync_brand_attributes( $product, $brand );
			$did  = array_merge( $did, $attr );
			$tax  = $this->sync_brand_taxonomies( $product_id, $brand );
			$did  = array_merge( $did, $tax );
			$cats = $this->repair_categories( $product_id, $brand, $title );
			$did  = array_merge( $did, $cats );
			$tags = $this->repair_stale_tags( $product_id, $brand );
			$did  = array_merge( $did, $tags );
		}

		$did = array_values( array_unique( array_filter( $did ) ) );
		if ( $did ) {
			clean_post_cache( $product_id );
			if ( function_exists( 'wc_delete_product_transients' ) ) {
				wc_delete_product_transients( $product_id );
			}
			do_action( 'woocommerce_update_product', $product_id );
		}

		return array(
			'id'     => $product_id,
			'title'  => $title,
			'brand'  => $brand ? $brand : 'Unknown',
			'status' => $did ? 'repaired' : 'clean',
			'did'    => $did,
		);
	}

	/**
	 * Brands Product Scout already treats as supported local evidence.
	 *
	 * @return string[]
	 */
	public static function supported_brands() {
		return array(
			'Apple',
			'Samsung',
			'Sony',
			'Bose',
			'DJI',
			'Google',
			'Microsoft',
			'Dell',
			'HP',
			'Lenovo',
			'Asus',
			'Acer',
			'Alienware',
			'Razer',
			'Logitech',
			'Anker',
			'JBL',
			'Beats',
			'Sennheiser',
			'Shokz',
			'SHOKZ',
			'Fitbit',
			'Garmin',
			'Canon',
			'Nikon',
			'GoPro',
			'AKASO',
			'Hisense',
			'TCL',
			'LG',
			'Panasonic',
			'Philips',
			'NETGEAR',
			'Amazon',
			'BAGSMART',
			'BENFEI',
			'tomtoc',
			'Lamicall',
			'FoldWise',
			'CyberPower',
			'Ruko',
			'Potensic',
			'Bwine',
			'JMGO',
			'PUTRIMS',
			'SYLVOX',
			'Ikarao',
			'ApoloSign',
			'Seiko',
			'SEIKO',
			'Rolex',
			'Omega',
			'Soundcore',
		);
	}

	/**
	 * @param string $title Title.
	 * @return string
	 */
	public function title_brand( $title ) {
		$title = $this->plain( $title );
		if ( '' === $title ) {
			return '';
		}
		$brands = self::supported_brands();
		usort(
			$brands,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		foreach ( $brands as $brand ) {
			if ( preg_match( '/^' . preg_quote( $brand, '/' ) . '\b/i', $title ) ) {
				return $this->canonical_brand( $brand );
			}
		}
		$head = substr( $title, 0, 48 );
		foreach ( $brands as $brand ) {
			if ( preg_match( '/\b' . preg_quote( $brand, '/' ) . '\b/i', $head ) ) {
				return $this->canonical_brand( $brand );
			}
		}
		return '';
	}

	/**
	 * @param string $brand Brand.
	 * @return string
	 */
	private function canonical_brand( $brand ) {
		$map = array(
			'SHOKZ'  => 'Shokz',
			'SEIKO'  => 'Seiko',
			'soundcore' => 'Anker',
			'Soundcore' => 'Anker',
		);
		return isset( $map[ $brand ] ) ? $map[ $brand ] : $brand;
	}

	/**
	 * @param string $text Text.
	 * @return bool
	 */
	private function is_hard_risk( $text ) {
		return (bool) preg_match( '/\b(renewed|refurbished|open[- ]box|for parts|used\b)/i', $text );
	}

	/**
	 * Align Brand / Manufacturer attributes with the title brand.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $brand   Brand.
	 * @return string[]
	 */
	private function sync_brand_attributes( $product, $brand ) {
		$did = array();
		foreach ( array( 'pa_brand', 'pa_manufacturer' ) as $tax ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$current = wp_get_object_terms( $product->get_id(), $tax, array( 'fields' => 'names' ) );
			if ( is_wp_error( $current ) ) {
				continue;
			}
			$names = array_map( array( $this, 'plain' ), (array) $current );
			if ( in_array( $brand, $names, true ) && 1 === count( $names ) ) {
				continue;
			}
			if ( $this->set_term_attribute( $product, $tax, $brand ) ) {
				$did[] = 'attribute:' . $tax . '=' . $brand;
			}
		}

		$attrs   = $product->get_attributes();
		$changed = false;
		if ( is_array( $attrs ) ) {
			foreach ( $attrs as $key => $attr ) {
				if ( ! is_object( $attr ) || ! method_exists( $attr, 'get_name' ) ) {
					continue;
				}
				if ( method_exists( $attr, 'is_taxonomy' ) && $attr->is_taxonomy() ) {
					continue;
				}
				$name = strtolower( $this->plain( $attr->get_name() ) );
				if ( ! in_array( $name, array( 'brand', 'manufacturer' ), true ) ) {
					continue;
				}
				$options = $attr->get_options();
				$first   = $this->plain( is_array( $options ) && $options ? (string) reset( $options ) : '' );
				if ( 0 === strcasecmp( $first, $brand ) ) {
					continue;
				}
				$attr->set_options( array( $brand ) );
				$attrs[ $key ] = $attr;
				$changed       = true;
				$did[]         = 'custom-attribute:' . $name . '=' . $brand;
			}
		}
		if ( $changed ) {
			$product->set_attributes( $attrs );
			$product->save();
		}

		$ensure = $this->ensure_brand_attribute( $product, $brand );
		if ( $ensure ) {
			$did[] = $ensure;
		}
		return $did;
	}

	/**
	 * Add a visible Brand attribute when the product has none.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $brand   Brand.
	 * @return string
	 */
	private function ensure_brand_attribute( $product, $brand ) {
		if ( taxonomy_exists( 'pa_brand' ) ) {
			$names = wp_get_object_terms( $product->get_id(), 'pa_brand', array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $names ) && $names ) {
				return '';
			}
		}
		$attrs = $product->get_attributes();
		if ( is_array( $attrs ) ) {
			foreach ( $attrs as $attr ) {
				if ( ! is_object( $attr ) || ! method_exists( $attr, 'get_name' ) ) {
					continue;
				}
				$name = strtolower( $this->plain( $attr->get_name() ) );
				if ( 'brand' === $name || 'pa_brand' === $name ) {
					return '';
				}
			}
		}
		if ( ! class_exists( 'WC_Product_Attribute' ) ) {
			return '';
		}
		if ( ! is_array( $attrs ) ) {
			$attrs = array();
		}
		$attribute = new WC_Product_Attribute();
		$attribute->set_id( 0 );
		$attribute->set_name( 'Brand' );
		$attribute->set_options( array( $brand ) );
		$attribute->set_visible( true );
		$attribute->set_variation( false );
		$attrs['brand'] = $attribute;
		$product->set_attributes( $attrs );
		$product->save();
		return 'added-brand-attribute=' . $brand;
	}

	/**
	 * @param WC_Product $product  Product.
	 * @param string     $taxonomy Taxonomy.
	 * @param string     $value    Value.
	 * @return bool
	 */
	private function set_term_attribute( $product, $taxonomy, $value ) {
		$term = term_exists( $value, $taxonomy );
		if ( ! $term ) {
			$term = wp_insert_term( $value, $taxonomy );
		}
		if ( is_wp_error( $term ) ) {
			return false;
		}
		$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
		$set     = wp_set_object_terms( $product->get_id(), array( $term_id ), $taxonomy, false );
		if ( is_wp_error( $set ) ) {
			return false;
		}

		$attrs = $product->get_attributes();
		if ( ! is_array( $attrs ) ) {
			$attrs = array();
		}
		if ( empty( $attrs[ $taxonomy ] ) && class_exists( 'WC_Product_Attribute' ) ) {
			$attribute = new WC_Product_Attribute();
			$attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
			$attribute->set_name( $taxonomy );
			$attribute->set_options( array( $term_id ) );
			$attribute->set_visible( true );
			$attribute->set_variation( false );
			$attrs[ $taxonomy ] = $attribute;
			$product->set_attributes( $attrs );
			$product->save();
		}
		return true;
	}

	/**
	 * @param int    $product_id Product ID.
	 * @param string $brand      Brand.
	 * @return string[]
	 */
	private function sync_brand_taxonomies( $product_id, $brand ) {
		$did  = array();
		$taxs = array( 'product_brand', 'pwb-brand', 'brand' );
		foreach ( $taxs as $tax ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$current = wp_get_object_terms( $product_id, $tax, array( 'fields' => 'names' ) );
			if ( is_wp_error( $current ) ) {
				continue;
			}
			$names = array_map( array( $this, 'plain' ), (array) $current );
			if ( in_array( $brand, $names, true ) && 1 === count( $names ) ) {
				continue;
			}
			$term = term_exists( $brand, $tax );
			if ( ! $term ) {
				$term = wp_insert_term( $brand, $tax );
			}
			if ( is_wp_error( $term ) ) {
				continue;
			}
			$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
			$set     = wp_set_object_terms( $product_id, array( $term_id ), $tax, false );
			if ( ! is_wp_error( $set ) ) {
				$did[] = 'taxonomy:' . $tax . '=' . $brand;
			}
		}
		return $did;
	}

	/**
	 * Remove Apple Watches / Samsung Watches / MacBooks leftovers that Product Scout caps at 94.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $brand      Brand.
	 * @param string $title      Title.
	 * @return string[]
	 */
	private function repair_categories( $product_id, $brand, $title ) {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}
		$terms = wp_get_object_terms( $product_id, 'product_cat' );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$is_watch   = (bool) preg_match( '/\b(watch|smartwatch)\b/i', $title ) && ! preg_match( '/\b(earbuds?|airpods|headphones?|buds)\b/i', $title );
		$is_apple_w = $is_watch && ( 0 === strcasecmp( $brand, 'Apple' ) );
		$is_sam_w   = $is_watch && ( 0 === strcasecmp( $brand, 'Samsung' ) );
		$is_mac     = (bool) preg_match( '/\b(macbook|imac|mac mini|mac studio|mac pro)\b/i', $title ) && ( 0 === strcasecmp( $brand, 'Apple' ) );
		$is_laptop  = (bool) preg_match( '/\b(laptop|notebook|macbook)\b/i', $title ) && ! preg_match( '/\b(stand|hub|dock|organizer|sleeve|bag|cleaner|riser)\b/i', $title );

		$remove = array();
		foreach ( $terms as $term ) {
			$label = strtolower( $this->plain( $term->name ) . ' ' . $term->slug );
			$label = str_replace( array( '&amp;', '+' ), ' ', $label );
			if ( ! $is_apple_w && preg_match( '/apple[- ]watches?\b/', $label ) ) {
				$remove[] = (int) $term->term_id;
			}
			if ( ! $is_sam_w && preg_match( '/samsung[- ]watches?\b/', $label ) ) {
				$remove[] = (int) $term->term_id;
			}
			if ( ! $is_mac && ! $is_laptop && preg_match( '/\bmacbooks?\b|apple[- ]laptops?\b/', $label ) ) {
				$remove[] = (int) $term->term_id;
			}
		}
		$remove = array_values( array_unique( $remove ) );
		if ( ! $remove ) {
			return array();
		}
		$left = array();
		foreach ( $terms as $term ) {
			if ( ! in_array( (int) $term->term_id, $remove, true ) ) {
				$left[] = (int) $term->term_id;
			}
		}
		if ( ! $left ) {
			return array();
		}
		$set = wp_set_object_terms( $product_id, $left, 'product_cat', false );
		if ( is_wp_error( $set ) ) {
			return array();
		}
		return array( 'removed-conflicting-categories:' . implode( ',', $remove ) );
	}

	/**
	 * Drop manufacturer tags that disagree with the supported brand.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $brand      Brand.
	 * @return string[]
	 */
	private function repair_stale_tags( $product_id, $brand ) {
		if ( ! taxonomy_exists( 'product_tag' ) ) {
			return array();
		}
		$tags = wp_get_object_terms( $product_id, 'product_tag' );
		if ( is_wp_error( $tags ) || empty( $tags ) ) {
			return array();
		}
		$brands  = self::supported_brands();
		$keep    = array();
		$removed = array();
		foreach ( $tags as $tag ) {
			$name = $this->plain( $tag->name );
			$hit  = '';
			foreach ( $brands as $other ) {
				if ( 0 === strcasecmp( $name, $other ) || 0 === strcasecmp( $tag->slug, sanitize_title( $other ) ) ) {
					$hit = $this->canonical_brand( $other );
					break;
				}
			}
			if ( $hit && 0 !== strcasecmp( $hit, $brand ) ) {
				$removed[] = $name;
				continue;
			}
			$keep[] = (int) $tag->term_id;
		}
		if ( ! $removed ) {
			return array();
		}
		wp_set_object_terms( $product_id, $keep, 'product_tag', false );
		return array( 'removed-stale-brand-tags:' . implode( ',', $removed ) );
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	public function plain( $text ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/[\x{200B}-\x{200F}\x{FEFF}]/u', '', $text );
		$text = str_replace( "\xC2\xA0", ' ', $text );
		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * @param array $result Result.
	 */
	private function push_log( $result ) {
		$log = get_option( self::OPTION_LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift(
			$log,
			array(
				'at'       => $result['at'],
				'scanned'  => $result['scanned'],
				'repaired' => $result['repaired'],
				'held'     => $result['held'],
				'clean'    => $result['clean'],
				'cursor'   => $result['cursor'],
			)
		);
		update_option( self::OPTION_LOG, array_slice( $log, 0, 20 ), false );
	}

	/**
	 * LiteSpeed / WooCommerce object cache only. Does not call Hostinger CDN.
	 */
	private function flush_catalog_caches() {
		if ( class_exists( 'WC_Cache_Helper' ) && method_exists( 'WC_Cache_Helper', 'invalidate_cache_group' ) ) {
			WC_Cache_Helper::invalidate_cache_group( 'product' );
		}
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
		}
		if ( class_exists( '\LiteSpeed\Purge' ) && method_exists( '\LiteSpeed\Purge', 'purge_all' ) ) {
			\LiteSpeed\Purge::purge_all();
		}
	}

	/**
	 * @return array
	 */
	public static function last() {
		$last = get_option( self::OPTION_LAST, array() );
		return is_array( $last ) ? $last : array();
	}

	/**
	 * @return array
	 */
	public static function log() {
		$log = get_option( self::OPTION_LOG, array() );
		return is_array( $log ) ? $log : array();
	}
}
