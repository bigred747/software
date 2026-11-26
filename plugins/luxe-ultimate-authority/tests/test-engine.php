<?php
/**
 * php tests/test-engine.php
 */
define( 'ABSPATH', '/tmp/' );

$dir = dirname( __DIR__ ) . '/includes/';
require $dir . 'class-text.php';
require $dir . 'class-junk.php';
require $dir . 'class-approve.php';
require $dir . 'class-render.php';
require $dir . 'class-post.php';
require $dir . 'class-overlap.php';
require $dir . 'class-speed.php';
require $dir . 'class-board.php';
require $dir . 'class-stats.php';

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

$ids = LUAE_Board::ids();
expect( 34 === count( $ids ), '34 processes' );
expect( count( $ids ) === count( array_unique( $ids ) ), 'process ids are unique' );
$armed = LUAE_Board::armed();
expect( 34 === count( $armed ), 'armed board has every process' );
expect( 'green' === $armed[0]['level'] && false !== strpos( $armed[0]['detail'], 'Armed' ), 'armed signal is labeled armed' );

$home = 'https://luxetrendsetters.com/';
$amazon = 'https://www.amazon.com/dp/B0GR6BVYS5?tag=luxetrendse0f-20';
$template = '<p>What This Blog Is About. Review Apple 2026 MacBook Neo, including Item Weight 2.71 pounds. '
	. 'Apple 2026 MacBook Neo is worth considering if the live product page confirms the exact model. '
	. 'Use this guide as a final checkpoint. Verify live price before checkout.</p>'
	. '<a href="' . $amazon . '">View on Amazon</a>';

$thin = LUAE_Post::assess(
	array(
		'title'        => 'Apple 2026 MacBook Neo 13-inch Laptop with A18 Pro chip: Built for AI Review (2026): Buyer Guide',
		'content'     => $template,
		'home'         => $home,
		'expected_tag' => 'luxetrendse0f-20',
		'modules'      => LUAE_Post::empty_modules(),
		'siblings'     => array(
			array(
				'title' => 'Apple 2026 MacBook Neo 13-inch Laptop with A18 Buyer Guide',
				'url'   => $home . 'apple-macbook-neo-other/',
			),
		),
	)
);
expect( $thin['thin'], 'template guide is thin' );
expect( $thin['words'] < 1000, 'template guide is under 1000 words' );
expect( 'HIGH' === $thin['opportunity'], 'missing sections are a high opportunity' );
expect( null === $thin['views'], 'views are not invented' );
expect( array( $amazon ) === $thin['amazon_urls'], 'amazon url is unchanged' );
expect( 'ok' === $thin['tag_state'], 'matching tag passes' );
expect( $thin['cannibals'], 'same product title is cannibalization' );
$guide_signals = LUAE_Post::signals( $thin );
expect( count( $guide_signals ) >= 10, 'each guide has a signal per check' );
expect( 'yellow' === $guide_signals[0]['level'], 'a weak guide score is yellow' );
expect( false === strpos( implode( ' ', $thin['prompts'] ), 'I tested' ), 'prompts do not invent a test' );
expect( false === strpos( strtolower( implode( ' ', $thin['prompts'] ) ), 'if i were buying' ), 'prompts do not write the verdict' );

$modules = LUAE_Post::empty_modules();
expect( array() === $modules['scores']['design'] || '' === $modules['scores']['design'], 'scorecard starts blank' );
expect( '' === LUAE_Render::section( $modules, $home ), 'blank modules render nothing' );
$modules['verdict'] = '[[Rick writes this]] and then some extra words to look long enough for a paragraph.';
$modules['approved']['verdict'] = 1;
expect( '' === LUAE_Render::section( $modules, $home ), 'placeholder verdict stays off the page' );
expect( LUAE_Approve::verdict( 'Battery is the reason I would buy this, and the single port is the limit.' ) === array(), 'a real verdict can be approved' );

$modules = LUAE_Post::empty_modules();
$modules['verdict'] = 'Battery is the reason I would buy this, and the single port is the limit.';
$modules['pros'] = "All-day battery\nSharp screen";
$modules['cons'] = 'Only one external port';
$modules['buy'] = 'People who want a small Apple laptop';
$modules['skip'] = 'People who edit 4K video all day';
$modules['scores'] = array(
	'design' => '9.5', 'performance' => '9.0', 'battery' => '9.7', 'value' => '8.1', 'overall' => '9.1',
);
$modules['compare'] = array(
	array( 'title' => 'MacBook Air M5', 'url' => $home . 'macbook-air-m5/' ),
	array( 'title' => 'Amazon', 'url' => $amazon ),
);
$modules['faq'] = "Q: Does it stay cool?\nA: It stays quiet in documents, and it warms up while exporting video.";
$modules['approved'] = array(
	'verdict' => 1, 'pros_cons' => 1, 'buyers' => 1, 'scorecard' => 1, 'compare' => 1, 'faq' => 1,
);
expect( LUAE_Approve::compare( $modules['compare'], $home ) !== array(), 'amazon comparison is rejected' );
$modules['compare'] = array(
	array( 'title' => 'MacBook Air M5', 'url' => $home . 'macbook-air-m5/' ),
);
$section = LUAE_Render::section( $modules, $home );
expect( false !== strpos( $section, "Rick's Verdict" ), 'approved verdict is rendered' );
expect( false !== strpos( $section, 'Who Should Buy This?' ), 'buyer section is rendered' );
expect( false !== strpos( $section, 'Compare This Product To' ), 'comparison is rendered' );
expect( false === strpos( $section, 'amazon.com' ), 'rendered block adds no amazon url' );
expect( array( $amazon ) === LUAE_Text::amazon_urls( $template . $section ), 'appending a block does not change amazon urls' );

expect( 'noindex' === LUAE_Junk::action( '/item-weight/2-71-pounds/' ), 'weight archive is noindex' );
expect( 'noindex' === LUAE_Junk::action( '/product-tag/apple/page/5/' ), 'tag pagination is noindex' );
expect( '' === LUAE_Junk::action( '/product-category/electronics/computers-laptops/' ), 'shopping category is not junk' );
expect( '' === LUAE_Junk::action( '/' ), 'homepage is not junk' );
expect( LUAE_Junk::matches_approved( '/product-tag/apple/page/7/', array( '/product-tag/apple/' ) ), 'approved tag covers pagination' );
expect( ! LUAE_Junk::matches_approved( '/', array( '/' ) ), 'homepage cannot be noindexed by an empty approval' );
expect( LUAE_Junk::watched_taxonomy( 'product_tag', 'product-tag' ), 'product tags are watched' );
expect( LUAE_Junk::watched_taxonomy( 'pa_item-weight', 'item-weight' ), 'weight attributes are watched' );
expect( ! LUAE_Junk::watched_taxonomy( 'category', 'category' ), 'blog categories are not scanned' );
expect( '/item-weight/2-71-pounds/' === LUAE_Junk::term_path( 'item-weight', '2-71-pounds' ), 'term path is built without a lookup' );
$merged = LUAE_Junk::merge_noindex( array( '/' ), array( '/item-weight/2-71-pounds/', 'https://evil.example/steal' ) );
expect( array( '/item-weight/2-71-pounds/' ) === $merged, 'noindex merge drops the homepage and foreign urls' );
$many = array();
for ( $i = 0; $i < 250; $i++ ) {
	$many[] = '/item-weight/sample-' . $i . '/';
}
expect( 200 === count( LUAE_Junk::merge_noindex( array(), $many ) ), 'noindex list stops at 200 paths' );
$html = str_repeat( '<p>word </p>', 4000 ) . '<img src="/a.jpg" alt=""><script>alert(1)</script>';
$snippet = LUAE_Speed::snippet( $html );
expect( strlen( $snippet ) < 8001, 'homepage sample stays under 8kb' );
expect( false === strpos( $snippet, '<script>' ), 'homepage sample keeps image tags only' );
$verdict_html = LUAE_Render::verdict_html( '<script>alert(1)</script> Battery is fine and the port is the limit here.' );
expect( false === strpos( $verdict_html, '<script>' ), 'verdict html cannot inject a script' );
$started = microtime( true );
for ( $i = 0; $i < 200; $i++ ) {
	LUAE_Post::assess(
		array(
			'title'        => 'Speed sample guide',
			'content'      => $html,
			'home'         => $home,
			'expected_tag' => 'luxetrendse0f-20',
			'modules'      => LUAE_Post::empty_modules(),
		)
	);
}
$elapsed = microtime( true ) - $started;
expect( $elapsed < 2, '200 guide assessments finish in under 2 seconds (' . round( $elapsed, 3 ) . 's)' );

expect( 'keep' === LUAE_Overlap::classify( 'WooCommerce' ), 'woocommerce stays' );
expect( 'keep' === LUAE_Overlap::classify( 'Rank Math SEO' ), 'rank math stays' );
expect( 'keep' === LUAE_Overlap::classify( 'LiteSpeed Cache' ), 'litespeed stays' );
expect( 'keep' === LUAE_Overlap::classify( 'Wordfence Security' ), 'wordfence stays' );
expect( 'keep' === LUAE_Overlap::classify( 'WZone - WooCommerce Amazon Affiliates' ), 'wzone stays' );
expect( 'keep' === LUAE_Overlap::classify( 'Link Whisper Premium' ), 'link whisper stays' );
expect( 'keep' === LUAE_Overlap::classify( 'Luxe Amazon View on Amazon Bridge' ), 'amazon bridge stays' );
expect( 'keep' === LUAE_Overlap::classify( 'Luxe Affiliate Product Scout' ), 'product scout stays' );
expect( 'retire' === LUAE_Overlap::classify( 'Amazon Affiliate Autopilot for WooCommerce' ), 'amazon autopilot retires' );
expect( 'retire' === LUAE_Overlap::classify( 'Luxe Reader-Love Unified 95–100 Prep & Score' ), 'reader-love retires' );
expect( 'retire' === LUAE_Overlap::classify( 'Luxe Optimizer' ), 'optimizer retires' );
expect( 'retire' === LUAE_Overlap::classify( 'Action Scheduler Healer PRO (Autonomous & Self-Learning)' ), 'action scheduler healer retires' );
expect( 'leave' === LUAE_Overlap::classify( 'Luxe Simple Homepage Mirror Master' ), 'homepage plugin is not auto-retired' );

$split = LUAE_Overlap::split( array( 'WooCommerce', 'Luxe Optimizer', 'Contact Form 7' ) );
expect( array( 'Luxe Optimizer' ) === $split['retire'], 'split retires only the overlap' );
expect( array( 'WooCommerce', 'Contact Form 7' ) === $split['keep'], 'split keeps woocommerce and contact form 7' );

$cache = LUAE_Speed::cache( 'no-store, no-cache', false );
expect( 'yellow' === $cache['level'], 'no-store cache is yellow' );

$stats = LUAE_Stats::blank();
$stats = LUAE_Stats::add( $stats, $thin );
$stats['weight_paths'] = 2;
$stats['tag_paths'] = 1;
$board = LUAE_Board::build(
	$stats,
	array(
		'rank_math'        => true,
		'litespeed'        => true,
		'overlapping'      => array( 'Luxe Optimizer' ),
		'cache_control'    => 'no-store',
		'litespeed_hit'    => false,
		'html_sample'      => '<img src="/a.jpg" alt="">',
		'approved_noindex' => 0,
	)
);
expect( 34 === count( $board ), 'live board still has every process' );
$by_id = array();
foreach ( $board as $row ) {
	$by_id[ $row['id'] ] = $row;
}
expect( 'green' === $by_id['safety_publish']['level'], 'publish lock stays green' );
expect( 'green' === $by_id['safety_amazon']['level'], 'amazon lock stays green' );
expect( 'green' === $by_id['web_vitals_lock']['level'], 'second optimizer stays off' );
expect( 'green' === $by_id['schema_owner']['level'], 'rank math keeps schema' );
expect( 'yellow' === $by_id['thin_detector']['level'], 'thin guides are yellow' );
expect( 'yellow' === $by_id['verdict']['level'], 'missing verdict is yellow' );
expect( 'yellow' === $by_id['weight_pages']['level'], 'weight pages are yellow until approved' );
expect( 'yellow' === $by_id['overlap_report']['level'], 'active overlap is yellow' );
expect( 'yellow' === $by_id['cache_headers']['level'], 'cache header signal is yellow' );
expect( 'green' === $by_id['affiliate_tag']['level'], 'matching tag is green on this sample' );

$waiting = LUAE_Board::build( LUAE_Stats::blank(), array( 'rank_math' => true, 'litespeed' => true, 'overlapping' => array() ) );
$wait_ids = array();
foreach ( $waiting as $row ) {
	$wait_ids[ $row['id'] ] = $row['level'];
}
expect( 'yellow' === $wait_ids['article_score'], 'unscanned article score is not a fake green result' );
expect( 'green' === $wait_ids['safety_publish'], 'safety is green before the scan' );

$broken = LUAE_Post::assess(
	array(
		'title'        => 'SHOKZ OpenFit Pro Review',
		'content'      => '<a href="https://www.amazon.com/s?k=earbuds&tag=wrong-tag-20">View on Amazon</a>',
		'home'         => $home,
		'expected_tag' => 'luxetrendse0f-20',
		'modules'      => LUAE_Post::empty_modules(),
	)
);
expect( 'broken' === $broken['link_state'], 'search url without an ASIN is broken' );
expect( 'mismatch' === $broken['tag_state'], 'wrong tag is flagged' );
expect( 'https://www.amazon.com/s?k=earbuds&tag=wrong-tag-20' === $broken['amazon_urls'][0], 'wrong tag is not rewritten' );

if ( $fail ) {
	echo "\n$fail failed\n";
	exit( 1 );
}
echo "\nAll checks passed\n";
exit( 0 );
