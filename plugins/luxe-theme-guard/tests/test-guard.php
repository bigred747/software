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

require LUXE_THEME_GUARD_DIR . 'includes/class-learning.php';
expect( Luxe_Theme_Guard_Learning::needs_reschedule( 'ltg_twelve' ), 'old 12-hour timer must be rescheduled' );
expect( ! Luxe_Theme_Guard_Learning::needs_reschedule( 'ltg_five' ), '5-minute timer stays' );

$stale = Luxe_Theme_Guard_Signals::build(
	array(
		'installed'     => true,
		'version'       => '3.20.9',
		'available'     => '',
		'package'       => '',
		'stylesheet'    => 'flatsome-child',
		'child'         => 'flatsome-child',
		'license'       => true,
		'xss_ok'        => true,
		'recommended'   => true,
		'can_upgrade'   => false,
		'learning_on'   => true,
		'cron_next'     => time() + 43200,
		'cron_schedule' => 'ltg_twelve',
	)
);
$stale_cron = null;
foreach ( $stale['signals'] as $signal ) {
	if ( 'cron' === $signal['id'] ) {
		$stale_cron = $signal;
	}
}
expect( $stale_cron && 'red' === $stale_cron['level'], 'leftover 12-hour cron is red until migrated' );
expect( 3600 === Luxe_Theme_Guard_Learning::SNAPSHOT_EVERY, 'snapshot is hourly' );
expect( 43200 === Luxe_Theme_Guard_Learning::UPGRADE_EVERY, 'official upgrade at most every 12 hours' );
expect( 0 === Luxe_Theme_Guard_Learning::next_cursor( 12, 13 ), 'cursor wraps after the last process' );
expect( 1 === Luxe_Theme_Guard_Learning::next_cursor( 0, 13 ), 'cursor advances one process' );
expect( Luxe_Theme_Guard_Learning::should_snapshot( 0, 100 ), 'first snapshot is due' );
expect( ! Luxe_Theme_Guard_Learning::should_snapshot( 90, 100 ), 'snapshot is not due inside an hour' );
expect( Luxe_Theme_Guard_Learning::should_snapshot( 1, 3602 ), 'snapshot is due after an hour' );
expect( 'bg91c1z0-bdcf-457f-a3e5-4e0762896c2d' === Luxe_Theme_Guard_Signals::sanitize_purchase_code( 'BG91C1Z0-BDCF-457F-A3E5-4E0762896C2D' ), 'Envato UUID purchase code is accepted' );
expect( 'a1b2c3d4-e5f6-4789-abcd-ef1234567890' === Luxe_Theme_Guard_Signals::sanitize_purchase_code( 'A1B2C3D4-E5F6-4789-ABCD-EF1234567890' ), 'hex UUID purchase code is accepted' );
expect( '' === Luxe_Theme_Guard_Signals::sanitize_purchase_code( 'not-a-purchase-code' ), 'garbage purchase code is refused' );
expect( Luxe_Theme_Guard_Learning::should_refresh_updates( true, 90, 100 ), 'behind Flatsome refreshes every cycle' );
expect( Luxe_Theme_Guard_Learning::should_apply_upgrade( true ), 'official package is applied immediately' );
expect( ! Luxe_Theme_Guard_Learning::should_apply_upgrade( false ), 'no package means no upgrade' );
expect( ! Luxe_Theme_Guard_Learning::should_upgrade_check( 10, 100 ), 'upgrade is not due inside 12 hours' );
expect( Luxe_Theme_Guard_Learning::should_upgrade_check( 1, 43202 ), 'upgrade check is due after 12 hours' );

$focus = Luxe_Theme_Guard_Learning::focus_row( $parent_only['signals'], 2 );
expect( isset( $focus['id'] ) && 'recommended' === $focus['id'], 'cycle 2 learns the recommended-version process' );

$off = Luxe_Theme_Guard_Signals::build(
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
		'learning_on' => false,
		'cron_next'   => 0,
	)
);
$cron_row = null;
foreach ( $off['signals'] as $signal ) {
	if ( 'cron' === $signal['id'] ) {
		$cron_row = $signal;
	}
}
expect( $cron_row && 'red' === $cron_row['level'], '24/7 off is a red process-learning signal' );

if ( $fail ) {
	echo "FAILED $fail\n";
	exit( 1 );
}
echo "ALL PASSED\n";
exit( 0 );
