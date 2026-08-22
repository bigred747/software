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

echo $fail ? "FAILED\n" : "ALL PASSED\n";
exit( $fail ? 1 : 0 );
