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

if ( $fail ) {
	echo "FAILED $fail\n";
	exit( 1 );
}
echo "ALL PASSED\n";
exit( 0 );
