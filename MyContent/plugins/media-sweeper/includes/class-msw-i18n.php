<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translation layer. The interface was written in Persian, so Persian is the
 * "source language": keys of the catalog are the Persian strings and the
 * values their translation. fa* locales use the source text untouched; every
 * other locale loads languages/<locale>.php, then languages/<lang>.php, then
 * languages/en_US.php. Most strings are translated in the browser (admin.js)
 * from the same catalog; this class serves the few strings rendered by PHP.
 */
class MSW_I18n {

	private static $cat = null;

	public static function locale() {
		return function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
	}

	public static function active() {
		return 0 !== strpos( strtolower( self::locale() ), 'fa' );
	}

	public static function catalog() {
		if ( null !== self::$cat ) {
			return self::$cat;
		}
		self::$cat = array();
		if ( ! self::active() ) {
			return self::$cat;
		}
		$loc   = self::locale();
		$lang  = substr( $loc, 0, 2 );
		$cands = array( $loc, $lang, 'en_US' );
		foreach ( $cands as $c ) {
			$f = MSW_DIR . 'languages/' . preg_replace( '~[^A-Za-z_]~', '', $c ) . '.php';
			if ( is_file( $f ) ) {
				$data = include $f;
				if ( is_array( $data ) ) {
					self::$cat = $data;
					break;
				}
			}
		}
		return self::$cat;
	}

	/** Exact-match translation of a Persian source string. */
	public static function tr( $fa ) {
		$c = self::catalog();
		return isset( $c[ $fa ] ) ? $c[ $fa ] : $fa;
	}

	/** Catalog for JavaScript (fragments with printf placeholders are PHP-only and skipped). */
	public static function js_catalog() {
		$out = array();
		foreach ( self::catalog() as $k => $v ) {
			if ( false === strpos( $k, '%' ) ) {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}
}
