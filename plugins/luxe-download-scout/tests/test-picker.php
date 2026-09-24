<?php
/**
 * Download decisions for a pasted WZone list.
 * php tests/test-picker.php
 */
define( 'ABSPATH', '/tmp/' );
define( 'LAPS_DIR', dirname( __DIR__, 2 ) . '/luxe-affiliate-product-scout/' );

function wp_strip_all_tags( $text ) {
	return strip_tags( $text );
}

require LAPS_DIR . 'includes/class-catalog.php';
require dirname( __DIR__ ) . '/includes/class-parser.php';
require dirname( __DIR__ ) . '/includes/class-picker.php';

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

function titles_of( $rows ) {
	$out = array();
	foreach ( $rows as $row ) {
		$out[] = $row['title'];
	}
	return $out;
}

function find_row( $rows, $needle ) {
	foreach ( $rows as $row ) {
		if ( false !== strpos( $row['title'], $needle ) ) {
			return $row;
		}
	}
	return null;
}

$paste   = file_get_contents( __DIR__ . '/fixtures/wzone-drones.txt' );
$catalog = file( __DIR__ . '/fixtures/catalog-hold.txt', FILE_IGNORE_NEW_LINES );
$result  = LDS_Picker::evaluate( $paste, $catalog );

expect( ! empty( $result['ready'] ), 'catalog classes loaded' );
expect( 59 === $result['parsed'], 'parsed 59 WZone rows, pagination ignored' );

$downloads = $result['downloads'];
$skips     = $result['skips'];
echo "DOWNLOADS " . count( $downloads ) . "\n";
foreach ( $downloads as $row ) {
	echo $row['integrity'] . ' ' . $row['brand'] . ' ' . $row['price_label'] . ' ' . $row['model'] . ' | ' . $row['title'] . "\n";
}

expect( 10 === count( $downloads ), 'ten products are safe to download' );

$want = array(
	'Ruko Drone with Camera for Adults 4K Video & 8K Photo, 96 Mins Flight Time' => array( 'Ruko', 95, 239.97, '' ),
	'Holy Stone Saturn T60A' => array( 'Holy Stone', 100, 499.99, 't60a' ),
	'Autel Robotics EVO Lite 6K Enterprise' => array( 'Autel', 100, 1579.00, 'evo lite|enterprise' ),
	'Bwine F7GB2 Pro' => array( 'Bwine', 100, 339.88, 'f7gb2' ),
	'Potensic ATOM 2' => array( 'Potensic', 100, 439.99, 'atom 2' ),
	'Autel Robotics EVO 2 Pro V3' => array( 'Autel', 100, 2099.00, 'evo 2' ),
	'Autel Robotics EVO II Dual 640T V3' => array( 'Autel', 100, 4799.00, 'evo ii dual 640t' ),
	'Autel Robotics EVO II Dual 640T Enterprise' => array( 'Autel', 100, 5299.00, 'evo ii dual 640t|enterprise' ),
	'HS360S' => array( 'Holy Stone', 100, 179.99, 'hs360s' ),
	'DJI Air 3 Fly More Combo with RC-N2' => array( 'DJI', 100, 1449.00, 'air 3' ),
);
foreach ( $want as $needle => $spec ) {
	$row = find_row( $downloads, $needle );
	expect( null !== $row, "download includes $needle" );
	if ( ! $row ) {
		continue;
	}
	expect( $spec[0] === $row['brand'], "$needle brand {$spec[0]}" );
	expect( $spec[1] === $row['integrity'], "$needle integrity {$spec[1]}" );
	expect( abs( $spec[2] - $row['price'] ) < 0.001, "$needle price {$spec[2]}" );
	expect( $spec[3] === $row['model'], "$needle model {$spec[3]}" );
}

$blocked = array(
	'Bingchat'                         => 'unknown-brand',
	'Ruko U11MINI 4K GPS'              => 'already-in-catalog',
	'DJI Air 3S Drone with RC 2 Fly More Combo, Dual' => 'already-in-catalog',
	'SKYROVER X1 Drone with Camera Combo with Screen' => 'unknown-brand',
	'Ruko F11PRO 2 Drone'              => 'already-in-catalog',
	'DJI Mavic 4 Pro'                  => 'already-in-catalog',
	'DJI Mini 5 Pro Drone Fly More Combo with RC 2' => 'already-in-catalog',
	'DJI Mini 3, 3-Axis'               => 'already-in-catalog',
	'DJI Neo Fly More Combo'           => 'already-in-catalog',
	'DJI Neo 2 Fly More Combo With RC-N3' => 'already-in-catalog',
	'FPVtosky'                         => 'unknown-brand',
	'DJI Mini 4K, Drone'               => 'already-imported',
	'Potensic ATOM SE'                 => 'already-imported',
	'GPS Drone with 4K UHD Camera'     => 'unknown-brand',
);
foreach ( $blocked as $needle => $reason ) {
	$row = find_row( $skips, $needle );
	expect( null !== $row, "skip includes $needle" );
	if ( $row ) {
		expect( $reason === $row['reason'], "$needle reason $reason got {$row['reason']}" );
	}
}

$renewed = LDS_Picker::evaluate(
	"SAMSUNG Galaxy Buds 2 Pro True Wireless Bluetooth Earbuds (Renewed)\n\$12999\nAdd to import list\n",
	array()
);
expect( 'used-refurbished-or-parts' === $renewed['skips'][0]['reason'], 'renewed Samsung stays off the download list' );
expect( 0 === count( $renewed['downloads'] ), 'renewed produces no download' );

$pad = LDS_Picker::evaluate(
	"DJI Mini 3 Landing Pad\n\$1499\nAdd to import list\nDJI Mini 3 Fly More Combo\n\$41900\nAdd to import list\n",
	array()
);
expect( 'accessory' === $pad['skips'][0]['reason'], 'landing pad is an accessory' );
expect( 1 === count( $pad['downloads'] ), 'landing pad does not block the Mini 3 drone' );
expect( 'mini 3' === $pad['downloads'][0]['model'], 'Mini 3 drone still downloads' );

$dup = LDS_Picker::evaluate(
	"Apple AirPods Pro 3 Wireless Earbuds\n\$24900\nAdd to import list\nApple AirPods Pro 3 Wireless Earbuds\n\$24900\nAdd to import list\n",
	array()
);
expect( 1 === count( $dup['downloads'] ), 'exact second AirPods row is not downloaded twice' );
expect( 100 === $dup['downloads'][0]['integrity'], 'AirPods Pro 3 can reach 100' );
expect( 'already-in-catalog' === $dup['skips'][0]['reason'], 'second AirPods row is a duplicate' );

$money = LDS_Parser::money( '$1,82995' );
expect( abs( 1829.95 - $money ) < 0.001, 'WZone bundled cents parse to 1829.95' );

if ( $fail ) {
	echo "FAILED $fail\n";
	exit( 1 );
}
echo "ALL OK\n";
exit( 0 );
