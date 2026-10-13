<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-side helpers for the admin screens.
 */
class MSW_Query {

	/* ------------------------------------------------------------ helpers */

	public static function describe_src( $src ) {
		$weak = false;
		if ( 0 === strpos( $src, '~' ) ) {
			$weak = true;
			$src  = substr( $src, 1 );
		}
		$label = $src;
		$link  = '';
		$p     = explode( ':', $src );
		switch ( $p[0] ) {
			case 'post':
				$t     = get_the_title( (int) $p[1] );
				$label = 'محتوای «' . ( $t ? wp_strip_all_tags( $t ) : '#' . $p[1] ) . '»';
				$link  = get_edit_post_link( (int) $p[1], 'raw' );
				break;
			case 'pmeta':
				$t     = get_the_title( (int) $p[1] );
				$label = 'متا «' . ( isset( $p[2] ) ? $p[2] : '' ) . '» در «' . ( $t ? wp_strip_all_tags( $t ) : '#' . $p[1] ) . '»';
				$link  = get_edit_post_link( (int) $p[1], 'raw' );
				break;
			case 'opt':
				$label = 'تنظیم/ویجت: ' . ( isset( $p[1] ) ? $p[1] : '' );
				break;
			case 'tmeta':
				$label = 'دسته/برچسب #' . $p[1];
				$link  = get_edit_term_link( (int) $p[1] );
				break;
			case 'umeta':
				$label = 'کاربر #' . $p[1];
				$link  = get_edit_user_link( (int) $p[1] );
				break;
			case 'file':
				$label = 'فایل قالب/افزونه: ' . ( isset( $p[1] ) ? $p[1] : '' );
				break;
			case 'parent':
				$label = 'پیوست‌شده به «' . wp_strip_all_tags( get_the_title( (int) $p[1] ) ) . '»';
				$link  = get_edit_post_link( (int) $p[1], 'raw' );
				break;
			case 'poster':
				$label = 'پوستر ویدئو/صدای #' . $p[1];
				break;
			default:
				if ( 0 === strpos( $src, 'tbl:' ) ) {
					$label = 'جدول افزونه: ' . preg_replace( '~#.*$~', '', substr( $src, 4 ) );
				}
		}
		return array(
			'label' => $label . ( $weak ? ' (تطبیق ضعیف بر اساس نام فایل)' : '' ),
			'link'  => $link ? $link : '',
			'weak'  => $weak,
		);
	}

	private static function media_item( $r ) {
		$id    = (int) $r->attachment_id;
		$title = isset( $r->post_title ) ? $r->post_title : get_the_title( $id );
		$url   = $r->rel_path ? MSW_Util::url( $r->rel_path ) : '';
		$thumb = '';
		if ( 'image' === $r->type ) {
			$thumb = wp_get_attachment_image_url( $id, 'medium' );
			if ( ! $thumb ) {
				$thumb = $url;
			}
		}
		$used = $r->used_in ? self::describe_src( $r->used_in ) : null;
		$item = array(
			'id'       => $id,
			'title'    => $title,
			'file'     => wp_basename( $r->rel_path ),
			'rel'      => $r->rel_path,
			'url'      => $url,
			'thumb'    => $thumb,
			'type'     => $r->type,
			'mime'     => $r->mime,
			'bytes'    => (int) $r->bytes,
			'size'     => MSW_Util::size( $r->bytes ),
			'w'        => (int) $r->width,
			'h'        => (int) $r->height,
			'date'     => $r->uploaded ? mysql2date( 'Y/m/d', $r->uploaded ) : '',
			'status'   => $r->status,
			'locked'   => $r->locked,
			'missing'  => (int) $r->missing,
			'used_in'  => $used,
			'edit'     => get_edit_post_link( $id, 'raw' ),
			'parent'   => null,
		);
		if ( (int) $r->parent_id ) {
			$item['parent'] = array(
				'id'    => (int) $r->parent_id,
				'title' => wp_strip_all_tags( get_the_title( (int) $r->parent_id ) ),
				'link'  => get_edit_post_link( (int) $r->parent_id, 'raw' ),
			);
		}
		return $item;
	}

	/* --------------------------------------------------------- media lists */

	private static function media_where( array $a, array &$args ) {
		global $wpdb;
		$w      = array();
		$status = isset( $a['status'] ) ? $a['status'] : 'unused';
		if ( in_array( $status, array( 'unused', 'used', 'skipped' ), true ) ) {
			$w[]    = 'm.status = %s';
			$args[] = $status;
		}
		$locked = isset( $a['locked'] ) ? $a['locked'] : '';
		if ( 'any' !== $locked ) {
			if ( in_array( $locked, array( 'protected', 'recent' ), true ) ) {
				$w[]    = 'm.locked = %s';
				$args[] = $locked;
			} elseif ( 'unused' === $status ) {
				$w[] = "m.locked = ''";
			}
		}
		if ( ! empty( $a['type'] ) && in_array( $a['type'], array( 'image', 'video', 'audio', 'doc' ), true ) ) {
			$w[]    = 'm.type = %s';
			$args[] = $a['type'];
		}
		if ( ! empty( $a['missing'] ) ) {
			$w[] = 'm.missing = 1';
		}
		if ( ! empty( $a['q'] ) ) {
			$like   = '%' . $wpdb->esc_like( $a['q'] ) . '%';
			$w[]    = '(m.rel_path LIKE %s OR p.post_title LIKE %s)';
			$args[] = $like;
			$args[] = $like;
		}
		if ( isset( $a['min_mb'] ) && '' !== $a['min_mb'] && (float) $a['min_mb'] > 0 ) {
			$w[]    = 'm.bytes >= %d';
			$args[] = (int) ( (float) $a['min_mb'] * MB_IN_BYTES );
		}
		if ( isset( $a['max_mb'] ) && '' !== $a['max_mb'] && (float) $a['max_mb'] > 0 ) {
			$w[]    = 'm.bytes <= %d';
			$args[] = (int) ( (float) $a['max_mb'] * MB_IN_BYTES );
		}
		if ( ! empty( $a['before'] ) && preg_match( '~^\d{4}-\d{2}-\d{2}$~', $a['before'] ) ) {
			$w[]    = 'm.uploaded < %s';
			$args[] = $a['before'] . ' 00:00:00';
		}
		if ( ! empty( $a['year'] ) && preg_match( '~^\d{4}$~', $a['year'] ) ) {
			$w[]    = 'm.rel_path LIKE %s';
			$args[] = $a['year'] . '/%';
		}
		return $w ? ' WHERE ' . implode( ' AND ', $w ) : '';
	}

	private static function order( array $a ) {
		$map = array(
			'bytes'    => 'm.bytes',
			'uploaded' => 'm.uploaded',
			'name'     => 'm.rel_path',
			'id'       => 'm.attachment_id',
		);
		$by  = isset( $a['orderby'], $map[ $a['orderby'] ] ) ? $map[ $a['orderby'] ] : 'm.bytes';
		$dir = ( isset( $a['order'] ) && 'asc' === strtolower( $a['order'] ) ) ? 'ASC' : 'DESC';
		return " ORDER BY $by $dir, m.attachment_id DESC";
	}

	public static function media( array $a ) {
		global $wpdb;
		$m    = MSW_Util::t( 'media' );
		$args = array();
		$w    = self::media_where( $a, $args );
		$per  = isset( $a['per_page'] ) ? max( 12, min( 200, (int) $a['per_page'] ) ) : 48;
		$page = isset( $a['page'] ) ? max( 1, (int) $a['page'] ) : 1;
		$join = " FROM $m m LEFT JOIN {$wpdb->posts} p ON p.ID = m.attachment_id";

		$sql_tot = "SELECT COUNT(*) c, COALESCE(SUM(m.bytes),0) b $join$w";
		$tot     = $args ? $wpdb->get_row( $wpdb->prepare( $sql_tot, $args ), ARRAY_A ) : $wpdb->get_row( $sql_tot, ARRAY_A ); // phpcs:ignore

		$sql  = "SELECT m.*, p.post_title $join$w" . self::order( $a ) . ' LIMIT %d OFFSET %d';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $args, array( $per, ( $page - 1 ) * $per ) ) ) ); // phpcs:ignore
		if ( $rows ) {
			update_meta_cache( 'post', wp_list_pluck( $rows, 'attachment_id' ) );
		}
		return array(
			'items'       => array_map( array( __CLASS__, 'media_item' ), (array) $rows ),
			'total'       => (int) $tot['c'],
			'total_bytes' => (int) $tot['b'],
			'total_size'  => MSW_Util::size( $tot['b'] ),
			'pages'       => max( 1, (int) ceil( $tot['c'] / $per ) ),
			'page'        => $page,
		);
	}

	/** All IDs matching a filter (for "select all N results"). */
	public static function media_ids( array $a, $max = 50000 ) {
		global $wpdb;
		$m    = MSW_Util::t( 'media' );
		$args = array();
		$w    = self::media_where( $a, $args );
		$sql  = "SELECT m.attachment_id FROM $m m LEFT JOIN {$wpdb->posts} p ON p.ID = m.attachment_id$w ORDER BY m.attachment_id LIMIT " . (int) $max;
		$ids  = $args ? $wpdb->get_col( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_col( $sql ); // phpcs:ignore
		return array_map( 'intval', (array) $ids );
	}

	public static function years() {
		global $wpdb;
		$m = MSW_Util::t( 'media' );
		return array_map(
			'strval',
			(array) $wpdb->get_col( "SELECT DISTINCT SUBSTRING(rel_path,1,4) y FROM $m WHERE rel_path REGEXP '^[0-9]{4}/' ORDER BY y DESC" ) // phpcs:ignore
		);
	}

	/* ---------------------------------------------------------- duplicates */

	public static function duplicates( array $a ) {
		global $wpdb;
		$m    = MSW_Util::t( 'media' );
		$per  = 10;
		$page = isset( $a['page'] ) ? max( 1, (int) $a['page'] ) : 1;
		$zero = str_repeat( '0', 32 );
		$base = "FROM $m WHERE file_hash IS NOT NULL AND file_hash <> '$zero' AND status <> 'skipped' AND missing = 0";
		$tot  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM (SELECT file_hash $base GROUP BY file_hash HAVING COUNT(*) > 1) x" ); // phpcs:ignore
		$wasted = (int) $wpdb->get_var( "SELECT COALESCE(SUM(b),0) FROM (SELECT SUM(bytes) - MAX(bytes) b $base GROUP BY file_hash HAVING COUNT(*) > 1) x" ); // phpcs:ignore
		$groups = $wpdb->get_results( $wpdb->prepare( "SELECT file_hash, COUNT(*) c, SUM(bytes) b $base GROUP BY file_hash HAVING c > 1 ORDER BY b DESC LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ) ); // phpcs:ignore
		$out = array();
		foreach ( (array) $groups as $g ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT m.*, p.post_title FROM $m m LEFT JOIN {$wpdb->posts} p ON p.ID = m.attachment_id WHERE m.file_hash = %s ORDER BY m.status DESC, m.attachment_id", $g->file_hash ) ); // phpcs:ignore
			$out[] = array(
				'hash'  => $g->file_hash,
				'count' => (int) $g->c,
				'size'  => MSW_Util::size( $g->b ),
				'items' => array_map( array( __CLASS__, 'media_item' ), (array) $rows ),
			);
		}
		return array(
			'groups'      => $out,
			'total'       => $tot,
			'pages'       => max( 1, (int) ceil( $tot / $per ) ),
			'page'        => $page,
			'wasted'      => $wasted,
			'wasted_size' => MSW_Util::size( $wasted ),
		);
	}

	/* ------------------------------------------------------------- orphans */

	public static function orphans( array $a ) {
		global $wpdb;
		$t      = MSW_Util::t( 'orphans' );
		$status = ( isset( $a['status'] ) && 'quarantined' === $a['status'] ) ? 'quarantined' : 'orphan';
		$args   = array( $status );
		$w      = 'status = %s';
		if ( ! empty( $a['q'] ) ) {
			$w     .= ' AND rel_path LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $a['q'] ) . '%';
		}
		$per  = isset( $a['per_page'] ) ? max( 12, min( 200, (int) $a['per_page'] ) ) : 48;
		$page = isset( $a['page'] ) ? max( 1, (int) $a['page'] ) : 1;
		$map  = array( 'bytes' => 'bytes', 'mtime' => 'mtime', 'name' => 'rel_path' );
		$by   = isset( $a['orderby'], $map[ $a['orderby'] ] ) ? $map[ $a['orderby'] ] : 'bytes';
		$dir  = ( isset( $a['order'] ) && 'asc' === strtolower( $a['order'] ) ) ? 'ASC' : 'DESC';
		$tot  = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) c, COALESCE(SUM(bytes),0) b FROM $t WHERE $w", $args ), ARRAY_A ); // phpcs:ignore
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $w ORDER BY $by $dir LIMIT %d OFFSET %d", array_merge( $args, array( $per, ( $page - 1 ) * $per ) ) ) ); // phpcs:ignore
		$items = array();
		foreach ( (array) $rows as $r ) {
			$ext   = strtolower( pathinfo( $r->rel_path, PATHINFO_EXTENSION ) );
			$type  = in_array( $ext, array( 'mp4', 'm4v', 'webm', 'mov', 'ogv', 'avi', 'mkv', 'wmv', 'flv', '3gp' ), true ) ? 'video' : ( in_array( $ext, array( 'mp3', 'wav', 'ogg', 'oga', 'm4a', 'flac', 'aac', 'wma' ), true ) ? 'audio' : ( in_array( $ext, array( 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip' ), true ) ? 'doc' : 'image' ) );
			$items[] = array(
				'hash'  => $r->path_hash,
				'rel'   => $r->rel_path,
				'file'  => wp_basename( $r->rel_path ),
				'url'   => 'orphan' === $status ? MSW_Util::url( $r->rel_path ) : '',
				'thumb' => ( 'image' === $type && 'orphan' === $status ) ? MSW_Util::url( $r->rel_path ) : '',
				'type'  => $type,
				'bytes' => (int) $r->bytes,
				'size'  => MSW_Util::size( $r->bytes ),
				'date'  => $r->mtime ? wp_date( 'Y/m/d', (int) $r->mtime ) : '',
			);
		}
		return array(
			'items'       => $items,
			'total'       => (int) $tot['c'],
			'total_bytes' => (int) $tot['b'],
			'total_size'  => MSW_Util::size( $tot['b'] ),
			'pages'       => max( 1, (int) ceil( $tot['c'] / $per ) ),
			'page'        => $page,
		);
	}

	public static function orphan_ids( array $a ) {
		global $wpdb;
		$t      = MSW_Util::t( 'orphans' );
		$status = ( isset( $a['status'] ) && 'quarantined' === $a['status'] ) ? 'quarantined' : 'orphan';
		$args   = array( $status );
		$w      = 'status = %s';
		if ( ! empty( $a['q'] ) ) {
			$w     .= ' AND rel_path LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $a['q'] ) . '%';
		}
		return (array) $wpdb->get_col( $wpdb->prepare( "SELECT path_hash FROM $t WHERE $w LIMIT 50000", $args ) ); // phpcs:ignore
	}

	/* ---------------------------------------------------------- quarantine */

	public static function quarantine( array $a ) {
		global $wpdb;
		$per   = 48;
		$page  = isset( $a['page'] ) ? max( 1, (int) $a['page'] ) : 1;
		$days  = (int) MSW_Util::get( 'quarantine_days' );
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status=%s", MSW_Quarantine::STATUS ) );
		$bytes = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CAST(m.meta_value AS UNSIGNED)),0) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_msw_q_bytes' WHERE p.post_status=%s", MSW_Quarantine::STATUS ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_mime_type FROM {$wpdb->posts} p WHERE p.post_type='attachment' AND p.post_status=%s ORDER BY p.ID DESC LIMIT %d OFFSET %d",
				MSW_Quarantine::STATUS,
				$per,
				( $page - 1 ) * $per
			)
		);
		if ( $rows ) {
			update_meta_cache( 'post', wp_list_pluck( $rows, 'ID' ) );
		}
		$items = array();
		foreach ( (array) $rows as $r ) {
			$t     = (int) get_post_meta( $r->ID, '_msw_q_time', true );
			$files = (array) get_post_meta( $r->ID, '_msw_q_files', true );
			$items[] = array(
				'id'        => (int) $r->ID,
				'title'     => $r->post_title,
				'file'      => $files ? wp_basename( $files[0] ) : '',
				'type'      => MSW_Scanner::type_of( $r->post_mime_type ),
				'size'      => MSW_Util::size( (int) get_post_meta( $r->ID, '_msw_q_bytes', true ) ),
				'date'      => $t ? wp_date( 'Y/m/d H:i', $t ) : '',
				'days_left' => ( $days > 0 && $t ) ? max( 0, (int) ceil( ( $t + $days * DAY_IN_SECONDS - time() ) / DAY_IN_SECONDS ) ) : null,
			);
		}
		return array(
			'items'       => $items,
			'total'       => $total,
			'total_bytes' => $bytes,
			'total_size'  => MSW_Util::size( $bytes ),
			'pages'       => max( 1, (int) ceil( $total / $per ) ),
			'page'        => $page,
		);
	}

	public static function quarantine_ids() {
		global $wpdb;
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status=%s LIMIT 50000", MSW_Quarantine::STATUS ) ) );
	}

	/* ----------------------------------------------------------- converted */

	public static function converted( array $a ) {
		global $wpdb;
		$c      = MSW_Util::t( 'converted' );
		$status = isset( $a['status'] ) ? $a['status'] : '';
		$w      = '1=1';
		$args   = array();
		if ( in_array( $status, array( 'done', 'deleted', 'skipped', 'failed', 'error', 'converted', 'reverting', 'reverted' ), true ) ) {
			$w     = 'c.status = %s';
			$args[] = $status;
		}
		$per  = 40;
		$page = isset( $a['page'] ) ? max( 1, (int) $a['page'] ) : 1;
		$tot  = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $c c WHERE $w", $args ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM $c c WHERE $w" ) ); // phpcs:ignore
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT c.*, p.post_title FROM $c c LEFT JOIN {$wpdb->posts} p ON p.ID=c.attachment_id WHERE $w ORDER BY c.updated DESC LIMIT %d OFFSET %d", array_merge( $args, array( $per, ( $page - 1 ) * $per ) ) ) ); // phpcs:ignore
		$items = array();
		foreach ( (array) $rows as $r ) {
			$saved = max( 0, (int) $r->bytes_before - (int) $r->bytes_after );
			$items[] = array(
				'id'      => (int) $r->attachment_id,
				'title'   => $r->post_title,
				'status'  => $r->status,
				'reason'  => $r->reason,
				'before'  => MSW_Util::size( $r->bytes_before ),
				'after'   => MSW_Util::size( $r->bytes_after ),
				'saved'   => MSW_Util::size( $saved ),
				'pct'     => $r->bytes_before ? round( $saved / $r->bytes_before * 100 ) : 0,
				'delete_after' => $r->delete_after ? get_date_from_gmt( $r->delete_after, 'Y/m/d' ) : '',
				'edit'    => get_edit_post_link( (int) $r->attachment_id, 'raw' ),
			);
		}
		$sum = $wpdb->get_row( "SELECT SUM(status IN ('done','deleted')) ok, SUM(status='skipped') sk, SUM(status='failed' OR status='error') fl, SUM(status='done') pend, COALESCE(SUM(CASE WHEN status IN ('done','deleted') THEN bytes_before-bytes_after ELSE 0 END),0) saved FROM $c", ARRAY_A ); // phpcs:ignore
		return array(
			'items'   => $items,
			'total'   => $tot,
			'pages'   => max( 1, (int) ceil( $tot / $per ) ),
			'page'    => $page,
			'summary' => array(
				'converted' => (int) $sum['ok'],
				'skipped'   => (int) $sum['sk'],
				'failed'    => (int) $sum['fl'],
				'pending_originals' => (int) $sum['pend'],
				'saved'     => MSW_Util::size( $sum['saved'] ),
			),
		);
	}

	/** Undoable operations: renamed files still held and optimizations with backups. */
	public static function undo_items() {
		global $wpdb;
		$ut   = MSW_Util::t( 'undo' );
		$rows = $wpdb->get_results( "SELECT * FROM $ut WHERE type = 'rename' AND status = 'pending' ORDER BY id DESC LIMIT 100" ); // phpcs:ignore
		$ren  = array();
		foreach ( (array) $rows as $r ) {
			$pay   = json_decode( (string) $r->payload, true );
			$pairs = isset( $pay['pairs'] ) ? (array) $pay['pairs'] : array();
			$old   = (string) key( $pairs );
			$ren[] = array(
				'id'    => (int) $r->id,
				'att'   => (int) $r->att_id,
				'title' => get_the_title( (int) $r->att_id ),
				'old'   => $old,
				'new'   => (string) current( $pairs ),
				'until' => $r->delete_after ? get_date_from_gmt( $r->delete_after, 'Y/m/d' ) : '',
			);
		}
		$opt  = array();
		$bk   = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_msw_opt_bak' ORDER BY meta_id DESC LIMIT 100" );
		$days = (int) MSW_Util::get( 'undo_days' );
		foreach ( (array) $bk as $r ) {
			$b = maybe_unserialize( $r->meta_value );
			if ( ! is_array( $b ) ) {
				continue;
			}
			$opt[] = array( 'att' => (int) $r->post_id, 'title' => get_the_title( (int) $r->post_id ), 'until' => wp_date( 'Y/m/d', $b['time'] + $days * DAY_IN_SECONDS ) );
		}
		return array( 'renames' => $ren, 'optimized' => $opt );
	}

	/* ------------------------------------------------------------------ seo */

	const BAD_NAME_SQL = "m.rel_path REGEXP '(^|/)(img|dsc|dscn|dscf|dji|pxl|mvimg|imgp|image|photo|pic|picture|screenshot|screen-shot|whatsapp-image|untitled|unnamed|download|copy-of)[-_ ]*[0-9(]|(^|/)[0-9_-]+[.][a-z0-9]+$|(^|/)[0-9a-f]{8}-[0-9a-f]{4}-'";

	public static function seo( array $a ) {
		global $wpdb;
		$m     = MSW_Util::t( 'media' );
		$issue = isset( $a['status'] ) ? $a['status'] : 'any';
		$alt   = "COALESCE(am.meta_value,'') = ''";
		$w     = "m.type = 'image' AND m.status = 'used' AND m.missing = 0";
		if ( 'alt' === $issue ) {
			$w .= " AND $alt";
		} elseif ( 'name' === $issue ) {
			$w .= ' AND ' . self::BAD_NAME_SQL;
		} elseif ( 'ok' !== $issue ) {
			$w .= " AND ($alt OR " . self::BAD_NAME_SQL . ')';
		}
		$args = array();
		if ( ! empty( $a['q'] ) ) {
			$w     .= ' AND (m.rel_path LIKE %s OR p.post_title LIKE %s)';
			$like   = '%' . $wpdb->esc_like( $a['q'] ) . '%';
			$args[] = $like;
			$args[] = $like;
		}
		$join = " FROM $m m INNER JOIN {$wpdb->posts} p ON p.ID = m.attachment_id LEFT JOIN {$wpdb->postmeta} am ON am.post_id = m.attachment_id AND am.meta_key = '_wp_attachment_image_alt'";
		$per  = 30;
		$page = isset( $a['page'] ) ? max( 1, (int) $a['page'] ) : 1;
		$sql  = "SELECT COUNT(*) $join WHERE $w";
		$tot  = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_var( $sql ) ); // phpcs:ignore
		$sql  = "SELECT m.attachment_id, m.rel_path, m.used_in, p.post_title, am.meta_value alt $join WHERE $w ORDER BY m.attachment_id DESC LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $args, array( $per, ( $page - 1 ) * $per ) ) ) ); // phpcs:ignore
		$items = array();
		foreach ( (array) $rows as $r ) {
			$bad  = MSW_Renamer::bad_name( $r->rel_path );
			$sug  = MSW_Renamer::sanitize_base( $r->post_title );
			$items[] = array(
				'id'      => (int) $r->attachment_id,
				'rel'     => $r->rel_path,
				'file'    => wp_basename( $r->rel_path ),
				'thumb'   => wp_get_attachment_image_url( (int) $r->attachment_id, 'thumbnail' ),
				'title'   => $r->post_title,
				'alt'     => (string) $r->alt,
				'bad'     => $bad,
				'suggest' => ( $bad && $sug && ! MSW_Renamer::bad_name( $sug ) ) ? $sug : '',
				'used_in' => $r->used_in ? self::describe_src( $r->used_in ) : null,
			);
		}
		return array( 'items' => $items, 'total' => $tot, 'pages' => max( 1, (int) ceil( $tot / $per ) ), 'page' => $page );
	}

	public static function redirects( array $a ) {
		global $wpdb;
		$t    = MSW_Util::t( 'redirects' );
		$per  = 30;
		$page = isset( $a['page'] ) ? max( 1, (int) $a['page'] ) : 1;
		$tot  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ); // phpcs:ignore
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t ORDER BY hits DESC, created DESC LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ) ); // phpcs:ignore
		$items = array();
		foreach ( (array) $rows as $r ) {
			$items[] = array( 'old' => $r->old_rel, 'new' => $r->new_rel, 'hits' => (int) $r->hits, 'date' => $r->created ? get_date_from_gmt( $r->created, 'Y/m/d' ) : '' );
		}
		return array( 'items' => $items, 'total' => $tot, 'pages' => max( 1, (int) ceil( $tot / $per ) ), 'page' => $page );
	}

	/** Numbers for the printable client report. */
	public static function report() {
		global $wpdb;
		$m   = MSW_Util::t( 'media' );
		$st  = (array) get_option( 'msw_stats', array() );
		$ls  = (array) get_option( 'msw_last_scan', array() );
		$h   = (array) get_option( 'msw_history', array() );
		$alt = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $m m LEFT JOIN {$wpdb->postmeta} am ON am.post_id = m.attachment_id AND am.meta_key = '_wp_attachment_image_alt' WHERE m.type = 'image' AND m.status = 'used' AND COALESCE(am.meta_value,'') = ''" ); // phpcs:ignore
		$bad = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $m m WHERE m.type = 'image' AND m.status = 'used' AND " . self::BAD_NAME_SQL ); // phpcs:ignore
		$conv = $wpdb->get_row( 'SELECT COUNT(*) n FROM ' . MSW_Util::t( 'converted' ) . " WHERE status IN ('done','deleted')", ARRAY_A ); // phpcs:ignore
		$red  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . MSW_Util::t( 'redirects' ) ); // phpcs:ignore
		$rows = array(
			'webp'      => (int) ( isset( $st['saved'] ) ? $st['saved'] : 0 ),
			'optimized' => (int) ( isset( $st['optimized'] ) ? $st['optimized'] : 0 ),
			'deleted'   => (int) ( isset( $st['deleted'] ) ? $st['deleted'] : 0 ),
			'orphans'   => (int) ( isset( $st['orphans'] ) ? $st['orphans'] : 0 ),
		);
		$total = array_sum( $rows );
		return array(
			'site'        => home_url(),
			'date'        => wp_date( 'Y/m/d H:i' ),
			'scan_time'   => ! empty( $ls['time'] ) ? wp_date( 'Y/m/d H:i', $ls['time'] ) : '',
			'freed'       => array_map( array( 'MSW_Util', 'size' ), $rows ),
			'freed_total' => MSW_Util::size( $total ),
			'library'     => MSW_Util::size( isset( $ls['total_bytes'] ) ? $ls['total_bytes'] : 0 ),
			'first'       => $h ? MSW_Util::size( $h[0]['total'] ) : '',
			'first_date'  => $h ? wp_date( 'Y/m/d', $h[0]['t'] ) : '',
			'converted'   => (int) $conv['n'],
			'redirects'   => $red,
			'unused'      => isset( $ls['unused'] ) ? (int) $ls['unused'] : 0,
			'unused_size' => MSW_Util::size( isset( $ls['unused_bytes'] ) ? $ls['unused_bytes'] : 0 ),
			'orphans'     => isset( $ls['orphans'] ) ? (int) $ls['orphans'] : 0,
			'broken'      => isset( $ls['broken'] ) ? (int) $ls['broken'] : 0,
			'alt_missing' => $alt,
			'bad_names'   => $bad,
		);
	}

	/* -------------------------------------------------------------- broken */

	public static function broken( array $a ) {
		global $wpdb;
		$t    = MSW_Util::t( 'broken' );
		$per  = 50;
		$page = isset( $a['page'] ) ? max( 1, (int) $a['page'] ) : 1;
		$args = array();
		$w    = '1=1';
		if ( ! empty( $a['q'] ) ) {
			$w     = 'rel_path LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $a['q'] ) . '%';
		}
		$tot  = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE $w", $args ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM $t" ) ); // phpcs:ignore
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $w ORDER BY rel_path LIMIT %d OFFSET %d", array_merge( $args, array( $per, ( $page - 1 ) * $per ) ) ) ); // phpcs:ignore
		$items = array();
		foreach ( (array) $rows as $r ) {
			$d       = self::describe_src( $r->src );
			$items[] = array( 'rel' => $r->rel_path, 'where' => $d['label'], 'link' => $d['link'] );
		}
		return array( 'items' => $items, 'total' => $tot, 'pages' => max( 1, (int) ceil( $tot / $per ) ), 'page' => $page );
	}

	/* ------------------------------------------------------------- dashboard */

	public static function dashboard() {
		global $wpdb;
		$ls = get_option( 'msw_last_scan', array() );
		$q  = self::quarantine( array( 'page' => 1 ) );
		$c  = $wpdb->get_row( 'SELECT SUM(status IN (\'done\',\'deleted\')) n, COALESCE(SUM(CASE WHEN status IN (\'done\',\'deleted\') THEN bytes_before-bytes_after ELSE 0 END),0) saved FROM ' . MSW_Util::t( 'converted' ), ARRAY_A ); // phpcs:ignore
		$free = @disk_free_space( MSW_Util::basedir() );
		$dup  = $wpdb->get_var( "SELECT COUNT(*) FROM " . MSW_Util::t( 'media' ) . " WHERE file_hash IS NOT NULL" ); // phpcs:ignore
		return array(
			'scan'        => array(
				'time'         => ! empty( $ls['time'] ) ? wp_date( 'Y/m/d H:i', $ls['time'] ) : '',
				'ago'          => ! empty( $ls['time'] ) ? human_time_diff( $ls['time'] ) : '',
				'duration'     => isset( $ls['duration'] ) ? (int) $ls['duration'] : 0,
				'total'        => isset( $ls['total'] ) ? (int) $ls['total'] : 0,
				'used'         => isset( $ls['used'] ) ? (int) $ls['used'] : 0,
				'unused'       => isset( $ls['unused'] ) ? (int) $ls['unused'] : 0,
				'unused_size'  => MSW_Util::size( isset( $ls['unused_bytes'] ) ? $ls['unused_bytes'] : 0 ),
				'locked'       => isset( $ls['locked'] ) ? (int) $ls['locked'] : 0,
				'missing'      => isset( $ls['missing'] ) ? (int) $ls['missing'] : 0,
				'broken'       => isset( $ls['broken'] ) ? (int) $ls['broken'] : 0,
				'offloaded'    => isset( $ls['offloaded'] ) ? (int) $ls['offloaded'] : 0,
				'total_size'   => MSW_Util::size( isset( $ls['total_bytes'] ) ? $ls['total_bytes'] : 0 ),
				'orphans'      => isset( $ls['orphans'] ) ? (int) $ls['orphans'] : 0,
				'orphan_size'  => MSW_Util::size( isset( $ls['orphan_bytes'] ) ? $ls['orphan_bytes'] : 0 ),
				'has_dupes'    => (int) $dup > 0,
			),
			'history'     => array_map( function ( $h ) {
				return array( 'd' => wp_date( 'm/d', $h['t'] ), 'total' => (int) $h['total'], 'unused' => (int) $h['unused'], 'orph' => (int) $h['orph'] );
			}, (array) get_option( 'msw_history', array() ) ),
			'quarantine'  => array( 'count' => $q['total'], 'size' => $q['total_size'] ),
			'webp'        => array( 'count' => (int) $c['n'], 'saved' => MSW_Util::size( $c['saved'] ) ),
			'caps'        => MSW_Converter::capabilities(),
			'probe'       => array( 'state' => MSW_Util::quarantine_probe(), 'path' => (string) wp_parse_url( MSW_Util::baseurl(), PHP_URL_PATH ) ),
			'free'        => false === $free ? '' : MSW_Util::size( $free ),
			'job'         => MSW_Job::public_state(),
		);
	}

	/* ---------------------------------------------------------- live usage */

	/**
	 * Direct database search for an attachment's name/ID. Slower than the scan
	 * index but independent of it: use it to double-check before deleting.
	 */
	public static function live_usage( $id ) {
		global $wpdb;
		$rel = (string) get_post_meta( $id, '_wp_attached_file', true );
		if ( '' === $rel ) {
			return array();
		}
		$stem = preg_replace( '~\.[A-Za-z0-9]{2,5}$~', '', wp_basename( $rel ) );
		$stem = preg_replace( '~(-scaled|-\d{1,5}x\d{1,5})+$~', '', $stem );
		$like = '%' . $wpdb->esc_like( $stem ) . '%';
		$out  = array();

		$posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_type, post_status FROM {$wpdb->posts} WHERE post_type NOT IN ('attachment','revision') AND post_status NOT IN ('trash','auto-draft') AND (post_content LIKE %s OR post_content LIKE %s) LIMIT 15", $like, '%wp-image-' . (int) $id . '"%' ) );
		foreach ( (array) $posts as $p ) {
			$out[] = array( 'where' => 'محتوا', 'label' => wp_strip_all_tags( $p->post_title ) . ' (' . $p->post_type . '/' . $p->post_status . ')', 'link' => get_edit_post_link( $p->ID, 'raw' ) );
		}
		$meta = $wpdb->get_results( $wpdb->prepare( "SELECT m.post_id, m.meta_key FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE p.post_type <> 'attachment' AND p.post_status NOT IN ('trash','auto-draft') AND (m.meta_value LIKE %s OR (m.meta_value = %s AND m.meta_key <> '_edit_lock')) LIMIT 15", $like, (string) (int) $id ) );
		foreach ( (array) $meta as $r ) {
			$out[] = array( 'where' => 'متا', 'label' => $r->meta_key . ' ← «' . wp_strip_all_tags( get_the_title( $r->post_id ) ) . '»', 'link' => get_edit_post_link( $r->post_id, 'raw' ) );
		}
		$opt = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name NOT LIKE %s AND option_name NOT LIKE %s AND (option_value LIKE %s OR option_value = %s) LIMIT 15", '%transient%', 'msw%', $like, (string) (int) $id ) );
		foreach ( (array) $opt as $name ) {
			$out[] = array( 'where' => 'تنظیمات', 'label' => $name, 'link' => '' );
		}
		return $out;
	}
}
