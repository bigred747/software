<?php
/**
 * CLI checks for copyright-safe Instagram scanning.
 * php tests/test-instagram.php
 */
define( 'ABSPATH', '/tmp/' );
define( 'LAPS_DIR', dirname( __DIR__ ) . '/' );

function wp_strip_all_tags( $text ) {
	return strip_tags( $text );
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

require LAPS_DIR . 'includes/class-catalog.php';
require LAPS_DIR . 'includes/class-instagram.php';

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

$reel = LAPS_Instagram::parse( 'https://www.instagram.com/reel/AbC123_xy/' );
expect( ! empty( $reel['ok'] ), 'reel permalink is accepted' );
expect( 'video' === $reel['type'], 'reel is a video permalink' );
expect( 'AbC123_xy' === $reel['shortcode'], 'reel shortcode parsed' );
expect( ! empty( $reel['video'] ), 'reel marked as video' );

$post = LAPS_Instagram::parse( 'instagram.com/p/Zz99aa/' );
expect( ! empty( $post['ok'] ), 'post permalink is accepted' );
expect( 'post' === $post['type'], 'p/ is a post' );

$profile = LAPS_Instagram::parse( 'https://instagram.com/luxetrendsetters/' );
expect( 'profile' === $profile['type'], 'profile URL is recorded as profile' );
expect( 'luxetrendsetters' === $profile['handle'], 'profile handle parsed' );

$cdn = LAPS_Instagram::parse( 'https://scontent.cdninstagram.com/v/t51.123/video.mp4' );
expect( empty( $cdn['ok'] ), 'Instagram CDN video URL is refused' );

$api = LAPS_Instagram::parse( 'https://www.instagram.com/api/v1/feed/' );
expect( empty( $api['ok'] ), 'Instagram API path is refused' );

$stories = LAPS_Instagram::parse( 'https://www.instagram.com/stories/someone/123/' );
expect( empty( $stories['ok'] ), 'stories URL is refused' );

$lock = LAPS_Instagram::copyright_lock( 'https://www.instagram.com/reel/AbC123_xy/' );
expect( false === $lock['download_video'], 'copyright lock blocks download' );
expect( false === $lock['store_video'], 'copyright lock blocks store' );
expect( false === $lock['copy_frames'], 'copyright lock blocks frames' );
expect( false === $lock['scrape_caption'], 'copyright lock blocks caption scrape' );
expect( false === $lock['republish'], 'copyright lock blocks republish' );

$urls = LAPS_Instagram::extract_urls( "see https://www.instagram.com/reel/AbC123_xy/ and https://example.com/nope" );
expect( 1 === count( $urls ), 'extracts only Instagram permalinks' );

$brands = LAPS_Instagram::brands_in_text( 'Loved this DJI Mini and Bose QuietComfort Ultra clip' );
expect( in_array( 'DJI', $brands, true ), 'DJI matched from operator notes' );
expect( in_array( 'Bose', $brands, true ), 'Bose matched from operator notes' );

$item = LAPS_Instagram::scan_item( 'https://www.instagram.com/reel/AbC123_xy/', 'DJI Mini 4 Pro' );
expect( false === $item['copied'], 'scan does not copy video' );
expect( 'READY' === $item['status'], 'video + supported brand is READY' );
expect( in_array( 'DJI', $item['brands'], true ), 'scan keeps DJI from notes' );

$profile_item = LAPS_Instagram::scan_item( 'https://www.instagram.com/iknowig/', '' );
expect( 'REVIEW' === $profile_item['status'], 'profile without a reel stays REVIEW' );

$studio = LAPS_Instagram::studio( 'DJI Mini 4 Pro', 'DJI' );
expect( 5 === count( $studio ), 'five original studio scripts' );
$blob = strtolower( implode( ' ', wp_list_pluck_safe( $studio ) ) );
expect( false === strpos( $blob, '48,000 followers' ), 'studio is not copied Instagram growth copy' );
expect( false === strpos( $blob, '12.5 million' ), 'studio is not copied view-count copy' );
expect( false === strpos( $blob, '37 words' ), 'studio refuses the thin-page ranking trick' );
expect( false !== strpos( $blob, 'amazon associate' ), 'studio includes affiliate disclosure' );

$signals = LAPS_Instagram::signals( array( $item ) );
expect( (int) $signals['green'] === (int) $signals['total'], 'all Instagram signals green' );
expect( $signals['total'] >= 10, 'at least 10 green signals' );

function wp_list_pluck_safe( $studio ) {
	$out = array();
	foreach ( $studio as $row ) {
		$out[] = isset( $row['script'] ) ? $row['script'] : '';
	}
	return $out;
}

if ( $fail ) {
	echo "FAILED $fail\n";
	exit( 1 );
}
echo "ALL PASSED\n";
exit( 0 );
