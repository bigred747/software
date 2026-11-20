<?php
/**
 * Google structured-data rules for affiliate pages.
 * php tests/test-schema-rules.php
 */
define( 'ABSPATH', '/tmp/' );

function wp_json_encode( $data, $flags = 0 ) {
	$json = json_encode( $data, (int) $flags );
	return is_string( $json ) ? $json : false;
}

require dirname( __DIR__ ) . '/includes/class-schema.php';

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

$graph = array(
	'@context' => 'https://schema.org',
	'@graph'   => array(
		array(
			'@type'           => 'Product',
			'name'            => 'Example headphones',
			'aggregateRating' => array(
				'@type'       => 'AggregateRating',
				'ratingValue' => '5',
				'reviewCount' => '10',
			),
			'review'          => array(
				array(
					'@type' => 'Review',
					'name'  => 'Copied star rating',
				),
			),
			'offers'          => array(
				'@type'          => 'Offer',
				'price'          => '10.00',
				'priceCurrency'  => 'USD',
				'priceValidUntil'=> '2027-12-31',
				'url'            => 'https://www.amazon.com/dp/B0ABCDEF12?tag=shop-20',
			),
		),
		array(
			'@type' => 'BlogPosting',
			'url'   => 'https://luxetrendsetters.com/privacy-policy/',
			'name'  => 'Privacy Policy',
		),
		array(
			'@type'    => 'BlogPosting',
			'url'      => 'https://luxetrendsetters.com/macbook-pro-m2-pro-review-2026/',
			'headline' => 'A real review',
		),
	),
);

$html = '<script type="application/ld+json">' . json_encode( $graph ) . '</script>';
$out  = Luxe_Score_Repair_Schema::instance()->sanitize_html( $html );
expect( false === strpos( $out, 'priceValidUntil' ), 'price is not marked valid until a future date' );
expect( false === strpos( $out, 'AggregateRating' ), 'aggregate rating markup is removed' );
expect( false === strpos( $out, 'Review' ), 'review markup is removed' );
expect( false !== strpos( $out, '"price":"10.00"' ) || false !== strpos( $out, '"price": "10.00"' ), 'visible offer price stays' );
expect( false !== strpos( $out, 'amazon.com/dp/B0ABCDEF12?tag=shop-20' ), 'Amazon offer URL is not rewritten' );
expect( false !== strpos( $out, 'privacy-policy' ), 'privacy page remains in the graph' );
expect( false === strpos( $out, '"@type":"BlogPosting","url":"https://luxetrendsetters.com/privacy-policy/"' ) && false === strpos( $out, '"@type": "BlogPosting"' . "\n" ), 'utility page check runs' );

$decoded = json_decode( preg_replace( '/^<script[^>]*>|<\/script>$/', '', $out ), true );
expect( is_array( $decoded ), 'schema still parses' );
$types = array();
if ( is_array( $decoded ) && isset( $decoded['@graph'] ) ) {
	foreach ( $decoded['@graph'] as $node ) {
		$types[] = $node['@type'] . ' ' . ( isset( $node['url'] ) ? $node['url'] : $node['name'] );
	}
}
expect( in_array( 'WebPage https://luxetrendsetters.com/privacy-policy/', $types, true ), 'privacy page is a WebPage' );
expect( in_array( 'BlogPosting https://luxetrendsetters.com/macbook-pro-m2-pro-review-2026/', $types, true ), 'real review post stays a BlogPosting' );

if ( $fail > 0 ) {
	echo "\n$fail failed\n";
	echo $out . "\n";
	exit( 1 );
}
echo "\nAll schema checks passed\n";
exit( 0 );
