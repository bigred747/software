<?php
/**
 * Keyword plan, score, and speed gate.
 * php plugins/luxe-keyword-intelligence-autopilot/tests/test-autopilot.php
 */
define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__ ) . '/includes/class-phrases.php';
require dirname( __DIR__ ) . '/includes/class-score.php';
require dirname( __DIR__ ) . '/includes/class-plan.php';
require dirname( __DIR__ ) . '/includes/class-gate.php';
require dirname( __DIR__ ) . '/includes/class-taxonomies.php';

$fail = 0;

/**
 * @param bool   $ok Result.
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

/**
 * @param array $item Item.
 * @param array $plan Plan.
 * @return array
 */
function apply_plan( $item, $plan ) {
	foreach ( $plan['writes'] as $write ) {
		if ( 'rank_math_focus_keyword' === $write['key'] ) {
			$item['focus'] = $write['value'];
		} elseif ( 'rank_math_title' === $write['key'] ) {
			$item['seo_title'] = $write['value'];
		} elseif ( 'rank_math_description' === $write['key'] ) {
			$item['description'] = $write['value'];
		} elseif ( '_wp_attachment_image_alt' === $write['key'] ) {
			$item['alt']        = $write['value'];
			$item['alt_broken'] = false;
		}
	}
	return $item;
}

$settings = array(
	'green'      => 95,
	'confidence' => 80,
	'phrases'    => 5,
	'repair_meta'=> 1,
	'repair_alt' => 1,
	'batch'      => 12,
);

$title = 'Apple 2026 MacBook Air 13-inch Laptop with M5 chip: Built for AI, 13-inch Liquid Retina display';
$item  = array(
	'id'          => 11726,
	'title'       => $title,
	'status'      => 'draft',
	'focus'       => '',
	'seo_title'   => '',
	'description' => '',
	'alt'         => '31JknBT2iiL',
	'has_image'   => true,
	'alt_broken'  => true,
);
$plan = Luxe_KIA_Plan::build( $item, $settings );
echo 'SCORE ' . $plan['before']['score'] . ' -> ' . $plan['after']['score'] . ' writes ' . count( $plan['writes'] ) . "\n";
expect( $plan['after']['score'] >= 95, 'a weak draft reaches 95-100' );
expect( count( $plan['phrases'] ) >= 3, 'at least three phrases' );
$keys = array();
foreach ( $plan['writes'] as $write ) {
	$keys[] = $write['key'];
	expect( false === strpos( (string) $write['value'], 'http' ), 'write has no URL: ' . $write['key'] );
	expect( in_array( $write['key'], Luxe_KIA_Plan::allowed_keys(), true ), 'allowed key ' . $write['key'] );
}
expect( ! in_array( 'post_content', $keys, true ) && ! in_array( 'post_status', $keys, true ), 'content and status are not written' );
$fixed = apply_plan( $item, $plan );
$again = Luxe_KIA_Plan::build( $fixed, $settings );
expect( array() === $again['writes'], 'a second pass does not write again' );
expect( $again['before']['score'] >= 95, 'the repaired item stays green' );

$junk = Luxe_KIA_Plan::build(
	array(
		'title'       => '31JknBT2iiL',
		'status'      => 'publish',
		'focus'       => '',
		'seo_title'   => '',
		'description' => '',
		'has_image'   => false,
		'alt_broken'  => false,
	),
	$settings
);
expect( array() === $junk['writes'], 'an image id is not turned into a keyword' );

$future = $item;
$future['status'] = 'future';
$future_plan = Luxe_KIA_Plan::build( $future, $settings );
expect( array() === $future_plan['writes'], 'scheduled items are scan-only' );

$linked = Luxe_KIA_Plan::build(
	array(
		'title'       => 'https://www.amazon.com/dp/B0ABCDEF12?tag=luxetrendse0f-20 Apple Watch Series 11',
		'status'      => 'publish',
		'focus'       => '',
		'seo_title'   => '',
		'description' => '',
		'has_image'   => false,
		'alt_broken'  => false,
	),
	$settings
);
foreach ( $linked['writes'] as $write ) {
	expect( false === stripos( (string) $write['value'], 'amazon.com' ), 'Amazon host is not stored in metadata' );
}

$batch = Luxe_KIA_Gate::batch_size( 32.37, 3 * 1024 * 1024 * 1024, $settings );
$burst = Luxe_KIA_Gate::burst_size( 32.37, 3 * 1024 * 1024 * 1024 );
expect( 6 === $batch, 'load 32 keeps a batch of 6' );
expect( 48 === $burst, 'load 32 allows a 48 item burst' );
expect( 0 === Luxe_KIA_Gate::batch_size( 70, 0, $settings ), 'extreme load skips the cycle' );
expect( 1 === Luxe_KIA_Gate::batch_size( 1, 32 * 1024 * 1024, $settings ), 'low memory stays at one item' );
expect( 'scan_cap' === Luxe_KIA_Gate::allowance( 960, 10, 960, 480 ), 'daily scan cap stops the engine' );
expect( 'ok' === Luxe_KIA_Gate::allowance( 96, 24, 960, 480 ), 'the old 96/24 day is no longer the stop' );
$per_hour = (int) floor( ( 60 / 5 ) * $batch );
expect( $per_hour >= 60, 'about ' . $per_hour . ' items an hour at the current load' );
expect( (int) ceil( 411 / $burst ) <= 10, 'the attention burst can cover 411 items in a handful of clicks' );

$slugs = Luxe_KIA_Taxonomies::merge( 'product_cat, item-weight' );
expect( in_array( 'item-weight', $slugs, true ) && in_array( 'date-first-available', $slugs, true ), 'weight and date filters stay in the guard' );
expect( ! in_array( 'product_cat', $slugs, true ), 'product categories stay out of the noindex list' );
expect( Luxe_KIA_Plan::alt_is_broken( '31wjBg5rSiL' ) && Luxe_KIA_Plan::alt_is_broken( 'Black Spor smartwatche' ), 'broken image labels are detected' );
expect( ! Luxe_KIA_Plan::alt_is_broken( 'Apple Watch Series 11' ), 'a real image label is kept' );

$root = dirname( __DIR__ );
foreach ( array( 'includes', 'luxe-keyword-intelligence-autopilot.php' ) as $relative ) {
	$path = $root . '/' . $relative;
	$files = is_dir( $path ) ? glob( $path . '/*.php' ) : array( $path );
	foreach ( $files as $file ) {
		$body = file_get_contents( $file );
		foreach ( array( 'wp_insert_post', 'wp_update_post', 'wp_publish_post' ) as $needle ) {
			expect( false === strpos( (string) $body, $needle ), basename( $file ) . ' has no ' . $needle );
		}
	}
}

if ( $fail > 0 ) {
	echo "FAILED $fail\n";
	exit( 1 );
}
echo "ALL GREEN\n";
exit( 0 );
