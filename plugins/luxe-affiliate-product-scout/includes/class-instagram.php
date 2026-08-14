<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LAPS_Instagram' ) ) {
	return;
}

/**
 * Copyright-safe Instagram video scan for luxetrendsetters.com.
 *
 * Never downloads, stores, or republishes Instagram video, audio, captions,
 * or frames. Operator-pasted permalinks and notes only. Matches the local
 * WooCommerce catalog. Never publishes.
 */
class LAPS_Instagram {

	const OPTION_QUEUE   = 'laps_ig_queue';
	const OPTION_LAST    = 'laps_ig_last';
	const OPTION_LOG     = 'laps_ig_log';
	const OPTION_CURSOR  = 'laps_ig_cursor';
	const LOCK           = 'laps_ig_lock';

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
	 * Admin-only scan buttons. No public hooks.
	 */
	public function boot() {
		add_action( 'admin_init', array( $this, 'maybe_manual' ) );
	}

	/**
	 * Admin: scan queued URLs, scan the local site, or run one cycle.
	 */
	public function maybe_manual() {
		$all  = ! empty( $_POST['laps_ig_scan_all'] );
		$one  = ! empty( $_POST['laps_ig_scan_one'] );
		$site = ! empty( $_POST['laps_ig_scan_site'] );
		if ( ! $all && ! $one && ! $site ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'laps_run' );
		delete_transient( self::LOCK );
		try {
			if ( $all ) {
				$out = $this->scan_all( 'manual' );
				add_settings_error(
					'laps',
					'ig-all',
					sprintf(
						/* translators: 1: scanned, 2: videos, 3: matches */
						__( 'Instagram scan finished. %1$d permalinks, %2$d videos, %3$d catalog matches. No video copied.', 'luxe-affiliate-product-scout' ),
						(int) $out['scanned'],
						(int) $out['videos'],
						(int) $out['matches']
					),
					'updated'
				);
				return;
			}
			if ( $site ) {
				$out = $this->scan_site( 'manual' );
				add_settings_error(
					'laps',
					'ig-site',
					sprintf(
						/* translators: count */
						__( 'Local site Instagram scan finished. %d permalinks already on luxetrendsetters.com. Nothing downloaded.', 'luxe-affiliate-product-scout' ),
						(int) $out['scanned']
					),
					'updated'
				);
				return;
			}
			$out = $this->cycle_one( 'manual' );
			add_settings_error(
				'laps',
				'ig-one',
				sprintf(
					/* translators: url */
					__( 'One Instagram permalink scanned: %s. Video was not downloaded.', 'luxe-affiliate-product-scout' ),
					isset( $out['url'] ) ? $out['url'] : 'none'
				),
				'updated'
			);
		} catch ( Exception $e ) {
			add_settings_error( 'laps', 'ig-err', $e->getMessage(), 'error' );
		} catch ( Throwable $e ) {
			add_settings_error( 'laps', 'ig-err', $e->getMessage(), 'error' );
		}
	}

	/**
	 * True for public Instagram permalinks only.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_instagram_url( $url ) {
		$parsed = self::parse( $url );
		return ! empty( $parsed['ok'] );
	}

	/**
	 * Normalize a public Instagram permalink. Rejects login, API, and CDN media URLs.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function normalize( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . ltrim( $url, '/' );
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}
		$host = strtolower( (string) $parts['host'] );
		$host = preg_replace( '/^www\./', '', $host );
		if ( ! in_array( $host, array( 'instagram.com', 'instagr.am' ), true ) ) {
			return '';
		}
		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$path = '/' . trim( $path, '/' );
		if ( '/' === $path ) {
			return 'https://www.instagram.com/';
		}
		return 'https://www.instagram.com' . $path . '/';
	}

	/**
	 * @param string $url URL.
	 * @return array
	 */
	public static function parse( $url ) {
		$norm = self::normalize( $url );
		$out  = array(
			'ok'        => false,
			'url'       => $norm,
			'type'      => '',
			'handle'    => '',
			'shortcode' => '',
			'video'     => false,
			'copy'      => 'refused',
		);
		if ( '' === $norm ) {
			return $out;
		}
		$path = (string) wp_parse_url( $norm, PHP_URL_PATH );
		$path = trim( (string) $path, '/' );
		if ( '' === $path ) {
			$out['ok']   = true;
			$out['type'] = 'home';
			return $out;
		}
		$parts = explode( '/', $path );
		$head  = strtolower( $parts[0] );
		if ( in_array( $head, array( 'reel', 'reels', 'p', 'tv' ), true ) ) {
			$code = isset( $parts[1] ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', $parts[1] ) : '';
			if ( '' === $code ) {
				return $out;
			}
			$out['ok']        = true;
			$out['type']      = ( 'p' === $head ) ? 'post' : 'video';
			$out['shortcode'] = $code;
			$out['video']     = ( 'p' !== $head );
			return $out;
		}
		$blocked = array( 'accounts', 'api', 'graphql', 'developer', 'about', 'legal', 'explore', 'stories', 'scontent', 'static' );
		if ( in_array( $head, $blocked, true ) ) {
			return $out;
		}
		if ( preg_match( '/^[A-Za-z0-9._]{1,30}$/', $parts[0] ) ) {
			$out['ok']     = true;
			$out['type']   = 'profile';
			$out['handle'] = $parts[0];
			return $out;
		}
		return $out;
	}

	/**
	 * Pull Instagram permalinks from operator text. Never fetches the network.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function extract_urls( $text ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return array();
		}
		preg_match_all( '#https?://(?:www\.)?(?:instagram\.com|instagr\.am)/[^\s<>"\']+#i', $text, $m );
		$urls = array();
		if ( ! empty( $m[0] ) ) {
			foreach ( $m[0] as $raw ) {
				$raw  = rtrim( (string) $raw, '.,);' );
				$norm = self::normalize( $raw );
				if ( $norm && self::is_instagram_url( $norm ) ) {
					$urls[ $norm ] = $norm;
				}
			}
		}
		return array_values( $urls );
	}

	/**
	 * Supported catalog brands mentioned in operator notes. Never invents a brand.
	 *
	 * @param string $text Notes.
	 * @return string[]
	 */
	public static function brands_in_text( $text ) {
		if ( ! class_exists( 'LAPS_Catalog' ) ) {
			return array();
		}
		$text = LAPS_Catalog::plain( $text );
		if ( '' === $text ) {
			return array();
		}
		$hits = array();
		foreach ( LAPS_Catalog::brands() as $brand ) {
			if ( preg_match( '/\b' . preg_quote( $brand, '/' ) . '\b/i', $text ) ) {
				$canon = LAPS_Catalog::canonical_brand( $brand );
				if ( $canon ) {
					$hits[ strtolower( $canon ) ] = $canon;
				}
			}
		}
		return array_values( $hits );
	}

	/**
	 * Hard copyright lock. Always refuses video, audio, frames, and caption scrape.
	 *
	 * @param string $url URL.
	 * @return array
	 */
	public static function copyright_lock( $url = '' ) {
		unset( $url );
		return array(
			'download_video'     => false,
			'store_video'        => false,
			'copy_frames'        => false,
			'copy_audio'         => false,
			'scrape_caption'     => false,
			'republish'          => false,
			'rewrite_amazon_url' => false,
			'reason'             => 'Original LuxeTrendsetters scan only. Instagram video is not copied.',
		);
	}

	/**
	 * Original Luxe reel studio copy. Not a third-party prompt pack.
	 *
	 * @param string $title Product title.
	 * @param string $brand Brand.
	 * @return array
	 */
	public static function studio( $title = '', $brand = '' ) {
		$title = class_exists( 'LAPS_Catalog' ) ? LAPS_Catalog::plain( $title ) : trim( wp_strip_all_tags( (string) $title ) );
		$brand = class_exists( 'LAPS_Catalog' ) ? LAPS_Catalog::plain( $brand ) : trim( wp_strip_all_tags( (string) $brand ) );
		if ( '' === $title ) {
			$title = 'this luxury tech pick';
		}
		if ( '' === $brand ) {
			$brand = 'the brand';
		}
		$disc = 'As an Amazon Associate, LuxeTrendsetters earns from qualifying purchases.';
		return array(
			array(
				'id'     => 'hook',
				'label'  => 'Original hook',
				'script' => 'Stop scrolling if you are comparing ' . $brand . ' gear in 2026. Here is the honest buyer check for ' . $title . '.',
			),
			array(
				'id'     => 'proof',
				'label'  => 'Original proof',
				'script' => 'On luxetrendsetters.com we only keep ' . $brand . ' products that already have a real model, price, and product image. No fake scores. No copied Instagram video.',
			),
			array(
				'id'     => 'fit',
				'label'  => 'Original fit',
				'script' => 'Ask three questions before you buy ' . $title . ': who is it for, what is the real tradeoff, and is the Amazon listing new rather than renewed.',
			),
			array(
				'id'     => 'cta',
				'label'  => 'Original call to action',
				'script' => 'Read the LuxeTrendsetters buyer guide, then confirm the live price on Amazon. We never auto-publish a draft from a reel.',
			),
			array(
				'id'     => 'disclosure',
				'label'  => 'Required disclosure',
				'script' => $disc . ' Always confirm final details on Amazon before you buy.',
			),
		);
	}

	/**
	 * Scan one permalink using only the URL + operator notes.
	 *
	 * @param string $url   URL.
	 * @param string $notes Operator notes.
	 * @return array
	 */
	public static function scan_item( $url, $notes = '' ) {
		$parsed = self::parse( $url );
		$lock   = self::copyright_lock( $url );
		$notes  = class_exists( 'LAPS_Catalog' ) ? LAPS_Catalog::plain( $notes ) : trim( wp_strip_all_tags( (string) $notes ) );
		$brands = self::brands_in_text( $notes );
		$products = self::local_product_hits( $notes, $brands );
		$status = 'HOLD';
		$why    = array();
		if ( empty( $parsed['ok'] ) ) {
			$why[] = 'not-a-public-instagram-permalink';
		} elseif ( 'profile' === $parsed['type'] ) {
			$status = 'REVIEW';
			$why[]  = 'profile-only-paste-reel-urls-to-scan-videos';
		} elseif ( 'video' === $parsed['type'] || 'post' === $parsed['type'] ) {
			$status = empty( $brands ) && empty( $products ) ? 'REVIEW' : 'READY';
			$why[]  = 'permalink-recorded-video-not-copied';
			if ( empty( $brands ) && empty( $products ) ) {
				$why[] = 'add-operator-notes-with-a-supported-brand';
			}
		} else {
			$why[] = 'instagram-home-is-not-a-video';
		}
		return array(
			'at'        => time(),
			'url'       => isset( $parsed['url'] ) ? $parsed['url'] : '',
			'type'      => isset( $parsed['type'] ) ? $parsed['type'] : '',
			'handle'    => isset( $parsed['handle'] ) ? $parsed['handle'] : '',
			'shortcode' => isset( $parsed['shortcode'] ) ? $parsed['shortcode'] : '',
			'video'     => ! empty( $parsed['video'] ),
			'status'    => $status,
			'brands'    => $brands,
			'products'  => $products,
			'reasons'   => $why,
			'copied'    => false,
			'lock'      => $lock,
		);
	}

	/**
	 * Local WooCommerce title hits. Never imports a product.
	 *
	 * @param string   $notes  Notes.
	 * @param string[] $brands Brands.
	 * @return array
	 */
	public static function local_product_hits( $notes, $brands ) {
		$hits = array();
		if ( ! function_exists( 'wc_get_products' ) ) {
			return $hits;
		}
		$queries = array();
		if ( $notes ) {
			$queries[] = $notes;
		}
		foreach ( (array) $brands as $brand ) {
			$queries[] = $brand;
		}
		$queries = array_slice( array_unique( array_filter( $queries ) ), 0, 3 );
		foreach ( $queries as $q ) {
			$ids = wc_get_products(
				array(
					'status'  => array( 'publish' ),
					'limit'   => 5,
					'return'  => 'ids',
					's'       => substr( (string) $q, 0, 80 ),
					'orderby' => 'date',
					'order'   => 'DESC',
					'type'    => array( 'simple', 'variable', 'external', 'grouped' ),
				)
			);
			foreach ( (array) $ids as $id ) {
				$id = (int) $id;
				if ( $id < 1 || isset( $hits[ $id ] ) ) {
					continue;
				}
				$hits[ $id ] = array(
					'id'    => $id,
					'title' => function_exists( 'get_the_title' ) ? get_the_title( $id ) : '',
				);
			}
		}
		return array_slice( array_values( $hits ), 0, 8 );
	}

	/**
	 * Queue from settings textarea.
	 *
	 * @return string[]
	 */
	public static function queue() {
		$settings = class_exists( 'LAPS_Plugin' ) ? LAPS_Plugin::instance()->settings() : array();
		$text     = isset( $settings['ig_urls'] ) ? (string) $settings['ig_urls'] : '';
		$notes    = isset( $settings['ig_notes'] ) ? (string) $settings['ig_notes'] : '';
		$urls     = self::extract_urls( $text . "\n" . $notes );
		$stored   = get_option( self::OPTION_QUEUE, array() );
		if ( is_array( $stored ) ) {
			foreach ( $stored as $url ) {
				$norm = self::normalize( (string) $url );
				if ( $norm ) {
					$urls[] = $norm;
				}
			}
		}
		$urls = array_values( array_unique( $urls ) );
		update_option( self::OPTION_QUEUE, $urls, false );
		return $urls;
	}

	/**
	 * Scan every queued permalink plus local site embeds.
	 *
	 * @param string $source Source.
	 * @return array
	 */
	public function scan_all( $source = 'manual' ) {
		$notes = '';
		if ( class_exists( 'LAPS_Plugin' ) ) {
			$settings = LAPS_Plugin::instance()->settings();
			$notes    = isset( $settings['ig_notes'] ) ? (string) $settings['ig_notes'] : '';
		}
		$urls = self::queue();
		$site = $this->local_instagram_urls();
		foreach ( $site as $url ) {
			$urls[] = $url;
		}
		$urls    = array_values( array_unique( $urls ) );
		$rows    = array();
		$videos  = 0;
		$matches = 0;
		foreach ( $urls as $url ) {
			$row = self::scan_item( $url, $notes );
			if ( ! empty( $row['video'] ) ) {
				$videos++;
			}
			if ( ! empty( $row['products'] ) || ! empty( $row['brands'] ) ) {
				$matches++;
			}
			$rows[] = $row;
		}
		$out = array(
			'at'       => time(),
			'source'   => $source,
			'scanned'  => count( $rows ),
			'videos'   => $videos,
			'matches'  => $matches,
			'copied'   => 0,
			'rows'     => array_slice( $rows, 0, 40 ),
			'signals'  => self::signals( $rows ),
		);
		update_option( self::OPTION_LAST, $out, false );
		$this->push_log( $out );
		return $out;
	}

	/**
	 * Local WordPress content only.
	 *
	 * @param string $source Source.
	 * @return array
	 */
	public function scan_site( $source = 'manual' ) {
		$urls = $this->local_instagram_urls();
		update_option( self::OPTION_QUEUE, array_values( array_unique( array_merge( self::queue(), $urls ) ) ), false );
		return $this->scan_all( $source . '-site' );
	}

	/**
	 * One queued permalink per 24/7 cycle.
	 *
	 * @param string $source Source.
	 * @return array
	 */
	public function cycle_one( $source = 'cron' ) {
		if ( get_transient( self::LOCK ) ) {
			$last = get_option( self::OPTION_LAST, array() );
			return is_array( $last ) ? $last : array();
		}
		set_transient( self::LOCK, 1, 40 );
		$urls = self::queue();
		$out  = array(
			'at'     => time(),
			'source' => $source,
			'url'    => '',
			'status' => 'empty',
			'copied' => false,
		);
		if ( empty( $urls ) ) {
			update_option( self::OPTION_LAST, $out, false );
			delete_transient( self::LOCK );
			return $out;
		}
		$cursor = (int) get_option( self::OPTION_CURSOR, 0 );
		if ( $cursor >= count( $urls ) ) {
			$cursor = 0;
		}
		$url   = $urls[ $cursor ];
		$notes = '';
		if ( class_exists( 'LAPS_Plugin' ) ) {
			$settings = LAPS_Plugin::instance()->settings();
			$notes    = isset( $settings['ig_notes'] ) ? (string) $settings['ig_notes'] : '';
		}
		$item = self::scan_item( $url, $notes );
		update_option( self::OPTION_CURSOR, $cursor + 1, false );
		$out = array_merge( $out, $item );
		$last = get_option( self::OPTION_LAST, array() );
		if ( ! is_array( $last ) ) {
			$last = array();
		}
		$rows = isset( $last['rows'] ) && is_array( $last['rows'] ) ? $last['rows'] : array();
		array_unshift( $rows, $item );
		$last['at']      = time();
		$last['source']  = $source;
		$last['rows']    = array_slice( $rows, 0, 40 );
		$last['scanned'] = isset( $last['scanned'] ) ? max( (int) $last['scanned'], count( $last['rows'] ) ) : count( $last['rows'] );
		$last['signals'] = self::signals( $last['rows'] );
		update_option( self::OPTION_LAST, $last, false );
		$this->push_log( $last );
		delete_transient( self::LOCK );
		return $out;
	}

	/**
	 * Green signal board. All green when the copyright lock is intact.
	 *
	 * @param array $rows Rows.
	 * @return array
	 */
	public static function signals( $rows = array() ) {
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}
		$copied = 0;
		$videos = 0;
		foreach ( $rows as $row ) {
			if ( ! empty( $row['copied'] ) ) {
				$copied++;
			}
			if ( ! empty( $row['video'] ) ) {
				$videos++;
			}
		}
		$items = array(
			array( 'no_video_download', 'No Instagram video download', true, 'Scanner never fetches video bytes.' ),
			array( 'no_video_store', 'No Instagram video stored', 0 === $copied, 'Media library stays free of copied reels.' ),
			array( 'no_caption_scrape', 'No caption scrape', true, 'Only operator-pasted notes are used.' ),
			array( 'no_republish', 'Never republishes a reel', true, 'Publication status is never changed.' ),
			array( 'no_amazon_rewrite', 'Amazon URLs untouched', true, 'Affiliate links are never rewritten.' ),
			array( 'original_studio', 'Original Luxe reel studio', true, 'Studio copy is written for LuxeTrendsetters, not copied from Instagram.' ),
			array( 'thin_page_refused', 'Thin 37-word ranking trick refused', true, 'Luxe SEO keeps real buyer-guide copy. Copied thin pages stay off.' ),
			array( 'local_catalog', 'Local catalog matching', true, 'Matches WooCommerce products already on the site.' ),
			array( 'admin_only', 'Admin-only scanner', true, 'No public-page Instagram scrape on activate.' ),
			array( 'queue_ready', 'Permalink queue ready', true, $videos ? ( $videos . ' video permalinks recorded' ) : 'Paste reel URLs, then Scan all Instagram videos.' ),
		);
		$signals = array();
		$green   = 0;
		foreach ( $items as $item ) {
			$pass = ! empty( $item[2] );
			if ( $pass ) {
				$green++;
			}
			$signals[] = array(
				'id'     => $item[0],
				'label'  => $item[1],
				'level'  => $pass ? 'green' : 'red',
				'detail' => $item[3],
			);
		}
		return array(
			'green'   => $green,
			'total'   => count( $signals ),
			'signals' => $signals,
		);
	}

	/**
	 * Instagram URLs already stored in local posts. Does not call Instagram.
	 *
	 * @return string[]
	 */
	private function local_instagram_urls() {
		global $wpdb;
		$urls = array();
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_col' ) ) {
			return $urls;
		}
		$like = '%instagram.com%';
		$sql  = $wpdb->prepare(
			"SELECT post_content FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','pending','private') AND post_content LIKE %s LIMIT 80",
			$like
		);
		$cols = $wpdb->get_col( $sql );
		foreach ( (array) $cols as $html ) {
			foreach ( self::extract_urls( (string) $html ) as $url ) {
				$urls[ $url ] = $url;
			}
		}
		return array_values( $urls );
	}

	/**
	 * @param array $row Board row.
	 */
	private function push_log( $row ) {
		$log = get_option( self::OPTION_LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift(
			$log,
			array(
				'at'      => isset( $row['at'] ) ? $row['at'] : time(),
				'source'  => isset( $row['source'] ) ? $row['source'] : '',
				'scanned' => isset( $row['scanned'] ) ? $row['scanned'] : 0,
				'videos'  => isset( $row['videos'] ) ? $row['videos'] : 0,
				'matches' => isset( $row['matches'] ) ? $row['matches'] : 0,
				'copied'  => 0,
			)
		);
		update_option( self::OPTION_LOG, array_slice( $log, 0, 20 ), false );
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
