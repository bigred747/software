<?php
/**
 * CLI checks for Luxe SEO 1.4.1.
 * php tests/test-seo-1-4.php
 */
define( 'ABSPATH', '/tmp/' );
define( 'LUXE_SCORE_REPAIR_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['lsr_opt'] = array();

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $s ) {
		return rtrim( (string) $s, '/' ) . '/';
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $s ) {
		return rtrim( (string) $s, '/' );
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return isset( $GLOBALS['lsr_opt'][ $key ] ) ? $GLOBALS['lsr_opt'][ $key ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = false ) {
		unset( $autoload );
		$GLOBALS['lsr_opt'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://luxetrendsetters.com' . $path;
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

require LUXE_SCORE_REPAIR_DIR . 'includes/class-plugin.php';
require LUXE_SCORE_REPAIR_DIR . 'includes/class-redirects.php';
require LUXE_SCORE_REPAIR_DIR . 'includes/class-robots.php';
require LUXE_SCORE_REPAIR_DIR . 'includes/class-content.php';
require LUXE_SCORE_REPAIR_DIR . 'includes/class-duplicates.php';

$fail = 0;
function expect( $ok, $msg ) {
	global $fail;
	if ( $ok ) {
		echo "OK  $msg\n";
		return;
	}
	$fail++;
	echo "FAIL $msg\n";
}

$php = (string) file_get_contents( LUXE_SCORE_REPAIR_DIR . 'luxe-score-repair.php' );
expect( false !== strpos( $php, 'Version: 1.4.1' ), 'plugin header is 1.4.1' );

$map = Luxe_Score_Repair_Redirects::base_map();
expect( isset( $map['/wp-sitemap.xml'] ) && '/sitemap_index.xml' === $map['/wp-sitemap.xml'], 'wp-sitemap.xml 301s to Rank Math sitemap' );

expect( Luxe_Score_Repair_Redirects::is_gone_path( '/meta.json' ), '/meta.json is 410' );
expect( Luxe_Score_Repair_Redirects::is_gone_path( '/wp-content/plugins/luxe-performance-link-guardian-suite/includes/assets/front.css' ), 'dead plugin CSS is 410' );
expect( Luxe_Score_Repair_Redirects::is_gone_path( '/wp-content/plugins/luxe-performance-link-guardian-suite/includes/assets/clicks.js' ), 'dead plugin JS is 410' );
expect( ! Luxe_Score_Repair_Redirects::is_gone_path( '/product/rolex-watch/' ), 'real products are not 410' );

expect( Luxe_Score_Repair_Redirects::is_forbidden_learn_source( '/product-category/watches/' ), 'learning refuses product-category' );
expect( Luxe_Score_Repair_Redirects::is_forbidden_learn_source( '/product-tag/drones/' ), 'learning refuses product-tag' );
expect( Luxe_Score_Repair_Redirects::is_forbidden_learn_source( '/product/some-asin/' ), 'learning refuses product permalinks' );
expect( Luxe_Score_Repair_Redirects::is_forbidden_learn_source( 'https://www.amazon.com/dp/B0ABCDEF12' ), 'learning refuses Amazon URLs' );
expect( ! Luxe_Score_Repair_Redirects::learn( '/product-category/watches/', '/blogs/' ), 'learn() returns false for product-category' );
expect( Luxe_Score_Repair_Redirects::learn( '/old-contact/', '/welcome-to-our-contact-us-page/' ), 'learn() still allows contact hops' );

$id = Luxe_Score_Repair_Content::associate_identification();
expect( 'As an Amazon Associate I earn from qualifying purchases.' === $id, 'official Associates identification phrase' );
expect( Luxe_Score_Repair_Content::html_has_identification( '<p>' . $id . '</p>' ), 'HTML detector finds official phrase' );

$html = '<html><body><h1>Shop</h1></body></html>';
$out  = Luxe_Score_Repair_Content::ensure_identification_html( $html );
expect( false !== strpos( $out, 'luxe-score-repair-disclosure' ) && false !== strpos( $out, $id ), 'buffer injects visible identification' );
$again = Luxe_Score_Repair_Content::ensure_identification_html( $out );
expect( 1 === substr_count( $again, 'luxe-score-repair-disclosure' ), 'identification paragraph prints once' );

$robots = Luxe_Score_Repair_Robots::clean_body();
expect( false !== strpos( $robots, 'Disallow: /wp-content/plugins/luxe-performance-link-guardian-suite/' ), 'robots.txt disallows dead plugin path' );
expect( false !== strpos( $robots, 'Disallow: /meta.json' ), 'robots.txt disallows meta.json' );
expect( false !== strpos( $robots, 'Sitemap: https://luxetrendsetters.com/sitemap_index.xml' ), 'robots.txt points at Rank Math sitemap' );

expect( 10.0 === Luxe_Score_Repair_Plugin::score_10( 8, 20, 12 ), 'score ignores unverified yellows' );
$yellow_signals = array(
	array(
		'id'    => 'home_title',
		'level' => 'yellow',
	),
	array(
		'id'    => 'robots_file',
		'level' => 'green',
	),
	array(
		'id'    => 'learning',
		'level' => 'green',
	),
);
expect( 'yellow' === Luxe_Score_Repair_Plugin::headers_probe_level( false, true, true ), 'HSTS hidden on loopback is yellow, not red' );
expect( 'green' === Luxe_Score_Repair_Plugin::headers_probe_level( true, true, true ), 'HSTS + public cache is green' );
expect( Luxe_Score_Repair_Plugin::blog_hop_ok( 301, 'https://luxetrendsetters.com/blogs/', '' ), '301 Location to /blogs/ is a pass' );
expect( ! Luxe_Score_Repair_Plugin::blog_hop_ok( 200, '', 'https://luxetrendsetters.com/blog/' ), '200 on /blog/ is not a hop pass' );

$live = array(
	array(
		'id'    => 'home_title',
		'level' => 'green',
	),
	array(
		'id'    => 'home_description',
		'level' => 'green',
	),
	array(
		'id'    => 'schema',
		'level' => 'green',
	),
	array(
		'id'    => 'robots_file',
		'level' => 'green',
	),
	array(
		'id'    => 'robots_public',
		'level' => 'green',
	),
	array(
		'id'    => 'noindex_test',
		'level' => 'green',
	),
	array(
		'id'    => 'alts',
		'level' => 'green',
	),
	array(
		'id'    => 'blog_301',
		'level' => 'green',
	),
	array(
		'id'    => 'copyright_lock',
		'level' => 'green',
	),
	array(
		'id'    => 'unique_copy',
		'level' => 'green',
	),
);
expect( '95-100-ready' === Luxe_Score_Repair_Plugin::seo_band( $live ), 'live all-green is still 95-100-ready' );

expect( array() === Luxe_Score_Repair_Duplicates::groups(), 'duplicate title scan is empty without WordPress' );

if ( $fail ) {
	echo "FAILED $fail\n";
	exit( 1 );
}
echo "ALL PASSED\n";
exit( 0 );
