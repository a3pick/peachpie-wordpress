<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Space savers that never change a file name (so no reference needs rewriting):
 *  - downscale oversized originals in place;
 *  - remove the huge "original_image" WordPress keeps next to "-scaled" files.
 */
class MSW_Optimizer {

	/**
	 * @return array|false [ last_id, stats ] or false when finished.
	 */
	public static function batch( $after, array $o, $limit = 6 ) {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'
				 AND post_mime_type IN ('image/jpeg','image/png','image/webp') AND ID > %d ORDER BY ID ASC LIMIT %d",
				$after,
				$limit
			)
		);
		if ( ! $ids ) {
			return false;
		}
		update_meta_cache( 'post', $ids );
		$st = array( 'resized' => 0, 'dropped' => 0, 'saved' => 0, 'skipped' => 0 );
		foreach ( $ids as $id ) {
			try {
				$r = self::one( (int) $id, $o );
			} catch ( \Throwable $e ) {
				MSW_Util::log( "بهینه‌سازی #$id: " . $e->getMessage(), 'warn' );
				continue;
			}
			foreach ( $r as $k => $v ) {
				$st[ $k ] += $v;
			}
		}
		return array( (int) end( $ids ), $st );
	}

	private static function one( $id, array $o ) {
		$out  = array( 'resized' => 0, 'dropped' => 0, 'saved' => 0, 'skipped' => 0 );
		$rel  = (string) get_post_meta( $id, '_wp_attached_file', true );
		$meta = get_post_meta( $id, '_wp_attachment_metadata', true );
		if ( '' === $rel || ! is_array( $meta ) || ! MSW_Util::safe_rel( $rel ) || get_post_meta( $id, 'amazonS3_info', true ) ) {
			return $out;
		}
		$dir   = ( false === strpos( $rel, '/' ) ) ? '' : dirname( $rel ) . '/';
		$abs   = MSW_Util::abs( $rel );
		$dirty = false;

		// 1) Drop the pre-"scaled" original.
		if ( ! empty( $o['drop_original'] ) && ! empty( $meta['original_image'] ) && preg_match( '~-scaled\.[a-z]+$~i', $rel ) ) {
			$orig = MSW_Util::abs( $dir . $meta['original_image'] );
			if ( is_file( $abs ) && is_file( $orig ) && $orig !== $abs ) {
				$sz = (int) filesize( $orig );
				if ( @unlink( $orig ) ) {
					unset( $meta['original_image'] );
					$out['dropped']++;
					$out['saved'] += $sz;
					$dirty = true;
				}
			}
		}

		// 2) Downscale the main file.
		$max = (int) $o['max_px'];
		if ( $max > 0 && is_file( $abs ) ) {
			$info = @getimagesize( $abs );
			if ( $info && max( $info[0], $info[1] ) > $max && ! self::animated( $abs ) ) {
				$mime   = $info['mime'];
				$ext    = strtolower( pathinfo( $abs, PATHINFO_EXTENSION ) );
				$tmp    = dirname( $abs ) . '/.msw-opt-' . substr( md5( $abs . microtime() ), 0, 8 ) . '.' . $ext;
				$editor = wp_get_image_editor( $abs );
				$ok     = false;
				if ( ! is_wp_error( $editor ) ) {
					if ( method_exists( $editor, 'maybe_exif_rotate' ) ) {
						$editor->maybe_exif_rotate();
					}
					$editor->set_quality( (int) $o['quality'] );
					if ( ! is_wp_error( $editor->resize( $max, $max, false ) ) ) {
						$saved = $editor->save( $tmp, $mime );
						$ok    = ! is_wp_error( $saved ) && is_file( $tmp );
					}
				}
				$new = $ok ? @getimagesize( $tmp ) : false;
				if ( $new && max( $new[0], $new[1] ) <= $max && filesize( $tmp ) < filesize( $abs ) ) {
					$before = (int) filesize( $abs );
					$after  = (int) filesize( $tmp );
					if ( @rename( $tmp, $abs ) ) {
						$meta['width']    = (int) $new[0];
						$meta['height']   = (int) $new[1];
						$meta['filesize'] = $after;
						$out['resized']++;
						$out['saved'] += max( 0, $before - $after );
						$dirty         = true;
					}
				}
				if ( is_file( $tmp ) ) {
					@unlink( $tmp );
				}
				if ( ! $dirty ) {
					$out['skipped']++;
				}
			}
		}
		if ( $dirty ) {
			update_post_meta( $id, '_wp_attachment_metadata', $meta );
			clean_post_cache( $id );
		}
		return $out;
	}

	private static function animated( $path ) {
		$head = (string) @file_get_contents( $path, false, null, 0, 65536 );
		return false !== strpos( $head, 'acTL' ) || ( 0 === strpos( $head, 'RIFF' ) && false !== strpos( $head, 'ANIM' ) );
	}
}
