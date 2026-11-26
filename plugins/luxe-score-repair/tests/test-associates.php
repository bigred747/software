<?php
/**
 * CLI checks for tracking IDs, ASINs, tagged links, and the starter guide.
 * php tests/test-hub.php
 */
define( 'ABSPATH', '/tmp/' );

require dirname( __DIR__ ) . '/includes/class-links.php';
require dirname( __DIR__ ) . '/includes/class-guide.php';

$fail = 0;

/**
 * @param bool   $ok  Result.
 * @param string $msg Label.
 */
function expect( $ok, $msg ) {
	global $fail;
	if ( $ok ) {
		echo "OK  $msg\n";
		return;
	}
	$fail++;
	echo "FAIL $msg\n";
}

expect( 'storename-20' === LAH_Links::tracking_id( ' storename-20 ' ), 'tracking id trimmed' );
expect( 'storename-20' === LAH_Links::tracking_id( 'tag=storename-20' ), 'tag= prefix stripped' );
expect( 'my-store-21' === LAH_Links::tracking_id( 'my-store-21' ), 'hyphenated store id' );
expect( '' === LAH_Links::tracking_id( 'https://evil.example/phish' ), 'url is not a tracking id' );
expect( '' === LAH_Links::tracking_id( 'shop-2' ), 'one digit suffix rejected' );
expect( '' === LAH_Links::tracking_id( 'shop-200' ), 'three digit suffix rejected' );
expect( '' === LAH_Links::tracking_id( 'password' ), 'word password is not a tracking id' );

expect( 'B0ABCDEF12' === LAH_Links::asin( 'b0abcdef12' ), 'asin uppercased' );
expect( 'B0ABCDEF12' === LAH_Links::asin( 'https://www.amazon.com/dp/B0ABCDEF12/?tag=other-20' ), 'asin from product url' );
expect( 'B0ABCDEF12' === LAH_Links::asin( 'https://www.amazon.com/gp/product/B0ABCDEF12' ), 'asin from gp/product' );
expect( '' === LAH_Links::asin( '../etc/passwd' ), 'path is not an asin' );
expect( '' === LAH_Links::asin( '1234567890' ), 'digits only rejected' );

$url = LAH_Links::product_url( 'B0ABCDEF12', 'luxetrendsetters-20' );
expect( 'https://www.amazon.com/dp/B0ABCDEF12?tag=luxetrendsetters-20' === $url, 'standard product link' );
expect(
	'https://www.amazon.com/dp/B0ABCDEF12?tag=shop-20' === LAH_Links::product_url( 'https://www.amazon.com/dp/B0ABCDEF12/?tag=other-20&linkCode=ll1', 'shop-20' ),
	'pasted url keeps only the new tag'
);
expect( '' === LAH_Links::product_url( 'B0ABCDEF12', '' ), 'missing tag builds nothing' );
expect( '' === LAH_Links::product_url( 'not-an-asin', 'shop-20' ), 'bad asin builds nothing' );
expect( LAH_Links::allowed_url( $url ), 'product link host allowed' );

expect( 'https://www.amazon.com/gp/bestsellers?tag=shop-20' === LAH_Links::tagged_page( '/gp/bestsellers', 'shop-20' ), 'bestsellers tagged' );
expect( 'https://www.amazon.com/gp/bestsellers' === LAH_Links::tagged_page( '/gp/bestsellers', '' ), 'bestsellers without tag' );
expect( '' === LAH_Links::tagged_page( '/gp/your-account', 'shop-20' ), 'account page is not taggable here' );
expect( '' === LAH_Links::tagged_page( 'https://evil.example', 'shop-20' ), 'external page rejected' );

expect( false !== strpos( LAH_Links::DISCLOSURE, 'Amazon Associate' ), 'official disclosure names the program' );

$block = LAH_Links::block_html( 'B0ABCDEF12', 'luxetrendsetters-20', 'Official Amazon store — guaranteed lowest price' );
expect( false !== strpos( $block, 'rel="sponsored noopener nofollow"' ), 'block uses Google sponsored rel' );
expect( false !== strpos( $block, 'https://www.amazon.com/dp/B0ABCDEF12?tag=luxetrendsetters-20' ), 'block href is the direct special link' );
expect( false !== strpos( $block, LAH_Links::DISCLOSURE ), 'block includes the required disclosure' );
expect( false !== strpos( $block, '>View on Amazon<' ), 'endorsement label is replaced' );
expect( false === strpos( $block, 'guaranteed' ), 'block does not keep a guarantee claim' );
expect( '' === LAH_Links::block_html( 'B0ABCDEF12', '' ), 'block is empty without a tracking id' );

$bare = '<a href="https://www.amazon.com/dp/B0ABCDEF12?tag=shop-20">View on Amazon</a>';
$qualified = LAH_Links::qualify_amazon_anchors( $bare );
expect( false !== strpos( $qualified, 'href="https://www.amazon.com/dp/B0ABCDEF12?tag=shop-20"' ), 'qualifier keeps the original href' );
expect( false !== strpos( $qualified, 'rel="sponsored noopener nofollow"' ), 'qualifier adds sponsored rel' );
$again = LAH_Links::qualify_amazon_anchors( $qualified );
expect( 1 === substr_count( strtolower( $again ), 'sponsored' ), 'qualifier does not duplicate rel tokens' );
$image = '<a href="https://m.media-amazon.com/images/I/41.jpg"><img src="https://m.media-amazon.com/images/I/41.jpg" alt=""></a>';
expect( $image === LAH_Links::qualify_amazon_anchors( $image ), 'image CDN links are left alone' );
$phrase = LAH_Links::honest_retailer_phrase( '<a href="https://www.amazon.com/dp/B0ABCDEF12?tag=shop-20">Go</a> then use the DJI button. Prices can change.' );
expect( false !== strpos( $phrase, 'then use the Amazon button' ), 'wrong retailer button name is corrected' );
expect( false === strpos( $phrase, 'DJI button' ), 'DJI button phrase is gone when the link is Amazon' );
expect( 'then use the DJI button' === LAH_Links::honest_retailer_phrase( 'then use the DJI button' ), 'phrase stays when no Amazon product link is present' );
expect( false !== strpos( LAH_Links::SITE_DISCLOSURE, 'LuxeTrendsetters' ), 'site disclosure names the store' );

expect( '' === LAH_Links::note( 'SSN 123-45-6789' ), 'tax id note rejected' );
expect( '' === LAH_Links::note( 'account 123456789' ), 'long account number rejected' );
expect( 'Check the March report in April.' === LAH_Links::note( ' Check the March report in April. ' ), 'plain reminder kept' );

$log = LAH_Links::push_log( array(), array( 'at' => 100, 'url' => $url ), 2 );
$log = LAH_Links::push_log( $log, array( 'at' => 200 ), 2 );
$log = LAH_Links::push_log( $log, array( 'at' => 300 ), 2 );
expect( 2 === count( $log ), 'log capped at 2' );
expect( 300 === $log[0]['at'], 'newest log row stays first' );

$open = LAH_Links::week_status( 1000, array( array( 'at' => 2000 ) ), 1000 + 100 );
expect( $open['open'] && $open['shared'], 'link inside the first week counts' );
$late = LAH_Links::week_status( 1000, array( array( 'at' => 1000 + 604800 ) ), 1000 + 700000 );
expect( ! $late['open'] && ! $late['shared'], 'link after day 7 does not count' );
$early = LAH_Links::week_status( 5000, array( array( 'at' => 1000 ) ), 5000 );
expect( $early['open'] && ! $early['shared'], 'link before the window does not count' );

$ids = array();
foreach ( LAH_Guide::sections() as $section ) {
	$ids[] = $section['id'];
}
foreach ( LAH_Guide::required_ids() as $id ) {
	expect( in_array( $id, $ids, true ), 'guide has ' . $id );
}
expect( 2 === count( LAH_Guide::steps() ), 'two starter steps' );

$urls = LAH_Guide::outbound_urls();
expect( count( $urls ) > 8, 'guide has official links' );
foreach ( $urls as $outbound ) {
	expect( LAH_Links::allowed_url( $outbound ), 'allowed host ' . $outbound );
}

if ( ! defined( 'LAH_VERSION' ) ) {
	define( 'LAH_VERSION', '1.0.0' );
}
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return (string) $url; }
function esc_textarea( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function checked( $on ) { if ( $on ) { echo 'checked="checked"'; } }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="test" />'; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' ); }
function submit_button( $label ) { echo '<button>' . esc_html( $label ) . '</button>'; }
function wp_date( $format, $timestamp ) { return gmdate( 'M j, Y', (int) $timestamp ); }
function add_query_arg( $args, $url ) { return $url . '?stub=1'; }

$settings = array(
	'tracking_id'   => 'storename-20',
	'payment_done'  => 1,
	'started_at'    => 1700000000,
	'earnings_note' => 'Check the March report in April.',
);
$log = array(
	array(
		'url'   => 'https://www.amazon.com/dp/B0ABCDEF12?tag=storename-20',
		'title' => 'Sample watch',
		'at'    => 1700001000,
	),
);
$last = array(
	'url'  => 'https://www.amazon.com/dp/B0ABCDEF12?tag=storename-20',
	'asin' => 'B0ABCDEF12',
);
$week = array( 'open' => true, 'shared' => true );
$signals = array(
	array(
		'tone'   => 'ok',
		'title'  => 'Product Scout is active',
		'detail' => 'Read only.',
		'url'    => 'https://example.test/wp-admin/admin.php',
		'action' => 'Open Product Scout',
	),
);
$catalog = array(
	array(
		'id'     => '10',
		'title'  => 'Sample watch',
		'asin'   => 'B0ABCDEF12',
		'status' => 'publish',
	),
);
$steps    = LAH_Guide::steps();
$sections = LAH_Guide::sections();
$notice   = 'Saved.';
$prefill  = 'B0ABCDEF12';
$title_in = 'Sample watch';
$bestsellers = LAH_Links::tagged_page( '/gp/bestsellers', 'storename-20' );

ob_start();
include dirname( __DIR__ ) . '/templates/associates-admin.php';
$html = ob_get_clean();
expect( false !== strpos( $html, 'Associates Hub 1.0.0' ), 'admin screen renders' );
expect( false !== strpos( $html, 'storename-20' ), 'tracking id is shown' );
expect( false !== strpos( $html, 'https://affiliate-program.amazon.com/home' ), 'associates central link renders' );
expect( false !== strpos( $html, 'https://www.amazon.com/dp/B0ABCDEF12?tag=storename-20' ), 'built link renders' );
expect( false !== strpos( $html, 'Luxe master set' ), 'master set section renders' );
expect( false !== strpos( $html, 'sponsored noopener nofollow' ), 'admin shows the compliant block' );
expect( false !== strpos( $html, '[luxe_amazon asin=' ), 'admin shows the shortcode' );
expect( false === strpos( $html, 'password=' ), 'screen has no password field' );

if ( $fail > 0 ) {
	echo "\n$fail failed\n";
	exit( 1 );
}
echo "\nAll checks passed\n";
exit( 0 );
