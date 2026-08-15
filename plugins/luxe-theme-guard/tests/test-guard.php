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
expect( 300 === Luxe_Theme_Guard_Learning::INTERVAL, '24/7 cycle is 5 minutes' );
expect( 3600 === Luxe_Theme_Guard_Learning::SNAPSHOT_EVERY, 'snapshot is hourly' );
expect( 43200 === Luxe_Theme_Guard_Learning::UPGRADE_EVERY, 'official upgrade at most every 12 hours' );
expect( 0 === Luxe_Theme_Guard_Learning::next_cursor( 12, 13 ), 'cursor wraps after the last process' );
expect( 1 === Luxe_Theme_Guard_Learning::next_cursor( 0, 13 ), 'cursor advances one process' );
expect( Luxe_Theme_Guard_Learning::should_snapshot( 0, 100 ), 'first snapshot is due' );
expect( ! Luxe_Theme_Guard_Learning::should_snapshot( 90, 100 ), 'snapshot is not due inside an hour' );
expect( Luxe_Theme_Guard_Learning::should_snapshot( 1, 3602 ), 'snapshot is due after an hour' );
expect( Luxe_Theme_Guard_Learning::should_upgrade_check( 0, 10 ), 'first upgrade check is due' );
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

require LUXE_THEME_GUARD_DIR . 'includes/class-amazon.php';
$good_url = 'https://www.amazon.com/dp/B0ABCDEF12/?tag=luxetrendse0f-20';
expect( 'B0ABCDEF12' === Luxe_Theme_Guard_Amazon::extract_asin( $good_url ), 'ASIN from Amazon URL' );
expect( 'luxetrendse0f-20' === Luxe_Theme_Guard_Amazon::extract_tag( $good_url ), 'Associates tag from URL' );
expect( Luxe_Theme_Guard_Amazon::tag_ok( $good_url ), 'official Luxe tag is accepted' );
expect( ! Luxe_Theme_Guard_Amazon::tag_ok( 'https://www.amazon.com/dp/B0ABCDEF12/?tag=other-20' ), 'foreign tag is refused' );
expect( Luxe_Theme_Guard_Amazon::urls_untouched( array( $good_url ), array( $good_url ) ), 'identical Amazon URLs are untouched' );
expect( ! Luxe_Theme_Guard_Amazon::urls_untouched( array( $good_url ), array( $good_url . '&foo=1' ) ), 'changed Amazon URL is detected' );
expect( Luxe_Theme_Guard_Amazon::may_request( 'https://creatorsapi.amazon/catalog/v1/getItems' ), 'Creators API GetItems is allowed' );
expect( Luxe_Theme_Guard_Amazon::may_request( 'https://api.amazon.com/auth/o2/token' ), 'Creators API token endpoint is allowed' );
expect( ! Luxe_Theme_Guard_Amazon::may_request( 'https://webservices.amazon.com/paapi5/getitems' ), 'retired PA-API v5 is refused' );
expect( ! Luxe_Theme_Guard_Amazon::may_request( 'https://www.amazon.com/dp/B0ABCDEF12' ), 'amazon.com HTML scrape is refused' );
expect( 'luxetrendse0f-20' === Luxe_Theme_Guard_Amazon::sanitize_tag( '???' ), 'invalid tag falls back to luxetrendse0f-20' );

$amazon_ok = Luxe_Theme_Guard_Amazon::build(
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

$miss_tag = Luxe_Theme_Guard_Amazon::audit_item(
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

if ( $fail ) {
	echo "FAILED $fail\n";
	exit( 1 );
}
echo "ALL PASSED\n";
exit( 0 );
