<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin contract. These signals do not call Google or Amazon.
 * A green row means this plugin's own output follows that rule.
 */
class Luxe_Google_Presence_Contract {

	/**
	 * @return array
	 */
	public static function evaluate() {
		$href = 'https://www.amazon.com/dp/B0ABCDEF12?tag=luxetrendsetters-20&linkCode=ll1';
		$tag  = '<a href="' . $href . '" rel="noopener">Buy</a>';
		$qualified = Luxe_Google_Presence_Anchors::qualify( $tag );
		$image = '<img src="https://m.media-amazon.com/images/I/example.jpg" alt="">';
		$aside = Luxe_Google_Presence_Copy::aside( 'https://luxetrendsetters.com/' );
		$css   = self::css();
		$with_view = '<html><head><meta name="viewport" content="width=device-width, initial-scale=1" /></head></html>';
		$no_view   = '<html><head><title>Example</title></head></html>';
		$shop_at   = strpos( $aside, 'https://luxetrendsetters.com/shop/' );
		$disc_at   = strpos( $aside, Luxe_Google_Presence_Copy::DISCLOSURE );
		$with_kept = Luxe_Google_Presence_Anchors::ensure_viewport( $with_view );
		$view_added = Luxe_Google_Presence_Anchors::ensure_viewport( $no_view );
		$verify_ok  = '' === Luxe_Google_Presence_Anchors::verification_tag( '<script>alert(1)</script>' )
			&& false !== strpos( Luxe_Google_Presence_Anchors::verification_tag( 'google-site-verification-token' ), 'content="google-site-verification-token"' );
		$blocked   = Luxe_Google_Presence_Copy::internal_links(
			'https://luxetrendsetters.com/',
			array(
				array(
					'url'   => 'https://www.amazon.com/dp/B0ABCDEF12?tag=luxetrendsetters-20',
					'label' => 'Amazon',
				),
				array(
					'url'   => 'javascript:alert(1)',
					'label' => 'Script',
				),
				array(
					'url'   => 'https://luxetrendsetters.com/blogs/',
					'label' => 'Buyer guides',
				),
			)
		);

		$signals = array(
			Luxe_Google_Presence_Score::signal( 'href_intact', 'Amazon URL is unchanged', false !== strpos( $qualified, 'href="' . $href . '"' ), 'Associate tag and query stay on the existing button' ),
			Luxe_Google_Presence_Score::signal( 'sponsored', 'Affiliate links use rel sponsored', false !== strpos( $qualified, 'rel="noopener sponsored"' ) && false === strpos( $qualified, 'noreferrer' ), 'Google paid-link qualification without a new noreferrer' ),
			Luxe_Google_Presence_Score::signal( 'image_host', 'Amazon image host is left alone', $image === Luxe_Google_Presence_Anchors::qualify( $image ) && ! Luxe_Google_Presence_Anchors::is_amazon_href( 'https://m.media-amazon.com/images/I/example.jpg' ), 'media-amazon.com is not treated as a shop link' ),
			Luxe_Google_Presence_Score::signal( 'disclosure', 'Official Associate sentence', false !== strpos( $aside, Luxe_Google_Presence_Copy::DISCLOSURE ), Luxe_Google_Presence_Copy::DISCLOSURE ),
			Luxe_Google_Presence_Score::signal( 'price', 'Official price disclaimer', false !== strpos( $aside, Luxe_Google_Presence_Copy::PRICE ), 'Shown next to the buy instruction' ),
			Luxe_Google_Presence_Score::signal( 'paid_label', 'Paid-link label near the button', false !== strpos( $aside, Luxe_Google_Presence_Copy::PAID ), Luxe_Google_Presence_Copy::PAID ),
			Luxe_Google_Presence_Score::signal( 'onsite_first', 'On-site guides come before Amazon', false !== $shop_at && false !== $disc_at && $shop_at < $disc_at, 'Shop, guides, and resources are internal links' ),
			Luxe_Google_Presence_Score::signal( 'no_new_amazon_href', 'Plugin does not mint Amazon URLs', ! preg_match( '/<a\b[^>]*href\s*=\s*(\'|")[^\'"]*amazon\./i', $aside ), 'The existing product button remains the only shop link' ),
			Luxe_Google_Presence_Score::signal( 'blocked_offsite', 'Compare links stay on this host', 1 === count( $blocked ) && false !== strpos( $blocked[0]['url'], 'luxetrendsetters.com/blogs/' ), 'Amazon and script URLs are dropped from the compare list' ),
			Luxe_Google_Presence_Score::signal( 'no_popup', 'No pop-up or pop-under', false === stripos( $aside, 'popup' ) && false === stripos( $css, 'popunder' ), 'Amazon Special Links stay in the page' ),
			Luxe_Google_Presence_Score::signal( 'no_frame', 'No Amazon iframe', false === stripos( $aside, '<iframe' ), 'The Amazon site is not framed' ),
			Luxe_Google_Presence_Score::signal( 'no_refresh', 'No automatic Amazon redirect', false === stripos( $aside, 'refresh' ) && false === stripos( $css, 'meta refresh' ), 'A shopper has to click the existing button' ),
			Luxe_Google_Presence_Score::signal( 'no_incentive', 'No reward for clicking', false === stripos( $aside, 'click to support' ) && false === stripos( $aside, 'reward' ) && false === stripos( $aside, 'discount for clicking' ), 'The click is for shopping, not a prize' ),
			Luxe_Google_Presence_Score::signal( 'mobile', 'Mobile layout', false !== strpos( $css, '@media (max-width: 782px)' ) && false !== strpos( $css, 'min-height: 48px' ) && false !== strpos( $css, 'font-size: 16px' ), '48px product controls, 16px fields, safe-area padding' ),
			Luxe_Google_Presence_Score::signal( 'computer', 'Computer layout', false !== strpos( $css, '@media (min-width: 783px)' ) && false !== strpos( $css, 'overflow-x: clip' ), 'Same HTML, wider compare block, no sideways scroll' ),
			Luxe_Google_Presence_Score::signal( 'viewport', 'Viewport is added only when missing', $with_kept === $with_view && 1 === substr_count( $view_added, 'name="viewport"' ), 'A second viewport meta is not printed' ),
			Luxe_Google_Presence_Score::signal( 'short_links', 'Short Amazon links qualify, image CDNs do not', Luxe_Google_Presence_Anchors::is_amazon_href( 'https://amzn.to/abc' ) && Luxe_Google_Presence_Anchors::is_amazon_href( 'https://a.co/d/abc' ) && ! Luxe_Google_Presence_Anchors::is_amazon_href( 'https://s3.amazonaws.com/bucket/file' ) && ! Luxe_Google_Presence_Anchors::is_amazon_href( 'https://images-na.ssl-images-amazon.com/images/I/x.jpg' ), 'amzn.to and a.co are shop links' ),
			Luxe_Google_Presence_Score::signal( 'verification', 'Search Console token is escaped', $verify_ok, 'Paste the token under Tools. Markup in the token is dropped' ),
			Luxe_Google_Presence_Score::signal( 'no_cloaking', 'One document for every visitor', false === strpos( $css, 'googlebot' ) && ! self::builder_reads_user_agent(), 'Computer and mobile share the compare block' ),
			Luxe_Google_Presence_Score::signal( 'scores', 'Product Scout scores stay untouched', ! Luxe_Google_Presence_Compat::we_write( 'product_scores' ), 'Readiness and integrity meta are not written' ),
			Luxe_Google_Presence_Score::signal( 'taxonomy', 'Product Integrity repairs stay untouched', ! Luxe_Google_Presence_Compat::we_write( 'taxonomy_repair' ), 'Taxonomies and brand attributes are not written' ),
			Luxe_Google_Presence_Score::signal( 'href_policy', 'Amazon hrefs are never rewritten', ! Luxe_Google_Presence_Compat::we_write( 'amazon_href' ), 'Only rel=sponsored is added' ),
			Luxe_Google_Presence_Score::signal( 'posts', 'Posts are not created or rewritten', ! Luxe_Google_Presence_Compat::we_write( 'post_content' ) && ! Luxe_Google_Presence_Compat::we_write( 'post_status' ), 'No post content and no status changes' ),
			Luxe_Google_Presence_Score::signal( 'titles', 'Titles stay with Score Repair and Rank Math', ! Luxe_Google_Presence_Compat::we_write( 'titles' ), 'This plugin does not filter document titles' ),
			Luxe_Google_Presence_Score::signal( 'robots', 'robots.txt stays with Score Repair', ! Luxe_Google_Presence_Compat::we_write( 'robots_file' ), 'The physical robots file is not overwritten' ),
			Luxe_Google_Presence_Score::signal( 'schema', 'Schema stays with Score Repair', ! Luxe_Google_Presence_Compat::we_write( 'schema' ), 'No review or rating markup is added' ),
			Luxe_Google_Presence_Score::signal( 'sitemap', 'Rank Math sitemap stays in place', ! Luxe_Google_Presence_Compat::we_write( 'rank_math_sitemap' ), 'sitemap_index.xml is not replaced' ),
			Luxe_Google_Presence_Score::signal( 'related', 'Scout related products stay in place', ! Luxe_Google_Presence_Compat::we_write( 'related_products' ), 'WooCommerce related IDs are not filtered' ),
			Luxe_Google_Presence_Score::signal( 'indexing_api', 'Google Indexing API is not used', ! Luxe_Google_Presence_Compat::we_write( 'indexing_api' ), 'That API is for jobs and livestreams, not this shop' ),
			Luxe_Google_Presence_Score::signal( 'no_purge', 'Front-end views do not flush cache', ! Luxe_Google_Presence_Compat::we_write( 'frontend_purge' ), 'LiteSpeed is not purged on public page views' ),
		);

		return Luxe_Google_Presence_Score::decide(
			$signals,
			array(
				'href_intact',
				'sponsored',
				'disclosure',
				'price',
				'no_popup',
				'no_frame',
				'no_refresh',
				'no_incentive',
				'no_cloaking',
				'no_new_amazon_href',
				'indexing_api',
				'href_policy',
			)
		);
	}

	/**
	 * @return string
	 */
	public static function css() {
		$path = dirname( __DIR__ ) . '/assets/device.css';
		if ( ! is_readable( $path ) ) {
			return '';
		}
		$css = file_get_contents( $path );
		return is_string( $css ) ? $css : '';
	}

	/**
	 * @return bool
	 */
	private static function builder_reads_user_agent() {
		$source = file_get_contents( __DIR__ . '/class-copy.php' );
		if ( ! is_string( $source ) ) {
			return true;
		}
		return false !== stripos( $source, 'HTTP_USER_AGENT' );
	}
}
