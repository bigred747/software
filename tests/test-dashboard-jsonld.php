<?php
/**
 * JSON-LD quarantine tests. No WordPress required.
 */
define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/plugins/richard-brummer-seo-mission-control-stay/includes/class-schema.php';

$fail = 0;
function assert_true( $cond, $msg ) {
	global $fail;
	if ( $cond ) {
		echo "OK  $msg\n";
		return;
	}
	$fail++;
	echo "FAIL $msg\n";
}

$valid_rank_math = '{"@context":"https://schema.org","@graph":[{"@type":"Organization","name":"Luxetrendsetters.com","url":"https://luxetrendsetters.com"}]}';
$valid_list      = '{"@context":"https://schema.org","@type":"ItemList","name":"Related luxury shopping paths","itemListElement":[{"@type":"ListItem","position":1,"name":"Blogs","url":"https://luxetrendsetters.com/blogs/"}]}';
$truncated_faq   = '{"@context":"https://schema.org","@graph":[{"@context":"https://schema.org","@type":"Organization","name":"LuxeTrendsetters","url":"https://luxetrendsetters.com/"},{"@context":"https://schema.org","@type":"WebSite","name":"LuxeTrendsetters","url":"https://luxetrendsetters.com/"},{"@context":"https://schema.org","@type":"FAQPage","mainEntity":[{"@type":"Question","name":"How are products selected?","acceptedAnswer":{"@type":"Answer","text":"Products are chosen for category relevance, clear presentation, useful specifications, current price visibility, premium appeal, and buyer value."}},{"@type":"Question","name":"Why compare more than one product?","acceptedAnswer":{"@type":"Answer","text":"The best choice balances price, build quality, features, compatibility, warranty, reviews, and long-term usefulness instead of relying on price alone."}},{"@type":"Question","name":"Are prices and availability final?","acceptedAnswer":{"@type":"Answer","text":"';

$html = '<html><head>'
	. '<script type="application/ld+json" class="rank-math-schema">' . $valid_rank_math . '</script>'
	. '<script type="application/ld+json" class="llgai-schema">' . $valid_list . '</script>'
	. '<script type="application/ld+json">' . $truncated_faq . '</script>'
	. '</head><body>ok</body></html>';

$audit = RBSMC_Stay_Schema::audit_html( $html );
assert_true( 3 === $audit['blocks'], 'audit finds 3 JSON-LD blocks' );
assert_true( 1 === $audit['invalid'], 'audit flags only the truncated FAQ' );
assert_true( isset( $audit['signals'][0]['hash'] ), 'red signal includes sha256' );
assert_true( false !== strpos( $audit['signals'][0]['detail'], 'Unterminated string' ) || false !== strpos( $audit['signals'][0]['error'] ?? '', 'Unterminated' ) || false !== strpos( $audit['signals'][0]['detail'], 'unterminated' ) || false !== strpos( strtolower( $audit['signals'][0]['detail'] ), 'unterminated' ) || false !== strpos( $audit['signals'][0]['detail'], 'error=' ), 'parser error is recorded' );

$out = RBSMC_Stay_Schema::quarantine_invalid( $html );
assert_true( false !== strpos( $out, 'rank-math-schema' ), 'valid Rank Math block kept' );
assert_true( false !== strpos( $out, 'llgai-schema' ), 'valid ItemList block kept' );
assert_true( false === strpos( $out, 'Are prices and availability final?' ), 'truncated FAQ script removed' );
assert_true( false !== strpos( $out, 'rbsmc-stay quarantined invalid json-ld sha256=' ), 'quarantine comment written' );
assert_true( false !== strpos( $out, '>ok<' ), 'page body unchanged' );

$clean_audit = RBSMC_Stay_Schema::audit_html( $out );
assert_true( 0 === $clean_audit['invalid'], 'quarantined HTML has zero invalid JSON-LD' );
assert_true( 2 === $clean_audit['blocks'], 'two valid blocks remain' );

$untouched = RBSMC_Stay_Schema::quarantine_invalid( '<p>no schema</p>' );
assert_true( '<p>no schema</p>' === $untouched, 'HTML without JSON-LD is unchanged' );

require dirname( __DIR__ ) . '/plugins/luxe-operator-dashboard/includes/class-cleaner.php';
$ids = Luxe_Operator_Cleaner::remove_ids();
assert_true( in_array( 'yith_dashboard_blog_news', $ids, true ), 'YITH blog widget is removed' );
assert_true( in_array( 'woocommerce_dashboard_recent_reviews', $ids, true ), 'empty reviews widget is removed' );
assert_true( in_array( 'dashboard_site_health', $ids, true ), 'empty Site Health widget is removed' );
assert_true( ! in_array( 'rank_math_dashboard_widget', $ids, true ), 'Rank Math Overview is kept' );
assert_true( ! in_array( 'wordfence_activity_report_widget', $ids, true ), 'Wordfence activity is kept' );
assert_true( ! in_array( 'dashboard_right_now', $ids, true ), 'At a Glance is kept' );

if ( $fail ) {
	echo "\n$fail failed\n";
	exit( 1 );
}
echo "\nAll tests passed\n";
exit( 0 );
