<?php
/**
 * php plugins/luxe-blog-master/tests/test-identity.php
 */
define( 'ABSPATH', '/tmp/' );
$GLOBALS['luxe_opt'] = array();
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = false ) {
		unset( $autoload );
		$GLOBALS['luxe_opt'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return isset( $GLOBALS['luxe_opt'][ $key ] ) ? $GLOBALS['luxe_opt'][ $key ] : $default;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $fn, $priority = 10, $accepted = 1 ) {
		unset( $tag, $fn, $priority, $accepted );
		return true;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $fn, $priority = 10, $accepted = 1 ) {
		unset( $tag, $fn, $priority, $accepted );
		return true;
	}
}

require dirname( __DIR__ ) . '/luxe-blog-master.php';

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

$header = (string) file_get_contents( dirname( __DIR__ ) . '/luxe-blog-master.php' );
expect( false !== strpos( $header, 'Plugin Name: Luxe Blog Master' ), 'plugin name is Luxe Blog Master' );
expect( false !== strpos( $header, 'Version: 2.8.1' ), 'version is 2.8.1' );
expect( false !== strpos( $header, 'class Luxe_Blog_Master' ), 'class Luxe_Blog_Master is in this file' );
expect( false !== strpos( $header, 'Never publishes' ), 'never publishes' );
expect( false === strpos( $header, 'wp_update_post' ), 'identity never publishes posts' );
expect( false === strpos( $header, 'wp_delete_post' ), 'identity never deletes posts' );
expect( class_exists( 'Luxe_Blog_Master' ), 'class exists' );
expect( '2.8.1' === LUXE_BLOG_MASTER_VERSION, 'constant is 2.8.1' );
expect( true === Luxe_Blog_Master::is_complete(), 'complete build' );
expect( true === Luxe_Blog_Master::hard_no_publish(), 'hard no-publish' );
expect( true === Luxe_Blog_Master::approval_only(), 'approval only' );
expect( function_exists( 'luxe_blog_master' ), 'luxe_blog_master() exists' );
$list = luxe_blog_master();
expect( isset( $list[0]['available'] ) && ! empty( $list[0]['hard_no_publish'] ), 'luxe_blog_master() returns a list' );
expect( isset( get_option( 'luxe_blog_master' )[0]['available'] ), 'option luxe_blog_master is a list' );
expect( true === is_blog_master(), 'is_blog_master()' );
expect( false === luxe_blog_master_status()['publishing_enabled'], 'publishing stays off' );
expect( 0 === luxe_blog_master_status()['published'], 'published count is 0' );

if ( $fail ) {
	echo "FAILED $fail\n";
	exit( 1 );
}
echo "ALL PASS\n";
