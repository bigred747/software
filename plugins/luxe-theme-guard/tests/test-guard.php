<?php
/**
 * CLI checks for Flatsome version gates and upgrade refusals.
 * php tests/test-guard.php
 */
define( 'ABSPATH', '/tmp/' );
define( 'LUXE_THEME_GUARD_DIR', dirname( __DIR__ ) . '/' );

require LUXE_THEME_GUARD_DIR . 'includes/class-signals.php';

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

expect( Luxe_Theme_Guard_Signals::xss_patched( '3.20.6' ), '3.20.6 is XSS patched' );
expect( Luxe_Theme_Guard_Signals::xss_patched( '3.20.9' ), '3.20.9 is XSS patched' );
expect( ! Luxe_Theme_Guard_Signals::xss_patched( '3.20.5' ), '3.20.5 is not XSS patched' );
expect( Luxe_Theme_Guard_Signals::at_recommended( '3.20.9' ), '3.20.9 meets recommended' );
expect( ! Luxe_Theme_Guard_Signals::at_recommended( '3.20.6' ), '3.20.6 is below recommended 3.20.9' );

expect(
	Luxe_Theme_Guard_Signals::may_upgrade( 'flatsome', 'https://api.uxthemes.com/flatsome.zip', '3.20.6', '3.20.9' ),
	'official HTTPS package may upgrade'
);
expect(
	! Luxe_Theme_Guard_Signals::may_upgrade( 'flatsome-child', 'https://api.uxthemes.com/flatsome.zip', '3.20.6', '3.20.9' ),
	'child theme is never upgraded'
);
expect(
	! Luxe_Theme_Guard_Signals::may_upgrade( 'luxe-affiliate-product-scout', 'https://example.com/x.zip', '1.0', '2.0' ),
	'plugins are never treated as Flatsome'
);
expect(
	! Luxe_Theme_Guard_Signals::may_upgrade( 'flatsome', '', '3.20.6', '3.20.9' ),
	'empty package is refused'
);
expect(
	! Luxe_Theme_Guard_Signals::may_upgrade( 'flatsome', 'http://nulled-theme.example/flatsome.zip', '3.20.6', '3.20.9' ),
	'http nulled URL is refused'
);
expect(
	! Luxe_Theme_Guard_Signals::may_upgrade( 'flatsome', 'https://weadown.com/flatsome.zip', '3.20.6', '3.20.9' ),
	'pirate host is refused'
);
expect(
	! Luxe_Theme_Guard_Signals::may_upgrade( 'flatsome', 'https://api.uxthemes.com/flatsome.zip', '3.20.9', '3.20.9' ),
	'already current is not upgraded'
);

$board = Luxe_Theme_Guard_Signals::build(
	array(
		'installed'   => true,
		'version'     => '3.20.9',
		'available'   => '',
		'package'     => '',
		'stylesheet'  => 'flatsome-child',
		'child'       => 'flatsome-child',
		'license'     => true,
		'xss_ok'      => true,
		'recommended' => true,
		'can_upgrade' => false,
	)
);
expect( (int) $board['green'] === (int) $board['total'], 'current licensed 3.20.9 is all green' );
expect( $board['total'] >= 12, 'at least 12 green signals' );

$parent_only = Luxe_Theme_Guard_Signals::build(
	array(
		'installed'   => true,
		'version'     => '3.20.6',
		'available'   => '',
		'package'     => '',
		'stylesheet'  => 'flatsome',
		'child'       => 'flatsome',
		'license'     => false,
		'xss_ok'      => true,
		'recommended' => false,
		'can_upgrade' => false,
	)
);
$child_row = null;
$red_ids   = array();
foreach ( $parent_only['signals'] as $signal ) {
	if ( 'child' === $signal['id'] ) {
		$child_row = $signal;
	}
	if ( 'red' === $signal['level'] ) {
		$red_ids[] = $signal['id'];
	}
}
expect( $child_row && 'green' === $child_row['level'], 'parent-only is green, not a failure' );
expect( array( 'recommended', 'license' ) === $red_ids, 'only license and 3.20.9 stay red without a purchase code' );
expect( 11 === (int) $parent_only['green'], '3.20.6 without license is 11 / 13 green' );

if ( $fail ) {
	echo "FAILED $fail\n";
	exit( 1 );
}
echo "ALL PASSED\n";
exit( 0 );
