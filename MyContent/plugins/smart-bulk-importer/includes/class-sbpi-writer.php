<?php
/**
 * Minimal dependency-free XLSX writer (inline strings, RTL sheets, bold frozen header).
 *
 * @package SBPI
 */

defined( 'ABSPATH' ) || exit;

/**
 * XLSX writer.
 */
final class SBPI_Writer {

	/**
	 * Build an .xlsx file.
	 *
	 * @param string $path   Destination file.
	 * @param array  $sheets [ ['name' => string, 'rows' => array<array>, 'widths' => int[], 'header' => bool] ].
	 *                       A cell may be a scalar or ['v' => value, 's' => 'bold'|'section'].
	 * @throws RuntimeException When zip is unavailable.
	 */
	public static function write( $path, array $sheets ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new RuntimeException( 'اکستنشن ZipArchive روی سرور فعال نیست.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			throw new RuntimeException( 'ساخت فایل خروجی ممکن نشد.' );
		}

		$n         = count( $sheets );
		$overrides = '';
		$wb_sheets = '';
		$wb_rels   = '';
		foreach ( array_values( $sheets ) as $i => $sheet ) {
			$id         = $i + 1;
			$overrides .= '<Override PartName="/xl/worksheets/sheet' . $id . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
			$wb_sheets .= '<sheet name="' . self::x( mb_substr( preg_replace( '/[\[\]\*\?\/\\\\:]/u', ' ', $sheet['name'] ), 0, 31 ) ) . '" sheetId="' . $id . '" r:id="rId' . $id . '"/>';
			$wb_rels   .= '<Relationship Id="rId' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $id . '.xml"/>';
			$zip->addFromString( 'xl/worksheets/sheet' . $id . '.xml', self::sheet( $sheet ) );
		}
		$wb_rels .= '<Relationship Id="rId' . ( $n + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

		$zip->addFromString(
			'[Content_Types].xml',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . $overrides . '</Types>'
		);
		$zip->addFromString(
			'_rels/.rels',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>'
		);
		$zip->addFromString(
			'xl/workbook.xml',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $wb_sheets . '</sheets></workbook>'
		);
		$zip->addFromString(
			'xl/_rels/workbook.xml.rels',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $wb_rels . '</Relationships>'
		);
		// Styles: 0 normal, 1 bold header (grey fill), 2 section row (bold, light fill), 3 wrap text.
		$zip->addFromString(
			'xl/styles.xml',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="2"><font><sz val="11"/><name val="Tahoma"/></font><font><b/><sz val="11"/><name val="Tahoma"/></font></fonts>'
			. '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
			. '<fill><patternFill patternType="solid"><fgColor rgb="FFD9D9D9"/></patternFill></fill>'
			. '<fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/></patternFill></fill></fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
			. '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
			. '<xf numFmtId="0" fontId="1" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf></cellXfs>'
			. '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>'
		);
		$zip->close();
	}

	/**
	 * Worksheet XML.
	 *
	 * @param array $sheet Sheet.
	 * @return string
	 */
	private static function sheet( array $sheet ) {
		$styles = array( 'bold' => 1, 'section' => 2, 'wrap' => 3 );
		$header = ! empty( $sheet['header'] );
		$xml    = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
		$xml   .= '<sheetViews><sheetView rightToLeft="1" workbookViewId="0">';
		if ( $header ) {
			$xml .= '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>';
		}
		$xml .= '</sheetView></sheetViews>';
		if ( ! empty( $sheet['widths'] ) ) {
			$xml .= '<cols>';
			foreach ( $sheet['widths'] as $i => $w ) {
				$xml .= '<col min="' . ( $i + 1 ) . '" max="' . ( $i + 1 ) . '" width="' . (int) $w . '" customWidth="1"/>';
			}
			$xml .= '</cols>';
		}
		$xml .= '<sheetData>';
		foreach ( array_values( $sheet['rows'] ) as $r => $row ) {
			$xml .= '<row r="' . ( $r + 1 ) . '">';
			foreach ( array_values( $row ) as $c => $cell ) {
				$style = 0;
				if ( is_array( $cell ) ) {
					$style = isset( $styles[ $cell['s'] ] ) ? $styles[ $cell['s'] ] : 0;
					$cell  = $cell['v'];
				} elseif ( $header && 0 === $r ) {
					$style = 1;
				}
				if ( null === $cell || '' === $cell ) {
					if ( $style ) {
						$xml .= '<c r="' . self::ref( $c, $r ) . '" s="' . $style . '"/>';
					}
					continue;
				}
				$s = $style ? ' s="' . $style . '"' : '';
				if ( is_int( $cell ) || is_float( $cell ) ) {
					$xml .= '<c r="' . self::ref( $c, $r ) . '"' . $s . '><v>' . $cell . '</v></c>';
				} else {
					$xml .= '<c r="' . self::ref( $c, $r ) . '" t="inlineStr"' . $s . '><is><t xml:space="preserve">' . self::x( (string) $cell ) . '</t></is></c>';
				}
			}
			$xml .= '</row>';
		}
		return $xml . '</sheetData></worksheet>';
	}

	/**
	 * Cell reference: (0,0) → A1.
	 *
	 * @param int $c Column.
	 * @param int $r Row.
	 * @return string
	 */
	private static function ref( $c, $r ) {
		$letters = '';
		for ( $n = $c + 1; $n > 0; $n = intdiv( $n - 1, 26 ) ) {
			$letters = chr( 65 + ( $n - 1 ) % 26 ) . $letters;
		}
		return $letters . ( $r + 1 );
	}

	/**
	 * XML-escape and strip characters invalid in XML 1.0.
	 *
	 * @param string $s Text.
	 * @return string
	 */
	private static function x( $s ) {
		$s = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string) $s );
		return htmlspecialchars( $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	/**
	 * Stream a file to the browser and delete it.
	 *
	 * @param string $path     File.
	 * @param string $filename Download name.
	 */
	public static function send( $path, $filename ) {
		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $filename ) . '"; filename*=UTF-8\'\'' . rawurlencode( $filename ) );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		wp_delete_file( $path );
		exit;
	}
}
