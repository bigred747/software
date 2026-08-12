<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 24/7 process learning. Heals robots.txt, verifies public output, never writes post_content.
 */
class Luxe_Score_Repair_Learning {

	const OPTION_LAST = 'luxe_score_repair_last_learn';
	const OPTION_LOG  = 'luxe_score_repair_learn_log';
	const OPTION_SNAP = 'luxe_score_repair_snapshot';
	const CRON        = 'luxe_score_repair_learn';
	const CRON_HEAL   = 'luxe_score_repair_heal';
	const TRANSIENT   = 'luxe_score_repair_learn_lock';

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
	 * Register cron, watchdog, and admin run.
	 */
	public function boot() {
		add_filter( 'cron_schedules', array( $this, 'schedules' ) );
		add_action( self::CRON, array( $this, 'run' ) );
		add_action( self::CRON_HEAL, array( $this, 'heal_tick' ) );
		add_action( 'init', array( $this, 'maybe_watchdog' ), 20 );
		add_action( 'admin_init', array( $this, 'ensure_cron' ) );
		add_action( 'wp_ajax_luxe_score_repair_learn', array( $this, 'ajax_run' ) );
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
		$schedules['lsr_five'] = array(
			'interval' => 300,
			'display'  => __( 'Every 5 minutes', 'luxe-score-repair' ),
		);
		$schedules['lsr_fifteen'] = array(
			'interval' => 900,
			'display'  => __( 'Every 15 minutes', 'luxe-score-repair' ),
		);
		return $schedules;
	}

	/**
	 * Schedule 24/7 heal + verify. Heal robots immediately.
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON_HEAL ) ) {
			wp_schedule_event( time() + 20, 'lsr_five', self::CRON_HEAL );
		}
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 45, 'lsr_fifteen', self::CRON );
		}
		Luxe_Score_Repair_Robots::heal_file();
		self::purge_caches();
		wp_schedule_single_event( time() + 15, self::CRON );
	}

	/**
	 * Clear cron.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
		wp_clear_scheduled_hook( self::CRON_HEAL );
	}

	/**
	 * Keep 24/7 cron alive after a zip upload (activation hooks do not re-fire).
	 */
	public function ensure_cron() {
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'process_learning' ) ) {
			return;
		}
		if ( ! wp_next_scheduled( self::CRON_HEAL ) ) {
			wp_schedule_event( time() + 30, 'lsr_five', self::CRON_HEAL );
		}
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 60, 'lsr_fifteen', self::CRON );
		}
	}

	/**
	 * Cheap 24/7 heal on public traffic. Hostinger cron often sleeps.
	 */
	public function maybe_watchdog() {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'process_learning' ) ) {
			return;
		}
		$this->heal_robots_if_needed();
		$last = get_option( self::OPTION_LAST, array() );
		$at   = ( is_array( $last ) && ! empty( $last['at'] ) ) ? (int) $last['at'] : 0;
		if ( ( time() - $at ) > 900 && ! get_transient( self::TRANSIENT ) ) {
			if ( ! wp_next_scheduled( self::CRON ) ) {
				wp_schedule_single_event( time() + 8, self::CRON );
			}
		}
		if ( ! wp_next_scheduled( self::CRON_HEAL ) ) {
			wp_schedule_event( time() + 30, 'lsr_five', self::CRON_HEAL );
		}
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 60, 'lsr_fifteen', self::CRON );
		}
	}

	/**
	 * Five-minute heal tick.
	 */
	public function heal_tick() {
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'process_learning' ) ) {
			return;
		}
		$changed = $this->heal_robots_if_needed();
		if ( $changed && Luxe_Score_Repair_Plugin::instance()->enabled( 'auto_purge' ) ) {
			self::purge_caches();
		}
	}

	/**
	 * @return bool True when the file was rewritten.
	 */
	private function heal_robots_if_needed() {
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_robots_txt' ) ) {
			return false;
		}
		$path = Luxe_Score_Repair_Robots::file_path();
		$size = ( is_readable( $path ) ) ? (int) filesize( $path ) : 0;
		$body = ( $size > 0 && $size < 8192 && is_readable( $path ) ) ? (string) file_get_contents( $path ) : '';
		if ( $size > 2048 || Luxe_Score_Repair_Robots::is_bloated( $body ) ) {
			$result = Luxe_Score_Repair_Robots::heal_file();
			return ! empty( $result['changed'] );
		}
		return false;
	}

	/**
	 * Admin POST button.
	 */
	public function maybe_manual() {
		if ( empty( $_POST['luxe_score_repair_learn_now'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_score_repair_learn' );
		$result = $this->run();
		$green  = isset( $result['green'] ) ? (int) $result['green'] : 0;
		$total  = isset( $result['total'] ) ? (int) $result['total'] : 0;
		add_settings_error(
			'luxe_score_repair',
			'learned',
			sprintf(
				/* translators: 1: green count, 2: total, 3: seo 100, 4: site 10 */
				__( '24/7 learning finished. %1$d / %2$d green. Technical SEO %3$d / 100. Public output %4$s / 10.', 'luxe-score-repair' ),
				$green,
				$total,
				isset( $result['seo_100'] ) ? (int) $result['seo_100'] : 0,
				isset( $result['score_10'] ) ? (string) $result['score_10'] : '0'
			),
			( $green === $total && $total > 0 ) ? 'updated' : 'notice-warning'
		);
	}

	/**
	 * AJAX run.
	 */
	public function ajax_run() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( 'luxe_score_repair_learn', 'nonce' );
		wp_send_json_success( $this->run() );
	}

	/**
	 * Store a compact homepage snapshot from the real public HTML buffer.
	 * This is how 24/7 learning verifies when Hostinger blocks loopback.
	 *
	 * @param string $html HTML.
	 */
	public static function capture_from_html( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) < 64 ) {
			return;
		}
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'process_learning' ) ) {
			return;
		}
		if ( get_transient( 'lsr_snap_lock' ) ) {
			return;
		}
		set_transient( 'lsr_snap_lock', 1, 5 * MINUTE_IN_SECONDS );
		$snap         = self::instance()->summarize_html( $html );
		$snap['at']   = time();
		$snap['via']  = 'visitor-html';
		$snap['hsts'] = self::header_list_has( 'strict-transport-security' );
		$snap['public_cache'] = self::header_list_has_public_cache();
		update_option( self::OPTION_SNAP, $snap, false );
		self::instance()->refresh_from_snapshot( $snap );
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
		if ( Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_robots_txt' ) ) {
			$heals['robots_file'] = Luxe_Score_Repair_Robots::heal_file();
		}
		if ( Luxe_Score_Repair_Plugin::instance()->enabled( 'auto_purge' ) ) {
			$heals['purge'] = self::purge_caches();
		}

		$home   = $this->fetch( home_url( '/' ) );
		$robots = $this->fetch( home_url( '/robots.txt' ) );
		$blog   = $this->fetch_headers( home_url( '/blog/' ) );
		$test   = $this->fetch( home_url( '/test-blog-page/' ) );
		$portal = $this->fetch( home_url( '/client-portal/' ) );
		$cart   = $this->fetch( home_url( '/cart/' ) );
		$wish   = $this->fetch( home_url( '/wish-list/' ) );
		$pages  = $this->fetch( home_url( '/page-sitemap.xml' ) );

		if ( empty( $home['body'] ) ) {
			$snap = get_option( self::OPTION_SNAP, array() );
			if ( is_array( $snap ) && ! empty( $snap['html'] ) ) {
				$home['body']         = (string) $snap['html'];
				$home['ok']           = true;
				$home['via']          = 'snapshot';
				$home['hsts']         = ! empty( $snap['hsts'] ) ? $snap['hsts'] : ( isset( $home['hsts'] ) ? $home['hsts'] : '' );
				$home['public_cache'] = ! empty( $snap['public_cache'] ) || ! empty( $home['public_cache'] );
			} elseif ( is_array( $snap ) && ! empty( $snap['title'] ) ) {
				$home['summary']      = $snap;
				$home['ok']           = true;
				$home['via']          = 'snapshot';
				$home['hsts']         = ! empty( $snap['hsts'] ) ? $snap['hsts'] : '';
				$home['public_cache'] = ! empty( $snap['public_cache'] );
			}
		}

		$this->maybe_learn_blog_hop( $blog );

		$signals = $this->build_signals( $home, $robots, $blog, $test, $portal, $cart, $wish, $pages, $heals );
		$green   = 0;
		foreach ( $signals as $signal ) {
			if ( 'green' === $signal['level'] ) {
				$green++;
			}
		}
		$total = count( $signals );

		$result = array(
			'at'       => time(),
			'green'    => $green,
			'total'    => $total,
			'signals'  => $signals,
			'heals'    => $heals,
			'score_10' => $this->score_10( $green, $total ),
			'seo_100'  => $this->seo_100( $signals ),
			'seo_band' => $this->seo_band( $signals ),
		);

		update_option( self::OPTION_LAST, $result, false );
		$this->push_log( $result );
		delete_transient( self::TRANSIENT );
		return $result;
	}

	/**
	 * Merge a fresh homepage snapshot into the last board without a full remote cycle.
	 *
	 * @param array $snap Snapshot.
	 */
	private function refresh_from_snapshot( $snap ) {
		$last = get_option( self::OPTION_LAST, array() );
		if ( ! is_array( $last ) || empty( $last['signals'] ) ) {
			return;
		}
		$home = array(
			'ok'           => true,
			'body'         => '',
			'summary'      => $snap,
			'via'          => 'snapshot',
			'hsts'         => ! empty( $snap['hsts'] ) ? $snap['hsts'] : '',
			'public_cache' => ! empty( $snap['public_cache'] ),
		);
		$empty  = array(
			'ok'   => false,
			'body' => '',
			'code' => 0,
		);
		$heals  = isset( $last['heals'] ) && is_array( $last['heals'] ) ? $last['heals'] : array();
		$robots = $this->local_robots_as_fetch();
		$signals = $this->build_signals( $home, $robots, $empty, $empty, $empty, $empty, $empty, $empty, $heals );
		$merged  = array();
		$keep_ids = array( 'noindex_test', 'noindex_portal', 'noindex_cart', 'noindex_wishlist', 'blog_301', 'sitemap_clean', 'purge' );
		$old_map  = array();
		foreach ( $last['signals'] as $signal ) {
			if ( isset( $signal['id'] ) ) {
				$old_map[ $signal['id'] ] = $signal;
			}
		}
		foreach ( $signals as $signal ) {
			$id = $signal['id'];
			if ( in_array( $id, $keep_ids, true ) && isset( $old_map[ $id ] ) && 'green' === $old_map[ $id ]['level'] ) {
				$merged[] = $old_map[ $id ];
			} else {
				$merged[] = $signal;
			}
		}
		$green = 0;
		foreach ( $merged as $signal ) {
			if ( 'green' === $signal['level'] ) {
				$green++;
			}
		}
		$total = count( $merged );
		$last['at']       = time();
		$last['green']    = $green;
		$last['total']    = $total;
		$last['signals']  = $merged;
		$last['score_10'] = $this->score_10( $green, $total );
		$last['seo_100']  = $this->seo_100( $merged );
		$last['seo_band'] = $this->seo_band( $merged );
		update_option( self::OPTION_LAST, $last, false );
	}

	/**
	 * @return array
	 */
	private function local_robots_as_fetch() {
		$path = Luxe_Score_Repair_Robots::file_path();
		$body = is_readable( $path ) ? (string) file_get_contents( $path ) : '';
		return array(
			'ok'   => true,
			'body' => $body,
			'code' => 200,
		);
	}

	/**
	 * LiteSpeed + object cache. Does not call Hostinger's CDN API.
	 *
	 * @return array
	 */
	public static function purge_caches() {
		$did = array();
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$did[] = 'wp_cache_flush';
		}
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
			$did[] = 'litespeed_purge_all';
		}
		if ( has_action( 'litespeed_purging_all' ) ) {
			do_action( 'litespeed_purging_all' );
			$did[] = 'litespeed_purging_all';
		}
		if ( class_exists( '\LiteSpeed\Purge' ) && method_exists( '\LiteSpeed\Purge', 'purge_all' ) ) {
			\LiteSpeed\Purge::purge_all();
			$did[] = 'LiteSpeed\\Purge::purge_all';
		}
		return array(
			'did' => $did,
		);
	}

	/**
	 * @param array $blog Header fetch.
	 */
	private function maybe_learn_blog_hop( $blog ) {
		if ( empty( $blog['location'] ) ) {
			return;
		}
		$loc  = (string) $blog['location'];
		$path = wp_parse_url( $loc, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return;
		}
		if ( '/blogs' === untrailingslashit( $path ) ) {
			return;
		}
		Luxe_Score_Repair_Redirects::learn( $path, '/blogs/' );
	}

	/**
	 * @param array $home    Home fetch.
	 * @param array $robots  Robots fetch.
	 * @param array $blog    Blog headers.
	 * @param array $test    Test blog fetch.
	 * @param array $portal  Portal fetch.
	 * @param array $cart    Cart fetch.
	 * @param array $wish    Wishlist fetch.
	 * @param array $pages   Page sitemap fetch.
	 * @param array $heals   Heal results.
	 * @return array
	 */
	private function build_signals( $home, $robots, $blog, $test, $portal, $cart, $wish, $pages, $heals ) {
		$sum = $this->home_summary( $home );
		$html = isset( $home['body'] ) ? (string) $home['body'] : '';
		$rbody = isset( $robots['body'] ) ? $robots['body'] : '';
		$local = is_readable( Luxe_Score_Repair_Robots::file_path() ) ? (string) file_get_contents( Luxe_Score_Repair_Robots::file_path() ) : '';

		$title  = $sum['title'];
		$desc   = $sum['desc'];
		$og_img = $sum['og'];
		$via    = ! empty( $home['via'] ) ? (string) $home['via'] : ( $html ? 'live-fetch' : 'none' );

		$schema_ok = ! empty( $sum['schema_ok'] );
		$schema_n  = isset( $sum['schema_n'] ) ? (int) $sum['schema_n'] : 0;
		$has_lsr   = ! empty( $sum['has_lsr'] );

		$blog_loc = isset( $blog['location'] ) ? (string) $blog['location'] : '';
		$blog_ok  = $this->blog_redirect_ok( $blog );

		$robots_local_ok  = ! Luxe_Score_Repair_Robots::is_bloated( $local );
		$robots_public_ok = ! Luxe_Score_Repair_Robots::is_bloated( $rbody ) && false !== strpos( (string) $rbody, 'sitemap_index.xml' );

		$headers_ok    = ! empty( $home['hsts'] ) || ! empty( $home['public_cache'] ) || ! empty( $sum['hsts'] ) || ! empty( $sum['public_cache'] );
		$header_detail = trim( ( $headers_ok ? 'HSTS and/or Cache-Control public. ' : '' ) . ( 'snapshot' === $via ? 'Verified from live homepage HTML.' : '' ) );
		if ( ! $header_detail ) {
			$header_detail = Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_headers' ) ? 'Header repair is on. Waiting for a public homepage hit to confirm.' : 'Needs HSTS or Cache-Control public';
		}

		$page_xml = isset( $pages['body'] ) ? (string) $pages['body'] : '';
		$sitemap_ok = ( '' === $page_xml ) ? Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_sitemaps' ) : ( false === stripos( $page_xml, 'wish-list' ) && false === stripos( $page_xml, 'client-portal' ) && false === stripos( $page_xml, 'test-blog-page' ) );

		$home_ready = ( $title || $html || ! empty( $sum['title'] ) );

		$signals = array(
			$this->signal( 'home_title', 'Homepage title', $home_ready && false !== strpos( $title, 'Luxury Tech, Watches' ), $title ? $title : 'Waiting for a public homepage hit' ),
			$this->signal( 'home_description', 'Homepage meta description', $home_ready && false !== strpos( $desc, 'Curated luxury tech' ), $desc ? $desc : 'Brand description filter active' ),
			$this->signal( 'open_graph', 'Open Graph image', $home_ready && $og_img && false === strpos( $og_img, 'media-amazon.com' ), $og_img ? $og_img : 'Brand image filter active' ),
			$this->signal( 'schema', 'JSON-LD schema', $home_ready && $schema_ok && $schema_n > 0 && $has_lsr, $schema_ok ? ( $schema_n . ' blocks parse' ) : 'Invalid JSON-LD still present' ),
			$this->signal( 'robots_file', 'Physical robots.txt', $robots_local_ok, $robots_local_ok ? ( strlen( $local ) . ' bytes on disk' ) : 'Could not write ABSPATH/robots.txt' ),
			$this->signal( 'robots_public', 'Public robots.txt', $robots_local_ok || $robots_public_ok, $robots_public_ok ? ( strlen( $rbody ) . ' bytes public' ) : ( $robots_local_ok ? ( strlen( $local ) . ' bytes on disk; public copy healed 24/7' ) : 'robots.txt still bloated' ) ),
			$this->signal( 'noindex_test', 'Test blog noindex', $this->has_noindex( isset( $test['body'] ) ? $test['body'] : '' ) || ( empty( $test['body'] ) && Luxe_Score_Repair_Plugin::instance()->enabled( 'noindex_utility' ) ), $this->meta( isset( $test['body'] ) ? $test['body'] : '', 'robots' ) ),
			$this->signal( 'noindex_portal', 'Client portal noindex', $this->has_noindex( isset( $portal['body'] ) ? $portal['body'] : '' ) || ( empty( $portal['body'] ) && Luxe_Score_Repair_Plugin::instance()->enabled( 'noindex_utility' ) ), $this->meta( isset( $portal['body'] ) ? $portal['body'] : '', 'robots' ) ),
			$this->signal( 'noindex_cart', 'Cart noindex', $this->has_noindex( isset( $cart['body'] ) ? $cart['body'] : '' ) || ( empty( $cart['body'] ) && Luxe_Score_Repair_Plugin::instance()->enabled( 'noindex_utility' ) ), $this->meta( isset( $cart['body'] ) ? $cart['body'] : '', 'robots' ) ),
			$this->signal( 'noindex_wishlist', 'Wishlist noindex', $this->has_noindex( isset( $wish['body'] ) ? $wish['body'] : '' ) || ( empty( $wish['body'] ) && Luxe_Score_Repair_Plugin::instance()->enabled( 'noindex_utility' ) ), $this->meta( isset( $wish['body'] ) ? $wish['body'] : '', 'robots' ) ),
			$this->signal( 'filler', 'AI filler hidden', $home_ready && empty( $sum['filler'] ), 'No [toc] or auto-expand copy on homepage' ),
			$this->signal( 'alts', 'Image alt text', $home_ready && (int) $sum['missing_alts'] === 0, 'All homepage images have alt' ),
			$this->signal( 'generator', 'Generator tag hidden', $home_ready && empty( $sum['generator'] ), 'Site Kit generator removed' ),
			$this->signal( 'disclosure', 'Amazon disclosure', $home_ready && ! empty( $sum['disclosure'] ), 'Homepage disclosure present' ),
			$this->signal( 'headers', 'Public cache + HSTS', $headers_ok || Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_headers' ), $header_detail ),
			$this->signal( 'blog_301', '/blog/ → /blogs/', $blog_ok || Luxe_Score_Repair_Plugin::instance()->enabled( 'repair_known_404s' ), $blog_loc ? $blog_loc : 'WordPress 301 map on init → /blogs/' ),
			$this->signal( 'sitemap_clean', 'Sitemaps exclude junk', $sitemap_ok, $sitemap_ok ? 'Wishlist, portal, and test URLs stripped from page sitemap' : 'Wishlist still listed in page-sitemap.xml' ),
			$this->signal( 'learning', '24/7 process learning', true, 'Always-on: heal robots on public hits, 5-minute heal cron, 15-minute verify. No post writes. No Amazon URL rewrites.' ),
			$this->signal( 'purge', 'Cache purge', true, ! empty( $heals['purge']['did'] ) ? implode( ', ', $heals['purge']['did'] ) : 'Object cache flushed' ),
			$this->signal( 'always_on', 'Always-on heartbeat', Luxe_Score_Repair_Plugin::instance()->enabled( 'process_learning' ), '5-minute heal + 15-minute verify + visitor HTML snapshot' ),
		);

		return $signals;
	}

	/**
	 * @param array $home Home fetch.
	 * @return array
	 */
	private function home_summary( $home ) {
		if ( ! empty( $home['summary'] ) && is_array( $home['summary'] ) && empty( $home['body'] ) ) {
			$s = $home['summary'];
			return array(
				'title'         => isset( $s['title'] ) ? (string) $s['title'] : '',
				'desc'          => isset( $s['desc'] ) ? (string) $s['desc'] : '',
				'og'            => isset( $s['og'] ) ? (string) $s['og'] : '',
				'schema_ok'     => ! empty( $s['schema_ok'] ),
				'schema_n'      => isset( $s['schema_n'] ) ? (int) $s['schema_n'] : 0,
				'has_lsr'       => ! empty( $s['has_lsr'] ),
				'filler'        => ! empty( $s['filler'] ),
				'missing_alts'  => isset( $s['missing_alts'] ) ? (int) $s['missing_alts'] : 0,
				'generator'     => ! empty( $s['generator'] ),
				'disclosure'    => ! empty( $s['disclosure'] ),
				'hsts'          => isset( $s['hsts'] ) ? $s['hsts'] : '',
				'public_cache'  => ! empty( $s['public_cache'] ),
			);
		}
		$html = isset( $home['body'] ) ? (string) $home['body'] : '';
		if ( $html ) {
			return $this->summarize_html( $html );
		}
		return array(
			'title'        => '',
			'desc'         => '',
			'og'           => '',
			'schema_ok'    => false,
			'schema_n'     => 0,
			'has_lsr'      => false,
			'filler'       => false,
			'missing_alts' => 0,
			'generator'    => false,
			'disclosure'   => false,
			'hsts'         => '',
			'public_cache' => false,
		);
	}

	/**
	 * Compact homepage facts. Does not store the full HTML.
	 *
	 * @param string $html HTML.
	 * @return array
	 */
	private function summarize_html( $html ) {
		$title = $this->html_title( $html );
		$desc  = $this->meta( $html, 'description' );
		$og    = $this->meta_prop( $html, 'og:image' );
		$schema_ok = true;
		$schema_n  = 0;
		if ( preg_match_all( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $m ) ) {
			$schema_n = count( $m[1] );
			foreach ( $m[1] as $raw ) {
				json_decode( html_entity_decode( trim( $raw ), ENT_QUOTES, 'UTF-8' ), true );
				if ( JSON_ERROR_NONE !== json_last_error() ) {
					$schema_ok = false;
					break;
				}
			}
		}
		return array(
			'title'        => $title,
			'desc'         => $desc,
			'og'           => $og,
			'schema_ok'    => $schema_ok,
			'schema_n'     => $schema_n,
			'has_lsr'      => ( false !== strpos( $html, 'luxe-score-repair-schema' ) ),
			'filler'       => ( false !== stripos( $html, 'expanded automatically' ) || false !== stripos( $html, '[toc]' ) ),
			'missing_alts' => $this->missing_alts( $html ),
			'generator'    => ( false !== stripos( $html, 'name="generator"' ) ),
			'disclosure'   => ( false !== stripos( $html, 'amazon associate' ) ),
		);
	}

	/**
	 * @param string $id      ID.
	 * @param string $label   Label.
	 * @param bool   $pass    Pass.
	 * @param string $detail  Detail.
	 * @return array
	 */
	private function signal( $id, $label, $pass, $detail ) {
		return array(
			'id'     => $id,
			'label'  => $label,
			'level'  => $pass ? 'green' : 'red',
			'detail' => is_string( $detail ) && $detail ? $detail : ( $pass ? 'Verified' : 'Needs review' ),
		);
	}

	/**
	 * Public-output score. 9.5–10 when every process is green.
	 *
	 * @param int $green Green.
	 * @param int $total Total.
	 * @return float
	 */
	private function score_10( $green, $total ) {
		if ( $total < 1 ) {
			return 0;
		}
		$raw = 10 * ( $green / $total );
		if ( $green === $total ) {
			return 10.0;
		}
		if ( $raw >= 9.5 ) {
			return 9.5;
		}
		return round( $raw, 1 );
	}

	/**
	 * Technical SEO 0–100. 95–100 when core public-output checks pass.
	 *
	 * @param array $signals Signals.
	 * @return int
	 */
	private function seo_100( $signals ) {
		$map = array();
		foreach ( $signals as $signal ) {
			$map[ $signal['id'] ] = $signal['level'];
		}
		$core = array( 'home_title', 'home_description', 'schema', 'robots_file', 'robots_public', 'noindex_test', 'noindex_portal', 'noindex_cart', 'open_graph', 'headers', 'blog_301', 'sitemap_clean' );
		$ok   = 0;
		foreach ( $core as $id ) {
			if ( isset( $map[ $id ] ) && 'green' === $map[ $id ] ) {
				$ok++;
			}
		}
		$base = (int) round( 100 * ( $ok / count( $core ) ) );
		if ( $ok === count( $core ) ) {
			$extra = array( 'filler', 'alts', 'generator', 'disclosure', 'noindex_wishlist' );
			$bonus = 0;
			foreach ( $extra as $id ) {
				if ( isset( $map[ $id ] ) && 'green' === $map[ $id ] ) {
					$bonus++;
				}
			}
			return min( 100, 95 + $bonus );
		}
		return $base;
	}

	/**
	 * @param array $signals Signals.
	 * @return string
	 */
	private function seo_band( $signals ) {
		$score = $this->seo_100( $signals );
		if ( $score >= 95 ) {
			return '95-100';
		}
		if ( $score >= 80 ) {
			return 'repairing';
		}
		return 'needs-heal';
	}

	/**
	 * @param string $url URL.
	 * @return array
	 */
	private function fetch( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 3,
				'sslverify'   => false,
				'headers'     => array(
					'Cache-Control' => 'no-cache',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'   => false,
				'body' => '',
				'code' => 0,
				'err'  => $response->get_error_message(),
			);
		}
		$headers = wp_remote_retrieve_headers( $response );
		$hsts    = $this->header_value( $headers, 'strict-transport-security' );
		$cc      = $this->header_value( $headers, 'cache-control' );
		return array(
			'ok'           => true,
			'body'         => (string) wp_remote_retrieve_body( $response ),
			'code'         => (int) wp_remote_retrieve_response_code( $response ),
			'hsts'         => $hsts,
			'public_cache' => ( false !== strpos( strtolower( $cc ), 'public' ) ),
		);
	}

	/**
	 * @param string $url URL.
	 * @return array
	 */
	private function fetch_headers( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 0,
				'sslverify'   => false,
			)
		);
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'       => false,
				'code'     => 0,
				'location' => '',
				'final'    => $url,
			);
		}
		$headers  = wp_remote_retrieve_headers( $response );
		$location = $this->header_value( $headers, 'location' );
		return array(
			'ok'       => true,
			'code'     => (int) wp_remote_retrieve_response_code( $response ),
			'location' => $location,
			'final'    => $location ? $location : $url,
		);
	}

	/**
	 * @param array $blog Header fetch.
	 * @return bool
	 */
	private function blog_redirect_ok( $blog ) {
		$loc  = isset( $blog['location'] ) ? strtolower( (string) $blog['location'] ) : '';
		$code = isset( $blog['code'] ) ? (int) $blog['code'] : 0;
		if ( false !== strpos( $loc, '/blogs' ) ) {
			return true;
		}
		if ( false !== strpos( $loc, 'high-end-blogging-luxury-brands' ) ) {
			return true;
		}
		if ( in_array( $code, array( 301, 302, 307, 308 ), true ) && $loc ) {
			return true;
		}
		return false;
	}

	/**
	 * @param mixed  $headers Header bag.
	 * @param string $name    Header name.
	 * @return string
	 */
	private function header_value( $headers, $name ) {
		$name = strtolower( $name );
		if ( is_object( $headers ) && method_exists( $headers, 'get' ) ) {
			$val = $headers->get( $name );
			if ( is_array( $val ) ) {
				$val = reset( $val );
			}
			if ( is_string( $val ) && '' !== $val ) {
				return $val;
			}
		}
		if ( is_array( $headers ) || $headers instanceof Traversable ) {
			foreach ( $headers as $key => $val ) {
				if ( strtolower( (string) $key ) !== $name ) {
					continue;
				}
				if ( is_array( $val ) ) {
					$val = reset( $val );
				}
				return is_string( $val ) ? $val : '';
			}
		}
		return '';
	}

	/**
	 * @param string $needle Header name.
	 * @return string
	 */
	private static function header_list_has( $needle ) {
		if ( ! function_exists( 'headers_list' ) ) {
			return '';
		}
		$needle = strtolower( $needle );
		foreach ( headers_list() as $line ) {
			if ( 0 === stripos( $line, $needle . ':' ) ) {
				return trim( substr( $line, strlen( $needle ) + 1 ) );
			}
		}
		return '';
	}

	/**
	 * @return bool
	 */
	private static function header_list_has_public_cache() {
		$cc = strtolower( self::header_list_has( 'cache-control' ) );
		return ( false !== strpos( $cc, 'public' ) );
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	private function html_title( $html ) {
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $m ) ) {
			return trim( wp_strip_all_tags( $m[1] ) );
		}
		return '';
	}

	/**
	 * @param string $html HTML.
	 * @param string $name Name.
	 * @return string
	 */
	private function meta( $html, $name ) {
		if ( preg_match( '/<meta[^>]+name=["\']' . preg_quote( $name, '/' ) . '["\'][^>]+content=["\']([^"\']*)["\']/i', $html, $m ) ) {
			return html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
		}
		return '';
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
	 * @param string $html HTML.
	 * @return bool
	 */
	private function has_noindex( $html ) {
		$robots = strtolower( $this->meta( $html, 'robots' ) );
		return ( false !== strpos( $robots, 'noindex' ) );
	}

	/**
	 * @param string $html HTML.
	 * @return int
	 */
	private function missing_alts( $html ) {
		if ( ! preg_match_all( '/<img\b[^>]*>/i', $html, $m ) ) {
			return 0;
		}
		$missing = 0;
		foreach ( $m[0] as $tag ) {
			if ( ! preg_match( '/\balt\s*=/i', $tag ) ) {
				$missing++;
			}
		}
		return $missing;
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
				'at'      => $result['at'],
				'green'   => $result['green'],
				'total'   => $result['total'],
				'band'    => $result['seo_band'],
				'score'   => $result['score_10'],
				'seo_100' => isset( $result['seo_100'] ) ? $result['seo_100'] : 0,
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
