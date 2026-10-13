<?php
// Standalone tests: php -d error_reporting=-1 tests/test-extractor.php
define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../../MyContent/plugins/media-sweeper/includes/class-msw-extractor.php';

$fail = 0;
function ok( $cond, $name ) { global $fail; if ( ! $cond ) { $fail++; echo "FAIL: $name\n"; } else { echo "ok   $name\n"; } }

// Keys.
ok( MSW_Extractor::path_key( '2024/05/Foo-300x200.JPG' ) === '2024/05/foo', 'size suffix' );
ok( MSW_Extractor::path_key( '2024/05/foo-scaled.jpg' ) === '2024/05/foo', 'scaled' );
ok( MSW_Extractor::path_key( '2024/05/foo.jpg.webp' ) === '2024/05/foo', 'double ext' );
ok( MSW_Extractor::path_key( '2024/05/foo.webp' ) === '2024/05/foo', 'webp' );
ok( MSW_Extractor::path_key( 'sites/2/2024/05/foo-1-768x512.png?ver=3' ) === '2024/05/foo-1', 'multisite+query' );
ok( MSW_Extractor::path_key( '2024/05/%D8%B9%DA%A9%D8%B3-300x300.jpg' ) === '2024/05/عکس', 'utf8 decode' );
ok( MSW_Extractor::base_key( '2024/05/foo-300x200.jpg' ) === 'foo', 'base key' );

// Paths.
$e = MSW_Extractor::extract( '<img src="https://x.com/wp-content/uploads/2024/05/foo-300x200.jpg" srcset="https://x.com/wp-content/uploads/2024/05/foo-768x512.jpg 768w, https://x.com/wp-content/uploads/2024/05/foo.jpg 1200w">' );
ok( isset( $e['paths']['2024/05/foo'] ) && count( $e['paths'] ) === 1, 'img/srcset paths' );
$e = MSW_Extractor::extract( '{"url":"https:\/\/x.com\/wp-content\/uploads\/2024\/06\/bar.png","id":77}' );
ok( isset( $e['paths']['2024/06/bar'] ), 'json-escaped path' );
ok( isset( $e['ids'][77] ), 'json id' );
$e = MSW_Extractor::extract( 'background:url(/wp-content/uploads/2023/01/hero.webp)' );
ok( isset( $e['paths']['2023/01/hero'] ), 'css url()' );
$e = MSW_Extractor::extract( '[video src="/wp-content/uploads/2023/01/clip.mp4" poster="/wp-content/uploads/2023/01/clip-poster.jpg"]' );
ok( isset( $e['paths']['2023/01/clip'] ) && isset( $e['paths']['2023/01/clip-poster'] ), 'video shortcode' );

// IDs.
$e = MSW_Extractor::extract( '<img class="alignnone wp-image-1234 size-full" src="x">' );
ok( isset( $e['ids'][1234] ), 'wp-image class' );
$e = MSW_Extractor::extract( '[gallery ids="5,6, 7" columns="3"]' );
ok( isset( $e['ids'][5], $e['ids'][6], $e['ids'][7] ) && ! isset( $e['ids'][3] ), 'gallery ids' );
$e = MSW_Extractor::extract( '<!-- wp:image {"id":99,"sizeSlug":"large"} --><!-- wp:gallery {"ids":[11,12]} -->' );
ok( isset( $e['ids'][99], $e['ids'][11], $e['ids'][12] ), 'gutenberg' );
$e = MSW_Extractor::extract( '{"id":"4f2a1c","settings":{"width":50,"image":{"id":321}}}' );
ok( isset( $e['ids'][321] ) && ! isset( $e['ids'][50] ) && ! isset( $e['ids'][4] ), 'elementor-like json' );
$e = MSW_Extractor::extract( 'a:2:{s:11:"custom_logo";i:55;s:5:"color";s:3:"123";}' );
ok( isset( $e['ids'][55] ) && ! isset( $e['ids'][123] ), 'serialized theme mod' );
$e = MSW_Extractor::extract( 'a:3:{i:0;s:3:"101";i:1;s:3:"102";i:2;i:103;}', true );
ok( isset( $e['ids'][101], $e['ids'][102], $e['ids'][103] ), 'ctx serialized list' );
$e = MSW_Extractor::extract( '["201","202"]', true );
ok( isset( $e['ids'][201], $e['ids'][202] ), 'ctx json list' );
$e = MSW_Extractor::extract( '<p>Call 09121234567 or order 1234</p>' );
ok( ! $e['ids'], 'no false ids in prose' );
$e = MSW_Extractor::extract( 'wp:image foo logo.png' );
ok( isset( $e['bases']['logo'] ), 'weak base' );

echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED\n";
exit( $fail ? 1 : 0 );
