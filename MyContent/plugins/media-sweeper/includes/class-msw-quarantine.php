<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reversible "soft delete": files are moved into uploads/msw-quarantine/<id>/
 * and the attachment gets a private post status so it disappears from the
 * library and from every query that asks for 'inherit' attachments.
 */
class MSW_Quarantine {

	const STATUS = 'msw_quarantine';

	public static function register_status() {
		register_post_status(
			self::STATUS,
			array(
				'label'                     => 'قرنطینه Media Sweeper',
				'public'                    => false,
				'internal'                  => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => false,
			)
		);
	}

	public static function dir() {
		return MSW_Util::abs( 'msw-quarantine' );
	}

	/** All files that belong to an attachment (relative to uploads). */
	public static function attachment_files( $id ) {
		$rel = (string) get_post_meta( $id, '_wp_attached_file', true );
		if ( '' === $rel ) {
			return array();
		}
		$dir   = ( false === strpos( $rel, '/' ) ) ? '' : dirname( $rel ) . '/';
		$files = array( $rel );
		$meta  = get_post_meta( $id, '_wp_attachment_metadata', true );
		if ( is_array( $meta ) ) {
			if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $s ) {
					if ( ! empty( $s['file'] ) ) {
						$files[] = $dir . $s['file'];
					}
				}
			}
			if ( ! empty( $meta['original_image'] ) ) {
				$files[] = $dir . $meta['original_image'];
			}
		}
		$backup = get_post_meta( $id, '_wp_attachment_backup_sizes', true );
		if ( is_array( $backup ) ) {
			foreach ( $backup as $b ) {
				if ( ! empty( $b['file'] ) ) {
					$files[] = $dir . $b['file'];
				}
			}
		}
		$out = array();
		foreach ( array_unique( $files ) as $f ) {
			if ( MSW_Util::safe_rel( $f ) ) {
				$out[] = $f;
			}
		}
		return $out;
	}

	private static function move( $from, $to ) {
		if ( ! wp_mkdir_p( dirname( $to ) ) ) {
			return false;
		}
		if ( @rename( $from, $to ) ) {
			return true;
		}
		if ( @copy( $from, $to ) ) {
			if ( @unlink( $from ) ) {
				return true;
			}
			@unlink( $to );
		}
		return false;
	}

	/**
	 * @return true|string true on success, error text otherwise.
	 */
	public static function quarantine( $id ) {
		global $wpdb;
		$post = get_post( $id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return 'پیوست یافت نشد.';
		}
		if ( self::STATUS === $post->post_status ) {
			return true;
		}
		if ( ! MSW_Util::protected_dir( self::dir() ) ) {
			return 'ساخت پوشه قرنطینه ممکن نیست (مجوز نوشتن).';
		}
		$files = self::attachment_files( $id );
		$moved = array();
		$bytes = 0;
		foreach ( $files as $rel ) {
			$src = MSW_Util::abs( $rel );
			if ( ! is_file( $src ) ) {
				continue;
			}
			$dst = self::dir() . '/' . (int) $id . '/' . $rel;
			$sz  = (int) filesize( $src );
			if ( ! self::move( $src, $dst ) ) {
				foreach ( array_reverse( $moved ) as $r ) { // Roll back.
					self::move( self::dir() . '/' . (int) $id . '/' . $r, MSW_Util::abs( $r ) );
				}
				return 'انتقال فایل ناموفق بود: ' . $rel;
			}
			$moved[] = $rel;
			$bytes  += $sz;
		}
		update_post_meta( $id, '_msw_q_files', $moved );
		update_post_meta( $id, '_msw_q_prev_status', $post->post_status );
		update_post_meta( $id, '_msw_q_time', time() );
		update_post_meta( $id, '_msw_q_bytes', $bytes );
		$wpdb->update( $wpdb->posts, array( 'post_status' => self::STATUS ), array( 'ID' => $id ) );
		clean_post_cache( $id );
		$wpdb->delete( MSW_Util::t( 'media' ), array( 'attachment_id' => $id ) );
		foreach ( $moved as $rel ) {
			MSW_Util::prune_empty_dirs( dirname( MSW_Util::abs( $rel ) ), MSW_Util::basedir() );
		}
		return true;
	}

	public static function restore( $id ) {
		global $wpdb;
		$post = get_post( $id );
		if ( ! $post || self::STATUS !== $post->post_status ) {
			return 'این مورد در قرنطینه نیست.';
		}
		$files = (array) get_post_meta( $id, '_msw_q_files', true );
		$done  = array();
		foreach ( $files as $rel ) {
			if ( ! MSW_Util::safe_rel( $rel ) ) {
				continue;
			}
			$src = self::dir() . '/' . (int) $id . '/' . $rel;
			$dst = MSW_Util::abs( $rel );
			if ( ! is_file( $src ) ) {
				continue;
			}
			if ( file_exists( $dst ) ) {
				// Something new took the name; refuse to overwrite.
				foreach ( array_reverse( $done ) as $r ) {
					self::move( MSW_Util::abs( $r ), self::dir() . '/' . (int) $id . '/' . $r );
				}
				return 'فایلی با همین نام وجود دارد: ' . $rel;
			}
			if ( ! self::move( $src, $dst ) ) {
				foreach ( array_reverse( $done ) as $r ) {
					self::move( MSW_Util::abs( $r ), self::dir() . '/' . (int) $id . '/' . $r );
				}
				return 'بازگردانی فایل ناموفق بود: ' . $rel;
			}
			$done[] = $rel;
		}
		$prev = (string) get_post_meta( $id, '_msw_q_prev_status', true );
		$wpdb->update( $wpdb->posts, array( 'post_status' => $prev ? $prev : 'inherit' ), array( 'ID' => $id ) );
		foreach ( array( '_msw_q_files', '_msw_q_prev_status', '_msw_q_time', '_msw_q_bytes' ) as $k ) {
			delete_post_meta( $id, $k );
		}
		clean_post_cache( $id );
		MSW_Util::rrmdir( self::dir() . '/' . (int) $id );
		return true;
	}

	/** Permanently remove a quarantined attachment. */
	public static function purge( $id ) {
		$post = get_post( $id );
		if ( ! $post || self::STATUS !== $post->post_status ) {
			return 'این مورد در قرنطینه نیست.';
		}
		MSW_Util::rrmdir( self::dir() . '/' . (int) $id );
		wp_delete_attachment( $id, true );
		return true;
	}

	/** Permanently delete a live attachment (files + DB). */
	public static function delete_now( $id ) {
		global $wpdb;
		$post = get_post( $id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return 'پیوست یافت نشد.';
		}
		if ( get_post_meta( $id, '_msw_protected', true ) ) {
			return 'این مورد محافظت‌شده است.';
		}
		$ok = wp_delete_attachment( $id, true );
		if ( ! $ok ) {
			return 'حذف انجام نشد.';
		}
		$wpdb->delete( MSW_Util::t( 'media' ), array( 'attachment_id' => $id ) );
		return true;
	}

	public static function restore_all() {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = %s", self::STATUS ) );
		foreach ( (array) $ids as $id ) {
			self::restore( (int) $id );
		}
	}

	/** Cron: purge items older than the retention period. */
	public static function purge_expired() {
		global $wpdb;
		$days = (int) MSW_Util::get( 'quarantine_days' );
		if ( $days <= 0 ) {
			return 0;
		}
		$limit = time() - $days * DAY_IN_SECONDS;
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_msw_q_time'
				 WHERE p.post_status = %s AND CAST(m.meta_value AS UNSIGNED) < %d LIMIT 200",
				self::STATUS,
				$limit
			)
		);
		$n = 0;
		foreach ( (array) $ids as $id ) {
			if ( true === self::purge( (int) $id ) ) {
				$n++;
			}
		}
		if ( $n ) {
			MSW_Util::log( "قرنطینه: $n مورد منقضی‌شده برای همیشه حذف شد." );
		}
		return $n;
	}

	/* ------------------------------------------------------------ orphans */

	public static function orphan_delete( $hash ) {
		global $wpdb;
		$t   = MSW_Util::t( 'orphans' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE path_hash = %s", $hash ) ); // phpcs:ignore
		if ( ! $row || ! MSW_Util::safe_rel( $row->rel_path ) ) {
			return 'فایل یافت نشد.';
		}
		$abs = MSW_Util::abs( $row->rel_path );
		if ( is_file( $abs ) && ! @unlink( $abs ) ) {
			return 'حذف فایل ممکن نیست (مجوز).';
		}
		MSW_Util::prune_empty_dirs( dirname( $abs ), MSW_Util::basedir() );
		$wpdb->delete( $t, array( 'path_hash' => $hash ) );
		return true;
	}

	public static function orphan_quarantine( $hash ) {
		global $wpdb;
		$t   = MSW_Util::t( 'orphans' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE path_hash = %s", $hash ) ); // phpcs:ignore
		if ( ! $row || ! MSW_Util::safe_rel( $row->rel_path ) ) {
			return 'فایل یافت نشد.';
		}
		if ( ! MSW_Util::protected_dir( self::dir() ) ) {
			return 'ساخت پوشه قرنطینه ممکن نیست.';
		}
		$abs = MSW_Util::abs( $row->rel_path );
		$dst = self::dir() . '/_orphans/' . $row->rel_path;
		if ( is_file( $abs ) && ! self::move( $abs, $dst ) ) {
			return 'انتقال فایل ناموفق بود.';
		}
		$wpdb->update( $t, array( 'status' => 'quarantined' ), array( 'path_hash' => $hash ) );
		return true;
	}

	public static function orphan_restore( $hash ) {
		global $wpdb;
		$t   = MSW_Util::t( 'orphans' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE path_hash = %s", $hash ) ); // phpcs:ignore
		if ( ! $row || ! MSW_Util::safe_rel( $row->rel_path ) ) {
			return 'فایل یافت نشد.';
		}
		$src = self::dir() . '/_orphans/' . $row->rel_path;
		$dst = MSW_Util::abs( $row->rel_path );
		if ( file_exists( $dst ) ) {
			return 'فایلی با همین نام وجود دارد.';
		}
		if ( is_file( $src ) && ! self::move( $src, $dst ) ) {
			return 'بازگردانی ناموفق بود.';
		}
		$wpdb->update( $t, array( 'status' => 'orphan' ), array( 'path_hash' => $hash ) );
		return true;
	}

	public static function orphan_purge( $hash ) {
		global $wpdb;
		$t   = MSW_Util::t( 'orphans' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE path_hash = %s", $hash ) ); // phpcs:ignore
		if ( ! $row || ! MSW_Util::safe_rel( $row->rel_path ) ) {
			return 'فایل یافت نشد.';
		}
		$src = self::dir() . '/_orphans/' . $row->rel_path;
		if ( is_file( $src ) ) {
			@unlink( $src );
		}
		$wpdb->delete( $t, array( 'path_hash' => $hash ) );
		return true;
	}
}
