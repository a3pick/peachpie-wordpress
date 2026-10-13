<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JPEG/PNG -> WebP conversion.
 *
 * Safety model:
 *  1. every new file is written to a temp name, verified (type + pixel count)
 *     and only then moved into place; nothing existing is ever overwritten;
 *  2. the database is changed only after ALL files of an attachment exist;
 *  3. old files stay on disk until references were rewritten and verified,
 *     then they are removed immediately or after a retention period.
 */
class MSW_Converter {

	public static function init() {
		add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'on_upload' ), 99, 3 );
	}

	/* --------------------------------------------------------- capability */

	public static function capabilities() {
		$im = false;
		if ( class_exists( 'Imagick' ) ) {
			try {
				$im = (bool) Imagick::queryFormats( 'WEBP' );
			} catch ( \Throwable $e ) {
				$im = false;
			}
		}
		$gd = function_exists( 'imagewebp' ) && function_exists( 'imagecreatefromjpeg' );
		return array(
			'imagick'  => $im,
			'gd'       => $gd,
			'exif'     => function_exists( 'exif_read_data' ),
			'lossless' => $im || ( $gd && defined( 'IMG_WEBP_LOSSLESS' ) ),
			'engine'   => self::engine(),
			'memory'   => (string) ini_get( 'memory_limit' ),
		);
	}

	public static function engine() {
		$pref = MSW_Util::get( 'webp_engine' );
		$cap  = array( 'imagick' => false, 'gd' => false );
		if ( class_exists( 'Imagick' ) ) {
			try {
				$cap['imagick'] = (bool) Imagick::queryFormats( 'WEBP' );
			} catch ( \Throwable $e ) {
				$cap['imagick'] = false;
			}
		}
		$cap['gd'] = function_exists( 'imagewebp' ) && function_exists( 'imagecreatefromjpeg' );
		if ( 'imagick' === $pref && $cap['imagick'] ) {
			return 'imagick';
		}
		if ( 'gd' === $pref && $cap['gd'] ) {
			return 'gd';
		}
		if ( $cap['imagick'] ) {
			return 'imagick';
		}
		return $cap['gd'] ? 'gd' : '';
	}

	public static function opts() {
		$s = MSW_Util::all();
		return array(
			'engine'   => self::engine(),
			'jpeg_q'   => (int) $s['webp_jpeg_quality'],
			'png_mode' => $s['webp_png_mode'],
			'png_q'    => (int) $s['webp_png_quality'],
		);
	}

	/* ----------------------------------------------------- file conversion */

	/**
	 * Convert one JPEG/PNG file to WebP.
	 *
	 * @param array $o { engine, jpeg_q, png_mode, png_q }
	 * @return true|string true on success, otherwise a short error message.
	 */
	public static function convert_file( $src, $dest, array $o ) {
		$info = @getimagesize( $src );
		if ( ! $info || empty( $info[0] ) || empty( $info[1] ) ) {
			return 'تصویر خوانا نیست';
		}
		$type = $info[2];
		if ( IMAGETYPE_JPEG !== $type && IMAGETYPE_PNG !== $type ) {
			return 'فرمت پشتیبانی نمی‌شود';
		}
		if ( IMAGETYPE_PNG === $type && false !== strpos( (string) @file_get_contents( $src, false, null, 0, 4096 ), 'acTL' ) ) {
			return 'PNG متحرک (APNG)';
		}
		if ( $info[0] * $info[1] > 80000000 ) {
			return 'ابعاد بیش از حد بزرگ';
		}
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'image' );
		}
		$need  = (int) ( $info[0] * $info[1] * 6 + 8 * MB_IN_BYTES );
		$limit = function_exists( 'wp_convert_hr_to_bytes' ) ? wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) ) : -1;
		if ( $limit > 0 && $need > ( $limit - memory_get_usage( true ) ) ) {
			return 'حافظه PHP برای این تصویر کافی نیست';
		}

		$lossless = ( IMAGETYPE_PNG === $type && 'lossless' === $o['png_mode'] );
		$quality  = ( IMAGETYPE_PNG === $type ) ? (int) $o['png_q'] : (int) $o['jpeg_q'];

		$r = 'imagick' === $o['engine']
			? self::with_imagick( $src, $dest, $type, $quality, $lossless )
			: self::with_gd( $src, $dest, $info, $type, $quality, $lossless );

		if ( true !== $r ) {
			@unlink( $dest );
			return $r;
		}
		clearstatcache( true, $dest );
		$out = @getimagesize( $dest );
		if ( ! $out || ! defined( 'IMAGETYPE_WEBP' ) || IMAGETYPE_WEBP !== $out[2] || filesize( $dest ) < 20 ) {
			@unlink( $dest );
			return 'خروجی WebP معتبر نیست';
		}
		if ( (int) $out[0] * (int) $out[1] !== (int) $info[0] * (int) $info[1] ) {
			@unlink( $dest );
			return 'ابعاد خروجی با ورودی برابر نیست';
		}
		return true;
	}

	private static function with_imagick( $src, $dest, $type, $quality, $lossless ) {
		try {
			$im = new Imagick();
			$im->readImage( $src );
			if ( $im->getNumberImages() > 1 ) {
				$im->clear();
				return 'تصویر چندفریمی';
			}
			// Bake EXIF orientation into pixels, WebP output carries none.
			if ( IMAGETYPE_JPEG === $type ) {
				switch ( $im->getImageOrientation() ) {
					case Imagick::ORIENTATION_BOTTOMRIGHT:
						$im->rotateImage( new ImagickPixel( 'none' ), 180 );
						break;
					case Imagick::ORIENTATION_RIGHTTOP:
						$im->rotateImage( new ImagickPixel( 'none' ), 90 );
						break;
					case Imagick::ORIENTATION_LEFTBOTTOM:
						$im->rotateImage( new ImagickPixel( 'none' ), 270 );
						break;
					case Imagick::ORIENTATION_TOPRIGHT:
						$im->flopImage();
						break;
					case Imagick::ORIENTATION_BOTTOMLEFT:
						$im->flipImage();
						break;
					case Imagick::ORIENTATION_LEFTTOP:
						$im->transposeImage();
						break;
					case Imagick::ORIENTATION_RIGHTBOTTOM:
						$im->transverseImage();
						break;
				}
				$im->setImageOrientation( Imagick::ORIENTATION_TOPLEFT );
			}
			$icc = null;
			if ( Imagick::COLORSPACE_CMYK === $im->getImageColorspace() ) {
				$im->transformImageColorspace( Imagick::COLORSPACE_SRGB );
			} else {
				try {
					$icc = $im->getImageProfile( 'icc' );
				} catch ( \Throwable $e ) {
					$icc = null;
				}
			}
			$im->stripImage();
			if ( $icc ) {
				$im->profileImage( 'icc', $icc );
			}
			$im->setImageFormat( 'webp' );
			$im->setImageCompressionQuality( $quality );
			$im->setOption( 'webp:method', '6' );
			if ( $lossless ) {
				$im->setOption( 'webp:lossless', 'true' );
			}
			$im->setOption( 'webp:alpha-quality', '100' );
			$ok = $im->writeImage( 'webp:' . $dest );
			$im->clear();
			return $ok ? true : 'Imagick نتوانست بنویسد';
		} catch ( \Throwable $e ) {
			return 'Imagick: ' . $e->getMessage();
		}
	}

	private static function with_gd( $src, $dest, $info, $type, $quality, $lossless ) {
		if ( ! function_exists( 'imagewebp' ) ) {
			return 'GD از WebP پشتیبانی نمی‌کند';
		}
		if ( IMAGETYPE_JPEG === $type && isset( $info['channels'] ) && 4 === (int) $info['channels'] ) {
			return 'JPEG با فضای رنگی CMYK (نیازمند Imagick)';
		}
		$im = ( IMAGETYPE_JPEG === $type ) ? @imagecreatefromjpeg( $src ) : @imagecreatefrompng( $src );
		if ( ! $im ) {
			return 'GD تصویر را باز نکرد';
		}
		if ( IMAGETYPE_JPEG === $type && function_exists( 'exif_read_data' ) ) {
			$exif = @exif_read_data( $src );
			$ori  = isset( $exif['Orientation'] ) ? (int) $exif['Orientation'] : 1;
			$rot  = null;
			if ( in_array( $ori, array( 5, 7 ), true ) ) {
				imagedestroy( $im );
				return 'جهت EXIF پیچیده (نیازمند Imagick)';
			}
			if ( 3 === $ori ) {
				$rot = imagerotate( $im, 180, 0 );
			} elseif ( 6 === $ori ) {
				$rot = imagerotate( $im, -90, 0 );
			} elseif ( 8 === $ori ) {
				$rot = imagerotate( $im, 90, 0 );
			} elseif ( 2 === $ori && function_exists( 'imageflip' ) ) {
				imageflip( $im, IMG_FLIP_HORIZONTAL );
			} elseif ( 4 === $ori && function_exists( 'imageflip' ) ) {
				imageflip( $im, IMG_FLIP_VERTICAL );
			}
			if ( $rot ) {
				imagedestroy( $im );
				$im = $rot;
			}
		}
		if ( ! imageistruecolor( $im ) ) {
			if ( function_exists( 'imagepalettetotruecolor' ) ) {
				imagepalettetotruecolor( $im );
			} else {
				$tc = imagecreatetruecolor( imagesx( $im ), imagesy( $im ) );
				imagealphablending( $tc, false );
				imagesavealpha( $tc, true );
				imagefill( $tc, 0, 0, imagecolorallocatealpha( $tc, 0, 0, 0, 127 ) );
				imagecopy( $tc, $im, 0, 0, 0, 0, imagesx( $im ), imagesy( $im ) );
				imagedestroy( $im );
				$im = $tc;
			}
		}
		imagealphablending( $im, false );
		imagesavealpha( $im, true );
		if ( $lossless ) {
			$quality = defined( 'IMG_WEBP_LOSSLESS' ) ? IMG_WEBP_LOSSLESS : 92;
		}
		$ok = @imagewebp( $im, $dest, $quality );
		imagedestroy( $im );
		return $ok ? true : 'GD نتوانست WebP بنویسد';
	}

	/* --------------------------------------------------------- attachments */

	private static function unique_name( $dir, $stem, array &$reserved ) {
		$i    = 0;
		$name = $stem . '.webp';
		while ( file_exists( $dir . '/' . $name ) || isset( $reserved[ $dir . '/' . $name ] ) ) {
			$i++;
			$name = $stem . '-' . $i . '.webp';
		}
		$reserved[ $dir . '/' . $name ] = true;
		return $name;
	}

	/**
	 * Convert every JPEG/PNG file of one attachment.
	 *
	 * @param int        $id
	 * @param string     $mode 'job' (record map, keep old files) | 'upload' (delete old files, return meta).
	 * @param array|null $meta Metadata (upload mode only: not stored yet).
	 * @return array { status: converted|skipped|failed, reason, before, after, meta? }
	 */
	public static function convert_attachment( $id, $mode = 'job', $meta = null ) {
		global $wpdb;
		$o = self::opts();
		if ( ! $o['engine'] ) {
			return array( 'status' => 'failed', 'reason' => 'هیچ موتور WebP (Imagick/GD) فعال نیست' );
		}
		$mime = get_post_mime_type( $id );
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
			return array( 'status' => 'skipped', 'reason' => 'نوع فایل پشتیبانی نمی‌شود' );
		}
		if ( get_post_meta( $id, 'amazonS3_info', true ) || get_post_meta( $id, '_wp_offload_media', true ) ) {
			return array( 'status' => 'skipped', 'reason' => 'فایل روی ذخیره‌ساز خارجی (آفلود) است' );
		}
		$rel = (string) get_post_meta( $id, '_wp_attached_file', true );
		if ( '' === $rel || ! MSW_Util::safe_rel( $rel ) ) {
			return array( 'status' => 'skipped', 'reason' => 'مسیر فایل نامعتبر' );
		}
		if ( ! is_file( MSW_Util::abs( $rel ) ) ) {
			return array( 'status' => 'skipped', 'reason' => 'فایل روی دیسک نیست' );
		}
		if ( null === $meta ) {
			$meta = get_post_meta( $id, '_wp_attachment_metadata', true );
		}
		$meta = is_array( $meta ) ? $meta : array();
		$dir  = ( false === strpos( $rel, '/' ) ) ? '' : dirname( $rel ) . '/';

		// Files of this attachment: main first.
		$files = array( $rel => array( 'role' => 'main' ) );
		if ( MSW_Util::get( 'webp_originals' ) && ! empty( $meta['original_image'] ) ) {
			$files[ $dir . $meta['original_image'] ] = array( 'role' => 'orig' );
		}
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $name => $sz ) {
				if ( ! empty( $sz['file'] ) && ! isset( $files[ $dir . $sz['file'] ] ) ) {
					$files[ $dir . $sz['file'] ] = array( 'role' => 'size' );
				}
			}
		}

		$reserved = array();
		$created  = array(); // abs paths for rollback.
		$pairs    = array(); // old_rel => new_rel
		$before   = 0;
		$after    = 0;
		$min      = (int) MSW_Util::get( 'webp_min_saving' );
		$dims     = array();

		foreach ( $files as $old_rel => $f ) {
			if ( ! MSW_Util::safe_rel( $old_rel ) || ! preg_match( '~\.(jpe?g|png)$~i', $old_rel ) ) {
				continue;
			}
			$old_abs = MSW_Util::abs( $old_rel );
			if ( ! is_file( $old_abs ) ) {
				if ( 'main' === $f['role'] ) {
					return array( 'status' => 'skipped', 'reason' => 'فایل اصلی روی دیسک نیست' );
				}
				continue;
			}
			if ( strlen( $old_rel ) > 185 ) {
				if ( 'main' === $f['role'] ) {
					return array( 'status' => 'skipped', 'reason' => 'مسیر فایل بیش از حد طولانی است' );
				}
				continue;
			}
			$abs_dir = dirname( $old_abs );
			$name    = self::unique_name( $abs_dir, pathinfo( $old_abs, PATHINFO_FILENAME ), $reserved );
			$tmp     = $abs_dir . '/.msw-' . substr( md5( $old_abs . microtime() ), 0, 10 ) . '.tmp';
			$r       = self::convert_file( $old_abs, $tmp, $o );
			if ( true !== $r ) {
				@unlink( $tmp );
				if ( 'main' === $f['role'] ) {
					self::rollback( $created );
					return array( 'status' => 'failed', 'reason' => $r );
				}
				continue; // A single sub-size keeps its original file.
			}
			$ob = (int) filesize( $old_abs );
			$nb = (int) filesize( $tmp );
			if ( 'main' === $f['role'] && $ob > 0 && ( ( $ob - $nb ) / $ob * 100 ) < $min ) {
				@unlink( $tmp );
				self::rollback( $created );
				return array( 'status' => 'skipped', 'reason' => 'WebP کوچک‌تر نمی‌شود (' . round( ( $ob - $nb ) / max( 1, $ob ) * 100 ) . '٪)', 'before' => $ob, 'after' => $nb );
			}
			if ( 'main' !== $f['role'] && $nb >= $ob ) {
				@unlink( $tmp ); // Keep the smaller original for this sub-size.
				continue;
			}
			$new_abs = $abs_dir . '/' . $name;
			if ( ! @rename( $tmp, $new_abs ) ) {
				@unlink( $tmp );
				if ( 'main' === $f['role'] ) {
					self::rollback( $created );
					return array( 'status' => 'failed', 'reason' => 'جایگزینی فایل ممکن نیست (مجوز نوشتن)' );
				}
				continue;
			}
			$perm = @fileperms( $old_abs );
			@chmod( $new_abs, $perm ? ( $perm & 0666 ) : 0644 );
			$created[]          = $new_abs;
			$new_rel            = $dir . $name;
			$pairs[ $old_rel ]  = $new_rel;
			$before            += $ob;
			$after             += $nb;
			$d                  = @getimagesize( $new_abs );
			$dims[ $old_rel ]   = $d ? array( (int) $d[0], (int) $d[1], $nb ) : array( 0, 0, $nb );
		}

		if ( empty( $pairs[ $rel ] ) ) {
			self::rollback( $created );
			return array( 'status' => 'failed', 'reason' => 'فایل اصلی تبدیل نشد' );
		}

		/* ---- database update (everything exists on disk now) ---- */
		$new_main = $pairs[ $rel ];
		if ( $meta ) {
			$meta['file'] = $new_main;
			if ( isset( $dims[ $rel ] ) && $dims[ $rel ][0] ) {
				$meta['width']  = $dims[ $rel ][0];
				$meta['height'] = $dims[ $rel ][1];
			}
			$meta['filesize'] = $dims[ $rel ][2];
			if ( ! empty( $meta['original_image'] ) && isset( $pairs[ $dir . $meta['original_image'] ] ) ) {
				$meta['original_image'] = basename( $pairs[ $dir . $meta['original_image'] ] );
			}
			if ( ! empty( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $name => $sz ) {
					$k = isset( $sz['file'] ) ? $dir . $sz['file'] : '';
					if ( $k && isset( $pairs[ $k ] ) ) {
						$meta['sizes'][ $name ]['file']      = basename( $pairs[ $k ] );
						$meta['sizes'][ $name ]['mime-type'] = 'image/webp';
						$meta['sizes'][ $name ]['filesize']  = $dims[ $k ][2];
						if ( $dims[ $k ][0] ) {
							$meta['sizes'][ $name ]['width']  = $dims[ $k ][0];
							$meta['sizes'][ $name ]['height'] = $dims[ $k ][1];
						}
					}
				}
			}
		}

		if ( 'upload' !== $mode ) {
			update_post_meta( $id, '_wp_attached_file', $new_main );
			if ( $meta ) {
				update_post_meta( $id, '_wp_attachment_metadata', $meta );
			}
		}
		// In upload mode WordPress stores $meta itself right after our filter.
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_mime_type' => 'image/webp',
				'guid'           => MSW_Util::url( $new_main ),
			),
			array( 'ID' => $id )
		);
		if ( 'upload' === $mode ) {
			update_post_meta( $id, '_wp_attached_file', $new_main );
		}
		clean_post_cache( $id );

		if ( 'upload' === $mode ) {
			foreach ( array_keys( $pairs ) as $old_rel ) {
				@unlink( MSW_Util::abs( $old_rel ) );
			}
			return array( 'status' => 'converted', 'before' => $before, 'after' => $after, 'meta' => $meta );
		}

		// Job mode: record the map for the rewrite phase.
		$mt = MSW_Util::t( 'map' );
		foreach ( $pairs as $old => $new ) {
			$wpdb->query( $wpdb->prepare( "REPLACE INTO $mt (old_rel,new_rel,att_id) VALUES (%s,%s,%d)", $old, $new, $id ) ); // phpcs:ignore
		}
		foreach ( $pairs as $old => $new ) {
			MSW_Redirects::add( $old, $new, $id );
		}
		$mrow = array(
			'rel_path' => $new_main,
			'path_key' => MSW_Extractor::path_key( $new_main ),
			'base_key' => MSW_Extractor::base_key( $new_main ),
			'mime'     => 'image/webp',
		);
		$wpdb->update( MSW_Util::t( 'media' ), $mrow, array( 'attachment_id' => $id ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . MSW_Util::t( 'media' ) . ' SET bytes = GREATEST(0, bytes - %d + %d) WHERE attachment_id = %d', $before, $after, $id ) ); // phpcs:ignore

		return array(
			'status' => 'converted',
			'before' => $before,
			'after'  => $after,
			'old'    => array_keys( $pairs ),
			'pairs'  => $pairs,
		);
	}

	private static function rollback( array $created ) {
		foreach ( $created as $p ) {
			@unlink( $p );
		}
	}

	/** Auto-convert new uploads (no references exist yet, so nothing to rewrite). */
	public static function on_upload( $meta, $id, $context = 'create' ) {
		if ( 'create' !== $context || ! MSW_Util::get( 'webp_on_upload' ) || ! self::engine() ) {
			return $meta;
		}
		try {
			$r = self::convert_attachment( (int) $id, 'upload', is_array( $meta ) ? $meta : array() );
		} catch ( \Throwable $e ) {
			MSW_Util::log( 'تبدیل هنگام آپلود #' . $id . ': ' . $e->getMessage(), 'warn' );
			return $meta;
		}
		if ( 'converted' === $r['status'] && ! empty( $r['meta'] ) ) {
			return $r['meta'];
		}
		return $meta;
	}

	/* ------------------------------------------------------ job helpers */

	/**
	 * Queue candidates. @return int|WP_Error number of attachments queued.
	 */
	public static function build_queue( $scope, $retry = false ) {
		global $wpdb;
		$c = MSW_Util::t( 'converted' );
		$m = MSW_Util::t( 'media' );
		if ( $retry ) {
			$wpdb->query( "DELETE FROM $c WHERE status IN ('failed','skipped')" ); // phpcs:ignore
		}
		$wpdb->query( "DELETE FROM $c WHERE status = 'pending'" ); // phpcs:ignore
		if ( 'used' === $scope ) {
			if ( ! (int) $wpdb->get_var( "SELECT COUNT(*) FROM $m" ) ) { // phpcs:ignore
				return new WP_Error( 'noscan', 'برای حالت «فقط عکس‌های در حال استفاده» ابتدا یک اسکن کامل انجام دهید.' );
			}
			$extra = MSW_Util::get( 'webp_skip_theme' ) ? ' AND m.theme_ref = 0' : '';
			$wpdb->query( "INSERT IGNORE INTO $c (attachment_id,status,updated) SELECT m.attachment_id,'pending',UTC_TIMESTAMP() FROM $m m WHERE m.mime IN ('image/jpeg','image/png') AND m.status = 'used' AND m.missing = 0$extra" ); // phpcs:ignore
		} else {
			$wpdb->query( "INSERT IGNORE INTO $c (attachment_id,status,updated) SELECT p.ID,'pending',UTC_TIMESTAMP() FROM {$wpdb->posts} p WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type IN ('image/jpeg','image/png')" ); // phpcs:ignore
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $c WHERE status = 'pending'" ); // phpcs:ignore
	}

	/** Convert the next pending attachment. @return array|false */
	public static function process_next() {
		global $wpdb;
		$c  = MSW_Util::t( 'converted' );
		$id = (int) $wpdb->get_var( "SELECT attachment_id FROM $c WHERE status = 'pending' ORDER BY attachment_id LIMIT 1" ); // phpcs:ignore
		if ( ! $id ) {
			return false;
		}
		try {
			$r = self::convert_attachment( $id, 'job' );
		} catch ( \Throwable $e ) {
			$r = array( 'status' => 'failed', 'reason' => $e->getMessage() );
		}
		$row = array(
			'status'       => $r['status'],
			'reason'       => isset( $r['reason'] ) ? substr( $r['reason'], 0, 190 ) : '',
			'bytes_before' => isset( $r['before'] ) ? (int) $r['before'] : 0,
			'bytes_after'  => isset( $r['after'] ) ? (int) $r['after'] : 0,
			'old_files'    => isset( $r['old'] ) ? wp_json_encode( $r['old'] ) : null,
			'new_files'    => isset( $r['pairs'] ) ? wp_json_encode( $r['pairs'] ) : null,
			'updated'      => gmdate( 'Y-m-d H:i:s' ),
		);
		$wpdb->update( $c, $row, array( 'attachment_id' => $id ) );
		if ( 'failed' === $r['status'] ) {
			MSW_Util::log( "تبدیل #$id ناموفق: " . $r['reason'], 'warn' );
		}
		return array( 'id' => $id ) + $r;
	}

	/**
	 * After the rewrite: verify and handle originals.
	 *
	 * @return int number of attachments finalised.
	 */
	public static function finalize( $retention_days ) {
		global $wpdb;
		$c    = MSW_Util::t( 'converted' );
		$rows = $wpdb->get_results( "SELECT * FROM $c WHERE status = 'converted' LIMIT 200" ); // phpcs:ignore
		$n    = 0;
		foreach ( (array) $rows as $r ) {
			$id   = (int) $r->attachment_id;
			$rel  = (string) get_post_meta( $id, '_wp_attached_file', true );
			$abs  = $rel ? MSW_Util::abs( $rel ) : '';
			$info = ( $abs && is_file( $abs ) ) ? @getimagesize( $abs ) : false;
			if ( ! $info ) {
				$wpdb->update( $c, array( 'status' => 'error', 'reason' => 'فایل WebP جدید پس از تبدیل یافت نشد؛ فایل‌های قدیمی حذف نشدند' ), array( 'attachment_id' => $id ) );
				MSW_Util::log( "بررسی نهایی #$id ناموفق؛ فایل‌های اصلی دست‌نخورده ماندند.", 'error' );
				continue;
			}
			if ( $retention_days > 0 ) {
				$wpdb->update(
					$c,
					array(
						'status'       => 'done',
						'delete_after' => gmdate( 'Y-m-d H:i:s', time() + $retention_days * DAY_IN_SECONDS ),
					),
					array( 'attachment_id' => $id )
				);
			} else {
				self::delete_originals_of( $r );
				$wpdb->update( $c, array( 'status' => 'deleted', 'delete_after' => null ), array( 'attachment_id' => $id ) );
			}
			$n++;
		}
		return $n;
	}

	private static function delete_originals_of( $row ) {
		$old  = json_decode( (string) $row->old_files, true );
		$id   = (int) $row->attachment_id;
		$keep = array();
		$rel  = (string) get_post_meta( $id, '_wp_attached_file', true );
		$meta = get_post_meta( $id, '_wp_attachment_metadata', true );
		$dir  = ( false === strpos( $rel, '/' ) ) ? '' : dirname( $rel ) . '/';
		$keep[ $rel ] = true;
		if ( is_array( $meta ) ) {
			if ( ! empty( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $s ) {
					if ( ! empty( $s['file'] ) ) {
						$keep[ $dir . $s['file'] ] = true;
					}
				}
			}
			if ( ! empty( $meta['original_image'] ) ) {
				$keep[ $dir . $meta['original_image'] ] = true;
			}
		}
		foreach ( (array) $old as $o ) {
			if ( isset( $keep[ $o ] ) || ! MSW_Util::safe_rel( $o ) ) {
				continue; // Still referenced by the attachment: never delete.
			}
			$abs = MSW_Util::abs( $o );
			if ( is_file( $abs ) ) {
				@unlink( $abs );
			}
		}
	}

	/** Delete retained originals (cron, or "now" button with $force). */
	public static function purge_originals( $force = false ) {
		global $wpdb;
		$c    = MSW_Util::t( 'converted' );
		$cond = $force ? '' : ' AND delete_after IS NOT NULL AND delete_after <= UTC_TIMESTAMP()';
		$rows = $wpdb->get_results( "SELECT * FROM $c WHERE status = 'done'$cond LIMIT 300" ); // phpcs:ignore
		$n    = 0;
		foreach ( (array) $rows as $r ) {
			self::delete_originals_of( $r );
			$wpdb->update( $c, array( 'status' => 'deleted', 'delete_after' => null ), array( 'attachment_id' => $r->attachment_id ) );
			$n++;
		}
		if ( $n ) {
			MSW_Util::log( "فایل‌های اصلی $n تصویر تبدیل‌شده حذف شد." );
		}
		return $n;
	}

	/* -------------------------------------------------------------- revert */

	/**
	 * Prepare a revert: restore attachment metadata to the original files and
	 * queue reverse map rows. References are rewritten by the revert job.
	 *
	 * @return int|WP_Error number of attachments prepared.
	 */
	public static function prepare_revert( array $ids = array() ) {
		global $wpdb;
		$c    = MSW_Util::t( 'converted' );
		$mt   = MSW_Util::t( 'map' );
		$cond = "status = 'done' AND new_files IS NOT NULL";
		if ( $ids ) {
			$cond .= ' AND attachment_id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')';
		}
		$rows = $wpdb->get_results( "SELECT * FROM $c WHERE $cond" ); // phpcs:ignore
		if ( ! $rows ) {
			return new WP_Error( 'none', 'موردی برای بازگردانی نیست (فقط تصاویری که اصل‌شان هنوز نگه‌داشته شده).' );
		}
		MSW_DB::truncate( 'map' );
		$n = 0;
		foreach ( $rows as $r ) {
			$pairs = json_decode( (string) $r->new_files, true );
			$id    = (int) $r->attachment_id;
			if ( ! is_array( $pairs ) || ! $pairs ) {
				continue;
			}
			$ok = true;
			foreach ( $pairs as $old => $new ) {
				if ( ! is_file( MSW_Util::abs( $old ) ) ) {
					$ok = false;
				}
			}
			if ( ! $ok ) {
				$wpdb->update( $c, array( 'reason' => 'فایل اصلی دیگر روی دیسک نیست؛ بازگردانی ممکن نیست' ), array( 'attachment_id' => $id ) );
				continue;
			}
			$rev  = array_flip( $pairs ); // new => old.
			$rel  = (string) get_post_meta( $id, '_wp_attached_file', true );
			$meta = get_post_meta( $id, '_wp_attachment_metadata', true );
			$dir  = ( false === strpos( $rel, '/' ) ) ? '' : dirname( $rel ) . '/';
			$mime = ( 'png' === strtolower( pathinfo( (string) array_search( $rel, $pairs, true ), PATHINFO_EXTENSION ) ) ) ? 'image/png' : 'image/jpeg';
			if ( isset( $rev[ $rel ] ) ) {
				update_post_meta( $id, '_wp_attached_file', $rev[ $rel ] );
			}
			if ( is_array( $meta ) ) {
				if ( isset( $rev[ $rel ] ) ) {
					$meta['file'] = $rev[ $rel ];
				}
				foreach ( (array) ( isset( $meta['sizes'] ) ? $meta['sizes'] : array() ) as $name => $sz ) {
					if ( ! empty( $sz['file'] ) && isset( $rev[ $dir . $sz['file'] ] ) ) {
						$old_rel = $rev[ $dir . $sz['file'] ];
						$meta['sizes'][ $name ]['file']      = basename( $old_rel );
						$meta['sizes'][ $name ]['mime-type'] = preg_match( '~\.png$~i', $old_rel ) ? 'image/png' : 'image/jpeg';
						$p = MSW_Util::abs( $old_rel );
						$meta['sizes'][ $name ]['filesize']  = (int) @filesize( $p );
					}
				}
				if ( ! empty( $meta['original_image'] ) && isset( $rev[ $dir . $meta['original_image'] ] ) ) {
					$meta['original_image'] = basename( $rev[ $dir . $meta['original_image'] ] );
				}
				if ( isset( $rev[ $rel ] ) ) {
					$meta['filesize'] = (int) @filesize( MSW_Util::abs( $rev[ $rel ] ) );
				}
				update_post_meta( $id, '_wp_attachment_metadata', $meta );
			}
			$main_old = isset( $rev[ $rel ] ) ? $rev[ $rel ] : $rel;
			$wpdb->update( $wpdb->posts, array( 'post_mime_type' => $mime, 'guid' => MSW_Util::url( $main_old ) ), array( 'ID' => $id ) );
			clean_post_cache( $id );
			foreach ( $rev as $new => $old ) {
				if ( strlen( $new ) <= 185 ) {
					$wpdb->query( $wpdb->prepare( "REPLACE INTO $mt (old_rel,new_rel,att_id) VALUES (%s,%s,%d)", $new, $old, $id ) ); // phpcs:ignore
				}
			}
			$wpdb->update( $c, array( 'status' => 'reverting', 'reason' => '', 'updated' => gmdate( 'Y-m-d H:i:s' ) ), array( 'attachment_id' => $id ) );
			$wpdb->update( MSW_Util::t( 'media' ), array( 'rel_path' => $main_old, 'path_key' => MSW_Extractor::path_key( $main_old ), 'mime' => $mime ), array( 'attachment_id' => $id ) );
			$n++;
		}
		return $n ? $n : new WP_Error( 'none', 'بازگردانی هیچ موردی ممکن نبود.' );
	}

	/** After references were rewritten back: remove the WebP files. */
	public static function finalize_revert() {
		global $wpdb;
		$c    = MSW_Util::t( 'converted' );
		$rows = $wpdb->get_results( "SELECT * FROM $c WHERE status = 'reverting' LIMIT 200" ); // phpcs:ignore
		foreach ( (array) $rows as $r ) {
			$pairs = json_decode( (string) $r->new_files, true );
			foreach ( (array) $pairs as $old => $new ) {
				$p = MSW_Util::abs( $new );
				if ( is_file( $p ) && is_file( MSW_Util::abs( $old ) ) ) {
					@unlink( $p );
				}
			}
			$wpdb->update( $c, array( 'status' => 'reverted' ), array( 'attachment_id' => $r->attachment_id ) );
		}
		return count( (array) $rows );
	}

	/** Convert a handful of images into temp files to report real-world savings. */
	public static function simulate( $limit = 5 ) {
		global $wpdb;
		$o = self::opts();
		if ( ! $o['engine'] ) {
			return array( 'error' => 'هیچ موتور WebP فعال نیست.' );
		}
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status='inherit' AND post_mime_type IN ('image/jpeg','image/png') ORDER BY RAND() LIMIT %d", $limit ) );
		$out = array();
		foreach ( (array) $ids as $id ) {
			$rel = (string) get_post_meta( $id, '_wp_attached_file', true );
			$abs = $rel ? MSW_Util::abs( $rel ) : '';
			if ( ! $abs || ! is_file( $abs ) ) {
				continue;
			}
			$tmp = dirname( $abs ) . '/.msw-sim-' . $id . '.tmp';
			$r   = self::convert_file( $abs, $tmp, $o );
			$ob  = (int) filesize( $abs );
			$nb  = ( true === $r && is_file( $tmp ) ) ? (int) filesize( $tmp ) : 0;
			@unlink( $tmp );
			$out[] = array(
				'id'     => (int) $id,
				'file'   => basename( $rel ),
				'before' => $ob,
				'after'  => $nb,
				'error'  => true === $r ? '' : $r,
			);
		}
		return array( 'items' => $out );
	}
}
