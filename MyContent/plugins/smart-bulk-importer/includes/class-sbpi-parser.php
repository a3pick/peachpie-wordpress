<?php
/**
 * Turns raw sheets into structured tables: title, header, section context, data rows.
 *
 * Handles "human" spreadsheets like:
 *   [Title row]
 *   [Header row]           N | MODEL | حافظه | رنگ | GRADE
 *   [Section row]          iPhone 13 — نو
 *   [Data rows]            1 | iPhone 13 | 128 GB | Blue | Pink | نو
 *   [Note row]             نکته: ...
 * and 2-column "guide" sheets which become a glossary for product descriptions.
 *
 * @package SBPI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sheet parser.
 */
final class SBPI_Parser {

	/** Virtual columns appended to every products sheet. */
	const VIRTUAL = array(
		'__s1'    => 'بخش ۱ (از سطر عنوان بخش)',
		'__s2'    => 'بخش ۲ (از سطر عنوان بخش)',
		'__s3'    => 'بخش ۳ (از سطر عنوان بخش)',
		'__sheet' => 'نام شیت',
	);

	/** Header words that identify the header row. */
	const HEADER_HINTS = array( 'model', 'name', 'title', 'sku', 'price', 'grade', 'مدل', 'نام', 'عنوان', 'قیمت', 'کد', 'ردیف' );

	/**
	 * Parse all sheets.
	 *
	 * @param array $sheets Output of SBPI_Reader::read().
	 * @return array
	 */
	public static function parse( array $sheets ) {
		$out = array();
		foreach ( $sheets as $sheet ) {
			$out[] = self::parse_sheet( $sheet['name'], $sheet['rows'] );
		}
		return $out;
	}

	/**
	 * Parse one sheet.
	 *
	 * @param string $name Sheet name.
	 * @param array  $raw  Raw rows.
	 * @return array
	 */
	public static function parse_sheet( $name, array $raw ) {
		$rows = array();
		foreach ( $raw as $i => $row ) {
			$clean = array_map( array( 'SBPI_Util', 'clean' ), $row );
			$rows[ $i ] = $clean;
		}

		$result = array(
			'name'    => SBPI_Util::clean( $name ),
			'kind'    => 'empty',
			'title'   => '',
			'headers' => array(),
			'rows'    => array(),
			'notes'   => array(),
			'glossary'=> array(),
			'samples' => array(),
		);

		// Instruction sheets (e.g. in our template) are never imported.
		if ( false !== mb_strpos( $result['name'], 'نادیده' ) || 0 === strpos( $result['name'], '!' ) ) {
			$result['kind'] = 'ignored';
			return $result;
		}

		$widths = array_map( array( __CLASS__, 'filled' ), $rows );
		if ( ! $widths || max( $widths ) === 0 ) {
			return $result;
		}

		if ( max( $widths ) <= 2 ) {
			$result['kind']     = 'glossary';
			$result['glossary'] = self::glossary( $rows );
			return $result;
		}

		$header_index = self::find_header( $rows, $widths );
		if ( null === $header_index ) {
			return $result;
		}

		foreach ( $rows as $i => $row ) {
			if ( $i >= $header_index ) {
				break;
			}
			if ( $widths[ $i ] >= 1 && '' === $result['title'] ) {
				$result['title'] = self::first( $row );
			}
		}

		$headers = $rows[ $header_index ];
		foreach ( $headers as $c => $h ) {
			$headers[ $c ] = '' === $h ? 'ستون ' . ( $c + 1 ) : $h;
		}
		$result['kind']    = 'products';
		$result['headers'] = $headers;

		$section = array();
		$count   = count( $rows );
		for ( $i = $header_index + 1; $i < $count; $i++ ) {
			$row   = $rows[ $i ];
			$width = $widths[ $i ];
			if ( 0 === $width ) {
				continue;
			}
			if ( 1 === $width ) {
				$text = self::first( $row );
				if ( preg_match( '/^(نکته|توجه|note)\s*[:：]/iu', $text ) ) {
					$result['notes'][] = $text;
				} else {
					$section = self::section_parts( $text );
				}
				continue;
			}
			$cells = array();
			foreach ( $headers as $c => $unused ) {
				$cells[ $c ] = isset( $row[ $c ] ) ? $row[ $c ] : '';
			}
			$result['rows'][] = array(
				'line'    => $i + 1,
				'cells'   => $cells,
				'section' => $section,
			);
		}

		foreach ( $headers as $c => $unused ) {
			$samples = array();
			foreach ( $result['rows'] as $r ) {
				$v = $r['cells'][ $c ];
				if ( ! SBPI_Util::is_empty( $v ) && ! in_array( $v, $samples, true ) ) {
					$samples[] = $v;
				}
				if ( count( $samples ) >= 3 ) {
					break;
				}
			}
			$result['samples'][ $c ] = $samples;
		}
		return $result;
	}

	/**
	 * Count non-empty cells.
	 *
	 * @param string[] $row Row.
	 * @return int
	 */
	private static function filled( array $row ) {
		$n = 0;
		foreach ( $row as $cell ) {
			if ( '' !== $cell ) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * First non-empty cell.
	 *
	 * @param string[] $row Row.
	 * @return string
	 */
	private static function first( array $row ) {
		foreach ( $row as $cell ) {
			if ( '' !== $cell ) {
				return $cell;
			}
		}
		return '';
	}

	/**
	 * Locate the header row: first row (within 20) containing a header hint and ≥2 cells,
	 * else the first row with ≥3 cells.
	 *
	 * @param array $rows   Rows.
	 * @param int[] $widths Filled counts.
	 * @return int|null
	 */
	private static function find_header( array $rows, array $widths ) {
		$fallback = null;
		foreach ( $rows as $i => $row ) {
			if ( $i > 20 ) {
				break;
			}
			if ( $widths[ $i ] < 2 ) {
				continue;
			}
			foreach ( $row as $cell ) {
				$key = SBPI_Util::key( SBPI_Util::header_label( $cell ) );
				if ( in_array( $key, self::HEADER_HINTS, true ) ) {
					return $i;
				}
			}
			if ( null === $fallback && $widths[ $i ] >= 3 ) {
				$fallback = $i;
			}
		}
		return $fallback;
	}

	/**
	 * "PLAY STATION 5 — ACCENT (اکانتی) — استوک" → three parts.
	 *
	 * @param string $text Section title.
	 * @return string[]
	 */
	public static function section_parts( $text ) {
		$parts = preg_split( '/\s+[—–]\s+|\s*[—–]\s*/u', $text );
		$parts = array_values( array_filter( array_map( array( 'SBPI_Util', 'clean' ), $parts ), 'strlen' ) );
		return $parts;
	}

	/**
	 * Two-column guide sheet → [ [term, explanation], ... ].
	 *
	 * @param array $rows Rows.
	 * @return array
	 */
	private static function glossary( array $rows ) {
		$items = array();
		$first = true;
		foreach ( $rows as $row ) {
			$cells = array_values( array_filter( $row, 'strlen' ) );
			if ( 2 !== count( $cells ) ) {
				continue;
			}
			// The first 2-cell row is the table header ("موضوع | توضیح").
			if ( $first ) {
				$first = false;
				if ( mb_strlen( $cells[1], 'UTF-8' ) < 25 ) {
					continue;
				}
			}
			$items[] = array( $cells[0], $cells[1] );
		}
		return $items;
	}
}
