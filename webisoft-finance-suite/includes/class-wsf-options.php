<?php
defined( 'ABSPATH' ) || exit;

/**
 * خواندن، ذخیره و پاک‌سازی تنظیمات بر اساس اسکیما.
 */
class WSF_Options {

	private static $cache = null;

	public static function activate() {
		self::ensure();
	}

	public static function ensure() {
		if ( false === get_option( WSF_OPT, false ) ) {
			add_option( WSF_OPT, array(), '', 'yes' );
		}
	}

	public static function flush() {
		self::$cache = null;
	}

	/** همه تنظیمات (پیش‌فرض + ذخیره‌شده). */
	public static function all() {
		if ( null === self::$cache ) {
			$saved    = get_option( WSF_OPT, array() );
			$saved    = is_array( $saved ) ? $saved : array();
			$defaults = WSF_Schema::defaults();
			$merged   = $defaults;
			foreach ( $defaults as $sid => $fields ) {
				if ( isset( $saved[ $sid ] ) && is_array( $saved[ $sid ] ) ) {
					foreach ( $saved[ $sid ] as $k => $v ) {
						if ( array_key_exists( $k, $fields ) ) {
							$merged[ $sid ][ $k ] = $v;
						}
					}
				}
			}
			$schema = WSF_Schema::sections();
			foreach ( $merged as $sid => $vals ) {
				$merged[ $sid ] = self::normalize( $schema[ $sid ]['fields'], $vals );
			}
			self::$cache = $merged;
		}
		return self::$cache;
	}

	/** تکمیل کلیدهای جاافتاده‌ی ردیف‌های تکرارشونده با مقدار پیش‌فرض (جلوگیری از Undefined index). */
	private static function normalize( $fields, $values ) {
		foreach ( $fields as $f ) {
			if ( 'repeater' !== $f['type'] || ! isset( $values[ $f['id'] ] ) ) {
				continue;
			}
			$defaults = WSF_Schema::field_defaults( $f['fields'] );
			$rows     = array();
			foreach ( (array) $values[ $f['id'] ] as $row ) {
				$row    = array_merge( $defaults, is_array( $row ) ? $row : array() );
				$rows[] = self::normalize( $f['fields'], $row );
			}
			$values[ $f['id'] ] = $rows;
		}
		return $values;
	}

	public static function sec( $sid ) {
		$all = self::all();
		return isset( $all[ $sid ] ) ? $all[ $sid ] : array();
	}

	public static function get( $sid, $key, $fallback = '' ) {
		$sec = self::sec( $sid );
		return array_key_exists( $key, $sec ) ? $sec[ $key ] : $fallback;
	}

	/** رنگ؛ اگر خالی بود مقدار پیش‌فرض اسکیما. */
	public static function color( $sid, $key ) {
		$v = self::get( $sid, $key, '' );
		if ( '' === $v ) {
			$d = WSF_Schema::defaults();
			$v = isset( $d[ $sid ][ $key ] ) ? $d[ $sid ][ $key ] : '';
		}
		return $v;
	}

	/* ---------- ذخیره ---------- */
	public static function save_section( $sid, $input, $reset = false ) {
		$sections = WSF_Schema::sections();
		if ( ! isset( $sections[ $sid ] ) ) {
			return false;
		}
		$saved = get_option( WSF_OPT, array() );
		$saved = is_array( $saved ) ? $saved : array();
		if ( $reset ) {
			unset( $saved[ $sid ] );
		} else {
			$saved[ $sid ] = self::sanitize_fields( $sections[ $sid ]['fields'], $input );
		}
		update_option( WSF_OPT, $saved );
		self::flush();
		return true;
	}

	/** ایمپورت JSON (آرایه‌ی section => fields). */
	public static function import( $data ) {
		if ( ! is_array( $data ) ) {
			return 0;
		}
		$saved    = get_option( WSF_OPT, array() );
		$saved    = is_array( $saved ) ? $saved : array();
		$sections = WSF_Schema::sections();
		$count    = 0;
		foreach ( $sections as $sid => $sec ) {
			if ( isset( $data[ $sid ] ) && is_array( $data[ $sid ] ) ) {
				$saved[ $sid ] = self::sanitize_fields( $sec['fields'], $data[ $sid ], true );
				$count++;
			}
		}
		update_option( WSF_OPT, $saved );
		self::flush();
		return $count;
	}

	/* ---------- پاک‌سازی ---------- */
	public static function sanitize_fields( $fields, $input, $keep_defaults = false ) {
		$out   = array();
		$input = is_array( $input ) ? $input : array();
		foreach ( $fields as $f ) {
			$id  = $f['id'];
			$has = array_key_exists( $id, $input );
			if ( $keep_defaults && ! $has ) {
				$out[ $id ] = $f['default'];
				continue;
			}
			$out[ $id ] = self::sanitize_value( $f, $has ? $input[ $id ] : null );
		}
		return $out;
	}

	public static function sanitize_value( $f, $v ) {
		$type = $f['type'];
		if ( 'repeater' === $type ) {
			return self::sanitize_repeater( $f, $v );
		}
		if ( is_array( $v ) ) {
			$v = '';
		}
		$v = null === $v ? null : (string) $v;

		switch ( $type ) {
			case 'toggle':
				return ( null !== $v && '' !== $v && '0' !== $v ) ? 1 : 0;

			case 'number':
				if ( null === $v || ! is_numeric( $v ) ) {
					return $f['default'];
				}
				$n = $v + 0;
				if ( isset( $f['min'] ) && $n < $f['min'] ) {
					$n = $f['min'];
				}
				if ( isset( $f['max'] ) && $n > $f['max'] ) {
					$n = $f['max'];
				}
				return $n;

			case 'select':
				$keys = array_map( 'strval', array_keys( $f['options'] ) );
				return ( null !== $v && in_array( $v, $keys, true ) ) ? $v : $f['default'];

			case 'color':
				$c = null === $v ? '' : sanitize_hex_color( $v );
				return $c ? $c : '';

			case 'url':
				return null === $v ? '' : esc_url_raw( trim( $v ) );

			case 'image':
				$v = null === $v ? '' : trim( $v );
				return ctype_digit( $v ) ? absint( $v ) : esc_url_raw( $v );

			case 'icon':
				return null === $v ? '' : WSF_Icons::sanitize( $v );

			case 'html':
				return null === $v ? '' : wp_kses_post( $v );

			case 'textarea':
			case 'lines':
				if ( null === $v ) {
					return '';
				}
				if ( isset( $f['san'] ) && 'css' === $f['san'] ) {
					return self::clean_css( $v );
				}
				if ( isset( $f['san'] ) && 'selectors' === $f['san'] ) {
					return self::clean_selectors( $v );
				}
				return sanitize_textarea_field( $v );

			case 'text':
			default:
				return null === $v ? '' : sanitize_text_field( $v );
		}
	}

	private static function sanitize_repeater( $f, $rows ) {
		$out = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		$count = 0;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean   = self::sanitize_fields( $f['fields'], $row );
			$content = false;
			foreach ( $f['fields'] as $sub ) {
				$val = $clean[ $sub['id'] ];
				if ( 'repeater' === $sub['type'] ) {
					if ( ! empty( $val ) ) {
						$content = true;
					}
				} elseif ( in_array( $sub['type'], array( 'text', 'textarea', 'html', 'url', 'image', 'lines' ), true ) && '' !== $val ) {
					$content = true;
				}
			}
			if ( $content ) {
				$out[] = $clean;
				if ( ++$count >= 200 ) {
					break;
				}
			}
		}
		return $out;
	}

	public static function clean_css( $css ) {
		$css = wp_check_invalid_utf8( (string) $css );
		$css = str_replace( array( '<', "\0" ), '', $css );
		return substr( trim( $css ), 0, 60000 );
	}

	public static function clean_selectors( $text ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
		$out   = array();
		foreach ( $lines as $line ) {
			$line = trim( preg_replace( '/[{};<>\\\\@]/', '', $line ) );
			if ( '' !== $line && strlen( $line ) < 300 ) {
				$out[] = $line;
			}
		}
		return implode( "\n", array_slice( $out, 0, 100 ) );
	}

	/* ---------- ابزارهای مشترک ---------- */
	public static function firm( $key ) {
		return (string) self::get( 'general', $key, '' );
	}

	/** لینک tel: از شماره‌ی دفتر. */
	public static function tel_href() {
		$p = preg_replace( '/[^0-9+]/', '', self::normalize_digits( self::firm( 'phone' ) ) );
		return $p ? 'tel:' . $p : '';
	}

	public static function normalize_digits( $str ) {
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$ar = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		return str_replace( $ar, $en, str_replace( $fa, $en, (string) $str ) );
	}
}
