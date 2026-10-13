<?php
// Creates an image, renames it through the plugin and prints OLD and NEW relative paths (undo period 0 so the old file is gone).
require_once ABSPATH . 'wp-admin/includes/image.php'; require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php';
MSW_Util::save( array( 'undo_days' => 0 ) );
$tmp = sys_get_temp_dir() . '/IMG_9999.jpg'; $im = imagecreatetruecolor( 800, 600 ); for ( $x = 0; $x < 800; $x += 4 ) { imagefilledrectangle( $im, $x, 0, $x + 3, 600, imagecolorallocate( $im, $x % 255, ( $x * 3 ) % 255, ( $x * 7 ) % 255 ) ); } imagejpeg( $im, $tmp, 90 );
$id  = media_handle_sideload( array( 'name' => 'IMG_9999.jpg', 'tmp_name' => $tmp ), 0 );
$old = get_post_meta( $id, '_wp_attached_file', true );
$r   = MSW_Job::start( 'rename', array( 'items' => array( $id => 'http-redirect-test' ) ) );
do { $st = MSW_Job::tick( 10 ); } while ( 'running' === $st['status'] );
echo $old . "\n" . get_post_meta( $id, '_wp_attached_file', true ) . "\n";
