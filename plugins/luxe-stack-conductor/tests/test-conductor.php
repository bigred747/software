<?php
/**
 * CLI checks for lanes, stagger order, and honest signals.
 * php tests/test-conductor.php
 */
define( 'ABSPATH', '/tmp/' );

require dirname( __DIR__ ) . '/includes/class-registry.php';
require dirname( __DIR__ ) . '/includes/class-dispatcher.php';
require dirname( __DIR__ ) . '/includes/class-signals.php';

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

$catalog = Luxe_Conductor_Registry::catalog();
$names   = array();
foreach ( $catalog as $entry ) {
	$names[] = Luxe_Conductor_Registry::normalize( $entry['name'] );
}
expect( count( $catalog ) >= 53, 'catalog covers the live stack plus companions' );
expect( count( $names ) === count( array_unique( $names ) ), 'catalog names are unique' );
expect( null !== Luxe_Conductor_Registry::by_name( 'Luxe Reader-Love Unified 95–100 Prep & Score' ), 'en dash matches Reader-Love' );
expect( null !== Luxe_Conductor_Registry::by_name( 'Luxe Unified Site Guardian – Green Signals Safe Rebuild' ), 'en dash matches Site Guardian' );
expect( null !== Luxe_Conductor_Registry::by_name( 'Luxe WOW Homepage Rotator — Ultra Premium Edition' ), 'em dash matches the rotator' );

$flags = Luxe_Conductor_Registry::event_flags( 'laps_learn', '' );
expect( $flags['heavy'] && 'catalog' === $flags['lane'] && ! $flags['protected'], 'Product Scout learn is a heavy catalog job' );
$flags = Luxe_Conductor_Registry::event_flags( 'luxe_conductor_tick', 'Luxe Stack Conductor' );
expect( ! $flags['heavy'] && ! $flags['protected'], 'Conductor tick is never postponed' );
$flags = Luxe_Conductor_Registry::event_flags( 'woocommerce_cancel_unpaid_orders', 'WooCommerce' );
expect( $flags['protected'] && ! $flags['heavy'], 'WooCommerce cron is protected' );
$flags = Luxe_Conductor_Registry::event_flags( 'rank_math/sitemap/hit_index', 'Rank Math SEO' );
expect( $flags['protected'] && ! $flags['heavy'], 'Rank Math cron is protected' );

$now  = 100000;
$plan = Luxe_Conductor_Dispatcher::plan(
	array(
		array( 'hook' => 'laps_learn', 'timestamp' => 10, 'lane' => 'catalog', 'heavy' => true, 'priority' => 5 ),
		array( 'hook' => 'luxe_download', 'timestamp' => 10, 'lane' => 'catalog', 'heavy' => true, 'priority' => 1 ),
		array( 'hook' => 'reader_love', 'timestamp' => 10, 'lane' => 'blog', 'heavy' => true, 'priority' => 1 ),
		array( 'hook' => 'keyword', 'timestamp' => 10, 'lane' => 'seo-meta', 'heavy' => true, 'priority' => 4 ),
		array( 'hook' => 'woocommerce_cleanup', 'timestamp' => 10, 'lane' => 'commerce', 'heavy' => true, 'protected' => true, 'priority' => 99 ),
		array( 'hook' => 'luxe_conductor_tick', 'timestamp' => 10, 'lane' => 'coordination', 'heavy' => false, 'priority' => 0 ),
	),
	$now,
	1
);
expect( array( 'laps_learn' ) === $plan['keep'], 'cap 1 keeps the highest priority heavy job' );
expect( 3 === count( $plan['move'] ), 'same-lane and extra lanes are postponed' );
$moved = array();
foreach ( $plan['move'] as $event ) {
	$moved[ $event['hook'] ] = $event['new_timestamp'];
}
expect( isset( $moved['luxe_download'], $moved['reader_love'] ) && ! isset( $moved['woocommerce_cleanup'] ), 'protected and idle hooks stay on this hit' );
expect( ( $now + 75 ) === min( $moved ) && ( $now + 225 ) === max( $moved ), 'postponed jobs land 75 seconds apart' );

$plan = Luxe_Conductor_Dispatcher::plan(
	array(
		array( 'hook' => 'a', 'timestamp' => 10, 'lane' => 'catalog', 'heavy' => true, 'priority' => 5 ),
		array( 'hook' => 'b', 'timestamp' => 10, 'lane' => 'catalog', 'heavy' => true, 'priority' => 1 ),
		array( 'hook' => 'c', 'timestamp' => 10, 'lane' => 'public', 'heavy' => true, 'priority' => 1 ),
	),
	$now,
	2
);
sort( $plan['keep'] );
expect( array( 'a', 'c' ) === $plan['keep'], 'cap 2 still keeps one job per lane' );

list( $cap, $streak ) = Luxe_Conductor_Dispatcher::next_cap( 4, 1, 0 );
expect( 1 === $cap && 1 === $streak, 'one fast hit does not raise the cap' );
list( $cap, $streak ) = Luxe_Conductor_Dispatcher::next_cap( 4, 1, 2 );
expect( 2 === $cap && 0 === $streak, 'three fast hits raise the cap to 2' );
list( $cap, $streak ) = Luxe_Conductor_Dispatcher::next_cap( 14, 3, 2 );
expect( 1 === $cap && 0 === $streak, 'a slow hit drops the cap to 1' );
list( $cap, $streak ) = Luxe_Conductor_Dispatcher::next_cap( 0, 2, 2 );
expect( 1 === $cap && 0 === $streak, 'unknown duration stays at one job' );

$now = 1700000000;
$installed = array(
	array( 'name' => 'Classic Editor', 'version' => '1.7.0', 'active' => true ),
	array( 'name' => 'WooCommerce', 'version' => '11.2.1', 'active' => true ),
	array( 'name' => 'Rank Math SEO', 'version' => '1.0.280', 'active' => true ),
	array( 'name' => 'LiteSpeed Cache', 'version' => '7.9.1', 'active' => true ),
	array( 'name' => 'WZone - WooCommerce Amazon Affiliates', 'version' => '15.3.0', 'active' => true ),
	array( 'name' => 'Luxe Affiliate Product Scout', 'version' => '5.6.8', 'active' => true ),
	array( 'name' => 'Luxe Stack Conductor', 'version' => '1.0.0', 'active' => true ),
	array( 'name' => 'Luxe WZone Stats Guardian', 'version' => '1.2.0', 'active' => true ),
	array( 'name' => 'Richard Brummer SEO Mission Control', 'version' => '1.8.0', 'active' => true ),
	array( 'name' => 'Luxe Score Repair', 'version' => '1.1.0', 'active' => false ),
);
$hooks = array(
	array( 'hook' => 'laps_learn', 'next' => $now + 120, 'interval' => 300 ),
);
$owners = array( 'laps_learn' => 'Luxe Affiliate Product Scout' );
$board  = Luxe_Conductor_Signals::board(
	$installed,
	$hooks,
	$owners,
	$now,
	array(
		'laps_dashboard' => array(
			'scanned' => 233,
			'ready' => 198,
			'held' => 35,
			'review' => 0,
			'avg_readiness' => 97,
			'avg_integrity' => 94,
		),
		'child_counts' => array(
			'Luxe WZone Stats Guardian' => array( 'green' => 48, 'total' => 49 ),
		),
	)
);
$by = array();
foreach ( $board['rows'] as $row ) {
	$by[ $row['name'] ] = $row;
}
expect( 'green' === $by['Classic Editor']['level'], 'idle active plugin is green' );
expect( 'green' === $by['Luxe Affiliate Product Scout']['level'], 'scout with a future cycle and a balanced board is green' );
expect( 'yellow' === $by['Luxe Stack Conductor']['level'], 'conductor without its 5-minute hook is yellow' );
expect( 'yellow' === $by['Luxe WZone Stats Guardian']['level'], '48/49 stays yellow' );
expect( 'red' === $by['Luxe Score Repair']['level'], 'inactive plugin is red' );
expect( false !== strpos( $by['Richard Brummer SEO Mission Control']['detail'], 'Mission Control' ) || 'green' === $by['Richard Brummer SEO Mission Control']['level'], 'Mission Control stays active without a painted evidence score' );

$late = Luxe_Conductor_Signals::board(
	array(
		array( 'name' => 'Luxe Affiliate Product Scout', 'version' => '5.6.8', 'active' => true ),
		array( 'name' => 'WooCommerce', 'version' => '11.2.1', 'active' => true ),
		array( 'name' => 'Rank Math SEO', 'version' => '1.0.280', 'active' => true ),
		array( 'name' => 'LiteSpeed Cache', 'version' => '7.9.1', 'active' => true ),
		array( 'name' => 'WZone - WooCommerce Amazon Affiliates', 'version' => '15.3.0', 'active' => true ),
		array( 'name' => 'Luxe Stack Conductor', 'version' => '1.0.0', 'active' => true ),
	),
	array(
		array( 'hook' => 'laps_learn', 'next' => $now - 50 * 60, 'interval' => 300 ),
		array( 'hook' => 'luxe_conductor_tick', 'next' => $now + 60, 'interval' => 300 ),
	),
	array(
		'laps_learn' => 'Luxe Affiliate Product Scout',
		'luxe_conductor_tick' => 'Luxe Stack Conductor',
	),
	$now,
	array()
);
$late_by = array();
foreach ( $late['rows'] as $row ) {
	$late_by[ $row['name'] ] = $row;
}
expect( 'green' !== $late_by['Luxe Affiliate Product Scout']['level'], 'overdue learning is not forced green' );
expect( 'green' === $late_by['Luxe Stack Conductor']['level'], 'a scheduled conductor tick is green' );

$missing = Luxe_Conductor_Signals::board( array(), array(), array(), $now, array() );
$missing_names = array();
foreach ( $missing['rows'] as $row ) {
	if ( 'red' === $row['level'] ) {
		$missing_names[] = $row['name'];
	}
}
expect( in_array( 'WooCommerce', $missing_names, true ), 'missing WooCommerce is red' );
expect( in_array( 'Rank Math SEO', $missing_names, true ), 'missing Rank Math is red' );

$bad = Luxe_Conductor_Signals::board(
	array(
		array( 'name' => 'Luxe Affiliate Product Scout', 'version' => '5.6.8', 'active' => true ),
		array( 'name' => 'WooCommerce', 'version' => '11.2.1', 'active' => true ),
		array( 'name' => 'Rank Math SEO', 'version' => '1.0.280', 'active' => true ),
		array( 'name' => 'LiteSpeed Cache', 'version' => '7.9.1', 'active' => true ),
		array( 'name' => 'WZone - WooCommerce Amazon Affiliates', 'version' => '15.3.0', 'active' => true ),
	),
	array( array( 'hook' => 'laps_learn', 'next' => $now + 60, 'interval' => 300 ) ),
	array(),
	$now,
	array(
		'laps_dashboard' => array(
			'scanned' => 10,
			'ready' => 10,
			'held' => 2,
			'review' => 0,
			'avg_readiness' => 100,
			'avg_integrity' => 100,
		),
	)
);
$bad_by = array();
foreach ( $bad['rows'] as $row ) {
	$bad_by[ $row['name'] ] = $row;
}
expect( 'yellow' === $bad_by['Luxe Affiliate Product Scout']['level'], 'catalog counts that do not add up stay yellow' );

$older = Luxe_Conductor_Signals::board(
	array(
		array( 'name' => 'Luxe Keyword Intelligence Autopilot', 'version' => '1.0.0', 'active' => true ),
		array( 'name' => 'WooCommerce', 'version' => '11.2.1', 'active' => true ),
		array( 'name' => 'Rank Math SEO', 'version' => '1.0.280', 'active' => true ),
		array( 'name' => 'LiteSpeed Cache', 'version' => '7.9.1', 'active' => true ),
		array( 'name' => 'WZone - WooCommerce Amazon Affiliates', 'version' => '15.3.0', 'active' => true ),
	),
	array(),
	array(),
	$now,
	array()
);
$older_by = array();
foreach ( $older['rows'] as $row ) {
	$older_by[ $row['name'] ] = $row;
}
expect( 'yellow' === $older_by['Luxe Keyword Intelligence Autopilot']['level'], 'an older known plugin is yellow' );

if ( $fail ) {
	echo "\n$fail failed\n";
	exit( 1 );
}
echo "\nAll conductor checks passed\n";
exit( 0 );
