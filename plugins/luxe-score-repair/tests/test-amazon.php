<?php
/**
 * CLI checks for Amazon AI on the SEO plugin.
 * php tests/test-amazon.php
 */
define( 'ABSPATH', '/tmp/' );
define( 'LUXE_SCORE_REPAIR_DIR', dirname( __DIR__ ) . '/' );

require LUXE_SCORE_REPAIR_DIR . 'includes/class-amazon.php';

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

$good_url = 'https://www.amazon.com/dp/B0ABCDEF12/?tag=luxetrendse0f-20';
expect( 'B0ABCDEF12' === Luxe_Score_Repair_Amazon::extract_asin( $good_url ), 'ASIN from Amazon URL' );
expect( 'luxetrendse0f-20' === Luxe_Score_Repair_Amazon::extract_tag( $good_url ), 'Associates tag from URL' );
expect( Luxe_Score_Repair_Amazon::tag_ok( $good_url ), 'official Luxe tag is accepted' );
expect( ! Luxe_Score_Repair_Amazon::tag_ok( 'https://www.amazon.com/dp/B0ABCDEF12/?tag=other-20' ), 'foreign tag is refused' );
expect( Luxe_Score_Repair_Amazon::urls_untouched( array( $good_url ), array( $good_url ) ), 'identical Amazon URLs are untouched' );
expect( ! Luxe_Score_Repair_Amazon::urls_untouched( array( $good_url ), array( $good_url . '&foo=1' ) ), 'changed Amazon URL is detected' );
expect( Luxe_Score_Repair_Amazon::may_request( 'https://creatorsapi.amazon/catalog/v1/getItems' ), 'Creators API GetItems is allowed' );
expect( Luxe_Score_Repair_Amazon::may_request( 'https://api.amazon.com/auth/o2/token' ), 'Creators API token endpoint is allowed' );
expect( ! Luxe_Score_Repair_Amazon::may_request( 'https://webservices.amazon.com/paapi5/getitems' ), 'retired PA-API v5 is refused' );
expect( ! Luxe_Score_Repair_Amazon::may_request( 'https://www.amazon.com/dp/B0ABCDEF12' ), 'amazon.com HTML scrape is refused' );
expect( 'luxetrendse0f-20' === Luxe_Score_Repair_Amazon::sanitize_tag( '???' ), 'invalid tag falls back to luxetrendse0f-20' );

$amazon_ok = Luxe_Score_Repair_Amazon::build(
	array(
		'armed'     => true,
		'tag'       => 'luxetrendse0f-20',
		'tag_ok'    => true,
		'asin'      => 'B0ABCDEF12',
		'asin_ok'   => true,
		'preserved' => true,
		'api_mode'  => 'local',
		'has_creds' => false,
		'source'    => 'test',
	)
);
expect( (int) $amazon_ok['green'] === (int) $amazon_ok['total'], 'local Amazon AI board is all green' );
expect( 10 === (int) $amazon_ok['total'], 'Amazon AI has 10 signals' );

$miss_tag = Luxe_Score_Repair_Amazon::audit_item(
	array(
		'want_tag'        => 'luxetrendse0f-20',
		'url'             => 'https://www.amazon.com/dp/B0ABCDEF12',
		'invented_rating' => false,
		'urls_before'     => array( 'https://www.amazon.com/dp/B0ABCDEF12' ),
		'urls_after'      => array( 'https://www.amazon.com/dp/B0ABCDEF12' ),
	)
);
expect( ! empty( $miss_tag['asin_ok'] ) && empty( $miss_tag['tag_ok'] ), 'missing tag is flagged and ASIN is still read' );
expect( ! empty( $miss_tag['preserved'] ), 'audit does not rewrite the Amazon URL' );

$php = (string) file_get_contents( LUXE_SCORE_REPAIR_DIR . 'luxe-score-repair.php' );
expect( false !== strpos( $php, 'Version: 1.3.0' ), 'SEO plugin version is 1.3.0' );
expect( false !== strpos( $php, 'class-amazon.php' ), 'Amazon AI is required by the SEO plugin' );

if ( $fail ) {
	echo "FAILED $fail\n";
	exit( 1 );
}
echo "ALL PASSED\n";
exit( 0 );
