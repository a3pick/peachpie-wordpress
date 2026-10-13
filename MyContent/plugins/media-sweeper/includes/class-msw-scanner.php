<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The scan engine: builds the reference index and the per-attachment verdicts.
 * All methods are batch-oriented; MSW_Job decides when to stop for time.
 */
class MSW_Scanner {

	/** Buffered references: 'i:ID' | 'p:pathkey' | 'b:basekey' => source. */
	private static $buf = array();
	private static $acf = null;

	/* --------------------------------------------------------------- refs */

	private static function add( $ref, $src ) {
		if ( ! isset( self::$buf[ $ref ] ) ) {
			self::$buf[ $ref ] = $src;
		}
	}

	public static function flush() {
		global $wpdb;
		if ( self::$broken ) {
			$vals = array();
			foreach ( self::$broken as $rel => $src ) {
				$vals[] = $wpdb->prepare( '(%s,%s,%s,%s)', md5( $rel ), $rel, MSW_Extractor::path_key( $rel ), substr( $src, 0, 120 ) );
			}
			self::$broken = array();
			foreach ( array_chunk( $vals, 200 ) as $chunk ) {
				$wpdb->query( 'INSERT IGNORE INTO ' . MSW_Util::t( 'broken' ) . ' (rel_hash,rel_path,path_key,src) VALUES ' . implode( ',', $chunk ) ); // phpcs:ignore
			}
		}
		if ( ! self::$buf ) {
			return;
		}
		$vals = array();
		foreach ( self::$buf as $ref => $src ) {
			$vals[] = "('" . esc_sql( $ref ) . "','" . esc_sql( substr( $src, 0, 120 ) ) . "')";
		}
		foreach ( array_chunk( $vals, 300 ) as $chunk ) {
			$wpdb->query( 'INSERT IGNORE INTO ' . MSW_Util::t( 'refs' ) . ' (ref, src) VALUES ' . implode( ',', $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		self::$buf = array();
	}

	/** Scan one value (any string) for references. */
	public static function value( $val, $ctx, $src ) {
		if ( null === $val || '' === $val || ! is_string( $val ) ) {
			return;
		}
		if ( MSW_Extractor::is_id_list( $val ) ) {
			if ( $ctx || MSW_Util::get( 'aggressive_meta' ) ) {
				preg_match_all( '~\d+~', $val, $m );
				foreach ( $m[0] as $n ) {
					self::add( 'i:' . (int) $n, $src );
				}
			}
			return;
		}
		$r = MSW_Extractor::extract( $val, $ctx );
		foreach ( $r['ids'] as $id => $_ ) {
			self::add( 'i:' . $id, $src );
		}
		foreach ( $r['raw'] as $rel => $_ ) {
			self::check_exists( $rel, $src );
		}
		foreach ( $r['paths'] as $k => $_ ) {
			self::add( 'p:' . $k, $src );
		}
		foreach ( $r['bases'] as $k => $_ ) {
			self::add( 'b:' . $k, $src );
		}
	}

	private static $seen = array();
	private static $broken = array();

	/** Remember upload paths that are referenced but missing on disk. */
	private static function check_exists( $rel, $src ) {
		if ( isset( self::$seen[ $rel ] ) || 0 === strpos( $src, 'file:' ) ) {
			return;
		}
		if ( count( self::$seen ) > 50000 ) {
			self::$seen = array();
		}
		self::$seen[ $rel ] = true;
		if ( MSW_Util::safe_rel( $rel ) && ! file_exists( MSW_Util::abs( $rel ) ) ) {
			self::$broken[ $rel ] = $src;
		}
	}

	private static function acf_keys() {
		if ( null === self::$acf ) {
			$k         = get_option( 'msw_acf', array() );
			self::$acf = is_array( $k ) ? array_flip( $k ) : array();
		}
		return self::$acf;
	}

	/** Collect ACF field names ("_name" meta whose value is "field_xxx"). */
	public static function prepare_acf() {
		global $wpdb;
		$names = $wpdb->get_col( "SELECT DISTINCT SUBSTRING(meta_key, 2) FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_%' AND meta_value LIKE 'field\\_%' LIMIT 30000" );
		update_option( 'msw_acf', is_array( $names ) ? $names : array(), false );
		self::$acf = null;
	}

	/** Process one DB row of a stage. */
	public static function row( array $st, array $row ) {
		switch ( $st['kind'] ) {
			case 'posts':
				$src = 'post:' . $row['_pk'];
				foreach ( $st['cols'] as $c => $_ ) {
					self::value( $row[ $c ], false, $src );
				}
				break;

			case 'postmeta':
				$key = $row['meta_key'];
				$ctx = MSW_Extractor::key_qualifies( $key ) || isset( self::acf_keys()[ $key ] );
				self::value( $row['meta_value'], $ctx, 'pmeta:' . $row['post_id'] . ':' . $key );
				break;

			case 'options':
				$name = $row['option_name'];
				self::value( $row['option_value'], MSW_Extractor::key_qualifies( $name ), 'opt:' . $name );
				break;

			case 'meta':
				$key = $row[ $st['keycol'] ];
				$ctx = MSW_Extractor::key_qualifies( $key ) || isset( self::acf_keys()[ $key ] );
				self::value( $row['meta_value'], $ctx, $st['short'] . ':' . $row[ $st['idcol'] ] . ':' . $key );
				break;

			default: // generic + custom tables.
				$src = ( isset( $st['id'] ) && 0 === strpos( $st['id'], 'tbl:' ) ? $st['id'] : 'tbl:' . $st['table'] ) . '#' . $row['_pk'];
				foreach ( $st['cols'] as $c => $mode ) {
					if ( ! isset( $row[ $c ] ) ) {
						continue;
					}
					self::value( (string) $row[ $c ], 'id' === $mode || MSW_Extractor::key_qualifies( $c ), $src );
				}
		}
	}

	/** Theme file. */
	public static function file( $path ) {
		$c = @file_get_contents( $path );
		if ( false === $c ) {
			return;
		}
		$rel = ltrim( str_replace( wp_normalize_path( WP_CONTENT_DIR ), '', $path ), '/' );
		$src = 'file:' . $rel;
		self::value( $c, false, $src );
		// Hard-coded paths cannot be rewritten later, so remember them separately.
		$r = MSW_Extractor::extract( $c );
		foreach ( $r['paths'] as $k => $_ ) {
			self::add( 'f:' . $k, $src );
		}
	}

	/* -------------------------------------------------------- attachments */

	public static function type_of( $mime ) {
		if ( 0 === strpos( $mime, 'image/' ) ) {
			return 'image';
		}
		if ( 0 === strpos( $mime, 'video/' ) ) {
			return 'video';
		}
		if ( 0 === strpos( $mime, 'audio/' ) ) {
			return 'audio';
		}
		return 'doc';
	}

	/**
	 * Index a batch of attachments. @return int|false last ID or false when finished.
	 */
	public static function attachments_batch( $after, $limit = 150 ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_mime_type, post_parent, post_date_gmt FROM {$wpdb->posts}
				 WHERE post_type = 'attachment' AND post_status IN ('inherit','private') AND ID > %d ORDER BY ID ASC LIMIT %d",
				$after,
				$limit
			)
		);
		if ( ! $rows ) {
			return false;
		}
		$ids = wp_list_pluck( $rows, 'ID' );
		update_meta_cache( 'post', $ids );

		$s        = MSW_Util::all();
		$types    = (array) $s['scan_types'];
		$protect  = (int) $s['protect_days'] * DAY_IN_SECONDS;
		$now      = time();
		$vals     = array();
		$pats     = array_filter( array_map( 'trim', preg_split( '~\R+~', (string) $s['protect_patterns'] ) ) );

		foreach ( $rows as $r ) {
			$id   = (int) $r->ID;
			$rel  = (string) get_post_meta( $id, '_wp_attached_file', true );
			$meta = get_post_meta( $id, '_wp_attachment_metadata', true );
			$meta = is_array( $meta ) ? $meta : array();
			$type = self::type_of( (string) $r->post_mime_type );
			$bytes = 0;
			$miss  = 0;
			$off   = MSW_Util::is_offloaded( $id ) ? 1 : 0;
			$w     = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
			$h     = isset( $meta['height'] ) ? (int) $meta['height'] : 0;

			if ( '' === $rel ) {
				$miss = 1;
			} else {
				$dir  = ( false === strpos( $rel, '/' ) ) ? '' : dirname( $rel ) . '/';
				$main = MSW_Util::abs( $rel );
				if ( is_file( $main ) ) {
					$bytes += (int) filesize( $main );
				} else {
					$miss = 1;
				}
				$seen = array( $rel => true );
				if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
					foreach ( $meta['sizes'] as $sz ) {
						if ( empty( $sz['file'] ) || isset( $seen[ $dir . $sz['file'] ] ) ) {
							continue;
						}
						$seen[ $dir . $sz['file'] ] = true;
						$p = MSW_Util::abs( $dir . $sz['file'] );
						if ( is_file( $p ) ) {
							$bytes += (int) filesize( $p );
						}
					}
				}
				if ( ! empty( $meta['original_image'] ) && ! isset( $seen[ $dir . $meta['original_image'] ] ) ) {
					$p = MSW_Util::abs( $dir . $meta['original_image'] );
					if ( is_file( $p ) ) {
						$bytes += (int) filesize( $p );
					}
				}
			}

			$status = in_array( $type, $types, true ) ? 'unused' : 'skipped';
			$locked = '';
			if ( get_post_meta( $id, '_msw_protected', true ) || self::matches_protection( $pats, $rel, (int) $r->post_parent ) ) {
				$locked = 'protected';
			} elseif ( $protect && $r->post_date_gmt && '0000-00-00 00:00:00' !== $r->post_date_gmt && ( $now - strtotime( $r->post_date_gmt . ' UTC' ) ) < $protect ) {
				$locked = 'recent';
			}
			if ( $off ) {
				$miss = 0; // The file may legitimately live on the remote storage only.
			}
			$poster = (int) get_post_meta( $id, '_thumbnail_id', true );

			$vals[] = $wpdb->prepare(
				'(%d,%s,%s,%s,%s,%s,%d,%d,%d,%s,%s,%d,%d,%d,%d,%s)',
				$id,
				$type,
				(string) $r->post_mime_type,
				$rel,
				MSW_Extractor::path_key( $rel ),
				MSW_Extractor::base_key( $rel ),
				$bytes,
				$w,
				$h,
				$status,
				$locked,
				(int) $r->post_parent,
				$poster,
				$miss,
				$off,
				'0000-00-00' === substr( (string) $r->post_date_gmt, 0, 10 ) ? gmdate( 'Y-m-d H:i:s' ) : (string) $r->post_date_gmt
			);
		}
		$wpdb->query(
			'REPLACE INTO ' . MSW_Util::t( 'media' ) . ' (attachment_id,type,mime,rel_path,path_key,base_key,bytes,width,height,status,locked,parent_id,poster_id,missing,offload,uploaded) VALUES ' . implode( ',', $vals ) // phpcs:ignore WordPress.DB.PreparedSQL
		);
		return (int) end( $ids );
	}

	/** Glob / substring / "parent:ID" rules from settings. */
	public static function matches_protection( array $pats, $rel, $parent ) {
		foreach ( $pats as $p ) {
			if ( 0 === stripos( $p, 'parent:' ) ) {
				if ( (int) substr( $p, 7 ) === $parent && $parent > 0 ) {
					return true;
				}
			} elseif ( false !== strpbrk( $p, '*?[' ) ) {
				if ( fnmatch( $p, $rel, FNM_CASEFOLD ) || fnmatch( $p, basename( $rel ), FNM_CASEFOLD ) ) {
					return true;
				}
			} elseif ( '' !== $p && false !== stripos( $rel, $p ) ) {
				return true;
			}
		}
		return false;
	}

	/* -------------------------------------------------------------- verdict */

	/** Turn the reference index into used / unused verdicts (pure SQL, fast). */
	public static function evaluate() {
		global $wpdb;
		$m = MSW_Util::t( 'media' );
		$r = MSW_Util::t( 'refs' );
		$s = MSW_Util::all();

		$wpdb->query( "UPDATE $m m INNER JOIN $r r ON r.ref = CONCAT('i:', m.attachment_id) SET m.status = 'used', m.used_in = r.src WHERE m.status = 'unused'" ); // phpcs:ignore
		$wpdb->query( "UPDATE $m m INNER JOIN $r r ON r.ref = CONCAT('p:', m.path_key) SET m.status = 'used', m.used_in = r.src WHERE m.status = 'unused' AND m.path_key <> ''" ); // phpcs:ignore
		if ( $s['weak_basename'] ) {
			$wpdb->query( "UPDATE $m m INNER JOIN $r r ON r.ref = CONCAT('b:', m.base_key) SET m.status = 'used', m.used_in = CONCAT('~', r.src) WHERE m.status = 'unused' AND m.base_key <> ''" ); // phpcs:ignore
		}
		if ( $s['parent_used'] ) {
			$wpdb->query( "UPDATE $m m INNER JOIN {$wpdb->posts} p ON p.ID = m.parent_id SET m.status = 'used', m.used_in = CONCAT('parent:', p.ID) WHERE m.status = 'unused' AND m.parent_id > 0 AND p.post_status = 'publish' AND p.post_type <> 'revision'" ); // phpcs:ignore
		}
		$wpdb->query( "UPDATE $m m INNER JOIN $r r ON r.ref = CONCAT('f:', m.path_key) SET m.theme_ref = 1 WHERE m.path_key <> ''" ); // phpcs:ignore
		// Poster images follow their video / audio.
		$wpdb->query( "UPDATE $m a INNER JOIN $m v ON v.poster_id = a.attachment_id SET a.status = 'used', a.used_in = CONCAT('poster:', v.attachment_id) WHERE a.status = 'unused' AND v.status = 'used' AND v.poster_id > 0" ); // phpcs:ignore
		// References to files that live on remote storage are not broken.
		$wpdb->query( 'DELETE b FROM ' . MSW_Util::t( 'broken' ) . " b INNER JOIN $m m ON m.path_key = b.path_key WHERE m.offload = 1" ); // phpcs:ignore
		self::summarize();
	}

	public static function summarize() {
		global $wpdb;
		$m   = MSW_Util::t( 'media' );
		$row = $wpdb->get_row(
			"SELECT COUNT(*) total,
			        SUM(status='unused' AND locked='') unused,
			        SUM(IF(status='unused' AND locked='', bytes, 0)) unused_bytes,
			        SUM(status='used') used,
			        SUM(status='unused' AND locked<>'') locked,
			        SUM(missing=1) missing,
			        SUM(offload=1) offloaded,
			        SUM(offload=1) offloaded,
			        SUM(bytes) total_bytes
			 FROM $m", // phpcs:ignore
			ARRAY_A
		);
		$orph = $wpdb->get_row( "SELECT COUNT(*) c, COALESCE(SUM(bytes),0) b FROM " . MSW_Util::t( 'orphans' ) . " WHERE status='orphan'", ARRAY_A ); // phpcs:ignore
		$brk  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . MSW_Util::t( 'broken' ) ); // phpcs:ignore
		$prev = get_option( 'msw_last_scan', array() );
		update_option(
			'msw_last_scan',
			array(
				'time'          => isset( $prev['time'] ) ? $prev['time'] : 0,
				'total'         => (int) $row['total'],
				'unused'        => (int) $row['unused'],
				'unused_bytes'  => (int) $row['unused_bytes'],
				'used'          => (int) $row['used'],
				'locked'        => (int) $row['locked'],
				'missing'       => (int) $row['missing'],
				'offloaded'     => (int) $row['offloaded'],
				'broken'        => $brk,
				'total_bytes'   => (int) $row['total_bytes'],
				'orphans'       => (int) $orph['c'],
				'orphan_bytes'  => (int) $orph['b'],
				'duration'      => isset( $prev['duration'] ) ? $prev['duration'] : 0,
			),
			false
		);
	}

	/* ------------------------------------------------------------- orphans */

	private static function media_exts() {
		$types = (array) MSW_Util::get( 'scan_types' );
		$e     = array();
		if ( in_array( 'image', $types, true ) ) {
			$e = array_merge( $e, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'bmp', 'ico', 'tif', 'tiff', 'heic' ) );
		}
		if ( in_array( 'video', $types, true ) ) {
			$e = array_merge( $e, array( 'mp4', 'm4v', 'webm', 'mov', 'ogv', 'avi', 'mkv', 'wmv', 'flv', '3gp' ) );
		}
		if ( in_array( 'audio', $types, true ) ) {
			$e = array_merge( $e, array( 'mp3', 'wav', 'ogg', 'oga', 'm4a', 'flac', 'aac', 'wma' ) );
		}
		if ( in_array( 'doc', $types, true ) ) {
			$e = array_merge( $e, array( 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip' ) );
		}
		return array_flip( $e );
	}

	/**
	 * Examine one directory (non-recursive). Returns sub-directories to queue.
	 */
	public static function orphan_dir( $abs_dir, $depth ) {
		global $wpdb;
		$base = MSW_Util::basedir();
		$s    = MSW_Util::all();
		$list = @scandir( $abs_dir );
		if ( false === $list ) {
			return array();
		}
		$subdirs = array();
		$files   = array();
		$exts    = self::media_exts();
		foreach ( $list as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			$p = $abs_dir . '/' . $name;
			if ( is_link( $p ) ) {
				continue;
			}
			if ( is_dir( $p ) ) {
				if ( 0 === strpos( $name, 'msw-' ) ) {
					continue;
				}
				if ( 0 === $depth && ! $s['orphan_all_dirs'] && ! preg_match( '~^\d{4}$~', $name ) ) {
					continue;
				}
				if ( 0 === $depth && in_array( $name, array( 'sites', 'cache', 'wc-logs', 'woocommerce_uploads', 'elementor', 'et-cache', 'wpo-plugins-tables-list', 'wp-rocket' ), true ) ) {
					continue;
				}
				$subdirs[] = $p;
				continue;
			}
			$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
			if ( isset( $exts[ $ext ] ) && '.' !== $name[0] ) {
				$files[ $name ] = $p;
			}
		}
		if ( $files ) {
			$keys = array();
			foreach ( $files as $name => $p ) {
				$rel = ltrim( substr( $p, strlen( $base ) ), '/' );
				// Companions created by WebP plugins: foo.jpg.webp / foo.jpg.avif.
				if ( preg_match( '~\.(?:jpe?g|png|gif)\.(?:webp|avif)$~i', $name ) ) {
					continue;
				}
				$keys[ MSW_Extractor::path_key( $rel ) ][] = array( $rel, $p );
			}
			$known = array();
			$held  = array();
			$kl    = array_keys( $keys );
			foreach ( array_chunk( $kl, 400 ) as $chunk ) {
				$in = "'" . implode( "','", array_map( 'esc_sql', $chunk ) ) . "'";
				foreach ( (array) $wpdb->get_col( 'SELECT DISTINCT path_key FROM ' . MSW_Util::t( 'media' ) . " WHERE path_key IN ($in)" ) as $k ) { // phpcs:ignore
					$known[ strtolower( $k ) ] = true;
				}
				$inr = "'p:" . implode( "','p:", array_map( 'esc_sql', $chunk ) ) . "'";
				foreach ( (array) $wpdb->get_col( 'SELECT ref FROM ' . MSW_Util::t( 'refs' ) . " WHERE ref IN ($inr)" ) as $k ) { // phpcs:ignore
					$known[ strtolower( substr( $k, 2 ) ) ] = true;
				}
			}
			$hashes = array();
			foreach ( $keys as $entries ) {
				foreach ( $entries as $e ) {
					$hashes[] = md5( $e[0] );
				}
			}
			foreach ( array_chunk( $hashes, 400 ) as $chunk ) {
				$hin = "'" . implode( "','", array_map( 'esc_sql', $chunk ) ) . "'";
				foreach ( (array) $wpdb->get_col( 'SELECT path_hash FROM ' . MSW_Util::t( 'hold' ) . " WHERE path_hash IN ($hin)" ) as $hh ) { // phpcs:ignore
					$held[ $hh ] = true;
				}
			}
			$vals = array();
			foreach ( $keys as $k => $entries ) {
				if ( isset( $known[ strtolower( $k ) ] ) ) {
					continue;
				}
				foreach ( $entries as $e ) {
					if ( isset( $held[ md5( $e[0] ) ] ) ) {
						continue;
					}
					$vals[] = $wpdb->prepare( '(%s,%s,%d,%d)', md5( $e[0] ), $e[0], (int) @filesize( $e[1] ), (int) @filemtime( $e[1] ) );
				}
			}
			foreach ( array_chunk( $vals, 200 ) as $chunk ) {
				$wpdb->query( 'REPLACE INTO ' . MSW_Util::t( 'orphans' ) . ' (path_hash,rel_path,bytes,mtime) VALUES ' . implode( ',', $chunk ) ); // phpcs:ignore
			}
		}
		return $subdirs;
	}

	/* --------------------------------------------------------------- hashes */

	public static function hash_batch( $limit = 40 ) {
		global $wpdb;
		$t    = MSW_Util::t( 'media' );
		$rows = $wpdb->get_results( "SELECT attachment_id, rel_path, bytes FROM $t WHERE file_hash IS NULL AND missing = 0 AND status <> 'skipped' ORDER BY attachment_id LIMIT " . (int) $limit ); // phpcs:ignore
		if ( ! $rows ) {
			return false;
		}
		foreach ( $rows as $r ) {
			$p    = MSW_Util::abs( $r->rel_path );
			$hash = ( is_file( $p ) && filesize( $p ) <= 100 * MB_IN_BYTES ) ? md5_file( $p ) : str_repeat( '0', 32 );
			$wpdb->update( $t, array( 'file_hash' => $hash ? $hash : str_repeat( '0', 32 ) ), array( 'attachment_id' => $r->attachment_id ) );
		}
		return true;
	}
}
