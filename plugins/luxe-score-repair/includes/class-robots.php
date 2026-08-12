<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replace the 110KB Autopilot robots.txt with a short valid file.
 */
class Luxe_Score_Repair_Robots {

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
	 * Last writer wins.
	 */
	public function boot() {
		add_filter( 'robots_txt', array( $this, 'robots_txt' ), 99999, 2 );
	}

	/**
	 * @param string $output Current robots.txt.
	 * @param bool   $public Blog public.
	 * @return string
	 */
	public function robots_txt( $output, $public ) {
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_robots_txt' ) ) {
			return $output;
		}
		if ( ! $public ) {
			return "User-agent: *\nDisallow: /\n";
		}
		$sitemap = home_url( '/sitemap_index.xml' );
		$lines   = array(
			'User-agent: *',
			'Allow: /',
			'Disallow: /wp-admin/',
			'Allow: /wp-admin/admin-ajax.php',
			'Disallow: /cart/',
			'Disallow: /checkout/',
			'Disallow: /my-account/',
			'Disallow: /wishlist/',
			'Disallow: /wish-list/',
			'Disallow: /client-portal/',
			'Disallow: /test-blog-page/',
			'',
			'User-agent: GPTBot',
			'Allow: /',
			'',
			'User-agent: Google-Extended',
			'Allow: /',
			'',
			'Sitemap: ' . $sitemap,
			'',
		);
		return implode( "\n", $lines );
	}
}
