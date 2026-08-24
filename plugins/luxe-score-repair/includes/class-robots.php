<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replace bloated Autopilot robots.txt in WordPress output and on disk.
 * Hostinger serves a physical robots.txt, so the filter alone cannot win.
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
	 * Last writer wins for the WP filter. File heal is separate.
	 */
	public function boot() {
		add_filter( 'robots_txt', array( $this, 'robots_txt' ), 99999, 2 );
		add_action( 'init', array( $this, 'maybe_serve' ), 0 );
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
		return self::clean_body();
	}

	/**
	 * If WordPress receives /robots.txt, print the clean file and stop.
	 */
	public function maybe_serve() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_robots_txt' ) ) {
			return;
		}
		$path = Luxe_Score_Repair_Plugin::request_path();
		if ( '/robots.txt' !== $path ) {
			return;
		}
		self::heal_file();
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Cache-Control: public, max-age=300' );
		status_header( 200 );
		echo self::clean_body();
		exit;
	}

	/**
	 * Short valid robots.txt. Rank Math sitemap plus search-focus sitemap.
	 *
	 * @return string
	 */
	public static function clean_body() {
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
		);
		if ( Luxe_Score_Repair_Plugin::instance()->enabled( 'search_focus' ) ) {
			$lines[] = 'Sitemap: ' . home_url( Luxe_Score_Repair_Search_Focus::SITEMAP_PATH );
		}
		$lines[] = '';
		return implode( "\n", $lines );
	}

	/**
	 * @param string $text File body.
	 * @return bool
	 */
	public static function is_bloated( $text ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return true;
		}
		if ( strlen( $text ) > 2048 ) {
			return true;
		}
		if ( substr_count( $text, 'END Luxe AI Readiness' ) > 1 ) {
			return true;
		}
		if ( false !== strpos( $text, 'Sitemap: ' . home_url( '/sitemap.xml' ) ) && false === strpos( $text, 'sitemap_index.xml' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Absolute path of the physical robots.txt Hostinger serves.
	 *
	 * @return string
	 */
	public static function file_path() {
		return trailingslashit( ABSPATH ) . 'robots.txt';
	}

	/**
	 * Overwrite the physical file when it is missing or bloated.
	 *
	 * @return array{changed:bool,bytes:int,path:string,error:string}
	 */
	public static function heal_file() {
		$path = self::file_path();
		$body = self::clean_body();
		$now  = is_readable( $path ) ? (string) file_get_contents( $path ) : '';
		if ( $now === $body ) {
			return array(
				'changed' => false,
				'bytes'   => strlen( $body ),
				'path'    => $path,
				'error'   => '',
			);
		}
		if ( self::is_bloated( $now ) && is_readable( $path ) && ! file_exists( $path . '.lsr-bak' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
			@copy( $path, $path . '.lsr-bak' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$ok = @file_put_contents( $path, $body, LOCK_EX );
		return array(
			'changed' => ( false !== $ok ),
			'bytes'   => strlen( $body ),
			'path'    => $path,
			'error'   => ( false === $ok ) ? 'write-failed' : '',
		);
	}
}
