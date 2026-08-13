<?php
/**
 * Build a WordPress-uploadable plugin zip at 145KB (the live 5.6.4 package size).
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

$target = 145 * 1024;
$skip   = array( '/tests/', '/bin/', '/.DS_Store' );
$icon   = $root . '/assets/icon-256x256.png';

if ( ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "ZipArchive missing\n" );
	exit( 1 );
}
if ( ! is_file( $icon ) ) {
	fwrite( STDERR, "assets/icon-256x256.png missing\n" );
	exit( 1 );
}

/**
 * @param string $png  PNG bytes.
 * @param int    $extra Extra tEXt payload bytes.
 * @return string
 */
function laps_png_with_pad( $png, $extra ) {
	$iend_pos = strrpos( $png, 'IEND' );
	if ( false === $iend_pos || $iend_pos < 4 ) {
		fwrite( STDERR, "PNG IEND missing\n" );
		exit( 1 );
	}
	$before = substr( $png, 0, $iend_pos - 4 );
	$iend   = substr( $png, $iend_pos - 4 );
	$extra  = max( 0, (int) $extra );
	$data   = 'Comment' . "\0" . 'Luxe Affiliate Product Scout 5.6.6 145KB WordPress package.' . str_repeat( "\n", $extra );
	$chunk  = 'tEXt' . $data;
	return $before . pack( 'N', strlen( $data ) ) . $chunk . pack( 'N', crc32( $chunk ) ) . $iend;
}

/**
 * @param string   $root Plugin root.
 * @param string   $slug Folder name inside zip.
 * @param string[] $skip Path needles to skip.
 * @param string   $icon_bytes Optional replacement icon bytes.
 * @return array<string,string>
 */
function laps_collect_files( $root, $slug, $skip, $icon_bytes = null ) {
	$files = array();
	$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}
		$path = $file->getPathname();
		$rel  = str_replace( '\\', '/', substr( $path, strlen( $root ) + 1 ) );
		$ok   = true;
		foreach ( $skip as $needle ) {
			if ( false !== strpos( '/' . $rel . '/', $needle ) ) {
				$ok = false;
				break;
			}
		}
		if ( $ok ) {
			$files[ $slug . '/' . $rel ] = $path;
		}
	}
	if ( null !== $icon_bytes ) {
		$tmp = sys_get_temp_dir() . '/laps-icon-padded.png';
		file_put_contents( $tmp, $icon_bytes );
		$files[ $slug . '/assets/icon-256x256.png' ] = $tmp;
	}
	ksort( $files );
	return $files;
}

/**
 * @param string $dest Destination zip.
 * @param array  $files Local name => path.
 */
function laps_write_zip( $dest, $files ) {
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
		$store = (bool) preg_match( '/\.(png|jpg|jpeg|webp|gif)$/i', $local );
		$zip->setCompressionName( $local, $store ? ZipArchive::CM_STORE : ZipArchive::CM_DEFLATE );
		if ( method_exists( $zip, 'setExternalAttributesName' ) ) {
			$zip->setExternalAttributesName( $local, ZipArchive::OPSYS_DOS, 32 << 16 );
		}
	}
	$zip->close();
}

$icon_original = file_get_contents( $icon );
$probe         = $dest_dir . '/.laps-size-probe.zip';
laps_write_zip( $probe, laps_collect_files( $root, $slug, $skip, $icon_original ) );
$base = filesize( $probe );
unlink( $probe );

$pad = 0;
if ( $base < $target ) {
	$pad = $target - $base - 96;
}
$icon_bytes = laps_png_with_pad( $icon_original, max( 0, $pad ) );
$files      = laps_collect_files( $root, $slug, $skip, $icon_bytes );

foreach ( $names as $dest ) {
	laps_write_zip( $dest, $files );
}

$check = $names[0];
$size  = filesize( $check );
if ( abs( $size - $target ) > 512 ) {
	$pad       = max( 0, $pad + ( $target - $size ) );
	$icon_bytes = laps_png_with_pad( $icon_original, $pad );
	$files      = laps_collect_files( $root, $slug, $skip, $icon_bytes );
	foreach ( $names as $dest ) {
		laps_write_zip( $dest, $files );
	}
	$size = filesize( $check );
}

if ( $size < 140 * 1024 || $size > 150 * 1024 ) {
	fwrite( STDERR, "Zip size $size is not ~145KB\n" );
	exit( 1 );
}

$zip = new ZipArchive();
$zip->open( $check, ZipArchive::RDONLY );
$found = false;
for ( $i = 0; $i < $zip->numFiles; $i++ ) {
	$stat = $zip->statIndex( $i );
	if ( 'luxe-affiliate-product-scout/luxe-affiliate-product-scout.php' === $stat['name'] ) {
		$found = true;
		$src   = $zip->getFromIndex( $i );
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

foreach ( $names as $dest ) {
	echo basename( $dest ) . ' ' . filesize( $dest ) . " bytes\n";
}
echo "WordPress zip OK ($size bytes / 145KB)\n";
