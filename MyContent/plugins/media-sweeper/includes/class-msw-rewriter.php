<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrites references to converted files (old.jpg -> new.webp) in the database.
 *
 * - works on plain HTML, JSON (including "\/"-escaped Elementor data) and
 *   PHP-serialized data without ever unserialize()-ing (string lengths are
 *   recalculated byte-exactly, so no object is instantiated and nothing breaks);
 * - keeps URL style: relative / absolute, %-encoding, JSON escaping;
 * - only touches paths that exist in the conversion map.
 */
class MSW_Rewriter {

	/** @var array old_rel => new_rel|null  (lookup cache) */
	private static $cache = array();
	public static $changed = 0;
	/** Source extensions the rewriter looks for (regex alternation). */
	public static $exts = 'jpe?g|png';
	/** Map table in use: 'map' (real jobs) or 'pmap' (dry-run preview). */
	public static $table = 'map';

	/* ---------------------------------------------------------------- regex */

	private static function re() {
		return '~' . preg_quote( MSW_Extractor::$marker, '~' ) . '(\\\\?/)((?:[^\s"\'<>()\\\\|,;{}\[\]]|\\\\/)+?)\.(' . self::$exts . ')(?![a-z0-9_]|\.(?:webp|avif))~i';
	}

	/** Split "sites/2/2024/05/foo" into [prefix, rest]. */
	private static function split_prefix( $decoded ) {
		if ( preg_match( '~^(sites/\d+/)(.*)$~s', $decoded, $m ) ) {
			return array( $m[1], $m[2] );
		}
		return array( '', $decoded );
	}

	/** Relative paths (with extension, as stored in the map) found in a string. */
	public static function candidates( $s ) {
		$out = array();
		if ( ! preg_match_all( self::re(), $s, $mm, PREG_SET_ORDER ) ) {
			return $out;
		}
		foreach ( $mm as $m ) {
			$rel = ( '/' === $m[1] ) ? $m[2] : str_replace( '\\/', '/', $m[2] );
			$rel = false !== strpos( $rel, '%' ) ? rawurldecode( $rel ) : $rel;
			list( , $rest ) = self::split_prefix( $rel );
			$out[ $rest . '.' . $m[3] ] = true;
		}
		return array_keys( $out );
	}

	/**
	 * Replace inside a plain (non-serialized) string. Only URLs that are
	 * relative or belong to this site (see MSW_Extractor::host_ok) are touched.
	 *
	 * @param callable $lookup function( string $old_rel ): ?string new_rel
	 */
	public static function replace_plain( $s, $lookup ) {
		if ( ! preg_match_all( self::re(), $s, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return $s;
		}
		$out = '';
		$pos = 0;
		foreach ( $mm as $m ) {
			$whole   = $m[0][0];
			$start   = $m[0][1];
			$sep     = $m[1][0];
			$escaped = ( '/' !== $sep );
			$rel     = $escaped ? str_replace( '\\/', '/', $m[2][0] ) : $m[2][0];
			$enc     = false !== strpos( $rel, '%' );
			$dec     = $enc ? rawurldecode( $rel ) : $rel;
			list( $prefix, $rest ) = self::split_prefix( $dec );
			$new = null;
			if ( MSW_Extractor::host_ok( substr( $s, max( 0, $start - 400 ), min( 400, $start ) ) ) ) {
				$new = call_user_func( $lookup, $rest . '.' . $m[3][0] );
			}
			if ( null === $new || false === $new || '' === $new ) {
				continue;
			}
			$new = $prefix . $new;
			if ( $enc ) {
				$new = implode( '/', array_map( 'rawurlencode', explode( '/', $new ) ) );
			}
			if ( $escaped ) {
				$new = str_replace( '/', '\\/', $new );
			}
			$out .= substr( $s, $pos, $start - $pos ) . substr( $whole, 0, strpos( $whole, $sep ) ) . $sep . $new;
			$pos  = $start + strlen( $whole );
		}
		return $out . substr( $s, $pos );
	}

	public static function reset() {
		self::$cache = array();
	}

	public static function looks_serialized( $s ) {
		$t = trim( $s );
		return strlen( $t ) > 4 && preg_match( '~^(?:[aOCsSibdN]:|N;)~', $t ) && preg_match( '~[;}]$~', $t );
	}

	/**
	 * Byte-exact search & replace inside PHP-serialized data.
	 */
	public static function replace_serialized( $s, $lookup ) {
		$out = '';
		$pos = 0;
		while ( preg_match( '/s:(\d+):"/', $s, $m, PREG_OFFSET_CAPTURE, $pos ) ) {
			$start = $m[0][1];
			$n     = (int) $m[1][0];
			$cs    = $start + strlen( $m[0][0] );
			if ( substr( $s, $cs + $n, 2 ) !== '";' ) {
				$out .= substr( $s, $pos, $cs - $pos );
				$pos  = $cs;
				continue;
			}
			$out .= substr( $s, $pos, $start - $pos );
			$str  = substr( $s, $cs, $n );
			if ( self::looks_serialized( $str ) ) {
				$new = self::replace_serialized( $str, $lookup );
			} else {
				$new = self::replace_plain( $str, $lookup );
			}
			$out .= 's:' . strlen( $new ) . ':"' . $new . '";';
			$pos  = $cs + $n + 2;
		}
		return $out . substr( $s, $pos );
	}

	public static function replace_any( $s, $lookup ) {
		return self::looks_serialized( $s ) ? self::replace_serialized( $s, $lookup ) : self::replace_plain( $s, $lookup );
	}

	/* ------------------------------------------------------------------ DB */

	/** Make sure every candidate path is in the lookup cache (one query). */
	private static function warm( array $cands ) {
		global $wpdb;
		if ( count( self::$cache ) > 8000 ) {
			self::$cache = array();
		}
		$need = array();
		foreach ( $cands as $c ) {
			if ( ! array_key_exists( $c, self::$cache ) && strlen( $c ) <= 191 ) {
				$need[] = $c;
			} elseif ( strlen( $c ) > 191 ) {
				self::$cache[ $c ] = null;
			}
		}
		if ( ! $need ) {
			return;
		}
		foreach ( array_chunk( $need, 300 ) as $chunk ) {
			$in   = "'" . implode( "','", array_map( 'esc_sql', $chunk ) ) . "'";
			$rows = $wpdb->get_results( 'SELECT old_rel, new_rel FROM ' . MSW_Util::t( self::$table ) . " WHERE old_rel IN ($in)" ); // phpcs:ignore WordPress.DB.PreparedSQL
			foreach ( $chunk as $c ) {
				self::$cache[ $c ] = null;
			}
			foreach ( (array) $rows as $r ) {
				self::$cache[ $r->old_rel ] = $r->new_rel; // Exact-case match only (see below).
			}
		}
	}

	public static function lookup( $old ) {
		return isset( self::$cache[ $old ] ) ? self::$cache[ $old ] : null;
	}

	/** Rewrite one string. Returns the new string (same one when untouched). */
	public static function rewrite_value( $val ) {
		if ( ! is_string( $val ) || '' === $val || false === stripos( $val, MSW_Extractor::$marker ) ) {
			return $val;
		}
		$c = self::candidates( $val );
		if ( ! $c ) {
			return $val;
		}
		self::warm( $c );
		return self::replace_any( $val, array( __CLASS__, 'lookup' ) );
	}

	/**
	 * Process a DB row of a stage. Returns [col => newValue] for changed columns.
	 */
	public static function row( array $st, array $row ) {
		$changes = array();
		foreach ( $st['cols'] as $col => $mode ) {
			if ( 'text' !== $mode || ! isset( $row[ $col ] ) ) {
				continue;
			}
			$new = self::rewrite_value( (string) $row[ $col ] );
			if ( $new !== (string) $row[ $col ] ) {
				$changes[ $col ] = $new;
			}
		}
		return $changes;
	}

	/** Persist the changes of one row. */
	public static function save( array $st, array $row, array $changes ) {
		global $wpdb;
		if ( ! $changes ) {
			return;
		}
		if ( 'meta' === $st['kind'] || 'rw_meta' === $st['kind'] || 'options' === $st['kind'] ) {
			// pk column name differs from the selected columns; handled by table pk.
		}
		$wpdb->update( $st['table'], $changes, array( $st['pk'] => $row['_pk'] ) );
		self::$changed++;
	}
}
