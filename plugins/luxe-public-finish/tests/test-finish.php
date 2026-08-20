<?php
/**
 * CLI checks for Luxe Public Finish 1.0.0.
 * php tests/test-finish.php
 */
define( 'ABSPATH', '/tmp/' );
define( 'LUXE_PUBLIC_FINISH_DIR', dirname( __DIR__ ) . '/' );
define( 'LUXE_PUBLIC_FINISH_VERSION', '1.0.0' );

$GLOBALS['lpf_opt'] = array();

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $s ) {
		return rtrim( (string) $s, '/' ) . '/';
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return isset( $GLOBALS['lpf_opt'][ $key ] ) ? $GLOBALS['lpf_opt'][ $key ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = false ) {
		unset( $autoload );
		$GLOBALS['lpf_opt'][ $key ] = $value;
		return true;
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
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return trim( preg_replace( '/<[^>]*>/', '', (string) $text ) );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0 ) {
		return json_encode( $data, $options );
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://luxetrendsetters.com' . $path;
	}
}

require LUXE_PUBLIC_FINISH_DIR . 'includes/class-plugin.php';
require LUXE_PUBLIC_FINISH_DIR . 'includes/class-titles.php';

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

$header = (string) file_get_contents( LUXE_PUBLIC_FINISH_DIR . 'luxe-public-finish.php' );
expect( false !== strpos( $header, 'Version: 1.0.0' ), 'plugin header is 1.0.0' );
expect( false !== strpos( $header, 'Never writes post content' ), 'header restates the write lock' );
expect( false === strpos( $header, 'wp_insert_post' ), 'bootstrap does not insert posts' );

$seiko = 'Seiko Watch Five Sports SKX Sports Style GMT Wristwatch | Pr';
expect( Luxe_Public_Finish_Titles::is_truncated( $seiko ), 'Seiko | Pr is truncated' );
$seiko_fixed = Luxe_Public_Finish_Titles::polish( $seiko, 'Seiko Watch Five Sports SKX Sports Style GMT Wristwatch' );
expect( false === strpos( $seiko_fixed, '| Pr' ), 'Seiko polish drops | Pr' );
expect( false === Luxe_Public_Finish_Titles::is_truncated( $seiko_fixed ), 'polished Seiko is complete' );
expect( strlen( $seiko_fixed ) <= 70, 'Seiko title fits 70 chars' );
expect( 0 === preg_match( '/\s[A-Z][a-z]{0,2}$/', $seiko_fixed ), 'Seiko does not end mid-word' );

$mac = 'Apple 2026 MacBook Pro Laptop with Apple M5 Pro chip with 15-core CPU… Review & Buyer Che';
expect( Luxe_Public_Finish_Titles::is_truncated( $mac ), 'MacBook Buyer Che is truncated' );
$mac_fixed = Luxe_Public_Finish_Titles::polish( $mac, 'Apple 2026 MacBook Pro Laptop with Apple M5 Pro chip with 15-core CPU and 16-core GPU' );
expect( false === strpos( $mac_fixed, 'Buyer Che' ), 'MacBook polish drops Buyer Che' );
expect( false === strpos( $mac_fixed, '…' ), 'MacBook polish drops ellipsis stub' );
expect( false === Luxe_Public_Finish_Titles::is_truncated( $mac_fixed ), 'polished MacBook is complete' );
expect( strlen( $mac_fixed ) <= 70, 'MacBook title fits 70 chars' );
expect( ! preg_match( '/\s\S{1,2}$/', $mac_fixed ) || preg_match( '/Pro$/', $mac_fixed ), 'MacBook ends on a word' );

$clip = Luxe_Public_Finish_Titles::word_clip( 'Seiko Watch Five Sports SKX Sports Style GMT Wristwatch | LuxeTrendsetters Extra Words Here', 70 );
expect( strlen( $clip ) <= 70, 'word_clip respects 70' );
expect( substr( $clip, -1 ) !== '…', 'word_clip does not add ellipsis' );
expect( false === strpos( $clip, '| Pr' ), 'word_clip does not invent | Pr' );

$seen = array();
$a    = Luxe_Public_Finish_Titles::unique_label( 'GEEKOM GeekBook X14 Pro', 101, 'GX14-A', $seen );
$b    = Luxe_Public_Finish_Titles::unique_label( 'GEEKOM GeekBook X14 Pro', 202, 'GX14-B', $seen );
$c    = Luxe_Public_Finish_Titles::unique_label( 'GEEKOM GeekBook X14 Pro', 101, 'GX14-A', $seen );
expect( 'GEEKOM GeekBook X14 Pro' === $a, 'first listing keeps the title' );
expect( 'GEEKOM GeekBook X14 Pro · GX14-B' === $b, 'duplicate listing gets SKU' );
expect( 'GEEKOM GeekBook X14 Pro' === $c, 'same ID does not double-suffix' );

$html = '<html><body>'
	. '<p>As an Amazon Associate I earn from qualifying purchases.</p>'
	. '<p>Disclosure: As an Amazon Associate, we earn from qualifying purchases at no extra cost to you.</p>'
	. '<p class="luxe-score-repair-disclosure">As an Amazon Associate I earn from qualifying purchases. Extra.</p>'
	. '<script type="application/ld+json">{"@type":"Organization","name":"Keep"}</script>'
	. '</body></html>';
$collapsed = Luxe_Public_Finish_Titles::collapse_disclosures( $html );
expect( 1 === Luxe_Public_Finish_Titles::disclosure_count( $collapsed ), 'disclosures collapse to one' );
expect( false !== strpos( $collapsed, 'application/ld+json' ), 'disclosure pass parks JSON-LD' );

$schema = '<html><head>'
	. '<script type="application/ld+json">{"@context":"https://schema.org","@type":"Organization","name":"LuxeTrendsetters"}</script>'
	. '<script type="application/ld+json" id="luxe-score-repair-schema">{"@graph":[{"@type":"Organization","name":"LuxeTrendsetters"},{"@type":"FAQPage"}]}</script>'
	. '</head></html>';
$one = Luxe_Public_Finish_Titles::collapse_organization( $schema );
expect( 1 === Luxe_Public_Finish_Titles::organization_count( $one ), 'Organization graphs collapse to one' );
expect( false !== strpos( $one, 'FAQPage' ), 'non-Organization nodes stay' );

expect( Luxe_Public_Finish_Titles::amazon_tag_ok( 'https://www.amazon.com/dp/B0TEST/?tag=luxetrendse0f-20', 'luxetrendse0f-20' ), 'locked tag is ok' );
expect( ! Luxe_Public_Finish_Titles::amazon_tag_ok( 'https://www.amazon.com/dp/B0TEST/?tag=other-20', 'luxetrendse0f-20' ), 'foreign tag is not ok' );
expect( Luxe_Public_Finish_Titles::no_frontend_assets( '<script src="/wp-content/themes/flatsome/a.js"></script>' ), 'theme JS is not this plugin' );
expect( ! Luxe_Public_Finish_Titles::no_frontend_assets( '<script src="/wp-content/plugins/luxe-public-finish/app.js"></script>' ), 'plugin JS would fail the asset lock' );

expect( 10 === Luxe_Public_Finish_Plugin::score_10( 10, 12, 2, 0 ), 'yellow-only still scores 10' );
expect( 10 === Luxe_Public_Finish_Plugin::score_10( 12, 12, 0, 0 ), 'all green scores 10' );
expect( 8 === Luxe_Public_Finish_Plugin::score_10( 10, 12, 0, 2 ), 'red drops the 10' );

$php_files = glob( LUXE_PUBLIC_FINISH_DIR . 'includes/*.php' );
$php_files[] = LUXE_PUBLIC_FINISH_DIR . 'luxe-public-finish.php';
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
