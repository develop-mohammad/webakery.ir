<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * خروجی CSV (Excel فارسی) و SpreadsheetML راست‌چین.
 */
class WBE_Export {

	public static function headers() {
		return array(
			'محصول',
			'SKU',
			'دسته‌بندی',
			'برند',
			'تاریخ انقضا',
			'روز تا انقضا',
			'قیمت فعال',
			'تخفیف ٪',
			'موجودی فعال',
			'تعداد رزرو',
			'تعداد فروش',
			'مبلغ فروش',
			'وضعیت',
		);
	}

	private static function status_label( $row ) {
		if ( 'empty' === $row['status'] ) {
			return 'بدون بچ فعال';
		}
		if ( 'near' === $row['status'] ) {
			return 'نزدیک به انقضا';
		}
		return 'فعال';
	}

	private static function matrix( array $rows ) {
		$out = array();
		foreach ( $rows as $r ) {
			$out[] = array(
				$r['name'],
				$r['sku'],
				$r['category'],
				$r['brand'],
				$r['expiry_fa'],
				null === $r['days'] ? '—' : (string) $r['days'],
				(string) $r['price'],
				isset( $r['discount'] ) ? (string) $r['discount'] : '0',
				(string) $r['stock'],
				(string) $r['reserves'],
				(string) $r['sold_qty'],
				(string) $r['sold_amt'],
				self::status_label( $r ),
			);
		}
		return $out;
	}

	public static function csv( array $rows ) {
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		$filename = 'expiry-report-' . gmdate( 'Y-m-d' ) . '.csv';
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		$fp = fopen( 'php://output', 'w' );
		fwrite( $fp, "\xEF\xBB\xBF" );
		fputcsv( $fp, self::headers() );
		foreach ( self::matrix( $rows ) as $line ) {
			fputcsv( $fp, $line );
		}
		fclose( $fp );
		exit;
	}

	public static function xls( array $rows ) {
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		$filename = 'expiry-report-' . gmdate( 'Y-m-d' ) . '.xls';
		header( 'Content-Type: application/vnd.ms-excel; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		echo self::xml_document( $rows );
		exit;
	}

	public static function xml_document( array $rows ) {
		$esc = function ( $v ) {
			return htmlspecialchars( (string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
		};
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
		$xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
		$xml .= '<Styles>';
		$xml .= '<Style ss:ID="hdr"><Font ss:Bold="1"/><Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/></Style>';
		$xml .= '<Style ss:ID="near"><Interior ss:Color="#FEF3C7" ss:Pattern="Solid"/></Style>';
		$xml .= '<Style ss:ID="empty"><Interior ss:Color="#FEE2E2" ss:Pattern="Solid"/></Style>';
		$xml .= '</Styles>';
		$xml .= '<Worksheet ss:Name="گزارش انقضا" ss:RightToLeft="1"><Table>';
		$xml .= '<Row>';
		foreach ( self::headers() as $h ) {
			$xml .= '<Cell ss:StyleID="hdr"><Data ss:Type="String">' . $esc( $h ) . '</Data></Cell>';
		}
		$xml .= '</Row>';
		foreach ( $rows as $r ) {
			$style = '';
			if ( isset( $r['status'] ) && 'near' === $r['status'] ) {
				$style = ' ss:StyleID="near"';
			} elseif ( isset( $r['status'] ) && 'empty' === $r['status'] ) {
				$style = ' ss:StyleID="empty"';
			}
			$xml .= '<Row>';
			foreach ( self::matrix( array( $r ) )[0] as $cell ) {
				$xml .= '<Cell' . $style . '><Data ss:Type="String">' . $esc( $cell ) . '</Data></Cell>';
			}
			$xml .= '</Row>';
		}
		$xml .= '</Table></Worksheet></Workbook>';
		return $xml;
	}
}
