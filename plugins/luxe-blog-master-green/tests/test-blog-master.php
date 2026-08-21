<?php
/**
 * CLI checks for Luxe Blog Master Green 2.9.0.
 * php tests/test-blog-master.php
 */
define( 'ABSPATH', '/tmp/' );
define( 'LUXE_BMG_DIR', dirname( __DIR__ ) . '/' );
define( 'LUXE_BMG_VERSION', '2.9.0' );

$GLOBALS['lbg_opt'] = array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return isset( $GLOBALS['lbg_opt'][ $key ] ) ? $GLOBALS['lbg_opt'][ $key ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = false ) {
		unset( $autoload );
		$GLOBALS['lbg_opt'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return trim( preg_replace( '/<[^>]*>/', '', (string) $text ) );
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://luxetrendsetters.com' . $path;
	}
}

require LUXE_BMG_DIR . 'includes/class-plugin.php';
require LUXE_BMG_DIR . 'includes/class-scan.php';

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

$header = (string) file_get_contents( LUXE_BMG_DIR . 'luxe-blog-master-green.php' );
expect( false !== strpos( $header, 'Version: 2.9.0' ), 'header is 2.9.0' );
expect( false !== strpos( $header, 'Never publishes' ), 'header restates no-publish' );
expect( 25 === count( Luxe_BMG_Plugin::keep_processes() ), '25 keep-processes preserved' );
expect( 10833 === Luxe_BMG_Plugin::MASTER, 'master ID 10833 locked' );
expect( Luxe_BMG_Scan::is_master( 10833 ), 'is_master detects 10833' );
expect( ! Luxe_BMG_Scan::is_master( 10493 ), 'other posts are not master' );

expect( 60 === Luxe_BMG_Scan::cap_product_zero( 0, 100 ), 'Product #0 cannot become 95-100' );
expect( 60 === Luxe_BMG_Scan::cap_product_zero( 0, 60 ), 'Product #0 cap is 60' );
expect( 100 === Luxe_BMG_Scan::cap_product_zero( 62970, 100 ), 'locked product can be 100' );

expect( Luxe_BMG_Scan::is_truncated( 'Seiko Watch | Pr' ), 'detects | Pr' );
expect( ! Luxe_BMG_Scan::is_truncated( 'MacBook Pro M2 Pro Review 2026: Best Apple Laptop Buyer Guide' ), 'complete title is clean' );

$html = '<html><head><title>Premium Buyer Guides</title><meta name="viewport" content="width=device-width"></head><body><p>As an Amazon Associate I earn from qualifying purchases.</p><a href="https://www.amazon.com/dp/B0TEST/?tag=luxetrendse0f-20">View on Amazon</a> buyer guide review</body></html>';
expect( 1 === Luxe_BMG_Scan::disclosure_count( $html ), 'one disclosure' );
expect( Luxe_BMG_Scan::amazon_tag_ok( $html ), 'tag lock ok' );
expect( Luxe_BMG_Scan::no_plugin_assets( $html ), 'no frontend plugin JS' );
expect( Luxe_BMG_Scan::homepage_untouched( $html ), 'no homepage overlay' );
expect( Luxe_BMG_Scan::flatsome_menu_safe(), 'Flatsome hiders-off is green' );

$scored = Luxe_BMG_Scan::score_public(
	array(
		'ok'   => true,
		'code' => 200,
		'body' => $html,
	)
);
expect( ! empty( $scored['all'] ), 'fixture public gates all green' );
expect( 100 === $scored['score'], 'fixture scores 100' );

expect( 10 === Luxe_BMG_Plugin::score_10( 34, 34, 0, 0 ), 'all green is 10' );
expect( 10 === Luxe_BMG_Plugin::score_10( 32, 34, 2, 0 ), 'yellow-only is still 10' );

$php_files = glob( LUXE_BMG_DIR . 'includes/*.php' );
$php_files[] = LUXE_BMG_DIR . 'luxe-blog-master-green.php';
foreach ( $php_files as $file ) {
	$src = (string) file_get_contents( $file );
	expect( false === strpos( $src, 'wp_insert_post' ), basename( $file ) . ' does not insert posts' );
	expect( false === strpos( $src, 'wp_delete_post' ), basename( $file ) . ' does not delete posts' );
	expect( false === strpos( $src, 'wp_update_post' ), basename( $file ) . ' does not update posts' );
	expect( false === strpos( $src, 'wp_enqueue_script' ), basename( $file ) . ' does not enqueue public JS' );
}

if ( $fail ) {
	echo "FAILED $fail\n";
	exit( 1 );
}
echo "ALL OK\n";
