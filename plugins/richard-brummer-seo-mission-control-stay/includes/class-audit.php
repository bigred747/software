<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bounded public-HTML audit. Does not rewrite posts.
 */
class RBSMC_Stay_Audit {

	/**
	 * Run homepage/cart/robots checks and store evidence.
	 *
	 * @return array
	 */
	public function run() {
		$home = home_url( '/' );
		$cart = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );

		$home_html = $this->fetch( $home );
		$cart_html = $this->fetch( $cart );
		$robots    = $this->fetch( home_url( '/robots.txt' ) );

		$signals = array();
		$signals[] = $this->signal_guest_vary( $home_html );
		$signals[] = $this->signal_history_traps( $home_html );
		$signals[] = $this->signal_leaked_shortcodes( $home_html, $cart_html );
		$signals[] = $this->signal_ai_filler( $cart_html );
		$signals[] = $this->signal_gsc_zero();
		$signals[] = $this->signal_woocommerce_affiliate();
		$signals[] = $this->signal_robots( $robots );
		$signals[] = $this->signal_404_volume();

		$red = 0;
		foreach ( $signals as $signal ) {
			if ( isset( $signal['level'] ) && 'red' === $signal['level'] ) {
				$red++;
			}
		}

		$result = array(
			'ran_at'     => time(),
			'home_url'   => $home,
			'home_bytes' => strlen( $home_html ),
			'red_count'  => $red,
			'signals'    => $signals,
		);
		update_option( RBSMC_Stay_Plugin::OPTION_AUDIT, $result, false );
		return $result;
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	private function fetch( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 12,
				'redirection' => 3,
				'sslverify'   => true,
				'headers'     => array(
					'User-Agent' => 'RBSMC-StayRepair/1.9.0; ' . home_url( '/' ),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return '';
		}
		$body = wp_remote_retrieve_body( $response );
		return is_string( $body ) ? $body : '';
	}

	/**
	 * LiteSpeed Guest Mode reload is the main 1-second dwell killer.
	 *
	 * @param string $html HTML.
	 * @return array
	 */
	private function signal_guest_vary( $html ) {
		$found = ( false !== strpos( $html, 'guest.vary.php' ) || false !== strpos( $html, 'litespeed_reloaded' ) );
		return array(
			'id'      => 'litespeed-guest-vary',
			'level'   => $found ? 'red' : 'green',
			'title'   => $found ? 'LiteSpeed Guest Mode reload is active' : 'No LiteSpeed Guest Mode reload script',
			'detail'  => $found
				? 'The public HTML includes guest.vary.php and window.location.reload. First-time visitors get a 1-second hit, then a reload. That matches Site Kit Avg. Time on Page of 1s. Disable LiteSpeed Cache → Cache → Guest Mode, then purge cache. Stay Repair can also inject a shield so the reload is skipped.'
				: 'Guest Mode reload script was not found in the homepage HTML.',
			'scored'  => true,
		);
	}

	/**
	 * @param string $html HTML.
	 * @return array
	 */
	private function signal_history_traps( $html ) {
		$trap = (bool) preg_match( '/history\.(pushState|replaceState|go\s*\(\s*1\s*\))|addEventListener\(\s*[\'"]popstate/i', $html );
		return array(
			'id'     => 'history-traps',
			'level'  => $trap ? 'yellow' : 'green',
			'title'  => $trap ? 'History/popstate code is present' : 'No back-button trap pattern found',
			'detail' => $trap
				? 'History API code is on the page. Stay Repair will not add back-button hijacking. Old 5–15 minute averages from traps are not restored. Real stay time comes from readable guides and related links.'
				: 'No pushState/popstate trap signature was found in homepage HTML.',
			'scored' => false,
		);
	}

	/**
	 * @param string $home Home HTML.
	 * @param string $cart Cart HTML.
	 * @return array
	 */
	private function signal_leaked_shortcodes( $home, $cart ) {
		$blob  = $home . "\n" . $cart;
		$found = (bool) preg_match( '/\[(\/?ez-?toc|toc)[^\]]*\]/i', $blob );
		return array(
			'id'     => 'leaked-shortcodes',
			'level'  => $found ? 'yellow' : 'green',
			'title'  => $found ? 'Leaked [toc] / [ez-toc] shortcodes in HTML' : 'No leaked TOC shortcodes in sampled HTML',
			'detail' => $found
				? 'Raw shortcodes in public HTML look broken and cause instant exits. Stay Repair hides them on output and does not rewrite the database.'
				: 'Sampled pages do not show leaked TOC shortcodes.',
			'scored' => true,
		);
	}

	/**
	 * @param string $cart Cart HTML.
	 * @return array
	 */
	private function signal_ai_filler( $cart ) {
		$found = ( false !== stripos( $cart, 'expanded automatically' ) || false !== stripos( $cart, 'Expanded Analysis' ) );
		return array(
			'id'     => 'utility-ai-filler',
			'level'  => $found ? 'red' : 'green',
			'title'  => $found ? 'AI filler is rendering on a utility page' : 'No AI filler found on cart sample',
			'detail' => $found
				? 'Cart/account/checkout pages contain “expanded automatically” filler. That is thin SEO spam on pages shoppers should trust. Stay Repair hides it on those templates only.'
				: 'Cart sample does not contain the automatic expansion filler.',
			'scored' => true,
		);
	}

	/**
	 * Rank Math 0 / Site Kit mismatch.
	 *
	 * @return array
	 */
	private function signal_gsc_zero() {
		$rank_math = defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' );
		$site_kit  = defined( 'GOOGLESITEKIT_VERSION' ) || class_exists( 'Google\Site_Kit\Plugin' );
		$snapshot  = get_option( RBSMC_Stay_Plugin::OPTION_SNAPSHOT, array() );
		$impr      = isset( $snapshot['impressions'] ) ? absint( $snapshot['impressions'] ) : 0;
		$clicks    = isset( $snapshot['clicks'] ) ? absint( $snapshot['clicks'] ) : 0;
		$users     = isset( $snapshot['users'] ) ? absint( $snapshot['users'] ) : 0;

		$level  = 'info';
		$title  = 'Search Console widgets need a matching-period snapshot';
		$detail = 'Enter the Site Kit / Rank Math 28-day numbers on the Stay Repair screen. Rank Math Overview showing 0 while Site Kit shows impressions usually means Rank Math is not connected to Search Console, not that Google has zero data.';

		if ( $users > 1000 && $impr < 100 && 0 === $clicks ) {
			$level  = 'red';
			$title  = 'Traffic is not coming from Google Search';
			$detail = 'Saved snapshot: ' . $users . ' users, ' . $impr . ' impressions, ' . $clicks . ' clicks. Almost all visits are Direct/referral/social/bots, not Search. Connect Rank Math to Search Console if that widget is 0, keep Site Kit as the measurement source, and fix dwell time before expecting clicks.';
		} elseif ( $rank_math && $site_kit ) {
			$level = 'yellow';
			$title = 'Rank Math and Site Kit are both active';
			$detail = 'Use one Search Console property. If Rank Math Overview is all zeros, open Rank Math → General Settings → Analytics and connect the same property Site Kit uses.';
		}

		return array(
			'id'     => 'gsc-zero',
			'level'  => $level,
			'title'  => $title,
			'detail' => $detail,
			'scored' => false,
		);
	}

	/**
	 * Affiliate sites should not expect WooCommerce net sales.
	 *
	 * @return array
	 */
	private function signal_woocommerce_affiliate() {
		$woo = class_exists( 'WooCommerce' );
		return array(
			'id'     => 'woocommerce-sales',
			'level'  => 'info',
			'title'  => $woo ? 'WooCommerce $0 sales is expected on an Amazon affiliate catalog' : 'WooCommerce is not active',
			'detail' => $woo
				? 'LuxeTrendsetters sends shoppers to Amazon. WooCommerce net sales staying at $0 does not mean the catalog is broken. Watch Amazon Associate reports, not WooCommerce orders.'
				: 'WooCommerce was not detected.',
			'scored' => false,
		);
	}

	/**
	 * @param string $robots robots.txt body.
	 * @return array
	 */
	private function signal_robots( $robots ) {
		$blocked = (bool) preg_match( '/Disallow:\s*\/\s*$/m', $robots );
		return array(
			'id'     => 'robots',
			'level'  => $blocked ? 'red' : 'green',
			'title'  => $blocked ? 'robots.txt appears to block the whole site' : 'robots.txt is not a full-site Disallow',
			'detail' => $blocked
				? 'A root Disallow would explain zero Search Console clicks. Remove the sitewide block and resubmit sitemaps.'
				: ( $robots ? 'robots.txt was fetched and is not a full-site block.' : 'robots.txt could not be fetched during this cycle.' ),
			'scored' => true,
		);
	}

	/**
	 * @return array
	 */
	private function signal_404_volume() {
		$log   = get_option( RBSMC_Stay_Plugin::OPTION_404, array() );
		$count = is_array( $log ) ? count( $log ) : 0;
		$hits  = 0;
		if ( is_array( $log ) ) {
			foreach ( $log as $row ) {
				$hits += isset( $row['hits'] ) ? absint( $row['hits'] ) : 0;
			}
		}
		$snapshot = get_option( RBSMC_Stay_Plugin::OPTION_SNAPSHOT, array() );
		$dash     = isset( $snapshot['not_found'] ) ? absint( $snapshot['not_found'] ) : 0;
		$level    = ( $dash >= 50 || $hits >= 50 ) ? 'yellow' : 'green';
		return array(
			'id'     => '404-volume',
			'level'  => $level,
			'title'  => '404 monitor: ' . $dash . ' Rank Math URLs, ' . $count . ' Stay Repair logged paths',
			'detail' => 'Rank Math shows 63 logged 404 URLs on the dashboard snapshot. Stay Repair logs new 404s and shows a helpful recovery page. It does not auto-create redirects unless you enable that unsafe option.',
			'scored' => false,
		);
	}
}
