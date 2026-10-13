<?php
/**
 * End-to-end scenario for Media Sweeper. Run inside WordPress:
 *   wp eval-file tests/media-sweeper/integration/scenario.php
 * Exits non-zero when any assertion fails.
 */
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
global $wpdb;
$GLOBALS['msw_fail'] = 0;

function t( $label, $cond ) {
	if ( ! $cond ) {
		$GLOBALS['msw_fail']++;
	}
	echo ( $cond ? 'ok   ' : 'FAIL ' ) . $label . "\n";
}
function run_job( $type, $opts = array() ) {
	$r = MSW_Job::start( $type, $opts );
	if ( is_wp_error( $r ) ) {
		return array( 'status' => 'error', 'msg' => $r->get_error_message() );
	}
	do {
		$st = MSW_Job::tick( 10 );
	} while ( 'running' === $st['status'] );
	return $st;
}
function mkjpg( $name, $w, $h, $seed ) {
	$tmp = sys_get_temp_dir() . '/' . $name;
	$im  = imagecreatetruecolor( $w, $h );
	mt_srand( $seed );
	for ( $x = 0; $x < $w; $x += 6 ) {
		imagefilledrectangle( $im, $x, 0, $x + 5, $h, imagecolorallocate( $im, mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) ) );
	}
	for ( $i = 0; $i < 3000; $i++ ) {
		imagesetpixel( $im, mt_rand( 0, $w - 1 ), mt_rand( 0, $h - 1 ), imagecolorallocate( $im, mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) ) );
	}
	if ( '.png' === substr( $name, -4 ) ) {
		imagepng( $im, $tmp );
	} else {
		imagejpeg( $im, $tmp, 92 );
	}
	return $tmp;
}
function imp( $name, $seed, $days = 40, $w = 1600, $h = 1000, $title = '' ) {
	global $wpdb;
	$id = media_handle_sideload( array( 'name' => $name, 'tmp_name' => mkjpg( $name, $w, $h, $seed ) ), 0, $title ? $title : null );
	if ( is_wp_error( $id ) ) {
		echo 'IMPORT ERROR ' . $id->get_error_message() . "\n";
		exit( 1 );
	}
	$wpdb->update( $wpdb->posts, array( 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - $days * 86400 ) ), array( 'ID' => $id ) );
	return $id;
}
$img = function ( $id, $size = 'medium' ) {
	$s = wp_get_attachment_image_src( $id, $size );
	return $s[0];
};
$file = function ( $id ) { return get_attached_file( $id ); };

echo "== fixtures\n";
$I = array();
foreach ( array( 'in-content.jpg' => 1, 'featured.jpg' => 2, 'elementor.jpg' => 3, 'gallery-a.jpg' => 4, 'gallery-b.jpg' => 5, 'unused-one.jpg' => 6, 'unused-two.png' => 7, 'theme-hard.jpg' => 8, 'logo-mod.png' => 9 ) as $n => $s ) {
	$I[ $n ] = imp( $n, $s );
}
$I['recent.jpg']     = imp( 'recent.jpg', 11, 1 );
$I['protected.jpg']  = imp( 'protected.jpg', 12 );
update_post_meta( $I['protected.jpg'], '_msw_protected', 1 );
$I['dup-keep.jpg']   = imp( 'dup-keep.jpg', 20 );
$I['dup-url.jpg']    = imp( 'dup-url.jpg', 20 );
$I['dup-byid.jpg']   = imp( 'dup-byid.jpg', 20 );
$I['IMG_2045.jpg']   = imp( 'IMG_2045.jpg', 21, 40, 1500, 900, 'Eiffel tower at night' );
$I['huge.jpg']       = imp( 'huge.jpg', 22, 40, 4200, 3000 );

$content  = '<img class="wp-image-' . $I['in-content.jpg'] . '" src="' . $img( $I['in-content.jpg'] ) . '" srcset="' . $img( $I['in-content.jpg'], 'thumbnail' ) . ' 150w, ' . $img( $I['in-content.jpg'], 'full' ) . ' 1600w">';
$content .= '[gallery ids="' . $I['gallery-a.jpg'] . ',' . $I['gallery-b.jpg'] . '"]';
$p1 = wp_insert_post( array( 'post_title' => 'Post one', 'post_content' => $content, 'post_status' => 'publish' ) );
$p2 = wp_insert_post( array( 'post_title' => 'Post two', 'post_content' => 'x', 'post_status' => 'publish' ) );
set_post_thumbnail( $p2, $I['featured.jpg'] );
$p3 = wp_insert_post( array( 'post_title' => 'Elementor', 'post_content' => '', 'post_status' => 'publish' ) );
update_post_meta( $p3, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => '4f2a1c', 'settings' => array( 'image' => array( 'url' => $img( $I['elementor.jpg'], 'full' ), 'id' => $I['elementor.jpg'] ) ) ) ) ) ) );
update_option( 'widget_text', array( 2 => array( 'title' => 't', 'text' => '<img src="' . $img( $I['elementor.jpg'], 'full' ) . '">' ), '_multiwidget' => 1 ) );
set_theme_mod( 'custom_logo', $I['logo-mod.png'] );
file_put_contents( get_stylesheet_directory() . '/msw-hardcoded.css', '.hero{background:url(' . $img( $I['theme-hard.jpg'], 'full' ) . ')}' );
$p4 = wp_insert_post( array( 'post_title' => 'Dup', 'post_status' => 'publish', 'post_content' => '<img src="' . $img( $I['dup-url.jpg'] ) . '"><img src="' . $img( $I['dup-keep.jpg'] ) . '">' ) );
$p5 = wp_insert_post( array( 'post_title' => 'Dup id', 'post_status' => 'publish', 'post_content' => 'x' ) );
set_post_thumbnail( $p5, $I['dup-byid.jpg'] );
$p6 = wp_insert_post( array( 'post_title' => 'SEO', 'post_status' => 'publish', 'post_content' => '<img class="wp-image-' . $I['IMG_2045.jpg'] . '" src="' . $img( $I['IMG_2045.jpg'] ) . '"><img src="' . $img( $I['huge.jpg'], 'full' ) . '"><img src="' . MSW_Util::baseurl() . '/2019/01/ghost-image.jpg">' ) );
$u = wp_upload_dir();
wp_mkdir_p( $u['basedir'] . '/2020/01' );
copy( mkjpg( 'orph.jpg', 400, 300, 99 ), $u['basedir'] . '/2020/01/orphan.jpg' );
copy( $u['basedir'] . '/2020/01/orphan.jpg', $u['basedir'] . '/2020/01/companion.jpg.webp' );

echo "== scan\n";
$st = run_job( 'scan', array( 'duplicates' => true ) );
t( 'scan completes', 'done' === $st['status'] );
$status = function ( $id ) use ( $wpdb ) { return $wpdb->get_row( $wpdb->prepare( 'SELECT status, locked, theme_ref FROM ' . MSW_Util::t( 'media' ) . ' WHERE attachment_id = %d', $id ), ARRAY_A ); };
foreach ( array( 'in-content.jpg', 'featured.jpg', 'elementor.jpg', 'gallery-a.jpg', 'gallery-b.jpg', 'logo-mod.png', 'theme-hard.jpg', 'dup-keep.jpg', 'dup-url.jpg', 'dup-byid.jpg', 'IMG_2045.jpg', 'huge.jpg' ) as $n ) {
	t( "used: $n", 'used' === $status( $I[ $n ] )['status'] );
}
foreach ( array( 'unused-one.jpg', 'unused-two.png' ) as $n ) {
	$r = $status( $I[ $n ] );
	t( "unused: $n", 'unused' === $r['status'] && '' === $r['locked'] );
}
t( 'recent locked', 'recent' === $status( $I['recent.jpg'] )['locked'] );
t( 'protected locked', 'protected' === $status( $I['protected.jpg'] )['locked'] );
t( 'theme ref flagged', 1 === (int) $status( $I['theme-hard.jpg'] )['theme_ref'] );
$o = MSW_Query::orphans( array() );
t( 'one orphan, companion ignored', 1 === $o['total'] && 'orphan.jpg' === $o['items'][0]['file'] );
t( 'broken link found', 1 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . MSW_Util::t( 'broken' ) ) );
$d = MSW_Query::duplicates( array() );
t( 'duplicate group of 3', 1 === $d['total'] && 3 === $d['groups'][0]['count'] );

echo "== quarantine / restore / delete\n";
t( 'quarantine', true === MSW_Quarantine::quarantine( $I['unused-one.jpg'] ) && 'msw_quarantine' === get_post( $I['unused-one.jpg'] )->post_status && ! is_file( $file( $I['unused-one.jpg'] ) ) );
t( 'restore', true === MSW_Quarantine::restore( $I['unused-one.jpg'] ) && is_file( $file( $I['unused-one.jpg'] ) ) );
t( 'delete_now', true === MSW_Quarantine::delete_now( $I['unused-two.png'] ) && ! get_post( $I['unused-two.png'] ) );
t( 'protected refuses delete', is_string( MSW_Quarantine::delete_now( $I['protected.jpg'] ) ) );
$h = md5( '2020/01/orphan.jpg' );
t( 'orphan quarantine/restore/delete', true === MSW_Quarantine::orphan_quarantine( $h ) && true === MSW_Quarantine::orphan_restore( $h ) && true === MSW_Quarantine::orphan_delete( $h ) );

echo "== webp convert / revert\n";
$ids = array( $I['in-content.jpg'], $I['featured.jpg'], $I['elementor.jpg'], $I['gallery-a.jpg'], $I['gallery-b.jpg'], $I['logo-mod.png'] );
$st = run_job( 'convert', array( 'scope' => 'used' ) );
t( 'convert done ' . json_encode( $st['counts'] ), 'done' === $st['status'] && $st['counts']['converted'] >= 6 );
t( 'theme-ref image skipped', 'image/jpeg' === get_post_mime_type( $I['theme-hard.jpg'] ) );
$c = get_post_field( 'post_content', $p1 );
t( 'html rewritten', false !== strpos( $c, 'in-content-300x' ) && false !== strpos( $c, '.webp' ) && false === strpos( $c, 'in-content.jpg' ) );
$j = get_post_meta( $p3, '_elementor_data', true );
t( 'elementor json rewritten + valid', false !== strpos( $j, 'elementor.webp' ) && null !== json_decode( $j ) );
t( 'serialized widget rewritten', false !== strpos( wp_json_encode( get_option( 'widget_text' ) ), 'elementor.webp' ) );
t( 'metadata + mime', 'image/webp' === get_post_mime_type( $I['in-content.jpg'] ) && 'webp' === pathinfo( $file( $I['in-content.jpg'] ), PATHINFO_EXTENSION ) && is_file( $file( $I['in-content.jpg'] ) ) );
t( 'originals retained (retention)', is_file( dirname( $file( $I['in-content.jpg'] ) ) . '/in-content.jpg' ) );
$st = run_job( 'revert' );
t( 'revert done', 'done' === $st['status'] );
$c = get_post_field( 'post_content', $p1 );
t( 'content back to jpg', false !== strpos( $c, 'in-content.jpg' ) && false === strpos( $c, 'in-content.webp' ) );
t( 'mime back + webp removed', 'image/jpeg' === get_post_mime_type( $I['in-content.jpg'] ) && ! is_file( dirname( $file( $I['in-content.jpg'] ) ) . '/in-content.webp' ) );
MSW_Util::save( array( 'webp_retention_days' => 0 ) );
$st = run_job( 'convert', array( 'scope' => 'used', 'retry' => true ) );
t( 'convert again (retention 0)', 'done' === $st['status'] );
t( 'originals deleted immediately', ! is_file( dirname( $file( $I['in-content.jpg'] ) ) . '/in-content.jpg' ) && is_file( $file( $I['in-content.jpg'] ) ) );
t( 'redirect old jpg -> webp', (bool) MSW_Redirects::resolve( wp_parse_url( MSW_Util::url( '2026/10/in-content.jpg' ), PHP_URL_PATH ) ) );

echo "== optimize\n";
$before = filesize( $file( $I['huge.jpg'] ) );
$st = run_job( 'optimize', array( 'max_px' => 1600, 'drop_original' => true ) );
$m = wp_get_attachment_metadata( $I['huge.jpg'] );
t( 'optimize resized + dropped original', 'done' === $st['status'] && $m['width'] <= 1600 && empty( $m['original_image'] ) && filesize( $file( $I['huge.jpg'] ) ) < $before );

echo "== rename + redirect\n";
$old = get_post_meta( $I['IMG_2045.jpg'], '_wp_attached_file', true );
$st  = run_job( 'rename', array( 'items' => array( $I['IMG_2045.jpg'] => 'Eiffel Tower at Night' ) ) );
$new = get_post_meta( $I['IMG_2045.jpg'], '_wp_attached_file', true );
t( 'rename done', 'done' === $st['status'] && 'eiffel-tower-at-night' === pathinfo( $new, PATHINFO_FILENAME ) && is_file( MSW_Util::abs( $new ) ) && ! is_file( MSW_Util::abs( $old ) ) );
t( 'content rewritten after rename', false !== strpos( get_post_field( 'post_content', $p6 ), 'eiffel-tower-at-night' ) && false === strpos( get_post_field( 'post_content', $p6 ), 'IMG_2045' ) );
$to = MSW_Redirects::resolve( wp_parse_url( MSW_Util::url( $old ), PHP_URL_PATH ) );
t( '301 target resolves', $to && false !== strpos( $to, 'eiffel-tower-at-night.' ) );
t( 'unknown path no redirect', null === MSW_Redirects::resolve( '/nothing/here.jpg' ) );

echo "== merge duplicates\n";
$st = run_job( 'scan', array( 'duplicates' => true ) );
$keep = $I['dup-keep.jpg'];
$st   = run_job( 'merge', array( 'pairs' => array( array( 'd' => $I['dup-url.jpg'], 'k' => $keep ), array( 'd' => $I['dup-byid.jpg'], 'k' => $keep ) ) ) );
t( 'merge: 1 merged, 1 refused', 'done' === $st['status'] && 1 === $st['counts']['merged'] && 1 === $st['counts']['failed'] );
t( 'content points to keeper', false === strpos( get_post_field( 'post_content', $p4 ), 'dup-url' ) );
t( 'url-only dup quarantined, id-ref dup untouched', 'msw_quarantine' === get_post( $I['dup-url.jpg'] )->post_status && 'inherit' === get_post( $I['dup-byid.jpg'] )->post_status );

echo "== seo / queries / settings\n";
$s = MSW_Query::seo( array( 'status' => 'any' ) );
t( 'seo list works', $s['total'] >= 1 );
t( 'bad_name heuristic', MSW_Renamer::bad_name( 'IMG_2045.jpg' ) && ! MSW_Renamer::bad_name( 'eiffel-tower.jpg' ) );
t( 'report builds', ! empty( MSW_Query::report()['freed_total'] ) );
t( 'dashboard builds', isset( MSW_Query::dashboard()['scan']['total'] ) );
$sv = MSW_Util::save( array( 'webp_jpeg_quality' => 5, 'auto_scan' => 'bogus' ) );
t( 'settings sanitised', 30 === $sv['webp_jpeg_quality'] && 'off' === $sv['auto_scan'] );
t( 'no DB errors', '' === $wpdb->last_error );

echo "== on-upload webp\n";
MSW_Util::save( array( 'webp_on_upload' => 1 ) );
$id = imp( 'upload-test.jpg', 77, 0, 1200, 800 );
t( 'upload auto-converted', 'image/webp' === get_post_mime_type( $id ) && is_file( $file( $id ) ) );

echo $GLOBALS['msw_fail'] ? "\n{$GLOBALS['msw_fail']} FAILED\n" : "\nALL PASSED\n";
exit( $GLOBALS['msw_fail'] ? 1 : 0 );
