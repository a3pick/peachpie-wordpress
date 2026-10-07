<?php
/**
 * Dependency-free XLSX / CSV reader (ZipArchive + SimpleXML).
 *
 * @package SBPI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads every sheet of a workbook into a 2D array of strings.
 */
final class SBPI_Reader {

	/** Safety cap so a malformed file cannot exhaust memory. */
	const MAX_ROWS = 20000;

	/**
	 * Read a file.
	 *
	 * @param string $path      Absolute path.
	 * @param string $extension "xlsx" or "csv".
	 * @return array<int, array{name:string, rows:array<int, string[]>}>
	 * @throws RuntimeException On unreadable input.
	 */
	public static function read( $path, $extension ) {
		$extension = strtolower( $extension );
		if ( 'csv' === $extension ) {
			return array(
				array(
					'name' => 'CSV',
					'rows' => self::read_csv( $path ),
				),
			);
		}
		if ( 'xlsx' === $extension ) {
			return self::read_xlsx( $path );
		}
		throw new RuntimeException( 'فرمت فایل پشتیبانی نمی‌شود. فقط XLSX یا CSV.' );
	}

	/**
	 * CSV reader with delimiter sniffing and BOM removal.
	 *
	 * @param string $path File.
	 * @return array<int, string[]>
	 */
	private static function read_csv( $path ) {
		$fh = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $fh ) {
			throw new RuntimeException( 'فایل CSV خوانده نشد.' );
		}
		$first = (string) fgets( $fh );
		$delim = ',';
		foreach ( array( ';', "\t", ',' ) as $candidate ) {
			if ( substr_count( $first, $candidate ) > substr_count( $first, $delim ) ) {
				$delim = $candidate;
			}
		}
		rewind( $fh );
		$rows = array();
		while ( false !== ( $row = fgetcsv( $fh, 0, $delim, '"', '\\' ) ) && count( $rows ) < self::MAX_ROWS ) {
			if ( empty( $rows ) && isset( $row[0] ) ) {
				$row[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $row[0] );
			}
			$rows[] = array_map( 'strval', $row );
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $rows;
	}

	/**
	 * XLSX reader. Handles shared strings, inline strings, rich text, sparse cells.
	 *
	 * @param string $path File.
	 * @return array<int, array{name:string, rows:array<int, string[]>}>
	 */
	private static function read_xlsx( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new RuntimeException( 'اکستنشن ZipArchive روی سرور فعال نیست؛ فایل را CSV ذخیره کنید یا zip را فعال کنید.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			throw new RuntimeException( 'فایل XLSX معتبر نیست.' );
		}

		$shared = array();
		$xml    = self::xml( $zip, 'xl/sharedStrings.xml' );
		if ( $xml ) {
			foreach ( $xml->si as $si ) {
				$shared[] = self::rich_text( $si );
			}
		}

		$workbook = self::xml( $zip, 'xl/workbook.xml' );
		$rels     = self::xml( $zip, 'xl/_rels/workbook.xml.rels' );
		if ( ! $workbook || ! $rels ) {
			$zip->close();
			throw new RuntimeException( 'ساختار فایل XLSX ناقص است.' );
		}

		$targets = array();
		foreach ( $rels->Relationship as $rel ) {
			$target = (string) $rel['Target'];
			$target = 0 === strpos( $target, '/' ) ? ltrim( $target, '/' ) : 'xl/' . $target;
			$targets[ (string) $rel['Id'] ] = $target;
		}

		$sheets = array();
		foreach ( $workbook->sheets->sheet as $sheet ) {
			$rid  = (string) $sheet->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' )['id'];
			$file = isset( $targets[ $rid ] ) ? $targets[ $rid ] : '';
			$data = $file ? self::xml( $zip, $file ) : null;
			if ( ! $data ) {
				continue;
			}
			$sheets[] = array(
				'name' => (string) $sheet['name'],
				'rows' => self::sheet_rows( $data, $shared ),
			);
		}
		$zip->close();
		return $sheets;
	}

	/**
	 * Convert a worksheet XML to rows.
	 *
	 * @param SimpleXMLElement $data   Sheet.
	 * @param string[]         $shared Shared strings.
	 * @return array<int, string[]>
	 */
	private static function sheet_rows( SimpleXMLElement $data, array $shared ) {
		$rows = array();
		if ( ! isset( $data->sheetData->row ) ) {
			return $rows;
		}
		foreach ( $data->sheetData->row as $row ) {
			if ( count( $rows ) >= self::MAX_ROWS ) {
				break;
			}
			$cells = array();
			$next  = 0;
			foreach ( $row->c as $c ) {
				$ref = (string) $c['r'];
				$idx = '' !== $ref ? self::column_index( $ref ) : $next;
				$next = $idx + 1;
				$type = (string) $c['t'];
				if ( 's' === $type ) {
					$i     = (int) $c->v;
					$value = isset( $shared[ $i ] ) ? $shared[ $i ] : '';
				} elseif ( 'inlineStr' === $type ) {
					$value = self::rich_text( $c->is );
				} elseif ( 'b' === $type ) {
					$value = '1' === (string) $c->v ? 'TRUE' : 'FALSE';
				} else {
					$value = (string) $c->v;
					// Excel stores 0.1+0.2 style floats; tidy whole numbers like "128.0".
					if ( is_numeric( $value ) && false !== strpos( $value, '.' ) && (float) $value === floor( (float) $value ) ) {
						$value = (string) (int) $value;
					}
				}
				$cells[ $idx ] = $value;
			}
			$line = array();
			if ( $cells ) {
				$max = max( array_keys( $cells ) );
				for ( $i = 0; $i <= $max; $i++ ) {
					$line[] = isset( $cells[ $i ] ) ? $cells[ $i ] : '';
				}
			}
			$rows[] = $line;
		}
		return $rows;
	}

	/**
	 * "AB12" → 27.
	 *
	 * @param string $ref Cell reference.
	 * @return int Zero-based column index.
	 */
	private static function column_index( $ref ) {
		$letters = preg_replace( '/[^A-Z]/', '', strtoupper( $ref ) );
		$n       = 0;
		$len     = strlen( $letters );
		for ( $i = 0; $i < $len; $i++ ) {
			$n = $n * 26 + ( ord( $letters[ $i ] ) - 64 );
		}
		return max( 0, $n - 1 );
	}

	/**
	 * Text of an <si>/<is> node (plain or rich runs; phonetic runs ignored).
	 *
	 * @param SimpleXMLElement $node Node.
	 * @return string
	 */
	private static function rich_text( $node ) {
		if ( isset( $node->t ) ) {
			return (string) $node->t;
		}
		$text = '';
		if ( isset( $node->r ) ) {
			foreach ( $node->r as $run ) {
				$text .= (string) $run->t;
			}
		}
		return $text;
	}

	/**
	 * Load an XML part from the archive without network/entity expansion.
	 *
	 * @param ZipArchive $zip  Archive.
	 * @param string     $name Entry.
	 * @return SimpleXMLElement|null
	 */
	private static function xml( ZipArchive $zip, $name ) {
		$raw = $zip->getFromName( $name );
		if ( false === $raw || '' === $raw ) {
			return null;
		}
		$prev = libxml_use_internal_errors( true );
		$xml  = simplexml_load_string( $raw, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		return $xml ? $xml : null;
	}
}
