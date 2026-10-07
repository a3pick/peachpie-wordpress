<?php
/**
 * Text helpers shared by parser, planner and importer.
 *
 * @package SBPI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Static text utilities (no WordPress dependency, so they are unit-testable).
 */
final class SBPI_Util {

	/** Cell values that mean "no value". */
	const EMPTY_MARKERS = array( '', '-', '—', '–', '_', 'n/a', 'N/A', 'ندارد', 'هنوز اعلام نشده', 'نامشخص', '?' );

	/**
	 * Normalise a cell: Arabic ي/ك → Persian ی/ک, collapse whitespace, trim.
	 *
	 * @param mixed $value Raw cell.
	 * @return string
	 */
	public static function clean( $value ) {
		if ( null === $value ) {
			return '';
		}
		$value = (string) $value;
		$value = strtr(
			$value,
			array(
				'ي'      => 'ی',
				'ك'      => 'ک',
				'ى'      => 'ی',
				"\xC2\xA0" => ' ', // NBSP.
				"\t"     => ' ',
				"\r"     => '',
			)
		);
		$value = preg_replace( '/[ ]{2,}/u', ' ', $value );
		return trim( (string) $value );
	}

	/**
	 * Whether a cleaned cell should be treated as empty.
	 *
	 * @param string $value Cleaned cell.
	 * @return bool
	 */
	public static function is_empty( $value ) {
		return in_array( trim( (string) $value ), self::EMPTY_MARKERS, true );
	}

	/**
	 * Convert Persian/Arabic digits to Latin (used for keys, prices, numbers).
	 *
	 * @param string $value Text.
	 * @return string
	 */
	public static function latin_digits( $value ) {
		return strtr(
			(string) $value,
			array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
				'٬' => ',', '٫' => '.',
			)
		);
	}

	/**
	 * Parse a price-like cell ("12,500,000 تومان", "۱۲٬۵۰۰٬۰۰۰") to a decimal string.
	 *
	 * @param string $value Cell.
	 * @return string Empty string when not numeric.
	 */
	public static function to_number( $value ) {
		$value = self::latin_digits( self::clean( $value ) );
		$value = preg_replace( '/[^0-9.]/', '', $value );
		if ( '' === $value || ! is_numeric( $value ) ) {
			return '';
		}
		return (string) ( 0 + $value );
	}

	/**
	 * Split a multi-value cell.
	 *
	 * @param string $value     Cleaned cell.
	 * @param string $separator Separator (default "|").
	 * @return string[] Unique non-empty values, original order.
	 */
	public static function split( $value, $separator = '|' ) {
		$separator = '' === $separator ? '|' : $separator;
		$out       = array();
		foreach ( explode( $separator, (string) $value ) as $part ) {
			$part = self::clean( $part );
			if ( ! self::is_empty( $part ) && ! in_array( $part, $out, true ) ) {
				$out[] = $part;
			}
		}
		return $out;
	}

	/**
	 * Comparable key for a text (case/space/digit insensitive).
	 *
	 * @param string $value Text.
	 * @return string
	 */
	public static function key( $value ) {
		$value = mb_strtolower( self::latin_digits( self::clean( $value ) ), 'UTF-8' );
		return preg_replace( '/\s+/u', ' ', $value );
	}

	/**
	 * Strip a parenthesised hint from a header: "حافظه (Storage)" → "حافظه".
	 *
	 * @param string $header Header text.
	 * @return string
	 */
	public static function header_label( $header ) {
		$label = trim( preg_replace( '/\s*\([^)]*\)\s*/u', ' ', (string) $header ) );
		return '' === $label ? trim( (string) $header ) : $label;
	}

	/**
	 * Replace {tokens} in a template; unknown tokens become empty, then whitespace and
	 * dangling separators are tidied.
	 *
	 * @param string $template Template.
	 * @param array  $tokens   token => value.
	 * @return string
	 */
	public static function render( $template, array $tokens ) {
		$out = preg_replace_callback(
			'/\{([^{}]+)\}/u',
			static function ( $m ) use ( $tokens ) {
				$k = trim( $m[1] );
				return isset( $tokens[ $k ] ) ? (string) $tokens[ $k ] : '';
			},
			(string) $template
		);
		$out = preg_replace( '/\s{2,}/u', ' ', $out );
		$out = preg_replace( '/(\s*[|\-–—،,]\s*)+$/u', '', $out );
		$out = preg_replace( '/^(\s*[|\-–—،,]\s*)+/u', '', $out );
		return trim( $out );
	}

	/**
	 * Cut text to a length on a word boundary.
	 *
	 * @param string $text Text.
	 * @param int    $max  Max characters.
	 * @return string
	 */
	public static function truncate( $text, $max ) {
		$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
		if ( mb_strlen( $text, 'UTF-8' ) <= $max ) {
			return $text;
		}
		$cut   = mb_substr( $text, 0, $max - 1, 'UTF-8' );
		$space = mb_strrpos( $cut, ' ', 0, 'UTF-8' );
		if ( false !== $space && $space > $max * 0.6 ) {
			$cut = mb_substr( $cut, 0, $space, 'UTF-8' );
		}
		return rtrim( $cut, " ،,.-|" ) . '…';
	}

	/**
	 * Join a list for Persian prose: "a، b و c".
	 *
	 * @param string[] $items Items.
	 * @return string
	 */
	public static function join_fa( array $items ) {
		$items = array_values( array_filter( array_map( 'strval', $items ), 'strlen' ) );
		$n     = count( $items );
		if ( $n < 2 ) {
			return $n ? $items[0] : '';
		}
		return implode( '، ', array_slice( $items, 0, -1 ) ) . ' و ' . $items[ $n - 1 ];
	}
}
