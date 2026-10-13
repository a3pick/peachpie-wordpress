<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure string logic (no WordPress calls) used to find media references in
 * arbitrary text: HTML, shortcodes, block comments, JSON (Elementor & co.),
 * and PHP-serialized data.
 *
 * Three kinds of reference are produced:
 *   ids   - attachment IDs                       (strong)
 *   paths - normalised uploads-relative paths    (strong)
 *   bases - normalised file names only           (weak, optional)
 */
class MSW_Extractor {

	const EXT = 'jpe?g|png|gif|webp|avif|svg|bmp|ico|tiff?|heic|mp4|m4v|webm|mov|ogv|avi|mkv|wmv|flv|3gp|mp3|wav|ogg|oga|m4a|flac|aac|wma|pdf|docx?|xlsx?|pptx?|zip';

	/** Directory name that precedes media paths in URLs (normally "uploads"). */
	public static $marker = 'uploads';

	/**
	 * Hosts that count as "this site" (lower-case, without www.). null = compute
	 * from WordPress on first use; false = every host is accepted (unit tests).
	 */
	public static $hosts = null;

	private static function norm_host( $h ) {
		$h = strtolower( (string) $h );
		$h = preg_replace( '~^.*@~', '', $h );
		return preg_replace( '~^www\.~', '', $h );
	}

	public static function allowed_hosts() {
		if ( null === self::$hosts ) {
			if ( ! function_exists( 'home_url' ) ) {
				self::$hosts = false;
				return false;
			}
			$list = array( home_url(), site_url(), function_exists( 'wp_upload_dir' ) ? wp_upload_dir( null, false )['baseurl'] : '' );
			$out  = array();
			foreach ( $list as $u ) {
				$host = wp_parse_url( $u, PHP_URL_HOST );
				if ( $host ) {
					$port = wp_parse_url( $u, PHP_URL_PORT );
					$out[ self::norm_host( $host ) ] = true;
					if ( $port ) {
						$out[ self::norm_host( $host ) . ':' . $port ] = true;
					}
				}
			}
			$extra = class_exists( 'MSW_Util' ) ? (string) MSW_Util::get( 'extra_hosts' ) : '';
			foreach ( preg_split( '~[\s,]+~', $extra ) as $x ) {
				$x = self::norm_host( preg_replace( '~^https?://~i', '', trim( $x ) ) );
				if ( '' !== $x ) {
					$out[ rtrim( $x, '/' ) ] = true;
				}
			}
			self::$hosts = $out;
		}
		return self::$hosts;
	}

	/**
	 * Is the URL that ends right before the upload marker one of ours?
	 * $prefix is the text before the marker, e.g. 'src="https://cdn.x.com/wp-content/'.
	 * Relative URLs (no host) are always ours.
	 */
	public static function host_ok( $prefix ) {
		$hosts = self::allowed_hosts();
		if ( false === $hosts ) {
			return true;
		}
		$prefix = substr( $prefix, -400 );
		if ( ! preg_match( '~(?:https?:)?\\\\?/\\\\?/([^\s"\'<>()\\\\|,;/\\\\]+)(?:\\\\?/[^\s"\'<>()\\\\|,;/\\\\]*)*$~i', $prefix, $m ) ) {
			return true;
		}
		return isset( $hosts[ self::norm_host( $m[1] ) ] );
	}

	/* ----------------------------------------------------------------- keys */

	/** Shorten very long keys so they fit an indexed column. */
	public static function fit( $s ) {
		return strlen( $s ) > 185 ? 'h:' . md5( $s ) : $s;
	}

	/**
	 * Normalised, extension-less, size-less, lower-case key of a media path.
	 * foo-300x200.jpg, foo-scaled.jpg, foo.jpg.webp, foo.webp  ->  "2024/05/foo"
	 */
	public static function path_key( $path ) {
		$p = str_replace( '\\', '/', (string) $path );
		$p = preg_replace( '~[?#].*$~s', '', $p );
		$p = rawurldecode( $p );
		$p = strtolower( ltrim( $p, '/' ) );
		$p = preg_replace( '~^sites/\d+/~', '', $p );
		// Strip up to two extensions (foo.jpg.webp).
		for ( $i = 0; $i < 2; $i++ ) {
			if ( 0 === $i ) {
				$p = preg_replace( '~\.[a-z0-9]{2,5}$~', '', $p );
			} else {
				$p = preg_replace( '~\.(?:jpe?g|png|gif|webp|avif)$~', '', $p );
			}
		}
		$p = preg_replace( '~(?:-scaled|-rotated|-\d{1,5}x\d{1,5}|-e\d{10,})+$~', '', $p );
		$p = preg_replace( '~@\dx$~', '', $p );
		return self::fit( $p );
	}

	public static function base_key( $path ) {
		$k = self::path_key( $path );
		if ( 0 === strpos( $k, 'h:' ) ) {
			return $k;
		}
		$pos = strrpos( $k, '/' );
		return false === $pos ? $k : substr( $k, $pos + 1 );
	}

	/** Does a key (meta key, JSON key, shortcode attribute) look like it holds media IDs? */
	public static function key_qualifies( $k ) {
		$k = strtolower( (string) $k );
		if ( '' === $k ) {
			return false;
		}
		if ( preg_match( '~(^|[_\-])ids?$~', $k ) ) {
			return true;
		}
		return (bool) preg_match( '~image|img|gallery|photo|logo|icon|background|(^|[_\-])bg|bg([_\-]|$)|poster|thumb|attachment|media|avatar|cover|banner|video|picture|(^|[_\-])pic|upload~', $k );
	}

	public static function is_id_list( $v ) {
		return (bool) preg_match( '~^\s*\d+(?:\s*,\s*\d+)*\s*$~', (string) $v );
	}

	private static function ids_from( $str, array &$ids ) {
		if ( preg_match_all( '~\d+~', $str, $m ) ) {
			foreach ( $m[0] as $n ) {
				$n = (int) $n;
				if ( $n > 0 && $n < 2147483647 ) {
					$ids[ $n ] = true;
				}
			}
		}
	}

	/* --------------------------------------------------------------- extract */

	/**
	 * @param string $text Any string (may be serialized or JSON).
	 * @param bool   $ctx  True when the surrounding key already says "this holds media IDs".
	 * @return array{ids:array,paths:array,bases:array} keys are the values, values are true.
	 */
	public static function extract( $text, $ctx = false ) {
		$out = array(
			'ids'   => array(),
			'paths' => array(),
			'bases' => array(),
			'raw'   => array(),
		);
		$text = (string) $text;
		if ( strlen( $text ) < 2 ) {
			return $out;
		}
		$t = false !== strpos( $text, '\\/' ) ? str_replace( '\\/', '/', $text ) : $text;

		/* Paths below the uploads directory. */
		if ( false !== stripos( $t, self::$marker . '/' ) ) {
			$re = '~' . preg_quote( self::$marker, '~' ) . '/([^\s"\'<>()\\\\|,;{}\[\]]+?\.(?:' . self::EXT . '))(?![a-z0-9_])~i';
			if ( preg_match_all( $re, $t, $m, PREG_OFFSET_CAPTURE ) ) {
				foreach ( $m[1] as $i => $hit ) {
					$rel = $hit[0];
					$out['paths'][ self::path_key( $rel ) ] = true;
					$start = $m[0][ $i ][1];
					if ( self::host_ok( substr( $t, max( 0, $start - 400 ), min( 400, $start ) ) ) ) {
						$raw                = preg_replace( '~^sites/\d+/~', '', false !== strpos( $rel, '%' ) ? rawurldecode( $rel ) : $rel );
						$out['raw'][ $raw ] = true;
					}
				}
			}
		}

		/* Bare file names (weak references). */
		if ( preg_match_all( '~([a-z0-9][a-z0-9_\-.%@]*\.(?:' . self::EXT . '))(?![a-z0-9_])~i', $t, $m ) ) {
			foreach ( $m[1] as $f ) {
				$out['bases'][ self::base_key( $f ) ] = true;
			}
		}

		/* Attachment IDs. */
		if ( false !== strpos( $t, 'wp-image-' ) && preg_match_all( '~wp-image-(\d+)~', $t, $m ) ) {
			self::ids_from( implode( ' ', $m[1] ), $out['ids'] );
		}
		if ( false !== strpos( $t, 'wp-att-' ) && preg_match_all( '~wp-att-(\d+)~', $t, $m ) ) {
			self::ids_from( implode( ' ', $m[1] ), $out['ids'] );
		}
		// key = value / key: value (shortcodes, HTML data attributes, JSON).
		if ( preg_match_all( '~(?:^|["\'\s\[,{(])([a-z0-9_\-]{2,40})\\\\?["\']?\s*[:=]\s*\\\\?["\']?\[?\s*([0-9][0-9,\s]*)(?=\\\\?["\'\]\}\s,;)]|$)~i', $t, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $row ) {
				if ( self::key_qualifies( $row[1] ) ) {
					self::ids_from( $row[2], $out['ids'] );
				}
			}
		}
		// JSON arrays of IDs: "ids":[1,2,3] / "images":["1","2"].
		if ( preg_match_all( '~["\']?(ids|include|galleryids|gallery_ids|image_ids|images|mediaids|media_ids|attachment_ids|attachmentids)["\']?\s*[:=]\s*\[([0-9,\s"\']+)\]~i', $t, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $row ) {
				self::ids_from( $row[2], $out['ids'] );
			}
		}
		// Serialized: s:5:"image";s:3:"123";  /  s:5:"image";i:123;
		if ( false !== strpos( $t, 's:' ) && preg_match_all( '~s:\d+:"([^"]{1,80})";(?:s:\d+:"([0-9][0-9,\s]*)";|i:(\d+);)~', $t, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $row ) {
				if ( self::key_qualifies( $row[1] ) ) {
					self::ids_from( '' !== $row[2] ? $row[2] : ( isset( $row[3] ) ? $row[3] : '' ), $out['ids'] );
				}
			}
		}

		/* The parent key already told us these are IDs. */
		if ( $ctx && strlen( $text ) < 4000 ) {
			if ( preg_match_all( '~(?<=[;{])i:\d+;(?:i:(\d+);|s:\d+:"(\d+)";)~', $text, $m, PREG_SET_ORDER ) ) {
				foreach ( $m as $row ) {
					self::ids_from( $row[1] . ' ' . ( isset( $row[2] ) ? $row[2] : '' ), $out['ids'] );
				}
			}
			if ( preg_match( '~^\s*\[([\d"\',\s]+)\]\s*$~', $text, $m ) ) {
				self::ids_from( $m[1], $out['ids'] );
			}
		}

		return $out;
	}
}
