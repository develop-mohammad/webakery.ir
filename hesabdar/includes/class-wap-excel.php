<?php
defined( 'ABSPATH' ) || exit;

/**
 * خروجی Excel واقعی (.xlsx) بدون وابستگی خارجی — Office Open XML.
 */
class WAP_Excel {

	/**
	 * @param array<string,array{headers:array<int,string>,rows:array<int,array<int,scalar>>}> $sheets
	 */
	public static function download( array $sheets, string $filename ): void {
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		$bin = self::build( $sheets );
		if ( $bin === '' ) {
			wp_die( 'ساخت فایل اکسل ناموفق بود (ZipArchive لازم است).' );
		}
		$filename = preg_replace( '/\.xlsx$/i', '', $filename ) . '.xlsx';
		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $bin ) );
		header( 'Pragma: no-cache' );
		echo $bin; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * @param array<string,array{headers:array<int,string>,rows:array<int,array<int,scalar>>}> $sheets
	 */
	public static function build( array $sheets ): string {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return '';
		}
		$tmp = wp_tempnam( 'wap-xlsx-' );
		if ( ! $tmp ) {
			$tmp = sys_get_temp_dir() . '/wap-' . uniqid( 'xlsx', true ) . '.xlsx';
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE | ZipArchive::CREATE ) ) {
			return '';
		}

		$sheet_names = array_keys( $sheets );
		if ( empty( $sheet_names ) ) {
			$sheet_names = array( 'Sheet1' );
			$sheets      = array( 'Sheet1' => array( 'headers' => array(), 'rows' => array() ) );
		}

		$zip->addFromString( '[Content_Types].xml', self::content_types_xml( count( $sheet_names ) ) );
		$zip->addFromString( '_rels/.rels', self::root_rels_xml() );
		$zip->addFromString( 'xl/workbook.xml', self::workbook_xml( $sheet_names ) );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', self::workbook_rels_xml( count( $sheet_names ) ) );
		$zip->addFromString( 'xl/styles.xml', self::styles_xml() );

		$i = 1;
		foreach ( $sheet_names as $name ) {
			$pack = $sheets[ $name ];
			$zip->addFromString(
				'xl/worksheets/sheet' . $i . '.xml',
				self::sheet_xml( $pack['headers'] ?? array(), $pack['rows'] ?? array() )
			);
			$i++;
		}
		$zip->close();
		$bin = (string) file_get_contents( $tmp );
		@unlink( $tmp );
		return $bin;
	}

	/** @param array<int,string> $names */
	private static function content_types_xml( int $count ): string {
		$sheets = '';
		for ( $i = 1; $i <= $count; $i++ ) {
			$sheets .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		}
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. $sheets
			. '</Types>';
	}

	private static function root_rels_xml(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';
	}

	/** @param array<int,string> $names */
	private static function workbook_xml( array $names ): string {
		$sheets = '';
		$i      = 1;
		foreach ( $names as $name ) {
			$safe = self::xml( self::safe_sheet_name( (string) $name, $i ) );
			$sheets .= '<sheet name="' . $safe . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
			$i++;
		}
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
			. 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets>' . $sheets . '</sheets></workbook>';
	}

	private static function workbook_rels_xml( int $count ): string {
		$rels = '';
		for ( $i = 1; $i <= $count; $i++ ) {
			$rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
		}
		$rels .= '<Relationship Id="rId' . ( $count + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. $rels
			. '</Relationships>';
	}

	private static function styles_xml(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="2">'
			. '<font><sz val="11"/><name val="Calibri"/></font>'
			. '<font><b/><sz val="11"/><name val="Calibri"/></font>'
			. '</fonts>'
			. '<fills count="2"><fill/><fill><patternFill patternType="gray125"/></fill></fills>'
			. '<borders count="1"><border/></borders>'
			. '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
			. '<cellXfs count="2"><xf fontId="0"/><xf fontId="1" applyFont="1"/></cellXfs>'
			. '</styleSheet>';
	}

	/**
	 * @param array<int,string>         $headers
	 * @param array<int,array<int,mixed>> $rows
	 */
	private static function sheet_xml( array $headers, array $rows ): string {
		$xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
		$r    = 1;
		if ( $headers ) {
			$xml .= '<row r="' . $r . '">';
			$col = 0;
			foreach ( $headers as $h ) {
				$xml .= self::cell( $col, $r, $h, true );
				$col++;
			}
			$xml .= '</row>';
			$r++;
		}
		foreach ( $rows as $row ) {
			$xml .= '<row r="' . $r . '">';
			$col  = 0;
			foreach ( array_values( $row ) as $val ) {
				$xml .= self::cell( $col, $r, $val, false );
				$col++;
			}
			$xml .= '</row>';
			$r++;
		}
		$xml .= '</sheetData></worksheet>';
		return $xml;
	}

	/** @param mixed $val */
	private static function cell( int $col, int $row, $val, bool $header ): string {
		$ref = self::col_letter( $col ) . $row;
		$s   = $header ? ' s="1"' : '';
		if ( is_int( $val ) || is_float( $val ) || ( is_string( $val ) && is_numeric( $val ) && ! preg_match( '/^0\d+/', $val ) ) ) {
			return '<c r="' . $ref . '"' . $s . '><v>' . self::xml( (string) $val ) . '</v></c>';
		}
		$text = self::xml( (string) $val );
		return '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . $text . '</t></is></c>';
	}

	private static function col_letter( int $index ): string {
		$index++;
		$letter = '';
		while ( $index > 0 ) {
			$mod    = ( $index - 1 ) % 26;
			$letter = chr( 65 + $mod ) . $letter;
			$index  = (int) ( ( $index - $mod ) / 26 );
		}
		return $letter;
	}

	private static function safe_sheet_name( string $name, int $fallback_i ): string {
		$name = preg_replace( '/[\\\\\/\?\*\[\]:]/', ' ', $name );
		$name = trim( (string) $name );
		if ( $name === '' ) {
			$name = 'Sheet' . $fallback_i;
		}
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $name, 0, 31 );
		}
		return substr( $name, 0, 31 );
	}

	private static function xml( string $s ): string {
		return htmlspecialchars( $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}
}
