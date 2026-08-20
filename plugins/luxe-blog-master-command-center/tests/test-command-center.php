<?php
/**
 * CLI checks for Luxe Blog Master Command Center 2.9.0.
 * php tests/test-command-center.php
 */
define( 'ABSPATH', '/tmp/' );
define( 'LUXE_BMC_DIR', dirname( __DIR__ ) . '/' );
define( 'LUXE_BMC_VERSION', '2.9.3' );
define( 'LUXE_BMC_URL', 'https://example.test/bmc/' );
define( 'LUXE_BMC_BASENAME', 'luxe-blog-master-command-center/luxe-blog-master-command-center.php' );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['luxe_opt']  = array();
$GLOBALS['luxe_meta'] = array();
$GLOBALS['luxe_posts'] = array();
$GLOBALS['luxe_status'] = array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return isset( $GLOBALS['luxe_opt'][ $key ] ) ? $GLOBALS['luxe_opt'][ $key ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = false ) {
		unset( $autoload );
		$GLOBALS['luxe_opt'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'add_option' ) ) {
	function add_option( $key, $value, $deprecated = '', $autoload = false ) {
		unset( $deprecated, $autoload );
		$GLOBALS['luxe_opt'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key, $single = false ) {
		$val = isset( $GLOBALS['luxe_meta'][ $post_id ][ $key ] ) ? $GLOBALS['luxe_meta'][ $post_id ][ $key ] : '';
		return $single ? $val : array( $val );
	}
}
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['luxe_meta'][ $post_id ][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'get_post_status' ) ) {
	function get_post_status( $post_id ) {
		return isset( $GLOBALS['luxe_status'][ $post_id ] ) ? $GLOBALS['luxe_status'][ $post_id ] : false;
	}
}
if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post_id ) {
		return isset( $GLOBALS['luxe_posts'][ $post_id ] ) ? $GLOBALS['luxe_posts'][ $post_id ] : 'Product ' . $post_id;
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $text ) {
		return (string) $text;
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return trim( preg_replace( '/<[^>]*>/', '', (string) $text ) );
	}
}
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults ) {
		return array_merge( $defaults, is_array( $args ) ? $args : array() );
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://luxetrendsetters.com' . $path;
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
	}
}
if ( ! function_exists( 'get_the_terms' ) ) {
	function get_the_terms( $post_id, $tax ) {
		unset( $post_id, $tax );
		return false;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $fn, $priority = 10, $accepted = 1 ) {
		unset( $tag, $fn, $priority, $accepted );
		return true;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $fn, $priority = 10, $accepted = 1 ) {
		unset( $tag, $fn, $priority, $accepted );
		return true;
	}
}

require LUXE_BMC_DIR . 'includes/class-plugin.php';
require LUXE_BMC_DIR . 'includes/class-safety.php';
require LUXE_BMC_DIR . 'includes/class-amazon.php';
require LUXE_BMC_DIR . 'includes/class-score.php';
require LUXE_BMC_DIR . 'includes/class-public.php';
require LUXE_BMC_DIR . 'includes/class-blueprint.php';
require LUXE_BMC_DIR . 'includes/class-admin.php';
require LUXE_BMC_DIR . 'includes/class-companion.php';
Luxe_BMC_Companion::arm();

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

$header = (string) file_get_contents( LUXE_BMC_DIR . 'luxe-blog-master-command-center.php' );
expect( false !== strpos( $header, 'Plugin Name: Luxe Blog Master Command Center' ), 'header name is Command Center' );
expect( false !== strpos( $header, 'Version: 2.9.3' ), 'header is 2.9.3' );
expect( false !== strpos( $header, 'Never publishes' ), 'header restates no-publish' );
expect( false !== strpos( $header, 'Complete Blog Master approval companion' ), 'header names Mission Control companion' );
expect( false !== strpos( $header, 'Hard no-publish evidence armed' ), 'header names hard no-publish evidence' );
expect( 25 === count( Luxe_BMC_Plugin::keep_processes() ), '25 keep-processes preserved' );
expect( 13 === count( Luxe_BMC_Plugin::action_buttons() ), '13 action buttons routed' );
expect( 13 === count( Luxe_BMC_Admin::routed_actions() ), 'dispatcher lists 13 actions' );
expect( class_exists( 'Luxe_Blog_Master_Command_Center' ), '2.8.1 class name exists for Mission Control' );
expect( function_exists( 'luxe_blog_master_hard_no_publish' ) && luxe_blog_master_hard_no_publish(), 'hard no-publish function armed' );
expect( function_exists( 'luxe_blog_master_approval_companion' ) && luxe_blog_master_approval_companion(), 'approval companion function armed' );
$handshake = Luxe_BMC_Companion::evidence();
expect( ! empty( $handshake['complete_build'] ) && ! empty( $handshake['hard_no_publish'] ), 'companion evidence pack is complete' );
expect( ! empty( $handshake['master_locked'] ), 'master #10833 locked in companion evidence' );
expect( 46 === count( Luxe_BMC_Companion::stack() ), '46-plugin stack map present' );
$stub = (string) file_get_contents( LUXE_BMC_DIR . 'luxe-blog-master.php' );
expect( false === strpos( $stub, 'Plugin Name:' ), 'old bootstrap filename is not a second plugin' );
expect( 10833 === Luxe_BMC_Plugin::MASTER, 'master ID 10833 locked' );
expect( 10833 === Luxe_BMC_Plugin::MASTER_ID, 'MASTER_ID alias locked' );
expect( 1800 === Luxe_BMC_Plugin::MIN_WORDS, 'min words 1800' );
expect( 72 === Luxe_BMC_Plugin::SIM_LIMIT, 'uniqueness cap 72' );
expect( 'luxetrendse0f-20' === Luxe_BMC_Plugin::TAG, 'Amazon tag lock' );

expect( 60 === Luxe_BMC_Score::cap_product_zero( 0, 100 ), 'Product #0 cannot become 95-100' );
expect( 60 === Luxe_BMC_Score::cap_product_zero( 0, 60 ), 'Product #0 cap is 60' );
expect( 100 === Luxe_BMC_Score::cap_product_zero( 62970, 100 ), 'locked product can be 100' );
expect( '95-100-ready' === Luxe_BMC_Score::band( 97 ), 'band 95-100-ready' );

$GLOBALS['luxe_status'][10833] = 'publish';
$GLOBALS['luxe_status'][50]    = 'publish';
$GLOBALS['luxe_status'][51]    = 'draft';
expect( ! Luxe_BMC_Safety::can_write_body( 10833 ), 'cannot write master' );
expect( ! Luxe_BMC_Safety::can_write_body( 50 ), 'cannot write published' );
expect( Luxe_BMC_Safety::can_write_body( 51 ), 'can write draft' );

$url = 'https://www.amazon.com/dp/B0GQ721X1Q?tag=luxetrendse0f-20';
expect( 'green' === Luxe_BMC_Amazon::tag_level( $url ), 'native tag is green' );
expect( Luxe_BMC_Amazon::is_direct_dp( $url ), 'direct /dp/ detected' );
expect( 'red' === Luxe_BMC_Amazon::tag_level( 'https://www.amazon.com/dp/B0GQ721X1Q?tag=other-20' ), 'foreign tag is red' );

$html = '<html><head><title>Premium Buyer Guides</title><meta name="viewport" content="width=device-width"></head><body><p>As an Amazon Associate I earn from qualifying purchases.</p><a href="https://www.amazon.com/dp/B0TEST/?tag=luxetrendse0f-20">View on Amazon</a> buyer guide review</body></html>';
expect( 1 === Luxe_BMC_Public::disclosure_count( $html ), 'one disclosure' );
expect( Luxe_BMC_Public::amazon_tag_ok( $html ), 'tag lock ok' );
expect( Luxe_BMC_Public::no_plugin_assets( $html ), 'no frontend plugin JS' );
expect( Luxe_BMC_Public::homepage_untouched( $html ), 'no homepage overlay' );
expect( Luxe_BMC_Public::flatsome_menu_safe(), 'Flatsome hiders-off is green' );
$scored = Luxe_BMC_Public::score_public( array( 'ok' => true, 'code' => 200, 'body' => $html ) );
expect( ! empty( $scored['all'] ), 'fixture public gates all green' );
expect( 100 === $scored['score'], 'fixture scores 100' );

$html_meta = '<html><head><title>Premium Buyer Guides</title><meta name="viewport" content="width=device-width"><meta name="amazon-associate" content="As an Amazon Associate I earn from qualifying purchases." /></head><body><p>As an Amazon Associate I earn from qualifying purchases.</p><a href="https://www.amazon.com/dp/B0TEST/?tag=luxetrendse0f-20">View on Amazon</a> buyer guide review</body></html>';
expect( 1 === Luxe_BMC_Public::disclosure_count( $html_meta ), 'head meta is not a second visible disclosure' );
$scored_meta = Luxe_BMC_Public::score_public( array( 'ok' => true, 'code' => 200, 'body' => $html_meta ) );
expect( 100 === $scored_meta['score'], 'meta duplicate does not drop public score' );

$html_dup = '<html><head><title>MacBook Pro M2 Pro Review 2026: Best Apple Laptop Buyer Guide</title><meta name="viewport" content="width=device-width"></head><body><p>As an Amazon Associate I earn from qualifying purchases.</p><article><p>As an Amazon Associate I earn from qualifying purchases.</p><a href="https://www.amazon.com/dp/B0TEST/?tag=luxetrendse0f-20">View on Amazon</a> buyer guide review</article></body></html>';
expect( 1 === Luxe_BMC_Public::disclosure_count( $html_dup ), 'official sentence twice is one disclosure' );
$scored_dup = Luxe_BMC_Public::score_public( array( 'ok' => true, 'code' => 200, 'body' => $html_dup ) );
expect( 100 === $scored_dup['score'], 'header plus article official line stays 100' );

$GLOBALS['luxe_posts'][9001] = 'Apple 2023 MacBook Pro 16-inch M2 Pro Space Black';
$GLOBALS['luxe_posts'][9002] = 'Seiko Presage Cocktail Time Automatic Watch';
$GLOBALS['luxe_meta'][9001]['_sku'] = 'MBP-M2P-16';
$GLOBALS['luxe_meta'][9002]['_sku'] = 'SRPB43';
$GLOBALS['luxe_meta'][9001]['_product_url'] = 'https://www.amazon.com/dp/B0GQ721X1Q?tag=luxetrendse0f-20';
$GLOBALS['luxe_meta'][9002]['_product_url'] = 'https://www.amazon.com/dp/B00TESTWATCH?tag=luxetrendse0f-20';

$a = Luxe_BMC_Blueprint::build( (object) array( 'ID' => 1 ), 9001 );
$b = Luxe_BMC_Blueprint::build( (object) array( 'ID' => 2 ), 9002 );
expect( is_array( $a ) && is_array( $b ), 'blueprint builds two products' );
$words_a = str_word_count( wp_strip_all_tags( $a['content'] ) );
$words_b = str_word_count( wp_strip_all_tags( $b['content'] ) );
expect( $words_a >= 1800, 'MacBook draft is >=1800 words (' . $words_a . ')' );
expect( $words_b >= 1800, 'Seiko draft is >=1800 words (' . $words_b . ')' );
expect( 1 === substr_count( strtolower( $a['content'] ), 'table of contents' ), 'single TOC' );
expect( 1 === preg_match_all( '/Amazon Associate/i', wp_strip_all_tags( $a['content'] ) ), 'single disclosure' );
expect( false === stripos( $a['content'], 'Q1–Q5' ) && false === stripos( $a['content'], 'Q1-' ), 'no generic numbered FAQ block' );
$overlap = Luxe_BMC_Score::overlap( strtolower( wp_strip_all_tags( $a['content'] ) ), strtolower( wp_strip_all_tags( $b['content'] ) ) );
expect( $overlap < 72, 'two product bodies overlap under 72% (' . $overlap . ')' );

$php_files = glob( LUXE_BMC_DIR . 'includes/*.php' );
$php_files[] = LUXE_BMC_DIR . 'luxe-blog-master-command-center.php';
$php_files[] = LUXE_BMC_DIR . 'uninstall.php';
$php_files[] = LUXE_BMC_DIR . 'luxe-blog-master.php';
foreach ( $php_files as $file ) {
	$src = (string) file_get_contents( $file );
	expect( false === strpos( $src, 'wp_delete_post' ), basename( $file ) . ' does not delete posts' );
	expect( false === strpos( $src, 'wp_enqueue_script' ), basename( $file ) . ' does not enqueue JS' );
	expect( false === strpos( $src, 'post_status\' => \'publish' ), basename( $file ) . ' does not publish' );
	expect( false === strpos( $src, "'post_status' => 'publish'" ), basename( $file ) . ' does not publish (single quotes)' );
}

$admin = (string) file_get_contents( LUXE_BMC_DIR . 'includes/class-admin.php' );
foreach ( array_keys( Luxe_BMC_Plugin::action_buttons() ) as $action ) {
	expect( false !== strpos( $admin, "case '" . $action . "'" ), 'router has ' . $action );
}

if ( $fail ) {
	echo "FAILED $fail\n";
	exit( 1 );
}
echo "ALL OK\n";
