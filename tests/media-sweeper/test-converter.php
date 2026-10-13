<?php
define( 'ABSPATH', __DIR__ );
define( 'MB_IN_BYTES', 1048576 );
function get_post_meta() { return ''; }
require __DIR__ . '/../../MyContent/plugins/media-sweeper/includes/class-msw-converter.php';
$fail = 0;
function ok( $c, $n ) { global $fail; if ( ! $c ) { $fail++; echo "FAIL: $n\n"; } else { echo "ok   $n\n"; } }

$d = sys_get_temp_dir() . '/msw-test-' . getmypid();
mkdir( $d );
$o = array( 'engine' => 'gd', 'jpeg_q' => 82, 'png_mode' => 'lossless', 'png_q' => 90 );

// JPEG photo-like.
$im = imagecreatetruecolor( 800, 600 );
for ( $i = 0; $i < 800; $i += 4 ) { imagefilledrectangle( $im, $i, 0, $i + 3, 599, imagecolorallocate( $im, $i % 255, ( $i * 3 ) % 255, ( $i * 7 ) % 255 ) ); }
for ( $i = 0; $i < 3000; $i++ ) { imagesetpixel( $im, rand( 0, 799 ), rand( 0, 599 ), imagecolorallocate( $im, rand( 0, 255 ), rand( 0, 255 ), rand( 0, 255 ) ) ); }
imagejpeg( $im, "$d/a.jpg", 95 );
ok( MSW_Converter::convert_file( "$d/a.jpg", "$d/a.tmp", $o ) === true, 'jpeg -> webp' );
$i = getimagesize( "$d/a.tmp" );
ok( $i[2] === IMAGETYPE_WEBP && $i[0] === 800 && $i[1] === 600, 'webp type+dims' );
ok( filesize( "$d/a.tmp" ) < filesize( "$d/a.jpg" ), 'webp smaller than q95 jpeg (' . filesize( "$d/a.tmp" ) . ' < ' . filesize( "$d/a.jpg" ) . ')' );

// PNG with alpha, lossless.
$p = imagecreatetruecolor( 300, 200 );
imagealphablending( $p, false ); imagesavealpha( $p, true );
imagefill( $p, 0, 0, imagecolorallocatealpha( $p, 0, 0, 0, 127 ) );
imagefilledellipse( $p, 150, 100, 200, 120, imagecolorallocatealpha( $p, 200, 30, 30, 20 ) );
imagepng( $p, "$d/b.png" );
ok( MSW_Converter::convert_file( "$d/b.png", "$d/b.tmp", $o ) === true, 'png alpha lossless -> webp' );
$w = imagecreatefromwebp( "$d/b.tmp" );
$px = imagecolorsforindex( $w, imagecolorat( $w, 2, 2 ) );
ok( $px['alpha'] === 127, 'alpha preserved (corner transparent)' );

// Palette PNG.
$q = imagecreate( 50, 50 ); imagecolorallocate( $q, 255, 255, 255 ); $red = imagecolorallocate( $q, 255, 0, 0 ); imagefilledrectangle( $q, 10, 10, 30, 30, $red );
imagepng( $q, "$d/c.png" );
ok( MSW_Converter::convert_file( "$d/c.png", "$d/c.tmp", $o ) === true, 'palette png' );

// Errors.
file_put_contents( "$d/bad.jpg", 'not an image' );
ok( is_string( MSW_Converter::convert_file( "$d/bad.jpg", "$d/bad.tmp", $o ) ) && ! file_exists( "$d/bad.tmp" ), 'corrupt file rejected, no leftovers' );
imagegif( $q, "$d/g.gif" );
ok( is_string( MSW_Converter::convert_file( "$d/g.gif", "$d/g.tmp", $o ) ), 'gif rejected' );

array_map( 'unlink', glob( "$d/*" ) ); rmdir( $d );
echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED\n";
exit( $fail ? 1 : 0 );
