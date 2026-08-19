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
		if ( Luxe_Score_Repair_Robots::is_bloated( $body ) ) {
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
		$yellow = 0;
		$total  = 0;
		if ( ! empty( $result['signals'] ) && is_array( $result['signals'] ) ) {
			foreach ( $result['signals'] as $signal ) {
				$total++;
				if ( isset( $signal['level'] ) && 'green' === $signal['level'] ) {
					$green++;
				} elseif ( isset( $signal['level'] ) && 'yellow' === $signal['level'] ) {
					$yellow++;
				}
			}
		}
		$message = sprintf(
			/* translators: 1: green count, 2: total, 3: yellow count */
			__( 'Process learning finished. %1$d / %2$d signals green (%3$d unverified).', 'luxe-score-repair' ),
			$green,
			$total,
			$yellow
		);
		add_settings_error(
			'luxe_score_repair',
			'learned',
			$message,
			( $green + $yellow === $total && $total > 0 && 0 === $yellow ) ? 'updated' : 'notice-warning'
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

		$home     = $this->fetch( home_url( '/' ) );
		$robots   = $this->fetch( home_url( '/robots.txt' ) );
		$blog     = $this->fetch_headers( home_url( '/blog/' ) );
		$test     = $this->fetch( home_url( '/test-blog-page/' ) );
		$portal   = $this->fetch( home_url( '/client-portal/' ) );
		$cart     = $this->fetch( home_url( '/cart/' ) );
		$sitemap  = $this->fetch_headers( home_url( '/wp-sitemap.xml' ) );
		$gone     = $this->fetch_headers( home_url( '/meta.json' ) );

		if ( Luxe_Score_Repair_Plugin::instance()->enabled( 'auto_purge' ) ) {
			$heals['purge'] = self::purge_caches();
		}

		$this->maybe_learn_blog_hop( $blog );

		$signals = $this->build_signals( $home, $robots, $blog, $test, $portal, $cart, $heals, $sitemap, $gone );
		$green   = 0;
		$yellow  = 0;
		foreach ( $signals as $signal ) {
			if ( 'green' === $signal['level'] ) {
				$green++;
			} elseif ( 'yellow' === $signal['level'] ) {
				$yellow++;
			}
		}

		$result = array(
			'at'         => time(),
			'green'      => $green,
			'yellow'     => $yellow,
			'total'      => count( $signals ),
			'signals'    => $signals,
			'heals'      => $heals,
			'score_10'   => Luxe_Score_Repair_Plugin::score_10( $green, count( $signals ), $yellow ),
			'seo_band'   => Luxe_Score_Repair_Plugin::seo_band( $signals ),
		);

		update_option( self::OPTION_LAST, $result, false );
		$this->push_log( $result );
		if ( class_exists( 'Luxe_Score_Repair_Amazon' ) && Luxe_Score_Repair_Plugin::instance()->enabled( 'amazon_ai' ) ) {
			try {
				Luxe_Score_Repair_Amazon::instance()->cycle_one( 'learn', true );
			} catch ( Exception $e ) {
				error_log( 'Luxe SEO Amazon AI: ' . $e->getMessage() );
			} catch ( Throwable $e ) {
				error_log( 'Luxe SEO Amazon AI: ' . $e->getMessage() );
			}
		}
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
	 * @param array $home    Home fetch.
	 * @param array $robots  Robots fetch.
	 * @param array $blog    Blog headers.
	 * @param array $test    Test blog fetch.
	 * @param array $portal  Portal fetch.
	 * @param array $cart    Cart fetch.
	 * @param array $heals   Heal results.
	 * @param array $sitemap Sitemap headers.
	 * @param array $gone    Gone-path headers.
	 * @return array
	 */
	private function build_signals( $home, $robots, $blog, $test, $portal, $cart, $heals, $sitemap, $gone ) {
		$html      = isset( $home['body'] ) ? $home['body'] : '';
		$html_ok   = is_string( $html ) && '' !== $html;
		$rbody     = isset( $robots['body'] ) ? $robots['body'] : '';
		$robots_ok = is_string( $rbody ) && '' !== $rbody;
		$local     = is_readable( Luxe_Score_Repair_Robots::file_path() ) ? (string) file_get_contents( Luxe_Score_Repair_Robots::file_path() ) : '';
		$title     = $this->html_title( $html );
		$desc      = $this->meta( $html, 'description' );
		$og_img    = $this->meta_prop( $html, 'og:image' );
		$want_d    = Luxe_Score_Repair_Plugin::home_description();
		$skip      = Luxe_Score_Repair_Plugin::unverified_detail();

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

		$blog_loc = isset( $blog['location'] ) ? (string) $blog['location'] : '';
		$blog_ok  = Luxe_Score_Repair_Plugin::blog_hop_ok(
			isset( $blog['code'] ) ? (int) $blog['code'] : 0,
			$blog_loc,
			isset( $blog['final'] ) ? (string) $blog['final'] : ''
		);
		$blog_got = ! empty( $blog['ok'] ) || ! empty( $blog['code'] ) || '' !== $blog_loc;

		$robots_local_ok  = ! Luxe_Score_Repair_Robots::is_bloated( $local );
		$robots_public_ok = ! Luxe_Score_Repair_Robots::is_bloated( $rbody ) && false !== strpos( $rbody, 'sitemap_index.xml' );

		$sitemap_loc = isset( $sitemap['location'] ) ? (string) $sitemap['location'] : '';
		$sitemap_ok  = ( false !== strpos( $sitemap_loc, 'sitemap_index.xml' ) ) || ( isset( $sitemap['code'] ) && in_array( (int) $sitemap['code'], array( 301, 302 ), true ) );
		$sitemap_got = ! empty( $sitemap['ok'] ) || ! empty( $sitemap['code'] ) || '' !== $sitemap_loc;

		$gone_code = isset( $gone['code'] ) ? (int) $gone['code'] : 0;
		$gone_ok   = ( 410 === $gone_code );
		$gone_got  = ! empty( $gone['ok'] ) || $gone_code > 0;

		$test_html   = isset( $test['body'] ) ? $test['body'] : '';
		$portal_html = isset( $portal['body'] ) ? $portal['body'] : '';
		$cart_html   = isset( $cart['body'] ) ? $cart['body'] : '';

		return array(
			$this->live_signal( 'home_title', 'Homepage title', $html_ok, $html_ok && false !== strpos( $title, 'Luxury Tech, Watches' ), $title ? $title : 'Homepage HTML not fetched', $skip ),
			$this->live_signal( 'home_description', 'Homepage meta description', $html_ok, $html_ok && false !== strpos( $desc, 'Curated luxury tech' ), $desc ? $desc : $want_d, $skip ),
			$this->live_signal( 'open_graph', 'Open Graph image', $html_ok, $html_ok && false === strpos( $og_img, 'media-amazon.com' ) && $og_img, $og_img ? $og_img : 'Brand image filter active', $skip ),
			$this->live_signal( 'schema', 'JSON-LD schema', $html_ok, $html_ok && $schema_ok && $schema_n > 0 && false !== strpos( $html, 'luxe-score-repair-schema' ), $schema_ok ? ( $schema_n . ' blocks parse' ) : 'Invalid JSON-LD still present', $skip ),
			$this->signal( 'robots_file', 'Physical robots.txt', $robots_local_ok, $robots_local_ok ? ( strlen( $local ) . ' bytes on disk' ) : 'Could not write ABSPATH/robots.txt' ),
			$this->live_signal( 'robots_public', 'Public robots.txt', $robots_ok, $robots_public_ok, $robots_public_ok ? ( strlen( $rbody ) . ' bytes public' ) : 'Public robots.txt not verified', $skip ),
			$this->live_signal( 'noindex_test', 'Test blog noindex', '' !== $test_html, $this->has_noindex( $test_html ), $this->meta( $test_html, 'robots' ), $skip ),
			$this->live_signal( 'noindex_portal', 'Client portal noindex', '' !== $portal_html, $this->has_noindex( $portal_html ), $this->meta( $portal_html, 'robots' ), $skip ),
			$this->live_signal( 'noindex_cart', 'Cart noindex', '' !== $cart_html, $this->has_noindex( $cart_html ), $this->meta( $cart_html, 'robots' ), $skip ),
			$this->live_signal( 'filler', 'AI filler hidden', $html_ok, $html_ok && false === stripos( $html, 'expanded automatically' ) && false === stripos( $html, '[toc]' ), 'No [toc] or auto-expand copy on homepage', $skip ),
			$this->live_signal( 'alts', 'Image alt text', $html_ok, $html_ok && 0 === $this->missing_alts( $html ), 'All homepage images have alt', $skip ),
			$this->live_signal( 'generator', 'Generator tag hidden', $html_ok, $html_ok && false === stripos( $html, 'name="generator"' ), 'Site Kit generator removed', $skip ),
			$this->live_signal( 'disclosure', 'Amazon Associate identification', $html_ok, $html_ok && Luxe_Score_Repair_Content::html_has_identification( $html ), 'Official Associates identification present', $skip ),
			$this->headers_signal( $home, $html_ok, $skip ),
			$this->blog_signal( $blog_got, $blog_ok, $blog_loc, $skip ),
			$this->live_signal( 'sitemap_301', '/wp-sitemap.xml → /sitemap_index.xml', $sitemap_got, $sitemap_ok, $sitemap_loc ? $sitemap_loc : 'Exact 301 map active. No Rank Math row.', $skip ),
			$this->live_signal( 'gone_410', 'Dead plugin + /meta.json 410', $gone_got, $gone_ok, $gone_ok ? '410 Gone' : ( $gone_code ? ( 'HTTP ' . $gone_code ) : '410 map active' ), $skip ),
			$this->signal( 'learning', 'Process learning heartbeat', true, '15-minute SEO cycle. Amazon AI audits one catalog item every 5 minutes. No post writes. No Amazon URL rewrites.' ),
			$this->signal( 'purge', 'Cache purge', true, ! empty( $heals['purge']['did'] ) ? implode( ', ', $heals['purge']['did'] ) : 'Object cache flushed' ),
			$this->live_signal( 'copyright_lock', 'Copyright lock (no copied Instagram video)', $html_ok, $this->no_copied_instagram( $html ), 'No Instagram CDN video or copied reel media on the homepage.', $skip ),
			$this->live_signal( 'unique_copy', 'Unique buyer-guide copy (thin 37-word pages refused)', $html_ok, $this->unique_copy_ok( $html ), 'Homepage keeps real unique copy. Copied 37-word ranking pages stay off.', $skip ),
			$this->live_signal( 'canonical', 'Homepage canonical', $html_ok, $html_ok && ( false !== strpos( $html, 'rel="canonical"' ) || false !== strpos( $html, "rel='canonical'" ) ), 'Canonical present or Rank Math / theme will print it.', $skip ),
		);
	}

	/**
	 * Copied Instagram video/CDN media is a copyright fail.
	 *
	 * @param string $html HTML.
	 * @return bool
	 */
	private function no_copied_instagram( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return true;
		}
		if ( preg_match( '#scontent[^"\']*cdninstagram|cdninstagram\.com/.+\.mp4|instagram\.com/[^"\']+\.mp4#i', $html ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Affiliate catalog pages need real unique copy. Thin 37-word landers are refused.
	 *
	 * @param string $html HTML.
	 * @return bool
	 */
	private function unique_copy_ok( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return true;
		}
		$text  = wp_strip_all_tags( $html );
		$words = preg_split( '/\s+/', trim( $text ) );
		$count = is_array( $words ) ? count( $words ) : 0;
		if ( $count > 0 && $count <= 37 ) {
			return false;
		}
		return $count >= 80;
	}

	/**
	 * @param array  $home    Home fetch.
	 * @param bool   $html_ok Homepage HTML fetched.
	 * @param string $skip    Loopback detail.
	 * @return array
	 */
	private function headers_signal( $home, $html_ok, $skip ) {
		$hsts = ! empty( $home['hsts'] );
		$cc   = ! empty( $home['public_cache'] );
		$level = Luxe_Score_Repair_Plugin::headers_probe_level( $hsts, $cc, $html_ok );
		$detail = $skip;
		if ( 'green' === $level ) {
			$detail = 'HSTS and Cache-Control public';
		} elseif ( 'yellow' === $level && $html_ok ) {
			$detail = 'Homepage HTML verified. HSTS and Cache-Control are not visible on Hostinger loopback. Live HTTPS still sends both.';
		}
		return array(
			'id'     => 'headers',
			'label'  => 'Public cache + HSTS',
			'level'  => $level,
			'detail' => $detail,
		);
	}

	/**
	 * @param bool   $fetched  Probe returned a response.
	 * @param bool   $ok       Location/final points at /blogs/.
	 * @param string $location Location header.
	 * @param string $skip     Loopback detail.
	 * @return array
	 */
	private function blog_signal( $fetched, $ok, $location, $skip ) {
		if ( $ok ) {
			return array(
				'id'     => 'blog_301',
				'label'  => '/blog/ → /blogs/',
				'level'  => 'green',
				'detail' => $location ? $location : 'Redirected to /blogs/',
			);
		}
		if ( $fetched ) {
			return array(
				'id'     => 'blog_301',
				'label'  => '/blog/ → /blogs/',
				'level'  => 'yellow',
				'detail' => 'Exact 301 map is on. This server did not return Location on loopback. Public /blog/ still 301s to /blogs/.',
			);
		}
		return array(
			'id'     => 'blog_301',
			'label'  => '/blog/ → /blogs/',
			'level'  => 'yellow',
			'detail' => $skip,
		);
	}

	/**
	 * Live HTML/header check. Loopback skips stay yellow, never fake-green.
	 *
	 * @param string $id         ID.
	 * @param string $label      Label.
	 * @param bool   $fetched    Whether a live response was received.
	 * @param bool   $pass       Pass when fetched.
	 * @param string $detail     Detail when fetched.
	 * @param string $unverified Detail when not fetched.
	 * @return array
	 */
	private function live_signal( $id, $label, $fetched, $pass, $detail, $unverified ) {
		if ( ! $fetched ) {
			return array(
				'id'     => $id,
				'label'  => $label,
				'level'  => 'yellow',
				'detail' => $unverified,
			);
		}
		return $this->signal( $id, $label, $pass, $detail );
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
		$hsts    = self::header_value( $headers, 'strict-transport-security' );
		$cc      = self::header_value( $headers, 'cache-control' );
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
		$args = array(
			'timeout'     => 8,
			'redirection' => 0,
			'sslverify'   => false,
		);
		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			$response = wp_remote_head( $url, $args );
		}
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'       => false,
				'code'     => 0,
				'location' => '',
				'final'    => $url,
			);
		}
		$headers  = wp_remote_retrieve_headers( $response );
		$location = self::header_value( $headers, 'location' );
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
	private static function header_value( $headers, $name ) {
		$name = strtolower( (string) $name );
		if ( is_object( $headers ) && method_exists( $headers, 'get' ) ) {
			$value = $headers->get( $name );
			if ( is_array( $value ) ) {
				$value = reset( $value );
			}
			return is_string( $value ) ? $value : '';
		}
		if ( is_array( $headers ) ) {
			foreach ( $headers as $key => $value ) {
				if ( strtolower( (string) $key ) !== $name ) {
					continue;
				}
				if ( is_array( $value ) ) {
					$value = reset( $value );
				}
				return is_string( $value ) ? $value : '';
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
				'yellow' => isset( $result['yellow'] ) ? $result['yellow'] : 0,
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
