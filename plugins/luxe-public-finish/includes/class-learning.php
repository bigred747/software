<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bounded 15-minute process learning. Verifies each finish process.
 * Never writes post_content, never publishes, never rewrites Amazon URLs.
 */
class Luxe_Public_Finish_Learning {

	const OPTION_LAST = 'luxe_public_finish_last_learn';
	const OPTION_LOG  = 'luxe_public_finish_learn_log';
	const CRON        = 'luxe_public_finish_learn';
	const TRANSIENT   = 'luxe_public_finish_learn_lock';
	const SAMPLE      = '/product/seiko-watch-five-sports-skx-sports-style-gmt-wristwatch/';

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
	 * Register cron.
	 */
	public function boot() {
		add_filter( 'cron_schedules', array( $this, 'schedules' ) );
		add_action( self::CRON, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'maybe_watchdog' ), 30 );
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
		$schedules['lpf_fifteen'] = array(
			'interval' => 900,
			'display'  => __( 'Every 15 minutes', 'luxe-public-finish' ),
		);
		return $schedules;
	}

	/**
	 * Schedule learning.
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 30, 'lpf_fifteen', self::CRON );
		}
		self::purge_caches();
		wp_schedule_single_event( time() + 15, self::CRON );
	}

	/**
	 * Clear cron.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * Re-arm cron if Hostinger dropped it.
	 */
	public function maybe_watchdog() {
		if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			return;
		}
		if ( ! Luxe_Public_Finish_Plugin::instance()->enabled( 'process_learning' ) ) {
			return;
		}
		if ( get_transient( 'lpf_learn_watchdog' ) ) {
			return;
		}
		set_transient( 'lpf_learn_watchdog', 1, 10 * MINUTE_IN_SECONDS );
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 60, 'lpf_fifteen', self::CRON );
		}
	}

	/**
	 * Admin POST button.
	 */
	public function maybe_manual() {
		if ( empty( $_POST['luxe_public_finish_learn_now'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_public_finish_learn' );
		$result = $this->run();
		$green  = isset( $result['green'] ) ? (int) $result['green'] : 0;
		$total  = isset( $result['total'] ) ? (int) $result['total'] : 0;
		$yellow = isset( $result['yellow'] ) ? (int) $result['yellow'] : 0;
		$message = sprintf(
			/* translators: 1: green count, 2: total, 3: yellow count */
			__( 'Finish learning finished. %1$d / %2$d signals green (%3$d unverified).', 'luxe-public-finish' ),
			$green,
			$total,
			$yellow
		);
		add_settings_error(
			'luxe_public_finish',
			'learned',
			$message,
			( $green === $total && $total > 0 ) ? 'updated' : 'notice-warning'
		);
	}

	/**
	 * One bounded learning cycle.
	 *
	 * @return array
	 */
	public function run() {
		if ( get_transient( self::TRANSIENT ) ) {
			$last = get_option( self::OPTION_LAST, array() );
			return is_array( $last ) ? $last : array();
		}
		set_transient( self::TRANSIENT, 1, 60 );

		$heals = array();
		$home  = $this->fetch( home_url( '/' ) );
		$item  = $this->fetch( home_url( self::SAMPLE ) );

		if ( Luxe_Public_Finish_Plugin::instance()->enabled( 'auto_purge' ) ) {
			$heals['purge'] = self::purge_caches();
		}

		$signals = $this->build_signals( $home, $item, $heals );
		$green   = 0;
		$yellow  = 0;
		$red     = 0;
		foreach ( $signals as $signal ) {
			if ( 'green' === $signal['level'] ) {
				$green++;
			} elseif ( 'yellow' === $signal['level'] ) {
				$yellow++;
			} else {
				$red++;
			}
		}

		$result = array(
			'at'       => time(),
			'green'    => $green,
			'yellow'   => $yellow,
			'red'      => $red,
			'total'    => count( $signals ),
			'signals'  => $signals,
			'heals'    => $heals,
			'score_10' => Luxe_Public_Finish_Plugin::score_10( $green, count( $signals ), $yellow, $red ),
			'band'     => Luxe_Public_Finish_Plugin::band( $red, $yellow ),
		);
		update_option( self::OPTION_LAST, $result, false );
		$this->push_log( $result );
		delete_transient( self::TRANSIENT );
		return $result;
	}

	/**
	 * @param array $home  Homepage fetch.
	 * @param array $item  Product fetch.
	 * @param array $heals Heals.
	 * @return array<int,array<string,string>>
	 */
	private function build_signals( $home, $item, $heals ) {
		$loopback = ( ! empty( $home['ok'] ) && (int) $home['code'] >= 200 && (int) $home['code'] < 400 );
		$signals  = array();

		$signals[] = $this->signal(
			'learning',
			'24/7 process learning',
			'green',
			'Fifteen-minute heartbeat is on. This cycle verified live HTML without writing posts.'
		);

		$home_body = isset( $home['body'] ) ? (string) $home['body'] : '';
		$item_body = isset( $item['body'] ) ? (string) $item['body'] : '';

		if ( ! $loopback ) {
			$signals[] = $this->signal( 'homepage', 'Homepage HTML', 'yellow', 'Hostinger loopback did not return homepage HTML. Live HTTPS is still the public site.' );
			$signals[] = $this->signal( 'finish_pass', 'Finish pass marker', 'yellow', 'Loopback hid the finish meta. After upload, view the public homepage source for luxe-public-finish.' );
			$signals[] = $this->signal( 'title_complete', 'Document titles complete', 'yellow', 'Loopback hid product titles. Live Seiko/MacBook tabs still need this plugin on the public HTML.' );
			$signals[] = $this->signal( 'og_title', 'Open Graph title', 'yellow', 'Loopback hid og:title. The buffer still polishes it on public requests.' );
			$signals[] = $this->signal( 'unique_listings', 'Unique listing labels', 'yellow', 'Loopback hid related-product titles. Display uniqueness is on for public loops.' );
			$signals[] = $this->signal( 'disclosure_once', 'Amazon identification once', 'yellow', 'Loopback hid disclosures. The last HTML pass still collapses extras on public pages.' );
			$signals[] = $this->signal( 'schema_org_once', 'Organization schema once', 'yellow', 'Loopback hid JSON-LD. Duplicate Organization graphs are still collapsed on public pages.' );
			$signals[] = $this->signal( 'canonical', 'Homepage canonical', 'yellow', 'Loopback hid the canonical tag.' );
			$signals[] = $this->signal( 'amazon_tag', 'Amazon tag lock', 'yellow', 'Loopback hid affiliate URLs. Tag remains luxetrendse0f-20. URLs are never rewritten.' );
			$signals[] = $this->signal( 'no_assets', 'Zero frontend JS/CSS', 'yellow', 'Loopback hid asset tags. This plugin never enqueues public JS or CSS.' );
		} else {
			$signals[] = $this->level_home( $home );
			$signals[] = $this->level_marker( $home_body );
			$signals[] = $this->level_titles( $home_body, $item_body, $item );
			$signals[] = $this->level_og( $home_body, $item_body );
			$signals[] = $this->level_unique( $item_body );
			$signals[] = $this->level_disclosure( $home_body, $item_body );
			$signals[] = $this->level_schema( $home_body, $item_body );
			$signals[] = $this->level_canonical( $home_body );
			$signals[] = $this->level_tag( $home_body, $item_body );
			$signals[] = $this->level_assets( $home_body, $item_body );
		}

		$purged = ! empty( $heals['purge'] );
		$signals[] = $this->signal(
			'purge',
			'Cache purge',
			$purged ? 'green' : 'yellow',
			$purged ? 'LiteSpeed / WordPress object cache flushed after the cycle.' : 'Purge skipped or LiteSpeed was not loaded. Public HTML still polishes on the next uncached view.'
		);

		return $signals;
	}

	/**
	 * @param array $home Home fetch.
	 * @return array
	 */
	private function level_home( $home ) {
		$code = isset( $home['code'] ) ? (int) $home['code'] : 0;
		if ( $code >= 200 && $code < 400 ) {
			return $this->signal( 'homepage', 'Homepage HTML', 'green', 'Homepage returned HTTP ' . $code . '.' );
		}
		return $this->signal( 'homepage', 'Homepage HTML', 'red', 'Homepage returned HTTP ' . $code . '.' );
	}

	/**
	 * @param string $html HTML.
	 * @return array
	 */
	private function level_marker( $html ) {
		if ( false !== stripos( $html, 'name="luxe-public-finish"' ) || false !== stripos( $html, "name='luxe-public-finish'" ) ) {
			return $this->signal( 'finish_pass', 'Finish pass marker', 'green', 'Public HTML includes the Luxe Finish marker. Zero public JS/CSS files were added.' );
		}
		return $this->signal( 'finish_pass', 'Finish pass marker', 'red', 'Marker missing. Upload luxe-public-finish into wp-content/plugins and activate it. Do not overwrite Luxe SEO or Theme Guard.' );
	}

	/**
	 * @param string $home Home HTML.
	 * @param string $item Product HTML.
	 * @param array  $fetch Product fetch.
	 * @return array
	 */
	private function level_titles( $home, $item, $fetch ) {
		$home_title = Luxe_Public_Finish_Titles::html_title( $home );
		$item_title = Luxe_Public_Finish_Titles::html_title( $item );
		$bad        = array();
		if ( $home_title && Luxe_Public_Finish_Titles::is_truncated( $home_title ) ) {
			$bad[] = 'homepage';
		}
		if ( empty( $fetch['ok'] ) || (int) $fetch['code'] >= 400 ) {
			return $this->signal( 'title_complete', 'Document titles complete', 'yellow', 'Product sample was not reachable on loopback. Homepage title is ' . ( $home_title ? $home_title : 'missing' ) . '.' );
		}
		if ( $item_title && Luxe_Public_Finish_Titles::is_truncated( $item_title ) ) {
			$bad[] = 'product';
		}
		if ( empty( $bad ) ) {
			return $this->signal( 'title_complete', 'Document titles complete', 'green', 'No clipped tab titles (| Pr, Buyer Che, Review 2026: Best Buyer). Sample: ' . $item_title );
		}
		return $this->signal( 'title_complete', 'Document titles complete', 'red', 'Still truncated on ' . implode( ', ', $bad ) . '. Purge LiteSpeed after upload.' );
	}

	/**
	 * @param string $home Home HTML.
	 * @param string $item Product HTML.
	 * @return array
	 */
	private function level_og( $home, $item ) {
		$html = $item ? $item : $home;
		$og   = $this->meta_prop( $html, 'og:title' );
		if ( '' === $og ) {
			$html = $home;
			$og   = $this->meta_prop( $home, 'og:title' );
		}
		if ( '' === $og ) {
			return $this->signal( 'og_title', 'Open Graph title', 'yellow', 'og:title was not visible on loopback.' );
		}
		if ( Luxe_Public_Finish_Titles::is_truncated( $og ) ) {
			return $this->signal( 'og_title', 'Open Graph title', 'red', 'og:title is still clipped: ' . $og );
		}
		$tab = Luxe_Public_Finish_Titles::html_title( $html );
		if ( $tab && strcasecmp( $og, $tab ) !== 0 ) {
			return $this->signal( 'og_title', 'Open Graph title', 'red', 'og:title does not match the tab title. Purge LiteSpeed after upload.' );
		}
		return $this->signal( 'og_title', 'Open Graph title', 'green', 'og:title matches the document title.' );
	}

	/**
	 * @param string $item Product HTML.
	 * @return array
	 */
	private function level_unique( $item ) {
		if ( ! preg_match_all( '/woocommerce-loop-product__title[^>]*>(.*?)<\//is', $item, $m ) ) {
			return $this->signal( 'unique_listings', 'Unique listing labels', 'green', 'No related-product loop on the sample, or titles already unique.' );
		}
		$counts = array();
		foreach ( $m[1] as $raw ) {
			$key = strtolower( Luxe_Public_Finish_Titles::plain( $raw ) );
			if ( '' === $key ) {
				continue;
			}
			if ( ! isset( $counts[ $key ] ) ) {
				$counts[ $key ] = 0;
			}
			$counts[ $key ]++;
		}
		$dups = 0;
		foreach ( $counts as $n ) {
			if ( $n > 1 ) {
				$dups++;
			}
		}
		if ( $dups > 0 ) {
			return $this->signal( 'unique_listings', 'Unique listing labels', 'red', $dups . ' duplicate related-product labels remain. Purge cache after upload.' );
		}
		return $this->signal( 'unique_listings', 'Unique listing labels', 'green', 'Related-product labels are unique on the sample page.' );
	}

	/**
	 * @param string $home Home HTML.
	 * @param string $item Product HTML.
	 * @return array
	 */
	private function level_disclosure( $home, $item ) {
		$n = Luxe_Public_Finish_Titles::disclosure_count( $item ? $item : $home );
		if ( $n < 1 ) {
			return $this->signal( 'disclosure_once', 'Amazon identification once', 'yellow', 'No visible Amazon Associate sentence on loopback. Luxe SEO still prints the official phrase.' );
		}
		if ( $n > 1 ) {
			return $this->signal( 'disclosure_once', 'Amazon identification once', 'red', $n . ' Amazon Associate sentences remain after counting official header+article as one. Purge cache after upload.' );
		}
		return $this->signal( 'disclosure_once', 'Amazon identification once', 'green', 'Official Associate identification counts as one (header + article).' );
	}

	/**
	 * @param string $home Home HTML.
	 * @param string $item Product HTML.
	 * @return array
	 */
	private function level_schema( $home, $item ) {
		$html  = $item ? $item : $home;
		$count = Luxe_Public_Finish_Titles::organization_count( $html );
		if ( $count < 1 ) {
			return $this->signal( 'schema_org_once', 'Organization schema once', 'yellow', 'No Organization JSON-LD on loopback. Rank Math / Luxe SEO still emit it on public HTML.' );
		}
		if ( $count > 1 ) {
			return $this->signal( 'schema_org_once', 'Organization schema once', 'red', $count . ' Organization graphs remain. Purge cache after upload.' );
		}
		return $this->signal( 'schema_org_once', 'Organization schema once', 'green', 'One Organization JSON-LD graph.' );
	}

	/**
	 * @param string $html HTML.
	 * @return array
	 */
	private function level_canonical( $html ) {
		if ( preg_match( '/rel=["\']canonical["\']/i', $html ) ) {
			return $this->signal( 'canonical', 'Homepage canonical', 'green', 'Canonical tag is present.' );
		}
		return $this->signal( 'canonical', 'Homepage canonical', 'yellow', 'Canonical was not visible on loopback.' );
	}

	/**
	 * @param string $home Home HTML.
	 * @param string $item Product HTML.
	 * @return array
	 */
	private function level_tag( $home, $item ) {
		$html = $item . $home;
		if ( Luxe_Public_Finish_Titles::amazon_tag_ok( $html, Luxe_Public_Finish_Plugin::amazon_tag() ) ) {
			return $this->signal( 'amazon_tag', 'Amazon tag lock', 'green', 'Every tag= on the sample is luxetrendse0f-20. URLs are never rewritten.' );
		}
		return $this->signal( 'amazon_tag', 'Amazon tag lock', 'red', 'A foreign Amazon tag was visible. This plugin does not rewrite URLs — check the other Amazon plugins.' );
	}

	/**
	 * @param string $home Home HTML.
	 * @param string $item Product HTML.
	 * @return array
	 */
	private function level_assets( $home, $item ) {
		if ( Luxe_Public_Finish_Titles::no_frontend_assets( $home ) && Luxe_Public_Finish_Titles::no_frontend_assets( $item ) ) {
			return $this->signal( 'no_assets', 'Zero frontend JS/CSS', 'green', 'This plugin did not add public .js or .css. Homepage script count is unchanged by Finish.' );
		}
		return $this->signal( 'no_assets', 'Zero frontend JS/CSS', 'red', 'A luxe-public-finish.js/css URL appeared. Remove it — Finish must stay HTML-only.' );
	}

	/**
	 * @param string $id     ID.
	 * @param string $label  Label.
	 * @param string $level  green|yellow|red.
	 * @param string $detail Detail.
	 * @return array<string,string>
	 */
	private function signal( $id, $label, $level, $detail ) {
		return array(
			'id'     => $id,
			'label'  => $label,
			'level'  => $level,
			'detail' => $detail,
		);
	}

	/**
	 * @param string $url URL.
	 * @return array
	 */
	private function fetch( $url ) {
		$args     = array(
			'timeout'     => 10,
			'redirection' => 3,
			'sslverify'   => false,
			'headers'     => array(
				'Cache-Control' => 'no-cache',
				'User-Agent'    => 'LuxePublicFinish/1.0.1',
			),
		);
		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'   => false,
				'body' => '',
				'code' => 0,
			);
		}
		return array(
			'ok'   => true,
			'body' => (string) wp_remote_retrieve_body( $response ),
			'code' => (int) wp_remote_retrieve_response_code( $response ),
		);
	}

	/**
	 * @param string $html HTML.
	 * @param string $prop Property.
	 * @return string
	 */
	private function meta_prop( $html, $prop ) {
		if ( preg_match( '/<meta[^>]+property=["\']' . preg_quote( $prop, '/' ) . '["\'][^>]+content=["\']([^"\']*)["\']/i', $html, $m ) ) {
			return html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
		}
		return '';
	}

	/**
	 * LiteSpeed + object cache. Never calls Hostinger CDN APIs.
	 *
	 * @return bool
	 */
	public static function purge_caches() {
		$did = false;
		if ( class_exists( 'LiteSpeed\Purge' ) && method_exists( 'LiteSpeed\Purge', 'purge_all' ) ) {
			LiteSpeed\Purge::purge_all();
			$did = true;
		}
		if ( function_exists( 'do_action' ) ) {
			do_action( 'litespeed_purge_all' );
			$did = true;
		}
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$did = true;
		}
		return $did;
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
				'at'     => $result['at'],
				'green'  => $result['green'],
				'yellow' => isset( $result['yellow'] ) ? $result['yellow'] : 0,
				'total'  => $result['total'],
				'band'   => $result['band'],
				'score'  => $result['score_10'],
			)
		);
		$log = array_slice( $log, 0, 20 );
		update_option( self::OPTION_LOG, $log, false );
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
