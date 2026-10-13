<?php
define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../../MyContent/plugins/media-sweeper/includes/class-msw-extractor.php';
require __DIR__ . '/../../MyContent/plugins/media-sweeper/includes/class-msw-rewriter.php';
$fail = 0;
function ok( $c, $n ) { global $fail; if ( ! $c ) { $fail++; echo "FAIL: $n\n"; } else { echo "ok   $n\n"; } }

$map = array(
	'2024/05/foo.jpg'         => '2024/05/foo.webp',
	'2024/05/foo-300x200.jpg' => '2024/05/foo-300x200.webp',
	'2024/05/ba r.png'        => '2024/05/ba r.webp',
	'2024/06/café.jpg'   => '2024/06/x.webp',
);
$lk = function ( $o ) use ( $map ) { return isset( $map[ $o ] ) ? $map[ $o ] : null; };

// Plain HTML.
$h = '<img src="https://s.com/wp-content/uploads/2024/05/foo-300x200.jpg" srcset="/wp-content/uploads/2024/05/foo.jpg 1024w"><a href="https://o.com/other/foo.jpg">';
$r = MSW_Rewriter::replace_plain( $h, $lk );
ok( strpos( $r, 'uploads/2024/05/foo-300x200.webp"' ) !== false && strpos( $r, 'uploads/2024/05/foo.webp 1024w' ) !== false, 'html rewrite' );
ok( strpos( $r, 'other/foo.jpg' ) !== false, 'non-uploads untouched' );
// Unknown file stays.
ok( MSW_Rewriter::replace_plain( 'uploads/2024/05/zzz.jpg', $lk ) === 'uploads/2024/05/zzz.jpg', 'unknown untouched' );
// JSON escaped.
$j = '{"url":"https:\/\/s.com\/wp-content\/uploads\/2024\/05\/foo.jpg","id":5}';
$r = MSW_Rewriter::replace_plain( $j, $lk );
ok( $r === '{"url":"https:\/\/s.com\/wp-content\/uploads\/2024\/05\/foo.webp","id":5}' && json_decode( $r ) !== null, 'json escaped' );
// Percent-encoded.
$r = MSW_Rewriter::replace_plain( 'x/uploads/2024/05/ba%20r.png', $lk );
ok( $r === 'x/uploads/2024/05/ba%20r.webp', 'percent encoded' );
// Upper-case ext not in map -> untouched; companion webp guard.
ok( MSW_Rewriter::replace_plain( 'uploads/2024/05/foo.jpg.webp', $lk ) === 'uploads/2024/05/foo.jpg.webp', 'jpg.webp guard' );
// Query string preserved.
ok( MSW_Rewriter::replace_plain( 'uploads/2024/05/foo.jpg?ver=2', $lk ) === 'uploads/2024/05/foo.webp?ver=2', 'query kept' );
// Multisite prefix.
ok( MSW_Rewriter::replace_plain( 'uploads/sites/2/2024/05/foo.jpg', $lk ) === 'uploads/sites/2/2024/05/foo.webp', 'multisite prefix' );

// Serialized with length fix.
$data = array( 'a' => 'https://s.com/wp-content/uploads/2024/05/foo.jpg', 'n' => array( 'html' => '<img src="/wp-content/uploads/2024/05/foo-300x200.jpg">', 'i' => 5 ), 'ünï' => 'ç' );
$ser  = serialize( $data );
$new  = MSW_Rewriter::replace_serialized( $ser, $lk );
$un   = @unserialize( $new );
ok( $un !== false && $un['a'] === 'https://s.com/wp-content/uploads/2024/05/foo.webp' && strpos( $un['n']['html'], 'foo-300x200.webp' ) !== false && $un['n']['i'] === 5 && $un['ünï'] === 'ç', 'serialized array' );
// Nested serialized string inside serialized.
$inner = serialize( array( 'u' => 'uploads/2024/05/foo.jpg' ) );
$ser   = serialize( array( 'blob' => $inner, 'o' => new stdClass ) );
$new   = MSW_Rewriter::replace_any( $ser, $lk );
$un    = @unserialize( $new );
ok( $un !== false && unserialize( $un['blob'] )['u'] === 'uploads/2024/05/foo.webp' && $un['o'] instanceof stdClass, 'nested serialized + object' );
// Object with private props.
class Foo { private $p = 'uploads/2024/05/foo.jpg'; public $q = 1; }
$ser = serialize( new Foo );
$new = MSW_Rewriter::replace_any( $ser, $lk );
ok( strpos( $new, 'foo.webp' ) !== false && @unserialize( $new ) instanceof Foo, 'object private prop length' );
// Candidate discovery.
ok( MSW_Rewriter::candidates( 'uploads/2024/05/foo.jpg uploads\/2024\/05\/foo-300x200.jpg' ) === array( '2024/05/foo.jpg', '2024/05/foo-300x200.jpg' ), 'candidates' );
echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED\n";
exit( $fail ? 1 : 0 );
