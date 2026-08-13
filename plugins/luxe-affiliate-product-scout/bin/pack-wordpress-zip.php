<?php
/**
 * Build the Windows 11 / WordPress PclZip package.
 * php plugins/luxe-affiliate-product-scout/bin/pack-wordpress-zip.php
 */
$script = __DIR__ . '/pack-windows-zip.py';
passthru( 'python3 ' . escapeshellarg( $script ), $code );
exit( $code );
