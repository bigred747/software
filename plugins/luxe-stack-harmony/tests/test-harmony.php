<?php
/**
 * CLI checks for Luxe Stack Harmony 1.0.0.
 * php tests/test-harmony.php
 */
define( 'ABSPATH', '/tmp/' );
define( 'LUXE_STACK_HARMONY_DIR', dirname( __DIR__ ) . '/' );
define( 'LUXE_STACK_HARMONY_VERSION', '1.0.0' );
define( 'LUXE_STACK_HARMONY_BASENAME', 'luxe-stack-harmony/luxe-stack-harmony.php' );

$GLOBALS['lsh_opt'] = array();
$GLOBALS['lsh_deactivated'] = array();

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $s ) {
		return rtrim( (string) $s, '/' ) . '/';
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return isset( $GLOBALS['lsh_opt'][ $key ] ) ? $GLOBALS['lsh_opt'][ $key ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = false ) {
		unset( $autoload );
		$GLOBALS['lsh_opt'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'deactivate_plugins' ) ) {
	function deactivate_plugins( $plugins, $silent = false ) {
		unset( $silent );
		foreach ( (array) $plugins as $p ) {
			$GLOBALS['lsh_deactivated'][] = $p;
		}
	}
}

require LUXE_STACK_HARMONY_DIR . 'includes/class-plugin.php';
require LUXE_STACK_HARMONY_DIR . 'includes/class-conductor.php';

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

$header = (string) file_get_contents( LUXE_STACK_HARMONY_DIR . 'luxe-stack-harmony.php' );
expect( false !== strpos( $header, 'Plugin Name: Luxe Stack Harmony' ), 'plugin header name' );
expect( false !== strpos( $header, 'Version: 1.0.0' ), 'plugin header is 1.0.0' );
expect( false !== strpos( $header, 'Never publishes' ), 'header restates no-publish' );
expect( false === strpos( $header, 'wp_insert_post' ), 'bootstrap does not insert posts' );

expect( 'KEEP' === Luxe_Stack_Harmony_Conductor::classify( 'luxe-blog-master-command-center/luxe-blog-master-command-center.php', 'Luxe Blog Master Command Center' ), 'Command Center is KEEP' );
expect( 'KEEP' === Luxe_Stack_Harmony_Conductor::classify( 'woocommerce/woocommerce.php', 'WooCommerce' ), 'WooCommerce core is KEEP' );
expect( 'KEEP' === Luxe_Stack_Harmony_Conductor::classify( 'seo-by-rank-math/rank-math.php', 'Rank Math SEO' ), 'Rank Math is KEEP' );
expect( 'KEEP' === Luxe_Stack_Harmony_Conductor::classify( 'luxe-public-finish/luxe-public-finish.php', 'Luxe Public Finish' ), 'Finish is KEEP' );
expect( 'KEEP' === Luxe_Stack_Harmony_Conductor::classify( 'seo-by-rank-math-pro/rank-math.php', 'Richard Brummer SEO Mission Control' ), 'Mission Control name is KEEP' );
expect( 'LAYERED' === Luxe_Stack_Harmony_Conductor::classify( 'amazon-affiliate-autopilot-for-woocommerce/plugin.php', 'Amazon Affiliate Autopilot for WooCommerce' ), 'Autopilot is LAYERED not KEEP' );
expect( 'LAYERED' === Luxe_Stack_Harmony_Conductor::classify( 'luxe-wow-homepage-rotator/plugin.php', 'Luxe WOW Homepage Rotator' ), 'WOW rotator is LAYERED' );
expect( 'PARK' === Luxe_Stack_Harmony_Conductor::classify( 'hostinger-ai-assistant/hostinger-ai-assistant.php', 'Hostinger AI' ), 'Hostinger AI is PARK' );
expect( 'PARK' === Luxe_Stack_Harmony_Conductor::classify( 'luxe-bad-content-eraser/plugin.php', 'Luxe Bad Content Eraser - Safe One-Time Cleanup' ), 'Eraser is PARK' );
expect( 'PARK' === Luxe_Stack_Harmony_Conductor::classify( 'luxe-master-plugin-orchestrator/plugin.php', 'Luxe Master Plugin Orchestrator' ), 'Orchestrator is PARK' );
expect( 'PARK' === Luxe_Stack_Harmony_Conductor::classify( 'luxe-blog-master-green/luxe-blog-master-green.php', 'Luxe Blog Master Green Board' ), 'Green Board is PARK' );
expect( 'PARK' === Luxe_Stack_Harmony_Conductor::classify( 'luxe-blog-master/luxe-blog-master.php', 'Luxe Blog Master Command Center' ), 'old Command Center folder is PARK' );
expect( ! Luxe_Stack_Harmony_Conductor::is_second_repair_engine( 'luxe-blog-master-command-center/luxe-blog-master-command-center.php', 'Luxe Blog Master Command Center' ), 'canonical Command Center is not a second engine' );

$catalog = array(
	'luxe-blog-master-command-center/luxe-blog-master-command-center.php' => 'Luxe Blog Master Command Center',
	'luxe-blog-master-green/luxe-blog-master-green.php' => 'Luxe Blog Master Green Board',
	'luxe-blog-master/luxe-blog-master.php' => 'Luxe Blog Master Command Center',
	'hostinger-ai-assistant/hostinger-ai-assistant.php' => 'Hostinger AI',
	'woocommerce/woocommerce.php' => 'WooCommerce',
	'luxe-public-finish/luxe-public-finish.php' => 'Luxe Public Finish',
	'luxe-master-plugin-orchestrator/plugin.php' => 'Luxe Master Plugin Orchestrator',
	'luxe-stack-harmony/luxe-stack-harmony.php' => 'Luxe Stack Harmony',
);
$active = array_keys( $catalog );
$cancel = Luxe_Stack_Harmony_Conductor::cancel_list( $catalog, $active );
expect( in_array( 'luxe-blog-master-green/luxe-blog-master-green.php', $cancel, true ), 'cancels Green Board' );
expect( in_array( 'luxe-blog-master/luxe-blog-master.php', $cancel, true ), 'cancels old Command Center folder' );
expect( in_array( 'hostinger-ai-assistant/hostinger-ai-assistant.php', $cancel, true ), 'cancels Hostinger AI' );
expect( in_array( 'luxe-master-plugin-orchestrator/plugin.php', $cancel, true ), 'cancels Orchestrator' );
expect( ! in_array( 'woocommerce/woocommerce.php', $cancel, true ), 'never cancels WooCommerce' );
expect( ! in_array( 'luxe-blog-master-command-center/luxe-blog-master-command-center.php', $cancel, true ), 'never cancels canonical Command Center' );
expect( ! in_array( 'luxe-public-finish/luxe-public-finish.php', $cancel, true ), 'never cancels Finish' );
expect( ! in_array( 'luxe-stack-harmony/luxe-stack-harmony.php', $cancel, true ), 'never cancels itself' );

expect( 10 === Luxe_Stack_Harmony_Plugin::score_10( 10, 12, 2, 0 ), 'yellow-only still scores 10' );
expect( 10 === Luxe_Stack_Harmony_Plugin::score_10( 12, 12, 0, 0 ), 'all green scores 10' );
expect( 8 === Luxe_Stack_Harmony_Plugin::score_10( 10, 12, 0, 2 ), 'red drops the 10' );

$php_files = glob( LUXE_STACK_HARMONY_DIR . 'includes/*.php' );
$php_files[] = LUXE_STACK_HARMONY_DIR . 'luxe-stack-harmony.php';
foreach ( $php_files as $file ) {
	$src = (string) file_get_contents( $file );
	expect( false === strpos( $src, 'wp_insert_post' ), basename( $file ) . ' does not insert posts' );
	expect( false === strpos( $src, 'wp_delete_post' ), basename( $file ) . ' does not delete posts' );
	expect( false === strpos( $src, 'wp_update_post' ), basename( $file ) . ' does not update posts' );
	expect( false === strpos( $src, 'wp_enqueue_script' ), basename( $file ) . ' does not enqueue public JS' );
	expect( false === strpos( $src, "unlink(" ), basename( $file ) . ' does not delete files' );
}

if ( $fail ) {
	echo "FAILED $fail\n";
	exit( 1 );
}
echo "ALL OK\n";
