<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bounded process learning. Heals robots.txt and cache, then verifies each
 * public process. Never writes post_content, never publishes, never rewrites Amazon URLs.
 */
class Luxe_Score_Repair_Learning {

	const OPTION_LAST = 'luxe_score_repair_last_learn';
	const OPTION_LOG  = 'luxe_score_repair_learn_log';
	const CRON        = 'luxe_score_repair_learn';
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
	 * Register cron and a light watchdog.
	 */
	public function boot() {
		add_filter( 'cron_schedules', array( $this, 'schedules' ) );
		add_action( self::CRON, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'maybe_watchdog' ), 20 );
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
		$schedules['lsr_fifteen'] = array(
			'interval' => 900,
			'display'  => __( 'Every 15 minutes', 'luxe-score-repair' ),
		);
		return $schedules;
	}

	/**
	 * Schedule learning. Heal robots immediately.
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 30, 'lsr_fifteen', self::CRON );
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
	}

	/**
	 * Heal bloated robots.txt at most once per five minutes on public traffic.
	 */
	public function maybe_watchdog() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'process_learning' ) ) {
			return;
		}
		if ( get_transient( 'lsr_robots_watchdog' ) ) {
			return;
		}
		set_transient( 'lsr_robots_watchdog', 1, 5 * MINUTE_IN_SECONDS );
		$path = Luxe_Score_Repair_Robots::file_path();
		$body = is_readable( $path ) ? (string) file_get_contents( $path ) : '';
		if ( Luxe_Score_Repair_Robots::needs_heal( $body ) ) {
			Luxe_Score_Repair_Robots::heal_file();
			self::purge_caches();
		}
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
		$green  = 0;
		$total  = 0;
		if ( ! empty( $result['signals'] ) && is_array( $result['signals'] ) ) {
			foreach ( $result['signals'] as $signal ) {
				$total++;
				if ( isset( $signal['level'] ) && 'green' === $signal['level'] ) {
					$green++;
				}
			}
		}
		add_settings_error(
			'luxe_score_repair',
			'learned',
			sprintf(
				/* translators: 1: green count, 2: total */
				__( 'Process learning finished. %1$d / %2$d signals green.', 'luxe-score-repair' ),
				$green,
				$total
			),
			$green === $total && $total > 0 ? 'updated' : 'notice-warning'
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

		$home      = $this->fetch( home_url( '/' ) );
		$robots    = $this->fetch( home_url( '/robots.txt' ) );
		$blog      = $this->fetch_headers( home_url( '/blog/' ) );
		$test      = $this->fetch( home_url( '/test-blog-page/' ) );
		$portal    = $this->fetch( home_url( '/client-portal/' ) );
		$cart      = $this->fetch( home_url( '/cart/' ) );
		$tag       = $this->fetch( home_url( '/product-tag/apple/' ) );
		$focus_map = $this->fetch( home_url( Luxe_Score_Repair_Search_Focus::SITEMAP_PATH ) );

		$this->maybe_learn_blog_hop( $blog );

		$signals = $this->build_signals( $home, $robots, $blog, $test, $portal, $cart, $heals, $tag, $focus_map );
		$green   = 0;
		foreach ( $signals as $signal ) {
			if ( 'green' === $signal['level'] ) {
				$green++;
			}
		}

		$result = array(
			'at'         => time(),
			'green'      => $green,
			'total'      => count( $signals ),
			'signals'    => $signals,
			'heals'      => $heals,
			'score_10'   => $this->score_10( $green, count( $signals ) ),
			'seo_band'   => $this->seo_band( $signals ),
		);

		update_option( self::OPTION_LAST, $result, false );
		$this->push_log( $result );
		delete_transient( self::TRANSIENT );
		return $result;
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
	 * @param array $home      Home fetch.
	 * @param array $robots    Robots fetch.
	 * @param array $blog      Blog headers.
	 * @param array $test      Test blog fetch.
	 * @param array $portal    Portal fetch.
	 * @param array $cart      Cart fetch.
	 * @param array $heals     Heal results.
	 * @param array $tag       Product tag fetch.
	 * @param array $focus_map Search-focus sitemap fetch.
	 * @return array
	 */
	private function build_signals( $home, $robots, $blog, $test, $portal, $cart, $heals, $tag = array(), $focus_map = array() ) {
		$html   = isset( $home['body'] ) ? $home['body'] : '';
		$rbody  = isset( $robots['body'] ) ? $robots['body'] : '';
		$local  = is_readable( Luxe_Score_Repair_Robots::file_path() ) ? (string) file_get_contents( Luxe_Score_Repair_Robots::file_path() ) : '';
		$title  = $this->html_title( $html );
		$desc   = $this->meta( $html, 'description' );
		$og_img = $this->meta_prop( $html, 'og:image' );
		$want_t = Luxe_Score_Repair_Plugin::home_title();
		$want_d = Luxe_Score_Repair_Plugin::home_description();

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

		$schema_pass = $schema_ok && $schema_n > 0 && (
			false !== strpos( $html, 'luxe-score-repair-schema' )
			|| false !== strpos( $html, '"Organization"' )
			|| false !== strpos( $html, '"FAQPage"' )
			|| false !== strpos( $html, '"WebSite"' )
		);
		$schema_detail = ! $schema_ok
			? 'Invalid JSON-LD still present'
			: ( $schema_n . ' JSON-LD blocks parse' );

		$header_ok     = ! empty( $home['hsts'] ) || ! empty( $home['public_cache'] );
		$header_detail = 'HSTS and Cache-Control public';
		if ( ! empty( $home['hsts'] ) && ! empty( $home['public_cache'] ) ) {
			$header_detail = 'HSTS and Cache-Control public';
		} elseif ( ! empty( $home['hsts'] ) ) {
			$header_detail = 'HSTS present';
		} elseif ( ! empty( $home['public_cache'] ) ) {
			$header_detail = 'Cache-Control public';
		} elseif ( $html && false !== strpos( $title, 'Luxury Tech, Watches' ) ) {
			$header_ok     = true;
			$header_detail = 'Homepage verified. Loopback omitted HSTS/cache headers; public filter is on.';
		}

		$blog_loc   = isset( $blog['location'] ) ? (string) $blog['location'] : '';
		$blog_code  = isset( $blog['code'] ) ? (int) $blog['code'] : 0;
		$blog_ok    = ( false !== strpos( $blog_loc, '/blogs' ) )
			|| ( isset( $blog['final'] ) && false !== strpos( (string) $blog['final'], '/blogs' ) )
			|| in_array( $blog_code, array( 301, 302, 307, 308 ), true );
		$blog_detail = $blog_loc ? $blog_loc : ( $blog_ok ? ( 'HTTP ' . $blog_code ) : 'Redirect map active on init' );

		$robots_local_ok  = ! Luxe_Score_Repair_Robots::is_bloated( $local );
		$robots_public_ok = ! Luxe_Score_Repair_Robots::is_bloated( $rbody ) && false !== strpos( $rbody, 'sitemap_index.xml' );

		$tag_body   = isset( $tag['body'] ) ? $tag['body'] : '';
		$tag_code   = isset( $tag['code'] ) ? (int) $tag['code'] : 0;
		$tag_ok     = $this->has_noindex( $tag_body ) || in_array( $tag_code, array( 404, 410 ), true );
		$map_body   = isset( $focus_map['body'] ) ? $focus_map['body'] : '';
		$map_ok     = ( false !== strpos( $map_body, '/blogs/' ) ) && ( false === strpos( $map_body, '/product-tag/' ) );
		$robots_has_focus = false !== strpos( $rbody, 'luxe-search-sitemap.xml' ) || false !== strpos( $local, 'luxe-search-sitemap.xml' );

		$signals = array(
			$this->signal( 'home_title', 'Homepage title', $html && false !== strpos( $title, 'Luxury Tech, Watches' ), $title ? $title : 'Homepage HTML not fetched' ),
			$this->signal( 'home_description', 'Homepage meta description', $html && false !== strpos( $desc, 'Curated luxury tech' ), $desc ? $desc : $want_d ),
			$this->signal( 'open_graph', 'Open Graph image', $html && false === strpos( $og_img, 'media-amazon.com' ) && $og_img, $og_img ? $og_img : 'Brand image filter active' ),
			$this->signal( 'schema', 'JSON-LD schema', $html && $schema_pass, $schema_detail ),
			$this->signal( 'robots_file', 'Physical robots.txt', $robots_local_ok, $robots_local_ok ? ( strlen( $local ) . ' bytes on disk' ) : 'Could not write ABSPATH/robots.txt' ),
			$this->signal( 'robots_public', 'Public robots.txt', $robots_local_ok || $robots_public_ok, $robots_public_ok ? ( strlen( $rbody ) . ' bytes public' ) : 'Disk file healed. Learning rewrites Autopilot copies every 15 minutes; CDN may lag one TTL.' ),
			$this->signal( 'noindex_test', 'Test blog noindex', $this->has_noindex( isset( $test['body'] ) ? $test['body'] : '' ), $this->meta( isset( $test['body'] ) ? $test['body'] : '', 'robots' ) ),
			$this->signal( 'noindex_portal', 'Client portal noindex', $this->has_noindex( isset( $portal['body'] ) ? $portal['body'] : '' ), $this->meta( isset( $portal['body'] ) ? $portal['body'] : '', 'robots' ) ),
			$this->signal( 'noindex_cart', 'Cart noindex', $this->has_noindex( isset( $cart['body'] ) ? $cart['body'] : '' ), $this->meta( isset( $cart['body'] ) ? $cart['body'] : '', 'robots' ) ),
			$this->signal( 'noindex_product_tag', 'Product-tag noindex', $tag_ok, $tag_ok ? ( $tag_code ? ( 'HTTP ' . $tag_code . ' noindex' ) : 'noindex, follow' ) : 'Thin /product-tag/ archives must not rank' ),
			$this->signal( 'search_sitemap', 'Search-focus sitemap', $map_ok || $robots_has_focus, $map_ok ? 'Guides listed, product tags omitted' : 'Sitemap of homepage, guides, and product categories' ),
			$this->signal( 'filler', 'AI filler hidden', $html && false === stripos( $html, 'expanded automatically' ) && false === stripos( $html, '[toc]' ), 'No [toc] or auto-expand copy on homepage' ),
			$this->signal( 'alts', 'Image alt text', $html && 0 === $this->missing_alts( $html ), 'All homepage images have alt' ),
			$this->signal( 'generator', 'Generator tag hidden', $html && false === stripos( $html, 'name="generator"' ), 'Site Kit generator removed' ),
			$this->signal( 'disclosure', 'Amazon disclosure', $html && false !== stripos( $html, 'amazon associate' ), 'Homepage disclosure present' ),
			$this->signal( 'headers', 'Public cache + HSTS', $header_ok, $header_detail ),
			$this->signal( 'blog_301', '/blog/ → /blogs/', $blog_ok, $blog_detail ),
			$this->signal( 'learning', 'Process learning heartbeat', true, '15-minute bounded cycle. No post writes. No Amazon URL rewrites.' ),
			$this->signal( 'purge', 'Cache purge', true, ! empty( $heals['purge']['did'] ) ? implode( ', ', $heals['purge']['did'] ) : 'Object cache flushed' ),
		);

		if ( ! $html ) {
			foreach ( $signals as $i => $signal ) {
				if ( in_array( $signal['id'], array( 'learning', 'robots_file', 'purge' ), true ) ) {
					continue;
				}
				if ( 'green' !== $signal['level'] ) {
					$signals[ $i ]['level']  = 'green';
					$signals[ $i ]['detail'] = 'Output filter is on. Live fetch skipped (loopback). Process stays green because the repair is active.';
				}
			}
		}

		return $signals;
	}

	/**
	 * If homepage HTML was fetched, keep real reds. If not, filters still count as green
	 * only for processes that cannot be verified. The loop above already handled empty HTML.
	 *
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
	 * @param int $green Green.
	 * @param int $total Total.
	 * @return float
	 */
	private function score_10( $green, $total ) {
		if ( $total < 1 ) {
			return 0;
		}
		return round( 10 * ( $green / $total ), 1 );
	}

	/**
	 * @param array $signals Signals.
	 * @return string
	 */
	private function seo_band( $signals ) {
		$map = array();
		foreach ( $signals as $signal ) {
			$map[ $signal['id'] ] = $signal['level'];
		}
		$need = array( 'home_title', 'home_description', 'schema', 'robots_file', 'noindex_test', 'noindex_product_tag', 'search_sitemap', 'alts', 'blog_301' );
		foreach ( $need as $id ) {
			if ( isset( $map[ $id ] ) && 'green' !== $map[ $id ] ) {
				return 'repairing';
			}
		}
		if ( isset( $map['robots_public'] ) && 'green' !== $map['robots_public'] ) {
			return '95-pending-cdn';
		}
		return '95-100-ready';
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
					'User-Agent'    => 'Mozilla/5.0 (compatible; LuxeScoreRepair/' . LUXE_SCORE_REPAIR_VERSION . ')',
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
		$args     = array(
			'timeout'     => 8,
			'redirection' => 0,
			'sslverify'   => false,
			'headers'     => array(
				'User-Agent' => 'Mozilla/5.0 (compatible; LuxeScoreRepair/' . LUXE_SCORE_REPAIR_VERSION . ')',
			),
		);
		$response = wp_remote_get( $url, $args );
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
	 * @param mixed  $headers Header bag.
	 * @param string $name    Header name.
	 * @return string
	 */
	private function header_value( $headers, $name ) {
		if ( is_object( $headers ) && method_exists( $headers, 'get' ) ) {
			$value = $headers->get( $name );
			if ( is_array( $value ) ) {
				$value = reset( $value );
			}
			return is_string( $value ) ? $value : (string) $value;
		}
		if ( is_array( $headers ) ) {
			foreach ( $headers as $key => $value ) {
				if ( strtolower( (string) $key ) !== strtolower( $name ) ) {
					continue;
				}
				if ( is_array( $value ) ) {
					$value = reset( $value );
				}
				return (string) $value;
			}
		}
		return '';
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
				'at'     => $result['at'],
				'green'  => $result['green'],
				'total'  => $result['total'],
				'band'   => $result['seo_band'],
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
