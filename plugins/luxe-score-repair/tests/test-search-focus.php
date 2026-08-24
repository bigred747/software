<?php
/**
 * php plugins/luxe-score-repair/tests/test-search-focus.php
 */
define( 'ABSPATH', '/tmp/' );
require dirname( __DIR__ ) . '/includes/class-plugin.php';
require dirname( __DIR__ ) . '/includes/class-search-focus.php';

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

expect( true === Luxe_Score_Repair_Search_Focus::is_thin_taxonomy_path( '/product-tag/apple' ), 'apple tag is thin' );
expect( true === Luxe_Score_Repair_Search_Focus::is_thin_taxonomy_path( '/product-tag/dji/' ), 'dji tag is thin' );
expect( true === Luxe_Score_Repair_Search_Focus::is_thin_taxonomy_path( '/date-first-available/2024' ), 'date attribute is thin' );
expect( true === Luxe_Score_Repair_Search_Focus::is_thin_taxonomy_path( '/pa-color/black' ), 'pa_ archive path is thin' );
expect( false === Luxe_Score_Repair_Search_Focus::is_thin_taxonomy_path( '/product-category/drones' ), 'product category stays indexable' );
expect( false === Luxe_Score_Repair_Search_Focus::is_thin_taxonomy_path( '/product/apple-watch' ), 'product permalink stays indexable' );
expect( false === Luxe_Score_Repair_Search_Focus::is_thin_taxonomy_path( '/' ), 'homepage is not thin' );
expect( false === Luxe_Score_Repair_Search_Focus::is_thin_taxonomy_path( '/blogs/' ), 'guides hub is not thin' );

expect( true === Luxe_Score_Repair_Search_Focus::is_thin_taxonomy_name( 'product_tag' ), 'product_tag name is thin' );
expect( true === Luxe_Score_Repair_Search_Focus::is_thin_taxonomy_name( 'pa_date-first-available' ), 'pa_ taxonomy name is thin' );
expect( false === Luxe_Score_Repair_Search_Focus::is_thin_taxonomy_name( 'product_cat' ), 'product_cat name stays indexable' );
expect( false === Luxe_Score_Repair_Search_Focus::is_thin_taxonomy_name( 'category' ), 'post category stays indexable' );

expect( true === Luxe_Score_Repair_Search_Focus::is_junk_sitemap_path( '/wish-list/' ), 'wishlist out of sitemap' );
expect( true === Luxe_Score_Repair_Search_Focus::is_junk_sitemap_path( '/product-tag/apple/' ), 'tag out of sitemap' );
expect( true === Luxe_Score_Repair_Search_Focus::is_junk_sitemap_path( '/test-blog-page/' ), 'test blog out of sitemap' );
expect( false === Luxe_Score_Repair_Search_Focus::is_junk_sitemap_path( '/shop-shopping-guide-for-top-products/' ), 'shop page stays in sitemap' );
expect( false === Luxe_Score_Repair_Search_Focus::is_junk_sitemap_path( '/privacy-policy/' ), 'privacy stays in sitemap' );
expect( false === Luxe_Score_Repair_Search_Focus::is_junk_sitemap_path( '/apple-watch-series-9-gps-cellular-41mm-review-2026/' ), 'published guide stays in sitemap' );

require dirname( __DIR__ ) . '/includes/class-admin.php';
$stale = array(
	'signals' => array(
		array( 'id' => 'home_title' ),
		array( 'id' => 'noindex_cart' ),
	),
);
$fresh = array(
	'signals' => array(
		array( 'id' => 'noindex_product_tag' ),
		array( 'id' => 'search_sitemap' ),
	),
);
expect( false === Luxe_Score_Repair_Admin::learn_has_search_focus( $stale ), 'old 22-process board is stale' );
expect( true === Luxe_Score_Repair_Admin::learn_has_search_focus( $fresh ), '1.2 board is current' );

require dirname( __DIR__ ) . '/includes/class-robots.php';
$crawlable = "User-agent: *\nAllow: /\nSitemap: https://luxetrendsetters.com/sitemap_index.xml\n# END Luxe AI Readiness Autopilot 100\n# END Luxe AI Readiness Autopilot 100\n";
$blocked   = "User-agent: *\nDisallow: /\n";
expect( true === Luxe_Score_Repair_Robots::allows_crawling( $crawlable ), 'duplicate AI comments still allow crawling' );
expect( false === Luxe_Score_Repair_Robots::allows_crawling( $blocked ), 'full-site Disallow is not crawlable' );
expect( true === Luxe_Score_Repair_Robots::is_bloated( $crawlable ), 'duplicate AI END comments stay bloated' );

require dirname( __DIR__ ) . '/includes/class-redirects.php';
$map = Luxe_Score_Repair_Redirects::base_map();
expect( '/sitemap_index.xml' === $map['/wp-sitemap.xml'], 'wp-sitemap.xml hops to Rank Math index' );

if ( $fail ) {
	echo "FAILED $fail\n";
	exit( 1 );
}
echo "ALL PASS\n";
