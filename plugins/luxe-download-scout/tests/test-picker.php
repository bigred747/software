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
require dirname( __DIR__ ) . '/includes/class-placer.php';

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
	'FPVtosky'                         => 'accessory',
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

$best = LDS_Placer::very_best( $downloads );
expect( 9 === count( $best ), 'very best keeps the nine 100-score rows' );
expect( null === find_row( $best, 'Ruko Drone with Camera for Adults' ), '95-score Ruko is not added to Products' );
foreach ( $best as $row ) {
	expect( 'Drones' === LDS_Placer::category_for( $row['title'], $row['brand'] ), 'best drone files under Drones: ' . $row['brand'] );
}
expect( 'Drone' === LDS_Placer::category_for( 'Holy Stone Saturn T60A Drone', 'Holy Stone', array( 'Drone', 'Apple Watches' ) ), 'uses the live Drone category' );
expect( 'Earbuds' === LDS_Placer::category_for( 'Apple AirPods Pro 3 Wireless Earbuds', 'Apple' ), 'AirPods file under Earbuds' );
expect( 'Apple Watches' === LDS_Placer::category_for( 'Apple Watch Series 9 GPS', 'Apple' ), 'Apple Watch files under Apple Watches' );
expect( 'Samsung Watches' === LDS_Placer::category_for( 'Samsung Galaxy Watch Ultra', 'Samsung', array( 'Apple Watches', 'Watches' ) ), 'Samsung watch does not use Apple Watches' );
expect( 'Fishing Drones' === LDS_Placer::category_for( 'Holy Stone Fishing Drone with Bait Release', 'Holy Stone' ), 'fishing drone has its own category' );
expect( 'MacBooks' === LDS_Placer::category_for( 'Apple MacBook Air 13', 'Apple' ), 'MacBook files under MacBooks' );

$watches = <<<'PASTE'
AppleWatch Ultra 4 GPS + Cellular 49mm Smartwatch
$79900
Add to import list
AppleWatch Ultra 4 GPS + Cellular 49mm Smartwatch
$89900
Add to import list
AppleWatch Series 12 GPS 46mm Smartwatch
$44900
Add to import list
AppleWatch Ultra 3 [GPS + Cellular 49mm] Running & Multisport Smartwatch
$69997
Add to import list
GooglePixel Watch 5 (45mm) - Stephen Curry Special Edition - LTE
$57999
Add to import list
GooglePixel Watch 5 (45mm) - Android Smartwatch - Olive - Wi-Fi
$42999
Add to import list
AppleWatch Series 12 GPS 42mm Smartwatch
$38999
Add to import list
AppleWatch Series 12 GPS 42mm Smartwatch
$39900
Add to import list
Amazon RenewedApple Watch Ultra 2 [GPS + Cellular, 49mm] Titanium Case With White Ocean Band (Renewed)
$41900
Add to import list
AppleWatch Series 10 [GPS + Cellular 46mm case] Smartwatch with Gold Titanium Case
$79900
Add to import list
AppleWatch SE 3 [GPS 40mm] Smartwatch with Starlight Aluminum Case
$23900
Already Imported
AppleWatch SE 3 GPS 44mm Smartwatch
$27900
Add to import list
AppleWatch Series 11 [GPS 42mm] Smartwatch with Rose Gold Aluminum Case
$39900
Already Imported
for Apple Watch Bands for Women, Silver Watch for Men
$1799
Add to import list
Ritche Christmas Gift Leather Apple Watch Band For Men Women Compatible with Apple Watch Series 8
$2709
Add to import list
Seven Band Stainless Steel for Apple Watch Bands for Women
$1999
Add to import list
PASTE;
$watch_result = LDS_Picker::evaluate( $watches, array() );
$watch_best   = LDS_Placer::very_best( $watch_result['downloads'] );
echo "WATCH DOWNLOADS " . count( $watch_result['downloads'] ) . " BEST " . count( $watch_best ) . "\n";
foreach ( $watch_best as $row ) {
	echo $row['integrity'] . ' ' . $row['brand'] . ' ' . $row['model'] . ' | ' . $row['title'] . "\n";
}
expect( 8 === count( $watch_best ), 'eight real watches are the very best' );
expect( null !== find_row( $watch_best, 'Apple Watch Ultra 4' ), 'glued AppleWatch Ultra 4 is Apple' );
expect( null !== find_row( $watch_best, 'Apple Watch Series 12 GPS 46mm' ), 'Series 12 46mm is kept' );
expect( null !== find_row( $watch_best, 'Apple Watch Series 12 GPS 42mm' ), 'Series 12 42mm is a separate product' );
expect( null !== find_row( $watch_best, 'Google Pixel Watch 5 (45mm) - Stephen Curry' ), 'glued GooglePixel LTE is Google' );
expect( null !== find_row( $watch_best, 'Google Pixel Watch 5 (45mm) - Android Smartwatch - Olive' ), 'Pixel Watch Wi-Fi is separate' );
expect( null !== find_row( $watch_best, 'Apple Watch SE 3 GPS 44mm' ), 'SE 3 44mm is new' );
expect( null === find_row( $watch_best, 'SE 3 [GPS 40mm]' ), 'imported SE 3 40mm is not added again' );
expect( null === find_row( $watch_result['downloads'], 'for Apple Watch Bands' ), 'a band is not an Apple watch download' );
expect( null === find_row( $watch_result['downloads'], 'Renewed' ), 'renewed watches are not downloads' );
$ultra = find_row( $watch_best, 'Apple Watch Ultra 4' );
expect( null !== $ultra && 'Apple Watches' === LDS_Placer::category_for( $ultra['title'], $ultra['brand'] ), 'Ultra 4 files under Apple Watches' );
$pixel = find_row( $watch_best, 'Google Pixel Watch 5 (45mm) - Stephen Curry' );
expect( null !== $pixel && 'Watches' === LDS_Placer::category_for( $pixel['title'], $pixel['brand'] ), 'Pixel Watch files under Watches' );
$band = find_row( $watch_result['skips'], 'for Apple Watch Bands' );
expect( null !== $band && 'accessory' === $band['reason'], 'watch band is an accessory' );

$tvs = <<<'PASTE'
Sony 65 Inch BRAVIA 3 II LED 4K HDR Smart Google TV with Gemini K-65XR30M2
$89800
Add to import list
Sony85 Inch BRAVIA 3 II LED 4K HDR Smart Google TV with Gemini K-85XR30M2
$159800
Add to import list
Sony55 Inch BRAVIA 3 II LED 4K HDR Smart Google TV with Gemini K-55XR30M2
$79800
Already Imported
SonyBRAVIA 7 75 inch 4K Smart QLED Mini-LED TV 2024 K75XR70 Renewed Bundle
$144999
Add to import list
Amazon RenewedSony K85XR50 85 inch Class Bravia 5 Series 4K Mini LED UHD Smart Google TV
$151303
Add to import list
Sony75 Inch BRAVIA 3 II LED 4K HDR Smart Google TV with Gemini K-75XR30M2
$119800
Add to import list
Samsung50-Inch Class U8000H Series Crystal UHD, 4K, Smart TV, 2026 Model
$24800
Add to import list
Sony43 Inch BRAVIA 3 II LED 4K HDR Smart Google TV with Gemini K-43XR30M2
$59800
Add to import list
Samsung 75-Inch Class M70H Series, Mini LED, 4K, Smart TV, 2026 Model
$54799
Add to import list
TCL 85 Inch Class QM7L Series | SQD-Mini-LED QLED Smart TV | 85QM7L, 2026 Model
$149799
Add to import list
32-Inch 4K Smart Portable TV, Android OS with Qualcomm CPU
$69999
Add to import list
Mounting Dream UL Listed Full Motion TV Wall Mount for 32/43/50/55 Inch TVs
$2399
Add to import list
PASTE;
$tv_result = LDS_Picker::evaluate( $tvs, array() );
$tv_best   = LDS_Placer::very_best( $tv_result['downloads'] );
echo "TV BEST " . count( $tv_best ) . "\n";
foreach ( $tv_best as $row ) {
	echo $row['integrity'] . ' ' . $row['brand'] . ' ' . $row['model'] . ' | ' . $row['title'] . "\n";
}
expect( 7 === count( $tv_best ), 'seven new TVs are the very best in the sample' );
expect( null !== find_row( $tv_best, 'Sony 65 Inch BRAVIA 3 II' ), 'spaced Sony BRAVIA 3 II is kept' );
expect( null !== find_row( $tv_best, 'Sony 85 Inch BRAVIA 3 II' ), 'glued Sony85 is Sony' );
expect( null !== find_row( $tv_best, 'Samsung 50-Inch Class U8000H' ), 'glued Samsung50 is Samsung' );
expect( null !== find_row( $tv_best, 'TCL 85 Inch Class QM7L' ), 'TCL QM7L is kept' );
expect( null === find_row( $tv_best, 'K-55XR30M2' ), 'imported 55 inch BRAVIA is not added' );
expect( null === find_row( $tv_result['downloads'], 'Renewed' ), 'renewed TVs are not downloads' );
expect( null === find_row( $tv_result['downloads'], 'Qualcomm' ), 'a no-name portable TV is not a Qualcomm product' );
expect( null === find_row( $tv_result['downloads'], 'Wall Mount' ), 'a wall mount is not a TV' );
$sony = find_row( $tv_best, 'Sony 65 Inch BRAVIA 3 II' );
expect( null !== $sony && 'TVs' === LDS_Placer::category_for( $sony['title'], $sony['brand'] ), 'Google TV still files under TVs' );

if ( $fail ) {
	echo "FAILED $fail\n";
	exit( 1 );
}
echo "ALL OK\n";
exit( 0 );
