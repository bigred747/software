<?php
/**
 * Smoke tests for UX Themes registration path allowlist and payload parsing.
 * Run: php plugins/luxe-theme-guard/tests/test-registration-paths.php
 */

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

define( 'ABSPATH', __DIR__ . '/' );
require_once dirname( __DIR__ ) . '/includes/class-signals.php';

$fail = 0;

function ltg_assert( $cond, $msg ) {
	global $fail;
	if ( $cond ) {
		echo "OK  $msg\n";
		return;
	}
	$fail++;
	echo "FAIL $msg\n";
}

$code = 'bg91c1z0-bdcf-457f-a3e5-4e0762896c2d';
$id   = '12345';
$ver  = '3.20.13';

ltg_assert(
	Luxe_Theme_Guard_Signals::uxthemes_url_allowed( 'https://api.uxthemes.com/v1/latest-version' ),
	'allows /v1/latest-version'
);
ltg_assert(
	Luxe_Theme_Guard_Signals::uxthemes_url_allowed( "https://api.uxthemes.com/v1/license/$code" ),
	'allows /v1/license/{code}'
);
ltg_assert(
	Luxe_Theme_Guard_Signals::uxthemes_url_allowed( "https://api.uxthemes.com/v1/license/$code/$id" ),
	'allows /v1/license/{code}/{id}'
);
ltg_assert(
	Luxe_Theme_Guard_Signals::uxthemes_url_allowed( "https://api.uxthemes.com/v1/license/$code/$id/latest-version" ),
	'allows /v1/license/{code}/{id}/latest-version'
);
ltg_assert(
	Luxe_Theme_Guard_Signals::uxthemes_url_allowed( "https://api.uxthemes.com/v1/license/$code/$id/download-url/$ver" ),
	'allows /v1/license/{code}/{id}/download-url/{version}'
);
ltg_assert(
	! Luxe_Theme_Guard_Signals::uxthemes_url_allowed( 'https://evil.example/v1/license/' . $code ),
	'rejects non-uxthemes host'
);
ltg_assert(
	! Luxe_Theme_Guard_Signals::uxthemes_url_allowed( "https://api.uxthemes.com/v1/license/$code/../admin" ),
	'rejects path traversal'
);

$parsed = Luxe_Theme_Guard_Signals::interpret_uxthemes_response(
	200,
	wp_json_encode(
		array(
			'version' => '3.20.13',
			'url'     => 'https://cdn.uxthemes.com/download/flatsome-3.20.13.zip',
		)
	)
);
ltg_assert( '3.20.13' === $parsed['version'], 'reads version from payload' );
ltg_assert( false !== strpos( $parsed['package'], 'flatsome-3.20.13.zip' ), 'reads url as package' );

$signed = Luxe_Theme_Guard_Signals::package_url_from_payload(
	array(
		'url' => 'https://files.uxthemes.com/releases/flatsome/abc123',
	)
);
ltg_assert( '' !== $signed, 'accepts temporary uxthemes release URL without .zip' );

$clean = Luxe_Theme_Guard_Signals::sanitize_purchase_code(
	"Item Purchase Code: BG91C1Z0-BDCF-457F-A3E5-4E0762896C2D\nBuyer: Example"
);
ltg_assert( $code === $clean, 'extracts UUID from certificate paste' );

exit( $fail ? 1 : 0 );
