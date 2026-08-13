<?php
/**
 * CLI checks for brand, bucket, ASIN, and category conflict rules.
 * php tests/test-catalog.php
 */
define( 'ABSPATH', '/tmp/' );
define( 'LAPS_DIR', dirname( __DIR__ ) . '/' );

function wp_strip_all_tags( $text ) {
	return strip_tags( $text );
}

require LAPS_DIR . 'includes/class-catalog.php';
require LAPS_DIR . 'includes/class-score.php';

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

expect( 'Samsung' === LAPS_Catalog::title_brand( 'Samsung Galaxy Watch Ultra 2025' ), 'Samsung title brand' );
expect( 'Apple' === LAPS_Catalog::title_brand( 'Apple Watch Series 9 GPS' ), 'Apple title brand' );
expect( 'BENFEI' === LAPS_Catalog::title_brand( 'BENFEI Laptop Stand for Desk' ), 'BENFEI title brand' );
expect( 'Bose' === LAPS_Catalog::title_brand( 'Bose QuietComfort Ultra Earbuds' ), 'Bose title brand' );
expect( '' === LAPS_Catalog::title_brand( 'Ultra Bright Google TV 4K Support Smart Projector' ), 'Google TV is a feature not a brand' );
expect( 'PUTRIMS' === LAPS_Catalog::title_brand( 'PUTRIMS K12 Projector' ), 'PUTRIMS leading brand' );
expect( '' === LAPS_Catalog::title_brand( '16 Purple Laptop Computer 512GB' ), 'unknown generic laptop stays unknown' );
expect( LAPS_Catalog::is_hard_risk( 'Apple Watch Series 11 Renewed' ), 'renewed is hard risk' );
expect( ! LAPS_Catalog::is_hard_risk( 'Apple Watch Series 9 GPS' ), 'new watch is not hard risk' );
expect( 'samsung-watch' === LAPS_Catalog::bucket( 'Samsung Galaxy Watch Ultra', 'Samsung' ), 'samsung watch bucket' );
expect( 'apple-watch' === LAPS_Catalog::bucket( 'Apple Watch Series 9', 'Apple' ), 'apple watch bucket' );
expect( 'laptop-accessory' === LAPS_Catalog::bucket( 'BENFEI Laptop Stand for Desk', 'BENFEI' ), 'stand is accessory' );
expect( 'earbuds' === LAPS_Catalog::bucket( 'Bose QuietComfort Ultra Earbuds', 'Bose' ), 'earbuds bucket' );
expect( LAPS_Catalog::category_conflicts( 'Apple Watches apple-watches', 'Samsung', 'Samsung Galaxy Watch Ultra' ), 'Samsung watch cannot stay in Apple Watches' );
expect( ! LAPS_Catalog::category_conflicts( 'Apple Watches apple-watches', 'Apple', 'Apple Watch Series 9' ), 'Apple watch may stay in Apple Watches' );
expect( LAPS_Catalog::category_conflicts( 'MacBooks macbooks', 'BENFEI', 'BENFEI Laptop Stand for Desk' ), 'stand cannot stay in MacBooks' );
expect( 'B0ABCDEF12' === LAPS_Catalog::extract_asin( 'https://www.amazon.com/dp/B0ABCDEF12/?tag=x' ), 'ASIN from Amazon URL' );
expect( 'B0ABCDEF12' === LAPS_Catalog::extract_asin( 'B0ABCDEF12' ), 'ASIN from SKU' );
expect( LAPS_Catalog::brand_matches( 'Bose Corporation', 'Bose' ), 'Bose Corporation matches Bose' );
expect( LAPS_Catalog::brand_matches( 'SHOKZ', 'Shokz' ), 'SHOKZ matches Shokz' );
expect( ! LAPS_Catalog::brand_matches( 'Sony', 'Bose' ), 'Sony does not match Bose' );
expect( LAPS_Catalog::has_model_signal( 'Sony WH-1000XM6 The Best Noise Canceling Wireless Headphones' ), 'WH-1000XM6 is a model signal' );
expect( LAPS_Catalog::has_model_signal( 'Apple Watch Series 11 GPS 42mm' ), 'Series 11 is a model signal' );
expect( LAPS_Catalog::category_conflicts( 'Sony sony', 'Bose', 'Bose QuietComfort Ultra Earbuds' ), 'Bose product cannot stay in Sony category' );

$bose = LAPS_Score::decide(
	array(
		'brand'              => 'Bose',
		'asin'               => true,
		'price'              => true,
		'image'              => true,
		'hard_risk'          => false,
		'image_count'        => 4,
		'model'              => true,
		'category'           => true,
		'tags'               => true,
		'taxonomy_conflict'  => true,
		'attribute_conflict' => true,
	)
);
expect( 100 === $bose['readiness'], 'Bose readiness 100 even with taxonomy leftover' );
expect( 100 === $bose['integrity'], 'Bose integrity 100 even with taxonomy leftover' );
expect( 'READY' === $bose['status'], 'Bose status READY' );
expect( 'yes' === $bose['ready'], 'Bose homepage ready' );

$airpods = LAPS_Score::decide(
	array(
		'brand'       => 'Apple',
		'asin'        => true,
		'price'       => true,
		'image'       => true,
		'hard_risk'   => false,
		'image_count' => 4,
		'model'       => true,
		'category'    => true,
		'tags'        => true,
	)
);
expect( 100 === $airpods['integrity'], 'Apple AirPods with model signal is 100' );

$unclear = LAPS_Score::decide(
	array(
		'brand'       => 'TCL',
		'asin'        => true,
		'price'       => true,
		'image'       => true,
		'hard_risk'   => false,
		'image_count' => 4,
		'model'       => false,
		'category'    => true,
		'tags'        => true,
	)
);
expect( 95 === $unclear['integrity'], 'Model not clear stays 95 not below' );
expect( $unclear['readiness'] >= 95, 'Model not clear readiness still 95+' );

$renewed = LAPS_Score::decide(
	array(
		'brand'       => 'Samsung',
		'asin'        => true,
		'price'       => true,
		'image'       => true,
		'hard_risk'   => true,
		'image_count' => 2,
		'model'       => true,
		'category'    => true,
		'tags'        => true,
	)
);
expect( 'HOLD' === $renewed['status'], 'Renewed stays HOLD' );
expect( $renewed['integrity'] <= 70, 'Renewed integrity capped' );
expect( $renewed['readiness'] <= 94, 'Renewed readiness capped' );

$unknown = LAPS_Score::decide(
	array(
		'brand'       => '',
		'asin'        => true,
		'price'       => true,
		'image'       => true,
		'hard_risk'   => false,
		'image_count' => 4,
		'model'       => false,
		'category'    => true,
		'tags'        => true,
	)
);
expect( 'HOLD' === $unknown['status'], 'Unknown brand stays HOLD' );
expect( $unknown['readiness'] <= 94, 'Unknown brand cannot reach 95' );

$missing = LAPS_Score::decide(
	array(
		'brand'       => 'Sony',
		'asin'        => false,
		'price'       => true,
		'image'       => true,
		'hard_risk'   => false,
		'image_count' => 4,
		'model'       => true,
		'category'    => true,
		'tags'        => true,
	)
);
expect( 'REVIEW' === $missing['status'], 'Missing ASIN is REVIEW' );
expect( $missing['integrity'] <= 94, 'Missing ASIN cannot reach 95' );

if ( $fail ) {
	echo "FAILED $fail\n";
	exit( 1 );
}
echo "ALL PASSED\n";
exit( 0 );
