<?php
/**
 * php tests/test-remover.php
 */
define( 'ABSPATH', '/tmp/' );

$dir = dirname( __DIR__ ) . '/includes/';
require $dir . 'class-targets.php';
require $dir . 'class-board.php';

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

$folders = LUOR_Targets::folders();
expect( 18 === count( $folders ), '18 folders on the list' );
expect( 18 === count( array_unique( $folders ) ), 'folder names are unique' );

$samples = array(
	'luxe-reader-love-polisher/luxe-reader-love-polisher.php',
	'luxe-keyword-intelligence-autopilot/luxe-keyword-intelligence-autopilot.php',
	'luxe-blog-master-command-center/luxe-blog-master-command-center.php',
	'richard-brummer-seo-mission-control/richard-brummer-seo-mission-control.php',
	'luxe-ai-readiness-autopilot/luxe-ai-readiness-autopilot.php',
	'luxe-performance-core-fusion/luxe-performance-core-fusion.php',
	'luxe-performance-link-guardian-suite/luxe-performance-link-guardian-suite.php',
	'luxe-auto-fix-pro-green-signals-v4.3.0/luxe-auto-fix-pro-green-signals.php',
	'luxe-index-recovery-guard/luxe-index-recovery-guard.php',
	'luxe-rankmath-schema-guard/luxe-rankmath-schema-guard.php',
	'amazon-affiliate-autopilot/amazon-affiliate-autopilot.php',
	'luxe-amazon-correct-tag-green-signals/luxe-amazon-correct-tag-green-signals.php',
	'luxe-amazon-blog-button-repair/luxe-amazon-blog-button-repair.php',
	'Luxe-CRON-Mobilizer-Pro-v1.2.1/luxe-cron-mobilizer-pro.php',
	'action-scheduler-healer-pro/action-scheduler-healer-pro.php',
	'luxe-master-plugin-orchestrator/luxe-master-plugin-orchestrator.php',
	'luxe-wow-homepage-ultra-premium/luxe-wow-homepage-learning.php',
	'luxe-category-authority-lock-safe/luxe-category-authority-lock-safe.php',
);
foreach ( $samples as $basename ) {
	expect( 'delete' === LUOR_Targets::decision( $basename ), 'delete ' . $basename );
}

$protected = array(
	'woocommerce/woocommerce.php',
	'woozone/plugin.php',
	'seo-by-rank-math/rank-math.php',
	'litespeed-cache/litespeed-cache.php',
	'wordfence/wordfence.php',
	'google-site-kit/google-site-kit.php',
	'link-whisper/link-whisper.php',
	'snapshot-backups/snapshot-backups.php',
	'luxe-ultimate-authority/luxe-ultimate-authority.php',
	'luxe-affiliate-product-scout/luxe-affiliate-product-scout.php',
	'luxe-amazon-view-on-amazon-bridge/luxe-amazon-view-on-amazon-bridge.php',
	'luxe-theme-guard/luxe-theme-guard.php',
	'luxe-simple-homepage-mirror-master-v2.9.6-HAMBURGER-9.7-SCORE-MODULE-FULL-250KB/luxe-simple-homepage-mirror-master.php',
	'luxe-overlap-remover/luxe-overlap-remover.php',
	'luxe-hard-rescue-admin-cleaner-v3.7.8/luxe-hard-rescue-admin-cleaner.php',
	'luxe-optimizer/luxe-optimizer.php',
	'luxe-duplicate-plugin-cleaner/luxe-duplicate-plugin-cleaner.php',
	'yith-woocommerce-wishlist/init.php',
	'luxe-stay/luxe-stay.php',
	'luxe-wzone-stats-guardian/luxe-wzone-stats-guardian.php',
	'luxe-wzone-deletion-shield/luxe-wzone-deletion-shield.php',
	'luxe-comment-shield-learning-guard/luxe-comment-shield-learning-guard.php',
	'luxe-site-hardening-guard/luxe-site-hardening-guard.php',
	'luxe-hard-rescue-safe-trash-extension/luxe-hard-rescue-safe-trash-extension.php',
	'luxe-safe-plugin-deleter/luxe-safe-plugin-deleter.php',
	'luxe-download-scout/luxe-download-scout.php',
	'luxe-storefront-repair/luxe-storefront-repair.php',
	'classic-editor/classic-editor.php',
	'contact-form-7/wp-contact-form-7.php',
);
foreach ( $protected as $basename ) {
	expect( 'keep' === LUOR_Targets::decision( $basename ), 'keep ' . $basename );
}

expect( 'leave' === LUOR_Targets::decision( 'luxe-reader-love-polisher-old/plugin.php' ), 'lookalike folder is left alone' );
expect( 'leave' === LUOR_Targets::decision( '../woocommerce/woocommerce.php' ), 'parent traversal is left alone' );
expect( 'leave' === LUOR_Targets::decision( 'hello.php' ), 'a single-file plugin is left alone' );

$root = sys_get_temp_dir() . '/luor-' . uniqid( '', true );
$outside = sys_get_temp_dir() . '/luor-out-' . uniqid( '', true );
mkdir( $root );
mkdir( $outside );
mkdir( $root . '/luxe-reader-love-polisher' );
mkdir( $root . '/woocommerce' );
expect( LUOR_Targets::is_inside_plugins( $root, 'luxe-reader-love-polisher' ), 'real folder stays inside plugins' );
expect( ! LUOR_Targets::is_inside_plugins( $root, '..' ), 'plugins root cannot be the delete target' );
expect( ! LUOR_Targets::is_inside_plugins( $root, 'missing-folder' ), 'missing folder is not deletable' );
if ( symlink( $outside, $root . '/linked-reader' ) ) {
	rename( $root . '/luxe-reader-love-polisher', $root . '/luxe-reader-love-polisher-real' );
	symlink( $outside, $root . '/luxe-reader-love-polisher' );
	expect( ! LUOR_Targets::is_inside_plugins( $root, 'luxe-reader-love-polisher' ), 'symlink outside plugins is rejected' );
}

$empty = LUOR_Board::build( array( 'scanned' => false, 'present' => array(), 'deleted' => array(), 'failed' => array() ) );
$empty_tally = LUOR_Board::tally( $empty );
expect( 26 === $empty_tally['total'], '26 signals' );
expect( 8 === $empty_tally['green'], '8 safety signals are green before a scan' );
expect( 18 === $empty_tally['yellow'], '18 plugin rows wait for a scan' );
expect( 'green' === $empty[0]['level'] && 'Backup gate' === $empty[0]['label'], 'backup gate is first and green' );
foreach ( $empty as $row ) {
	if ( 'Safety' === $row['group'] ) {
		expect( 'green' === $row['level'], 'safety signal green: ' . $row['label'] );
	} else {
		expect( 'yellow' === $row['level'], 'plugin signal yellow before delete: ' . $row['label'] );
	}
}

$present = array();
foreach ( $folders as $folder ) {
	$present[ $folder ] = $folder . '/plugin.php';
}
$waiting = LUOR_Board::tally( LUOR_Board::build( array( 'scanned' => true, 'present' => $present, 'deleted' => array(), 'failed' => array() ) ) );
expect( 8 === $waiting['green'] && 18 === $waiting['yellow'] && 0 === $waiting['red'], 'installed plugins stay yellow' );

$deleted = array();
foreach ( $folders as $folder ) {
	$deleted[ $folder ] = 'Deactivated and deleted.';
}
$done = LUOR_Board::tally( LUOR_Board::build( array( 'scanned' => true, 'present' => array(), 'deleted' => $deleted, 'failed' => array() ) ) );
expect( 26 === $done['green'] && 0 === $done['yellow'] && 0 === $done['red'], 'a finished delete is 26 / 26 green' );

$failed = $deleted;
unset( $failed['luxe-optimizer'] );
$failed['amazon-affiliate-autopilot'] = 'Deactivation failed, so the folder was left in place.';
unset( $deleted['amazon-affiliate-autopilot'] );
$partial = LUOR_Board::build( array( 'scanned' => true, 'present' => array(), 'deleted' => $deleted, 'failed' => array( 'amazon-affiliate-autopilot' => 'Deactivation failed, so the folder was left in place.' ) ) );
$levels = array();
foreach ( $partial as $row ) {
	$levels[ $row['id'] ] = $row['level'];
}
expect( 'red' === $levels['target_amazon-affiliate-autopilot'], 'a failed delete is red' );
expect( 'green' === $levels['target_luxe-reader-love-polisher'], 'a deleted neighbor stays green' );
expect( 'green' === $levels['public_lock'], 'public site lock stays green' );

$reinstalled = LUOR_Board::build(
	array(
		'scanned' => true,
		'present' => array( 'luxe-reader-love-polisher' => 'luxe-reader-love-polisher/luxe-reader-love-polisher.php' ),
		'deleted' => $deleted,
		'failed'  => array(),
	)
);
$re_levels = array();
foreach ( $reinstalled as $row ) {
	$re_levels[ $row['id'] ] = $row['level'];
}
expect( 'yellow' === $re_levels['target_luxe-reader-love-polisher'], 'a folder that is back on disk stays yellow' );

$started = microtime( true );
for ( $i = 0; $i < 5000; $i++ ) {
	LUOR_Targets::decision( $samples[ $i % count( $samples ) ] );
	LUOR_Targets::decision( 'woocommerce/woocommerce.php' );
	LUOR_Targets::decision( '../woocommerce/woocommerce.php' );
}
$elapsed = microtime( true ) - $started;
expect( $elapsed < 1.0, '15000 decisions finish in under a second (' . number_format( $elapsed, 4 ) . 's)' );

if ( $fail ) {
	echo "\n$fail failed\n";
	exit( 1 );
}
echo "\nAll checks passed\n";
exit( 0 );
