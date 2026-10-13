<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEO-friendly file renaming with reference rewriting and 301 redirects.
 * Files are COPIED to the new name; old files are removed only after the
 * database rewrite finished (see MSW_Job "rename").
 */
class MSW_Renamer {

	public static function sanitize_base( $name ) {
		$name = strtolower( trim( (string) $name ) );
		$name = preg_replace( '~\.(jpe?g|png|gif|webp|avif|bmp)$~i', '', $name );
		$name = sanitize_file_name( $name );
		$name = preg_replace( '~[^\p{L}\p{N}\-_]+~u', '-', str_replace( '_', '-', $name ) );
		$name = trim( preg_replace( '~-{2,}~', '-', $name ), '-' );
		return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 80 ) : substr( $name, 0, 80 );
	}

	/** Heuristic: does a file name carry no meaning (camera/phone/default names)? */
	public static function bad_name( $file ) {
		$s = strtolower( pathinfo( $file, PATHINFO_FILENAME ) );
		$s = preg_replace( '~(-scaled|-\d+x\d+)+$~', '', $s );
		return (bool) preg_match( '~^(img|dsc|dscn|dscf|dji|pxl|mvimg|imgp|image|photo|pic|picture|screenshot|screen-shot|whatsapp-image|untitled|unnamed|download|copy-of)[-_ ]*[0-9(]|^[0-9_\-\s]+$|^[0-9a-f]{8}-[0-9a-f]{4}-|^(image|photo|pic|picture|untitled|unnamed|download)$~', $s );
	}

	/**
	 * Work out old => new file names without touching disk or database.
	 *
	 * @return array|string [ 'rel' => main, 'dir' => ..., 'pairs' => [...], 'meta' => array ] or an error message.
	 */
	public static function plan( $id, $base ) {
		global $wpdb;
		$rel  = (string) get_post_meta( $id, '_wp_attached_file', true );
		if ( '' === $rel || ! MSW_Util::safe_rel( $rel ) || ! is_file( MSW_Util::abs( $rel ) ) ) {
			return 'فایل اصلی روی دیسک نیست';
		}
		if ( MSW_Util::is_offloaded( $id ) ) {
			return 'فایل روی ذخیره‌ساز خارجی است';
		}
		if ( MSW_Util::shares_file( $id ) ) {
			return 'پیوست دیگری از همین فایل استفاده می‌کند';
		}
		if ( '' === $base ) {
			return 'نام جدید خالی است';
		}
		$meta = get_post_meta( $id, '_wp_attachment_metadata', true );
		$meta = is_array( $meta ) ? $meta : array();
		$dir  = ( false === strpos( $rel, '/' ) ) ? '' : dirname( $rel ) . '/';
		$ext  = pathinfo( $rel, PATHINFO_EXTENSION );
		$root = preg_replace( '~-scaled$~', '', pathinfo( $rel, PATHINFO_FILENAME ) );
		if ( $root === $base ) {
			return 'نام جدید با نام فعلی یکی است';
		}

		$olds = array( $rel );
		if ( ! empty( $meta['original_image'] ) ) {
			$olds[] = $dir . $meta['original_image'];
		}
		foreach ( (array) ( isset( $meta['sizes'] ) ? $meta['sizes'] : array() ) as $sz ) {
			if ( ! empty( $sz['file'] ) ) {
				$olds[] = $dir . $sz['file'];
			}
		}
		$olds = array_values( array_unique( $olds ) );

		// Find a base name whose targets are all free.
		$try = $base;
		for ( $n = 1; $n < 50; $n++ ) {
			$pairs = array();
			$clash = false;
			foreach ( $olds as $o ) {
				$fn = basename( $o );
				if ( 0 !== strpos( $fn, $root ) ) {
					continue; // Unrelated file name; leave it alone.
				}
				$new = $dir . $try . substr( $fn, strlen( $root ) );
				if ( file_exists( MSW_Util::abs( $new ) ) || strlen( $o ) > 185 ) {
					$clash = true;
					break;
				}
				$pairs[ $o ] = $new;
			}
			if ( ! $clash ) {
				break;
			}
			$try = $base . '-' . ( $n + 1 );
		}
		if ( $clash || empty( $pairs[ $rel ] ) ) {
			return 'نام مقصد آزاد پیدا نشد';
		}

		return array( 'rel' => $rel, 'dir' => $dir, 'pairs' => $pairs, 'meta' => $meta );
	}

	/**
	 * Copy files to new names and update the attachment.
	 *
	 * @param int    $id
	 * @param string $base Desired base name (already sanitised).
	 * @return array|string [ 'old' => [...], 'pairs' => [...] ] or an error message.
	 */
	public static function rename_one( $id, $base ) {
		$pl = self::plan( $id, $base );
		if ( is_string( $pl ) ) {
			return $pl;
		}
		global $wpdb;
		$rel   = $pl['rel'];
		$dir   = $pl['dir'];
		$pairs = $pl['pairs'];
		$meta  = $pl['meta'];
		$copied = array();
		foreach ( $pairs as $o => $new ) {
			$src = MSW_Util::abs( $o );
			if ( ! is_file( $src ) ) {
				unset( $pairs[ $o ] );
				continue;
			}
			$dst = MSW_Util::abs( $new );
			if ( ! @copy( $src, $dst ) || filesize( $dst ) !== filesize( $src ) ) {
				foreach ( $copied as $c ) {
					@unlink( $c );
				}
				@unlink( $dst );
				return 'کپی فایل ناموفق بود (مجوز یا فضا)';
			}
			$copied[] = $dst;
		}

		// ---- database ----
		$new_main = $pairs[ $rel ];
		update_post_meta( $id, '_wp_attached_file', $new_main );
		if ( $meta ) {
			$meta['file'] = $new_main;
			if ( ! empty( $meta['original_image'] ) && isset( $pairs[ $dir . $meta['original_image'] ] ) ) {
				$meta['original_image'] = basename( $pairs[ $dir . $meta['original_image'] ] );
			}
			foreach ( (array) ( isset( $meta['sizes'] ) ? $meta['sizes'] : array() ) as $name => $sz ) {
				if ( ! empty( $sz['file'] ) && isset( $pairs[ $dir . $sz['file'] ] ) ) {
					$meta['sizes'][ $name ]['file'] = basename( $pairs[ $dir . $sz['file'] ] );
				}
			}
			update_post_meta( $id, '_wp_attachment_metadata', $meta );
		}
		$wpdb->update( $wpdb->posts, array( 'guid' => MSW_Util::url( $new_main ) ), array( 'ID' => $id ) );
		clean_post_cache( $id );
		$wpdb->update( MSW_Util::t( 'media' ), array( 'rel_path' => $new_main, 'path_key' => MSW_Extractor::path_key( $new_main ), 'base_key' => MSW_Extractor::base_key( $new_main ) ), array( 'attachment_id' => $id ) );
		$mt = MSW_Util::t( 'map' );
		foreach ( $pairs as $o => $new ) {
			$wpdb->query( $wpdb->prepare( "REPLACE INTO $mt (old_rel,new_rel,att_id) VALUES (%s,%s,%d)", $o, $new, $id ) ); // phpcs:ignore
			MSW_Redirects::add( $o, $new, $id );
		}
		return array( 'old' => array_keys( $pairs ), 'pairs' => $pairs );
	}

	/** Remove the old files (called after the rewrite). Never removes files still in use by the attachment. */
	public static function remove_old( $id, array $old ) {
		$rel  = (string) get_post_meta( $id, '_wp_attached_file', true );
		$meta = get_post_meta( $id, '_wp_attachment_metadata', true );
		$dir  = ( false === strpos( $rel, '/' ) ) ? '' : dirname( $rel ) . '/';
		$keep = array( $rel => true );
		if ( is_array( $meta ) ) {
			if ( ! empty( $meta['original_image'] ) ) {
				$keep[ $dir . $meta['original_image'] ] = true;
			}
			foreach ( (array) ( isset( $meta['sizes'] ) ? $meta['sizes'] : array() ) as $sz ) {
				if ( ! empty( $sz['file'] ) ) {
					$keep[ $dir . $sz['file'] ] = true;
				}
			}
		}
		foreach ( $old as $o ) {
			if ( ! isset( $keep[ $o ] ) && MSW_Util::safe_rel( $o ) && is_file( MSW_Util::abs( $o ) ) ) {
				@unlink( MSW_Util::abs( $o ) );
			}
		}
	}

	/* ------------------------------------------------------------- undo */

	/** Keep the old files for the undo period and journal the rename. */
	public static function keep_old( $id, array $pairs ) {
		global $wpdb;
		$days = (int) MSW_Util::get( 'undo_days' );
		$wpdb->insert(
			MSW_Util::t( 'undo' ),
			array(
				'type'         => 'rename',
				'att_id'       => $id,
				'payload'      => wp_json_encode( array( 'pairs' => $pairs ) ),
				'status'       => 'pending',
				'created'      => gmdate( 'Y-m-d H:i:s' ),
				'delete_after' => gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS ),
			)
		);
		$uid = (int) $wpdb->insert_id;
		foreach ( array_keys( $pairs ) as $old ) {
			$wpdb->query( $wpdb->prepare( 'REPLACE INTO ' . MSW_Util::t( 'hold' ) . ' (path_hash,rel_path,undo_id) VALUES (%s,%s,%d)', md5( $old ), $old, $uid ) ); // phpcs:ignore
		}
	}

	/**
	 * Restore the attachment to its previous file names and queue the reverse
	 * reference rewrite (done by the "undo_rename" job).
	 *
	 * @return int|WP_Error
	 */
	public static function prepare_undo( array $undo_ids = array() ) {
		global $wpdb;
		$ut   = MSW_Util::t( 'undo' );
		$cond = "type = 'rename' AND status = 'pending'";
		if ( $undo_ids ) {
			$cond .= ' AND id IN (' . implode( ',', array_map( 'intval', $undo_ids ) ) . ')';
		}
		$rows = $wpdb->get_results( "SELECT * FROM $ut WHERE $cond" ); // phpcs:ignore
		if ( ! $rows ) {
			return new WP_Error( 'none', 'تغییر نامی برای بازگردانی نیست (یا مهلت نگه‌داری فایل‌های قبلی تمام شده).' );
		}
		MSW_DB::truncate( 'map' );
		$mt = MSW_Util::t( 'map' );
		$n  = 0;
		foreach ( $rows as $r ) {
			$pay   = json_decode( (string) $r->payload, true );
			$pairs = isset( $pay['pairs'] ) ? (array) $pay['pairs'] : array();
			$id    = (int) $r->att_id;
			$cur   = (string) get_post_meta( $id, '_wp_attached_file', true );
			$rev   = array_flip( $pairs ); // new => old.
			if ( ! $pairs || ! isset( $rev[ $cur ] ) ) {
				MSW_Util::log( "بازگردانی نام #$id ممکن نیست: فایل پس از تغییر نام دوباره تغییر کرده است.", 'warn' );
				continue;
			}
			$ok = true;
			foreach ( array_keys( $pairs ) as $old ) {
				$ok = $ok && is_file( MSW_Util::abs( $old ) );
			}
			if ( ! $ok ) {
				MSW_Util::log( "بازگردانی نام #$id ممکن نیست: فایل‌های قبلی روی دیسک نیستند.", 'warn' );
				continue;
			}
			$dir  = ( false === strpos( $cur, '/' ) ) ? '' : dirname( $cur ) . '/';
			$meta = get_post_meta( $id, '_wp_attachment_metadata', true );
			update_post_meta( $id, '_wp_attached_file', $rev[ $cur ] );
			if ( is_array( $meta ) ) {
				$meta['file'] = $rev[ $cur ];
				if ( ! empty( $meta['original_image'] ) && isset( $rev[ $dir . $meta['original_image'] ] ) ) {
					$meta['original_image'] = basename( $rev[ $dir . $meta['original_image'] ] );
				}
				foreach ( (array) ( isset( $meta['sizes'] ) ? $meta['sizes'] : array() ) as $name => $sz ) {
					if ( ! empty( $sz['file'] ) && isset( $rev[ $dir . $sz['file'] ] ) ) {
						$meta['sizes'][ $name ]['file'] = basename( $rev[ $dir . $sz['file'] ] );
					}
				}
				update_post_meta( $id, '_wp_attachment_metadata', $meta );
			}
			$wpdb->update( $wpdb->posts, array( 'guid' => MSW_Util::url( $rev[ $cur ] ) ), array( 'ID' => $id ) );
			clean_post_cache( $id );
			$wpdb->update( MSW_Util::t( 'media' ), array( 'rel_path' => $rev[ $cur ], 'path_key' => MSW_Extractor::path_key( $rev[ $cur ] ), 'base_key' => MSW_Extractor::base_key( $rev[ $cur ] ) ), array( 'attachment_id' => $id ) );
			$rt = MSW_Util::t( 'redirects' );
			foreach ( $pairs as $old => $new ) {
				$wpdb->query( $wpdb->prepare( "REPLACE INTO $mt (old_rel,new_rel,att_id) VALUES (%s,%s,%d)", $new, $old, $id ) ); // phpcs:ignore
				$wpdb->query( $wpdb->prepare( "UPDATE $rt SET new_rel = %s WHERE new_rel = %s", $old, $new ) ); // phpcs:ignore
				$wpdb->delete( $rt, array( 'old_rel' => $old ) );
			}
			$wpdb->update( $ut, array( 'status' => 'undoing' ), array( 'id' => $r->id ) );
			$n++;
		}
		return $n ? $n : new WP_Error( 'none', 'بازگردانی هیچ موردی ممکن نبود.' );
	}

	/** After the reverse rewrite: delete the renamed copies. */
	public static function finalize_undo() {
		global $wpdb;
		$ut   = MSW_Util::t( 'undo' );
		$rows = $wpdb->get_results( "SELECT * FROM $ut WHERE type = 'rename' AND status = 'undoing'" ); // phpcs:ignore
		foreach ( (array) $rows as $r ) {
			$pay = json_decode( (string) $r->payload, true );
			self::remove_old( (int) $r->att_id, array_values( isset( $pay['pairs'] ) ? (array) $pay['pairs'] : array() ) );
			$wpdb->delete( MSW_Util::t( 'hold' ), array( 'undo_id' => $r->id ) );
			$wpdb->update( $ut, array( 'status' => 'undone' ), array( 'id' => $r->id ) );
		}
		return count( (array) $rows );
	}

	/** Remove old files whose undo period ended (cron) or all of them ($force). */
	public static function purge_undo( $force = false ) {
		global $wpdb;
		$ut   = MSW_Util::t( 'undo' );
		$cond = $force ? '' : ' AND delete_after <= UTC_TIMESTAMP()';
		$rows = $wpdb->get_results( "SELECT * FROM $ut WHERE type = 'rename' AND status = 'pending'$cond LIMIT 300" ); // phpcs:ignore
		foreach ( (array) $rows as $r ) {
			$pay = json_decode( (string) $r->payload, true );
			self::remove_old( (int) $r->att_id, array_keys( isset( $pay['pairs'] ) ? (array) $pay['pairs'] : array() ) );
			$wpdb->delete( MSW_Util::t( 'hold' ), array( 'undo_id' => $r->id ) );
			$wpdb->update( $ut, array( 'status' => 'purged' ), array( 'id' => $r->id ) );
		}
		return count( (array) $rows );
	}
}
