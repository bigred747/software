<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Amazon AI: Associates process learning. One catalog item per cycle.
 * Official Creators API only. Never scrapes amazon.com. Never rewrites
 * affiliate URLs. Never invents ratings. Never publishes.
 */
class Luxe_Score_Repair_Amazon {

	const TAG            = 'luxetrendse0f-20';
	const MARKET         = 'www.amazon.com';
	const OPTION_LAST    = 'luxe_score_repair_amazon_last';
	const OPTION_LOG     = 'luxe_score_repair_amazon_log';
	const OPTION_CURSOR  = 'luxe_score_repair_amazon_cursor';
	const LOCK           = 'luxe_score_repair_amazon_lock';
	const CRON           = 'luxe_score_repair_amazon';
	const INTERVAL       = 300;

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
	 * @param string $tag Tag.
	 * @return string
	 */
	public static function sanitize_tag( $tag ) {
		$tag = strtolower( trim( (string) $tag ) );
		if ( ! preg_match( '/^[a-z0-9-]{3,40}$/', $tag ) ) {
			return self::TAG;
		}
		return $tag;
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	public static function extract_asin( $text ) {
		$text = strtoupper( trim( (string) $text ) );
		if ( preg_match( '/\b(B0[A-Z0-9]{8})\b/', $text, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '#/(?:DP|GP/PRODUCT)/([A-Z0-9]{10})(?:[/?]|$)#', $text, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	public static function extract_tag( $url ) {
		if ( function_exists( 'wp_parse_url' ) ) {
			$parts = wp_parse_url( (string) $url );
		} else {
			$parts = parse_url( (string) $url );
		}
		if ( ! is_array( $parts ) || empty( $parts['query'] ) ) {
			return '';
		}
		$params = array();
		parse_str( (string) $parts['query'], $params );
		return isset( $params['tag'] ) ? strtolower( trim( (string) $params['tag'] ) ) : '';
	}

	/**
	 * @param string $url URL.
	 * @param string $want Wanted tag.
	 * @return bool
	 */
	public static function tag_ok( $url, $want = self::TAG ) {
		$want = self::sanitize_tag( $want );
		$got  = self::extract_tag( $url );
		return ( $want === $got );
	}

	/**
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function extract_amazon_urls( $text ) {
		if ( ! preg_match_all( '/https?:\/\/(?:www\.)?amazon\.[^"\'\s<]+/i', (string) $text, $m ) ) {
			return array();
		}
		$out = array();
		foreach ( $m[0] as $url ) {
			$out[] = $url;
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Identical Amazon URL set. Used to prove we did not rewrite.
	 *
	 * @param string[] $before Before.
	 * @param string[] $after  After.
	 * @return bool
	 */
	public static function urls_untouched( $before, $after ) {
		$before = array_values( array_unique( array_map( 'strval', (array) $before ) ) );
		$after  = array_values( array_unique( array_map( 'strval', (array) $after ) ) );
		sort( $before );
		sort( $after );
		return $before === $after;
	}

	/**
	 * Refuse PA-API v5, amazon.com HTML scrapes, and unofficial hosts.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function may_request( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || ! preg_match( '#^https://#i', $url ) ) {
			return false;
		}
		if ( preg_match( '/paapi5|webservices\.amazon\.com|nulled|scrape/i', $url ) ) {
			return false;
		}
		if ( preg_match( '#^https://api\.amazon\.com/auth/o2/token/?$#i', $url ) ) {
			return true;
		}
		if ( preg_match( '#^https://creatorsapi\.amazon/catalog/v1/(getItems|searchItems)/?$#i', $url ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param array $facts Item facts.
	 * @return array
	 */
	public static function audit_item( $facts ) {
		$tag  = isset( $facts['want_tag'] ) ? self::sanitize_tag( $facts['want_tag'] ) : self::TAG;
		$url  = isset( $facts['url'] ) ? (string) $facts['url'] : '';
		$asin = isset( $facts['asin'] ) ? strtoupper( (string) $facts['asin'] ) : '';
		if ( ! $asin && $url ) {
			$asin = self::extract_asin( $url );
		}
		$has_url   = (bool) $url;
		$tag_pass  = $has_url ? self::tag_ok( $url, $tag ) : true;
		$preserved = ! array_key_exists( 'urls_after', $facts ) || self::urls_untouched(
			isset( $facts['urls_before'] ) ? $facts['urls_before'] : array(),
			isset( $facts['urls_after'] ) ? $facts['urls_after'] : array()
		);
		return array(
			'asin'       => $asin,
			'tag'        => $tag,
			'url'        => $url,
			'tag_ok'     => $tag_pass,
			'asin_ok'    => (bool) $asin,
			'preserved'  => $preserved,
			'ratings'    => empty( $facts['invented_rating'] ),
			'api_mode'   => isset( $facts['api_mode'] ) ? (string) $facts['api_mode'] : 'local',
			'title'      => isset( $facts['title'] ) ? (string) $facts['title'] : '',
		);
	}

	/**
	 * @param array $state Board state.
	 * @return array
	 */
	public static function build( $state ) {
		$armed     = ! empty( $state['armed'] );
		$tag       = isset( $state['tag'] ) ? self::sanitize_tag( $state['tag'] ) : self::TAG;
		$tag_ok    = ! array_key_exists( 'tag_ok', $state ) || ! empty( $state['tag_ok'] );
		$asin      = isset( $state['asin'] ) ? (string) $state['asin'] : '';
		$asin_ok   = ! empty( $state['asin_ok'] ) || (bool) $asin;
		$waiting   = ! empty( $state['waiting'] );
		$preserved = ! array_key_exists( 'preserved', $state ) || ! empty( $state['preserved'] );
		$api_mode  = isset( $state['api_mode'] ) ? (string) $state['api_mode'] : 'local';
		$api_ok    = ( 'blocked' !== $api_mode );
		$creds     = ! empty( $state['has_creds'] );
		$signals   = array(
			self::row( 'armed', 'Amazon AI armed', $armed, $armed ? '24/7 Associates learning. One item per cycle.' : 'Amazon AI is off.' ),
			self::row( 'tag', 'Partner tag lock', $tag_ok, $tag_ok ? ( 'tag=' . $tag ) : ( 'Last Amazon URL is missing tag=' . $tag . '. SEO plugin did not rewrite it.' ) ),
			self::row( 'asin', 'ASIN on learned item', $waiting || $asin_ok, $asin_ok ? ( 'ASIN ' . $asin ) : ( $waiting ? 'Waiting for the first catalog item.' : 'No ASIN on the last Amazon URL.' ) ),
			self::row( 'urls', 'Amazon URLs never rewritten', $preserved, 'Affiliate links stay exactly as stored. Product Scout still owns catalog repair.' ),
			self::row( 'ratings', 'No invented ratings', true, 'Amazon AI never writes stars, seller, or warranty.' ),
			self::row( 'publish', 'Never publishes', true, 'Publication status is always preserved. Audit-only on live bodies.' ),
			self::row( 'official', 'Official Creators API only', $api_ok, 'PA-API v5 is retired. No amazon.com HTML scrape. HTTPS creatorsapi.amazon only.' ),
			self::row( 'creds', 'Creators API credentials', true, $creds ? 'Credential ID present. Live GetItems is audit-only.' : 'Local audit is on. Optional: Associates Central → Tools → Creators API.' ),
			self::row( 'market', 'Marketplace www.amazon.com', true, 'US Associates store. Partner tag ' . $tag . '.' ),
			self::row( 'cycle', 'One Amazon item per cycle', $armed, '5-minute WP-Cron on Luxe SEO. Never on a shopper page view.' ),
		);
		$green = 0;
		foreach ( $signals as $signal ) {
			if ( 'green' === $signal['level'] ) {
				$green++;
			}
		}
		$total = count( $signals );
		return array(
			'at'       => time(),
			'green'    => $green,
			'total'    => $total,
			'signals'  => $signals,
			'score_10' => $total ? round( 10 * ( $green / $total ), 1 ) : 0,
			'band'     => ( $green === $total ) ? 'green' : 'needs-amazon-review',
			'asin'     => $asin,
			'tag'      => $tag,
			'url'      => isset( $state['url'] ) ? (string) $state['url'] : '',
			'title'    => isset( $state['title'] ) ? (string) $state['title'] : '',
			'id'       => isset( $state['id'] ) ? (int) $state['id'] : 0,
			'api_mode' => $api_mode,
			'source'   => isset( $state['source'] ) ? (string) $state['source'] : 'cron',
		);
	}

	/**
	 * @param string $id     ID.
	 * @param string $label  Label.
	 * @param bool   $pass   Pass.
	 * @param string $detail Detail.
	 * @return array
	 */
	public static function row( $id, $label, $pass, $detail ) {
		return array(
			'id'     => $id,
			'label'  => $label,
			'level'  => $pass ? 'green' : 'red',
			'detail' => $detail,
		);
	}

	/**
	 * Register 5-minute Amazon AI cron and the admin cycle button.
	 * Never writes Amazon URLs on a shopper page.
	 */
	public function boot() {
		add_filter( 'cron_schedules', array( $this, 'schedules' ) );
		add_action( self::CRON, array( $this, 'cron_cycle' ) );
		add_action( 'init', array( $this, 'ensure_cron' ), 51 );
		add_action( 'init', array( $this, 'maybe_spawn' ), 61 );
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
		$schedules['lsr_amazon_five'] = array(
			'interval' => self::INTERVAL,
			'display'  => __( 'Every 5 minutes (Amazon AI)', 'luxe-score-repair' ),
		);
		return $schedules;
	}

	/**
	 * Schedule Amazon AI. Do not rewrite URLs on activate.
	 */
	public static function activate() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON );
		}
		if ( function_exists( 'wp_next_scheduled' ) && function_exists( 'wp_schedule_event' ) && ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 90, 'lsr_amazon_five', self::CRON );
		}
	}

	/**
	 * Clear Amazon AI cron only.
	 */
	public static function deactivate() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON );
		}
	}

	/**
	 * Keep the 5-minute Amazon loop registered.
	 */
	public function ensure_cron() {
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'amazon_ai' ) ) {
			return;
		}
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 60, 'lsr_amazon_five', self::CRON );
		}
	}

	/**
	 * Ping WP-Cron in the background. Never audits on the public HTML request.
	 */
	public function maybe_spawn() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'amazon_ai' ) ) {
			return;
		}
		if ( get_transient( 'lsr_amazon_spawn' ) ) {
			return;
		}
		set_transient( 'lsr_amazon_spawn', 1, 60 );
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * 5-minute catalog audit.
	 */
	public function cron_cycle() {
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'amazon_ai' ) ) {
			return;
		}
		try {
			$this->cycle_one( 'cron' );
		} catch ( Exception $e ) {
			error_log( 'Luxe SEO Amazon AI: ' . $e->getMessage() );
		} catch ( Throwable $e ) {
			error_log( 'Luxe SEO Amazon AI: ' . $e->getMessage() );
		}
	}

	/**
	 * Admin button.
	 */
	public function maybe_manual() {
		if ( empty( $_POST['luxe_score_repair_amazon_one'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_score_repair_learn' );
		delete_transient( self::LOCK );
		$result = $this->cycle_one( 'manual' );
		$green  = isset( $result['green'] ) ? (int) $result['green'] : 0;
		$total  = isset( $result['total'] ) ? (int) $result['total'] : 0;
		$asin   = isset( $result['asin'] ) ? (string) $result['asin'] : '';
		add_settings_error(
			'luxe_score_repair',
			'amazon',
			sprintf(
				/* translators: 1: asin, 2: green, 3: total */
				__( 'Amazon AI cycle finished. ASIN %1$s. %2$d / %3$d Amazon signals green. URLs not rewritten. Status preserved.', 'luxe-score-repair' ),
				$asin ? $asin : 'none',
				$green,
				$total
			),
			$green === $total && $total > 0 ? 'updated' : 'notice-warning'
		);
	}

	/**
	 * One catalog item. Nested calls skip the lock (SEO learning already holds one).
	 *
	 * @param string $source Source.
	 * @param bool   $nested Nested.
	 * @return array
	 */
	public function cycle_one( $source = 'cron', $nested = false ) {
		if ( class_exists( 'Luxe_Score_Repair_Plugin' ) && ! Luxe_Score_Repair_Plugin::instance()->enabled( 'amazon_ai' ) ) {
			$board = self::build(
				array(
					'armed'     => false,
					'tag'       => self::wanted_tag(),
					'waiting'   => true,
					'preserved' => true,
					'api_mode'  => 'local',
					'source'    => $source,
				)
			);
			update_option( self::OPTION_LAST, $board, false );
			return $board;
		}
		if ( ! $nested && get_transient( self::LOCK ) ) {
			$last = self::last();
			return $last ? $last : array();
		}
		if ( ! $nested ) {
			set_transient( self::LOCK, 1, 50 );
		}

		$before = $this->collect_item();
		$urls   = isset( $before['urls'] ) ? $before['urls'] : array();
		$url    = isset( $urls[0] ) ? (string) $urls[0] : '';
		$asin   = isset( $before['asin'] ) ? (string) $before['asin'] : '';
		if ( ! $asin && $url ) {
			$asin = self::extract_asin( $url );
		}
		$tag     = self::wanted_tag();
		$tag_ok  = $url ? self::tag_ok( $url, $tag ) : true;
		$api     = array(
			'mode'  => 'local',
			'title' => isset( $before['title'] ) ? (string) $before['title'] : '',
		);
		if ( $asin && class_exists( 'Luxe_Score_Repair_Amazon_API' ) && Luxe_Score_Repair_Amazon_API::has_credentials() ) {
			$hit = Luxe_Score_Repair_Amazon_API::get_item( $asin, $tag );
			if ( is_array( $hit ) && ! empty( $hit['asin'] ) ) {
				$api['mode']  = 'creators';
				$api['title'] = isset( $hit['title'] ) && $hit['title'] ? (string) $hit['title'] : $api['title'];
			}
		}

		$after_urls = $this->collect_item_urls( isset( $before['id'] ) ? (int) $before['id'] : 0 );
		$audit      = self::audit_item(
			array(
				'want_tag'        => $tag,
				'url'             => $url,
				'asin'            => $asin,
				'urls_before'     => $urls,
				'urls_after'      => $after_urls,
				'invented_rating' => false,
				'api_mode'        => $api['mode'],
				'title'           => $api['title'],
			)
		);
		$board = self::build(
			array(
				'armed'      => true,
				'tag'        => $tag,
				'tag_ok'     => $audit['tag_ok'],
				'asin'       => $audit['asin'],
				'asin_ok'    => $audit['asin_ok'],
				'waiting'    => empty( $before['id'] ),
				'preserved'  => $audit['preserved'],
				'api_mode'   => $audit['api_mode'],
				'has_creds'  => class_exists( 'Luxe_Score_Repair_Amazon_API' ) && Luxe_Score_Repair_Amazon_API::has_credentials(),
				'url'        => $url,
				'title'      => $api['title'],
				'id'         => isset( $before['id'] ) ? (int) $before['id'] : 0,
				'source'     => $source,
			)
		);
		update_option( self::OPTION_LAST, $board, false );
		$this->push_log( $board );
		if ( ! $nested ) {
			delete_transient( self::LOCK );
		}
		return $board;
	}

	/**
	 * @return array
	 */
	private function collect_item() {
		$cursor = (int) get_option( self::OPTION_CURSOR, 0 );
		$ids    = $this->product_ids( $cursor, 1 );
		if ( empty( $ids ) && $cursor > 0 ) {
			update_option( self::OPTION_CURSOR, 0, false );
			$cursor = 0;
			$ids    = $this->product_ids( 0, 1 );
		}
		if ( empty( $ids ) ) {
			update_option( self::OPTION_CURSOR, 0, false );
			return array(
				'id'    => 0,
				'asin'  => '',
				'urls'  => array(),
				'title' => '',
			);
		}
		$id = (int) $ids[0];
		update_option( self::OPTION_CURSOR, $cursor + 1, false );
		return array(
			'id'    => $id,
			'asin'  => $this->product_asin( $id ),
			'urls'  => $this->collect_item_urls( $id ),
			'title' => function_exists( 'get_the_title' ) ? (string) get_the_title( $id ) : '',
		);
	}

	/**
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return int[]
	 */
	private function product_ids( $offset, $limit ) {
		if ( function_exists( 'wc_get_products' ) ) {
			$ids = wc_get_products(
				array(
					'status'  => array( 'publish', 'draft', 'pending', 'private' ),
					'limit'   => (int) $limit,
					'offset'  => (int) $offset,
					'return'  => 'ids',
					'orderby' => 'ID',
					'order'   => 'DESC',
					'type'    => array( 'simple', 'variable', 'external', 'grouped' ),
				)
			);
			return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
		}
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => (int) $limit,
				'offset'         => (int) $offset,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);
		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	/**
	 * @param int $id Product ID.
	 * @return string[]
	 */
	private function collect_item_urls( $id ) {
		$id  = (int) $id;
		$raw = '';
		if ( $id && function_exists( 'get_post_meta' ) ) {
			$keys = array( '_product_url', '_amazon_url', '_amz_url', '_wzone_amazon_url', '_affiliate_url', '_laps_amazon_url' );
			foreach ( $keys as $key ) {
				$raw .= ' ' . (string) get_post_meta( $id, $key, true );
			}
		}
		if ( $id && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $id );
			if ( $product && method_exists( $product, 'get_product_url' ) ) {
				$raw .= ' ' . (string) $product->get_product_url();
			}
			if ( $product && method_exists( $product, 'get_description' ) ) {
				$raw .= ' ' . (string) $product->get_description() . ' ' . (string) $product->get_short_description();
			}
		} elseif ( $id && function_exists( 'get_post_field' ) ) {
			$raw .= ' ' . (string) get_post_field( 'post_content', $id );
		}
		return self::extract_amazon_urls( $raw );
	}

	/**
	 * @param int $id Product ID.
	 * @return string
	 */
	private function product_asin( $id ) {
		$id = (int) $id;
		if ( $id && function_exists( 'get_post_meta' ) ) {
			$keys = array( '_laps_asin', '_asin', 'asin', '_amazon_asin', '_wzone_asin' );
			foreach ( $keys as $key ) {
				$hit = self::extract_asin( (string) get_post_meta( $id, $key, true ) );
				if ( $hit ) {
					return $hit;
				}
			}
		}
		$urls = $this->collect_item_urls( $id );
		foreach ( $urls as $url ) {
			$hit = self::extract_asin( $url );
			if ( $hit ) {
				return $hit;
			}
		}
		if ( $id && function_exists( 'get_post_meta' ) ) {
			return self::extract_asin( (string) get_post_meta( $id, '_sku', true ) );
		}
		return '';
	}

	/**
	 * @return string
	 */
	public static function wanted_tag() {
		if ( class_exists( 'Luxe_Score_Repair_Plugin' ) ) {
			$settings = Luxe_Score_Repair_Plugin::instance()->settings();
			if ( ! empty( $settings['amazon_tag'] ) ) {
				return self::sanitize_tag( $settings['amazon_tag'] );
			}
		}
		return self::TAG;
	}

	/**
	 * @param array $board Board.
	 */
	private function push_log( $board ) {
		$log = get_option( self::OPTION_LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift(
			$log,
			array(
				'at'     => isset( $board['at'] ) ? $board['at'] : time(),
				'green'  => isset( $board['green'] ) ? $board['green'] : 0,
				'total'  => isset( $board['total'] ) ? $board['total'] : 0,
				'band'   => isset( $board['band'] ) ? $board['band'] : '',
				'source' => isset( $board['source'] ) ? $board['source'] : 'cron',
				'asin'   => isset( $board['asin'] ) ? $board['asin'] : '',
				'title'  => isset( $board['title'] ) ? $board['title'] : '',
				'mode'   => isset( $board['api_mode'] ) ? $board['api_mode'] : 'local',
			)
		);
		update_option( self::OPTION_LOG, array_slice( $log, 0, 30 ), false );
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
