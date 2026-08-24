<?php
/**
 * php plugins/luxe-blog-review-desk/tests/test-eligibility.php
 */
define( 'ABSPATH', '/tmp/' );
require dirname( __DIR__ ) . '/includes/class-eligibility.php';

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

$live = LBRD_Eligibility::publish_gate(
	array(
		'id'       => 10833,
		'status'   => 'publish',
		'product'  => 63388,
		'score'    => 100,
		'approval' => true,
	)
);
expect( false === $live['ok'], 'master stays read-only' );

$zero = LBRD_Eligibility::publish_gate(
	array(
		'id'       => 73030,
		'status'   => 'draft',
		'product'  => 0,
		'score'    => 100,
		'approval' => true,
	)
);
expect( false === $zero['ok'], 'product 0 blocked' );

$low = LBRD_Eligibility::publish_gate(
	array(
		'id'       => 63523,
		'status'   => 'draft',
		'product'  => 63472,
		'score'    => 93,
		'approval' => false,
	)
);
expect( false === $low['ok'], '93 is not publishable' );

$ok = LBRD_Eligibility::publish_gate(
	array(
		'id'       => 99999,
		'status'   => 'draft',
		'product'  => 63192,
		'score'    => 96,
		'approval' => false,
	)
);
expect( true === $ok['ok'], '96 with product may publish' );

$ready = LBRD_Eligibility::publish_gate(
	array(
		'id'       => 88888,
		'status'   => 'draft',
		'product'  => 63026,
		'score'    => 89,
		'approval' => true,
	)
);
expect( true === $ready['ok'], 'approval ready may publish' );

$already = LBRD_Eligibility::publish_gate(
	array(
		'id'       => 10493,
		'status'   => 'publish',
		'product'  => 62987,
		'score'    => 100,
		'approval' => true,
	)
);
expect( false === $already['ok'], 'already live is view only' );

$GLOBALS['lbrd_meta'] = array(
	73530 => array(
		'_luxe_locked_product'   => 0,
		'_luxe_bmc_product_id'   => 63192,
		'_luxe_bmc_last_score'   => 89,
		'_luxe_bmc_approval_ready' => '',
	),
	63523 => array(
		'_luxe_bmc_product_id' => 63472,
		'_luxe_bmc_last_score' => 93,
	),
	72954 => array(
		'_luxe_bmc_product_id' => 62812,
		'_luxe_bmc_last_score' => 0,
	),
);

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key, $single = false ) {
		unset( $single );
		if ( isset( $GLOBALS['lbrd_meta'][ $post_id ][ $key ] ) ) {
			return $GLOBALS['lbrd_meta'][ $post_id ][ $key ];
		}
		return '';
	}
}

expect( in_array( '_luxe_bmc_product_id', LBRD_Eligibility::product_keys(), true ), 'reads Command Center product lock' );
expect( in_array( '_luxe_bmc_last_score', LBRD_Eligibility::score_keys(), true ), 'reads Command Center last score' );
expect( in_array( '_luxe_bmc_approval_ready', LBRD_Eligibility::approval_keys(), true ), 'reads Command Center approval flag' );
expect( 63192 === LBRD_Eligibility::product_for( 73530 ), 'skips stored 0 and finds Command Center product' );
expect( 89 === LBRD_Eligibility::score_for( 73530 ), 'reads Command Center draft score' );
expect( 63472 === LBRD_Eligibility::product_for( 63523 ), 'laptop stand keeps product 63472' );
expect( 62812 === LBRD_Eligibility::product_for( 72954 ), 'live drone keeps product 62812' );
expect( false === LBRD_Eligibility::approval_for( 73530 ), '89 is not approval ready' );

echo $fail ? "FAILED\n" : "ALL PASSED\n";
exit( $fail ? 1 : 0 );
