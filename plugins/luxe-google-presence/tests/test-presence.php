<?php
/**
 * Contract signals and a live homepage scan.
 * php plugins/luxe-google-presence/tests/test-presence.php
 */
define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__ ) . '/includes/class-score.php';
require dirname( __DIR__ ) . '/includes/class-anchors.php';
require dirname( __DIR__ ) . '/includes/class-copy.php';
require dirname( __DIR__ ) . '/includes/class-visitor.php';
require dirname( __DIR__ ) . '/includes/class-compat.php';
require dirname( __DIR__ ) . '/includes/class-contract.php';
require dirname( __DIR__ ) . '/includes/class-scan.php';

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

$contract = Luxe_Google_Presence_Contract::evaluate();
echo "CONTRACT {$contract['score']} {$contract['status']} {$contract['green']}/{$contract['total']}\n";
foreach ( $contract['signals'] as $signal ) {
	$level = strtoupper( $signal['level'] );
	echo $level . '  ' . $signal['label'] . ' — ' . $signal['detail'] . "\n";
	expect( 'green' === $signal['level'], $signal['id'] );
}
expect( 100 === $contract['score'], 'contract score is 100' );
expect( 'READY' === $contract['status'], 'contract status is READY' );

$cloaked = $contract['signals'];
foreach ( $cloaked as $i => $signal ) {
	if ( 'no_cloaking' === $signal['id'] ) {
		$cloaked[ $i ]['level'] = 'red';
	}
}
$held = Luxe_Google_Presence_Score::decide( $cloaked, array( 'no_cloaking' ) );
expect( 70 === $held['score'], 'cloaking caps the score at 70' );
expect( 'HOLD' === $held['status'], 'cloaking status is HOLD' );

$soft = $contract['signals'];
foreach ( $soft as $i => $signal ) {
	if ( 'computer' === $signal['id'] ) {
		$soft[ $i ]['level'] = 'red';
	}
}
$review = Luxe_Google_Presence_Score::decide( $soft, array( 'no_cloaking' ) );
expect( 94 === $review['score'], 'a missing computer layout caps at 94' );

$optional = $contract['signals'];
foreach ( $optional as $i => $signal ) {
	if ( 'verification' === $signal['id'] ) {
		$optional[ $i ]['level'] = 'red';
	}
}
$ready = Luxe_Google_Presence_Score::decide( $optional, array( 'no_cloaking' ), array( 'verification' ) );
expect( 95 === $ready['score'], 'optional red signal scores 95' );

$amp = '<a href="https://www.amazon.com/dp/B0ABCDEF12?tag=luxetrendsetters-20&amp;linkCode=ll1" rel="nofollow">Buy</a>';
$amp_out = Luxe_Google_Presence_Anchors::qualify( $amp );
expect( false !== strpos( $amp_out, 'href="https://www.amazon.com/dp/B0ABCDEF12?tag=luxetrendsetters-20&amp;linkCode=ll1"' ), 'encoded href stays intact' );
expect( false !== strpos( $amp_out, 'rel="nofollow sponsored"' ), 'existing rel gains sponsored' );

$weight_url = Luxe_Google_Presence_Visitor::redirect_url( '/item-weight/2-71-pounds/', array(), 'https://luxetrendsetters.com/shop/' );
$ounces_url = Luxe_Google_Presence_Visitor::redirect_url( '/item-weight/1-12-ounces/page/2/', array(), 'https://luxetrendsetters.com/shop/' );
expect( 'https://luxetrendsetters.com/shop/' === $weight_url, 'weight filter falls back to the shop' );
expect( 'https://luxetrendsetters.com/shop/' === $ounces_url, 'paged weight filter falls back to the shop' );
expect( '' === Luxe_Google_Presence_Visitor::redirect_url( '/product-category/electronics/computers-laptops/', array(), 'https://luxetrendsetters.com/shop/' ), 'a department is not redirected' );
expect( '' === Luxe_Google_Presence_Visitor::redirect_url( '/product/apple-watch/', array(), 'https://luxetrendsetters.com/shop/' ), 'a product is not redirected' );
expect( Luxe_Google_Presence_Visitor::is_junk_archive_path( '/date-first-available/2026/' ), 'date-first-available is a filter' );
expect( ! Luxe_Google_Presence_Visitor::is_junk_archive_path( '/shop/' ), 'the shop is not a filter' );

$root = dirname( __DIR__ ) . '/includes';
$needles = array( 'wp_insert_post', 'wp_update_post', 'update_post_meta', 'indexing.googleapis', 'aggregateRating' );
foreach ( glob( $root . '/*.php' ) as $file ) {
	$body = file_get_contents( $file );
	foreach ( $needles as $needle ) {
		expect( false === strpos( (string) $body, $needle ), basename( $file ) . ' has no ' . $needle );
	}
}

/**
 * @param string $url URL.
 * @param string $agent Agent.
 * @return string
 */
function lgp_fetch( $url, $agent ) {
	$cmd = 'curl -fsL --max-time 25 -A ' . escapeshellarg( $agent ) . ' ' . escapeshellarg( $url );
	$out = shell_exec( $cmd );
	return is_string( $out ) ? $out : '';
}

$desktop = lgp_fetch( 'https://luxetrendsetters.com/', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36' );
$mobile  = lgp_fetch( 'https://luxetrendsetters.com/', 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Mobile Safari/537.36' );
$robots  = lgp_fetch( 'https://luxetrendsetters.com/robots.txt', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' );

echo "LIVE bytes desktop " . strlen( $desktop ) . " mobile " . strlen( $mobile ) . " robots " . strlen( $robots ) . "\n";
if ( strlen( $desktop ) < 500 || strlen( $mobile ) < 500 || strlen( $robots ) < 20 ) {
	echo "LIVE scan skipped because the homepage fetch was short\n";
} else {
	$live = Luxe_Google_Presence_Scan::evaluate( $desktop, $mobile, $robots );
	echo "LIVE {$live['score']} {$live['status']} {$live['green']}/{$live['total']}\n";
	foreach ( $live['signals'] as $signal ) {
		echo strtoupper( $signal['level'] ) . '  ' . $signal['label'] . ' — ' . $signal['detail'] . "\n";
	}
	expect( $live['score'] >= 95, 'live readiness is 95-100 before the official lines are installed' );
	foreach ( $live['signals'] as $signal ) {
		if ( in_array( $signal['id'], array( 'official_disclosure', 'official_price' ), true ) ) {
			continue;
		}
		expect( 'green' === $signal['level'], 'live ' . $signal['id'] );
	}
	$home_fixed = Luxe_Google_Presence_Visitor::check( $desktop );
	echo 'REPAIR homepage price ' . ( $home_fixed['price'] ? 'yes' : 'no' ) . ' disclosure-bad ' . ( $home_fixed['bad_disclosure'] ? 'yes' : 'no' ) . ' hrefs ' . ( $home_fixed['hrefs'] ? 'same' : 'CHANGED' ) . "\n";
	expect( $home_fixed['price'] && ! $home_fixed['bad_disclosure'] && $home_fixed['hrefs'] && $home_fixed['products'] && $home_fixed['stable'], 'homepage repair restores the price line without touching links' );
}

$item_pages = array(
	'/tmp/weight.html' => 'https://luxetrendsetters.com/item-weight/2-71-pounds/',
	'/tmp/ounces.html' => 'https://luxetrendsetters.com/item-weight/1-12-ounces/',
);
$weight_variants = 0;
foreach ( $item_pages as $file => $url ) {
	$html = lgp_fetch( $url, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1' );
	if ( strlen( $html ) < 500 && is_readable( $file ) ) {
		$html = file_get_contents( $file );
		$html = is_string( $html ) ? $html : '';
	}
	echo 'SCAN ' . $url . ' bytes ' . strlen( $html ) . "\n";
	expect( strlen( $html ) > 5000, 'fetched ' . $url );
	if ( strlen( $html ) < 500 ) {
		continue;
	}
	$before_bad = false !== stripos( $html, 'may contain affiliate links' );
	$before_id  = (bool) preg_match( '/\balt="[0-9]{2}[A-Za-z0-9]{6,14}"/', $html );
	$report     = Luxe_Google_Presence_Visitor::check( $html );
	echo '  before-bad ' . ( $before_bad ? 'yes' : 'no' );
	echo ' before-image-id ' . ( $before_id ? 'yes' : 'no' );
	echo ' after-bad ' . ( $report['bad_disclosure'] ? 'yes' : 'no' );
	echo ' official ' . ( $report['official'] ? 'yes' : 'no' );
	echo ' price ' . ( $report['price'] ? 'yes' : 'no' );
	echo ' image-id ' . ( $report['image_id'] ? 'STILL' : 'cleared' );
	echo ' smartwatche-alt ' . ( $report['smartwatche'] ? 'STILL' : 'cleared' );
	echo ' slug-alt ' . ( $report['slug_alt'] ? 'STILL' : 'cleared' );
	echo ' amazon ' . ( $report['hrefs'] ? 'same' : 'CHANGED' ) . ' (' . $report['amazon_count'] . ')';
	echo ' products ' . ( $report['products'] ? 'same' : 'CHANGED' );
	echo ' junk-anchors ' . $report['junk_anchors'];
	echo ' variants ' . $report['variants'];
	echo ' stable ' . ( $report['stable'] ? 'yes' : 'no' ) . "\n";
	expect( $before_bad, $url . ' still shows the generic disclosure before repair' );
	expect( ! $report['bad_disclosure'], $url . ' generic disclosure is gone' );
	expect( $report['official'] && $report['price'], $url . ' official Amazon lines are present' );
	expect( ! $report['image_id'] && ! $report['smartwatche'] && ! $report['slug_alt'], $url . ' broken image labels are gone' );
	expect( $report['hrefs'] && $report['amazon_count'] > 0, $url . ' Amazon hrefs stay intact' );
	expect( $report['products'], $url . ' product permalinks stay intact' );
	expect( 0 === $report['junk_anchors'], $url . ' filter links point at the shop' );
	expect( $report['stable'], $url . ' repair is stable' );
	if ( false !== strpos( $url, '2-71-pounds' ) ) {
		$weight_variants = (int) $report['variants'];
	}
}
expect( $weight_variants >= 7, 'macbook cards show storage and color' );

if ( $fail > 0 ) {
	echo "FAILED $fail\n";
	exit( 1 );
}
echo "ALL GREEN\n";
exit( 0 );
