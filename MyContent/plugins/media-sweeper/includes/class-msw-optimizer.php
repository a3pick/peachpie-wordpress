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
		if ( '' === $rel || ! is_array( $meta ) || ! MSW_Util::safe_rel( $rel ) || MSW_Util::is_offloaded( $id ) || MSW_Util::shares_file( $id ) ) {
			return $out;
		}
		$dir   = ( false === strpos( $rel, '/' ) ) ? '' : dirname( $rel ) . '/';
		$abs   = MSW_Util::abs( $rel );
		$dirty = false;
		$undo  = (int) MSW_Util::get( 'undo_days' ) > 0;
		$bak   = get_post_meta( $id, '_msw_opt_bak', true );
		$bak   = is_array( $bak ) ? $bak : array( 'time' => time(), 'meta' => $meta, 'files' => array() );
		$bak_changed = false;

		// 1) Drop the pre-"scaled" original.
		if ( ! empty( $o['drop_original'] ) && ! empty( $meta['original_image'] ) && preg_match( '~-scaled\.[a-z]+$~i', $rel ) ) {
			$orig = MSW_Util::abs( $dir . $meta['original_image'] );
			if ( is_file( $abs ) && is_file( $orig ) && $orig !== $abs ) {
				$sz = (int) filesize( $orig );
				if ( $undo && self::backup( $id, $dir . $meta['original_image'], $bak, true ) ) {
					$bak_changed = true;
					$gone        = true;
				} else {
					$gone = ! $undo && @unlink( $orig );
				}
				if ( ! empty( $gone ) ) {
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
					if ( $undo && self::backup( $id, $rel, $bak, false ) ) {
						$bak_changed = true;
					}
					if ( ( ! $undo || isset( $bak['files'][ $rel ] ) ) && @rename( $tmp, $abs ) ) {
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
			if ( $bak_changed ) {
				update_post_meta( $id, '_msw_opt_bak', $bak );
			}
			update_post_meta( $id, '_wp_attachment_metadata', $meta );
			clean_post_cache( $id );
		}
		return $out;
	}

	private static function bak_dir() {
		return MSW_Util::abs( 'msw-backup' );
	}

	/** Move ($move) or copy a file into the backup folder; remembers it in $bak. */
	private static function backup( $id, $rel, array &$bak, $move ) {
		if ( isset( $bak['files'][ $rel ] ) ) {
			return true; // First backup wins: it is the true original.
		}
		$src = MSW_Util::abs( $rel );
		if ( ! is_file( $src ) || ! MSW_Util::protected_dir( self::bak_dir() ) ) {
			return false;
		}
		$tok = strtolower( wp_generate_password( 24, false, false ) ) . '.b';
		$dst = self::bak_dir() . '/' . (int) $id . '/' . $tok;
		if ( ! wp_mkdir_p( dirname( $dst ) ) ) {
			return false;
		}
		$ok = $move ? ( @rename( $src, $dst ) || ( @copy( $src, $dst ) && @unlink( $src ) ) ) : @copy( $src, $dst );
		if ( ! $ok ) {
			return false;
		}
		$bak['files'][ $rel ] = $tok;
		return true;
	}

	/** Restore the files and metadata saved before the optimization. */
	public static function undo( $id ) {
		$bak = get_post_meta( $id, '_msw_opt_bak', true );
		if ( ! is_array( $bak ) || empty( $bak['files'] ) ) {
			return 'پشتیبانی برای این تصویر وجود ندارد.';
		}
		foreach ( $bak['files'] as $rel => $tok ) {
			if ( ! MSW_Util::safe_rel( $rel ) || ! is_file( self::bak_dir() . '/' . (int) $id . '/' . $tok ) ) {
				return 'فایل پشتیبان یافت نشد؛ بازگردانی ممکن نیست.';
			}
		}
		foreach ( $bak['files'] as $rel => $tok ) {
			$src = self::bak_dir() . '/' . (int) $id . '/' . $tok;
			$dst = MSW_Util::abs( $rel );
			$tmp = $dst . '.msw-restore';
			if ( ! wp_mkdir_p( dirname( $dst ) ) || ! @copy( $src, $tmp ) || ! @rename( $tmp, $dst ) ) {
				@unlink( $tmp );
				return 'بازگردانی فایل ناموفق بود: ' . $rel;
			}
		}
		if ( ! empty( $bak['meta'] ) ) {
			update_post_meta( $id, '_wp_attachment_metadata', $bak['meta'] );
		}
		delete_post_meta( $id, '_msw_opt_bak' );
		MSW_Util::rrmdir( self::bak_dir() . '/' . (int) $id );
		clean_post_cache( $id );
		return true;
	}

	/** Delete backups older than the undo period (cron) or all of them ($force). */
	public static function purge_backups( $force = false ) {
		global $wpdb;
		$days = (int) MSW_Util::get( 'undo_days' );
		$rows = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_msw_opt_bak' LIMIT 500" );
		$n    = 0;
		foreach ( (array) $rows as $r ) {
			$bak = maybe_unserialize( $r->meta_value );
			if ( ! $force && is_array( $bak ) && isset( $bak['time'] ) && $bak['time'] + $days * DAY_IN_SECONDS > time() ) {
				continue;
			}
			MSW_Util::rrmdir( self::bak_dir() . '/' . (int) $r->post_id );
			delete_post_meta( (int) $r->post_id, '_msw_opt_bak' );
			$n++;
		}
		return $n;
	}

	private static function animated( $path ) {
		$head = (string) @file_get_contents( $path, false, null, 0, 65536 );
		return false !== strpos( $head, 'acTL' ) || ( 0 === strpos( $head, 'RIFF' ) && false !== strpos( $head, 'ANIM' ) );
	}
}
