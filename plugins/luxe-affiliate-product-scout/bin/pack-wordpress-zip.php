<?php
/**
 * Build a WordPress-uploadable plugin zip with ZipArchive (same library WP uses).
 * php plugins/luxe-affiliate-product-scout/bin/pack-wordpress-zip.php
 */
$root = dirname( __DIR__ );
$slug = 'luxe-affiliate-product-scout';
$dest_dir = dirname( dirname( $root ) );
$names = array(
	$dest_dir . '/luxe-affiliate-product-scout.zip',
	$dest_dir . '/Luxe-Affiliate-Product-Scout.zip',
	$dest_dir . '/dist/Luxe-Affiliate-Product-Scout-v5.6.6.zip',
);

$skip = array( '/tests/', '/bin/', '/.DS_Store' );

$files = array();
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file ) {
	if ( ! $file->isFile() ) {
		continue;
	}
	$path = $file->getPathname();
	$rel  = substr( $path, strlen( $root ) + 1 );
	$ok   = true;
	foreach ( $skip as $needle ) {
		if ( false !== strpos( '/' . str_replace( '\\', '/', $rel ) . '/', $needle ) ) {
			$ok = false;
			break;
		}
	}
	if ( $ok ) {
		$files[ $slug . '/' . str_replace( '\\', '/', $rel ) ] = $path;
	}
}
ksort( $files );

if ( ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "ZipArchive missing\n" );
	exit( 1 );
}

foreach ( $names as $dest ) {
	$dir = dirname( $dest );
	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0755, true );
	}
	if ( file_exists( $dest ) ) {
		unlink( $dest );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $dest, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		fwrite( STDERR, "Cannot write $dest\n" );
		exit( 1 );
	}
	foreach ( $files as $local => $path ) {
		$zip->addFile( $path, $local );
		$zip->setCompressionName( $local, ZipArchive::CM_DEFLATE );
		if ( method_exists( $zip, 'setExternalAttributesName' ) ) {
			$zip->setExternalAttributesName( $local, ZipArchive::OPSYS_DOS, 32 << 16 );
		}
	}
	$zip->close();
	echo basename( $dest ) . ' ' . filesize( $dest ) . " bytes\n";
}

$check = $names[0];
$zip = new ZipArchive();
$zip->open( $check, ZipArchive::RDONLY );
$found = false;
for ( $i = 0; $i < $zip->numFiles; $i++ ) {
	$stat = $zip->statIndex( $i );
	if ( 'luxe-affiliate-product-scout/luxe-affiliate-product-scout.php' === $stat['name'] ) {
		$found = true;
		$src = $zip->getFromIndex( $i );
		if ( false === strpos( $src, 'Plugin Name: Luxe Affiliate Product Scout' ) ) {
			fwrite( STDERR, "Plugin header missing inside zip\n" );
			exit( 1 );
		}
	}
}
$zip->close();
if ( ! $found ) {
	fwrite( STDERR, "Main plugin file missing from zip\n" );
	exit( 1 );
}
echo "WordPress zip OK\n";
