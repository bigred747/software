<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bounded rendered-page audit.
 */
class RBSMC_Audit {

	/**
	 * @return array
	 */
	public function run() {
		$home = home_url( '/' );
		$cart = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
		$html = $this->fetch( $home );
		$cart_html = $this->fetch( $cart );
		$robots = $this->fetch( home_url( '/robots.txt' ) );

		$signals = array();
		$signals = array_merge( $signals, $this->technical( $html, $robots ) );
		$signals = array_merge( $signals, $this->stay( $html, $cart_html ) );
		$amazon  = RBSMC_Amazon::audit_html( $html, $home );
		$schema  = RBSMC_Schema::audit_html( $html );
		$compat  = RBSMC_Compat::inventory();
		$gsc     = RBSMC_GSC::status();
		$signals = array_merge( $signals, $amazon['signals'], $schema['signals'], $compat['signals'], $gsc['signals'] );

		$score  = RBSMC_Score::summarize( $signals );
		$result = array(
			'ran_at'     => time(),
			'home_url'   => $home,
			'home_bytes' => strlen( $html ),
			'score'      => $score,
			'signals'    => $signals,
			'amazon'     => $amazon,
			'schema'     => $schema,
			'compat'     => array(
				'catalog_size' => $compat['catalog_size'],
				'active'       => $compat['active'],
				'mu'           => $compat['mu'],
				'dropins'      => $compat['dropins'],
			),
			'gsc'        => $gsc,
		);
		update_option( RBSMC_Plugin::OPTION_AUDIT, $result, false );

		$front_id = (int) get_option( 'page_on_front' );
		if ( $front_id ) {
			update_post_meta( $front_id, '_rbsmc_last_audit', wp_json_encode( $result ) );
		}
		return $result;
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	private function fetch( $url ) {
		$r = wp_remote_get(
			$url,
			array(
				'timeout'     => 12,
				'redirection' => 3,
				'headers'     => array( 'User-Agent' => 'RBSMC/1.9.0; ' . home_url( '/' ) ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return '';
		}
		$body = wp_remote_retrieve_body( $r );
		return is_string( $body ) ? $body : '';
	}

	/**
	 * @param string $html HTML.
	 * @param string $robots robots.txt.
	 * @return array
	 */
	private function technical( $html, $robots ) {
		$signals = array();
		$https   = ( 0 === strpos( home_url( '/' ), 'https://' ) );
		$signals[] = array(
			'level'  => $https ? 'green' : 'red',
			'title'  => $https ? 'HTTPS is on' : 'Site is not HTTPS',
			'detail' => home_url( '/' ),
			'scored' => true,
		);
		$blog_public = ( '1' === (string) get_option( 'blog_public' ) );
		$signals[]   = array(
			'level'  => $blog_public ? 'green' : 'red',
			'title'  => $blog_public ? 'Search engines are allowed' : 'Discourage search engines is enabled',
			'detail' => 'Settings → Reading',
			'scored' => true,
		);
		$blocked = (bool) preg_match( '/Disallow:\s*\/\s*$/m', $robots );
		$signals[] = array(
			'level'  => $blocked ? 'red' : 'green',
			'title'  => $blocked ? 'robots.txt blocks the whole site' : 'robots.txt is not a full-site Disallow',
			'detail' => $robots ? 'fetched' : 'robots.txt empty or missing',
			'scored' => true,
		);
		$title = '';
		if ( preg_match( '/<title>(.*?)<\/title>/is', $html, $m ) ) {
			$title = wp_strip_all_tags( $m[1] );
		}
		$h1s = preg_match_all( '/<h1\b/i', $html, $unused );
		$signals[] = array(
			'level'  => 'info',
			'title'  => 'Title / H1 inventory (not a ranking failure)',
			'detail' => 'title_len=' . strlen( $title ) . ' h1_count=' . (int) $h1s,
			'scored' => false,
		);
		$loop = $this->loopback();
		$signals[] = array(
			'level'  => 'info',
			'title'  => 'WordPress loopback duration (not PageSpeed, TTFB, CrUX, or Core Web Vitals)',
			'detail' => $loop . ' ms',
			'scored' => false,
		);
		$social = RBSMC_Plugin::instance()->settings();
		if ( empty( $social['social_facebook'] ) && empty( $social['social_instagram'] ) ) {
			$signals[] = array(
				'level'  => 'info',
				'title'  => 'Missing optional social-profile URLs',
				'detail' => 'Unscored INFO',
				'scored' => false,
			);
		}
		$updates = get_site_transient( 'update_plugins' );
		$signals[] = array(
			'level'  => 'info',
			'title'  => 'Automatic-update coverage',
			'detail' => is_object( $updates ) ? 'WordPress update metadata present' : 'Update metadata not loaded this cycle',
			'scored' => false,
		);
		if ( class_exists( 'WooCommerce' ) ) {
			$signals[] = array(
				'level'  => 'yellow',
				'title'  => 'WooCommerce template version headers are a compatibility risk when older than core',
				'detail' => 'YELLOW compatibility risk, not a claim the store is broken. WooCommerce $0 sales is expected on an Amazon affiliate catalog.',
				'scored' => true,
			);
		}
		return $signals;
	}

	/**
	 * @param string $html Home HTML.
	 * @param string $cart Cart HTML.
	 * @return array
	 */
	private function stay( $html, $cart ) {
		$signals = array();
		$guest   = ( false !== strpos( $html, 'guest.vary.php' ) || false !== strpos( $html, 'litespeed_reloaded' ) );
		$signals[] = array(
			'level'  => $guest ? 'red' : 'green',
			'title'  => $guest ? 'LiteSpeed Guest Mode reload is destroying time-on-page' : 'No Guest Mode reload script',
			'detail' => $guest ? 'Disable LiteSpeed Cache → Cache → Guest Mode, purge cache. Stay Repair shield skips the first-visit reload.' : 'OK',
			'scored' => true,
		);
		$trap = (bool) preg_match( '/history\.(pushState|go\s*\(\s*1\s*\))|addEventListener\(\s*[\'"]popstate/i', $html );
		$signals[] = array(
			'level'  => 'info',
			'title'  => $trap ? 'history/popstate code present (not a hard ranking failure)' : 'No back-button trap signature',
			'detail' => 'Mission Control will not restore fake 5–15 minute sessions with back-button hijacking.',
			'scored' => false,
		);
		$toc = (bool) preg_match( '/\[(\/?ez-?toc|toc)[^\]]*\]/i', $html . $cart );
		$signals[] = array(
			'level'  => $toc ? 'yellow' : 'green',
			'title'  => $toc ? 'Leaked TOC shortcodes in HTML' : 'No leaked TOC shortcodes in sample',
			'detail' => 'Output-only hide is enabled by default.',
			'scored' => true,
		);
		$filler = ( false !== stripos( $cart, 'expanded automatically' ) );
		$signals[] = array(
			'level'  => $filler ? 'red' : 'green',
			'title'  => $filler ? 'AI filler on a utility page' : 'No AI filler on cart sample',
			'detail' => $filler ? 'Cart/account filler causes instant exits. Hidden on those templates only.' : 'OK',
			'scored' => true,
		);
		$snap = get_option( RBSMC_Plugin::OPTION_SNAPSHOT, array() );
		$users = isset( $snap['users'] ) ? absint( $snap['users'] ) : 0;
		$impr  = isset( $snap['impressions'] ) ? absint( $snap['impressions'] ) : 0;
		$clicks = isset( $snap['clicks'] ) ? absint( $snap['clicks'] ) : 0;
		if ( $users > 1000 && $impr < 100 ) {
			$signals[] = array(
				'level'  => 'red',
				'title'  => 'Traffic is not coming from Google Search',
				'detail' => $users . ' users vs ' . $impr . ' impressions and ' . $clicks . ' clicks. Rank Math zeros are a disconnected widget if Site Kit already has impressions.',
				'scored' => true,
			);
		}
		return $signals;
	}

	/**
	 * Loopback timing in ms.
	 *
	 * @return int
	 */
	private function loopback() {
		$start = microtime( true );
		wp_remote_get(
			home_url( '/?rbsmc-loopback=1' ),
			array(
				'timeout' => 8,
				'headers' => array( 'User-Agent' => 'RBSMC-loopback' ),
			)
		);
		return (int) round( ( microtime( true ) - $start ) * 1000 );
	}
}
