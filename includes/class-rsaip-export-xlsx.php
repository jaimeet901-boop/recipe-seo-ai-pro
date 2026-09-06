<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSAIP_Export_XLSX {
	public static function build( array $headers, array $rows, string $sheet_name = 'Report' ): string {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return '';
		}

		$tmp = wp_tempnam( 'rsaip.xlsx' );
		if ( ! $tmp ) {
			return '';
		}

		$zip = new ZipArchive();
		$ok  = $zip->open( $tmp, ZipArchive::OVERWRITE );
		if ( $ok !== true ) {
			return '';
		}

		$zip->addFromString( '[Content_Types].xml', self::content_types() );
		$zip->addFromString( '_rels/.rels', self::rels_root() );
		$zip->addFromString( 'xl/workbook.xml', self::workbook_xml( $sheet_name ) );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', self::workbook_rels() );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', self::sheet_xml( $headers, $rows ) );
		$zip->addFromString( 'xl/styles.xml', self::styles_xml() );
		$zip->close();

		$bin = (string) file_get_contents( $tmp );
		@unlink( $tmp );
		return $bin;
	}

	private static function content_types(): string {
		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '</Types>';
	}

	private static function rels_root(): string {
		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';
	}

	private static function workbook_xml( string $sheet_name ): string {
		$sheet_name = self::xml_escape( $sheet_name );
		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets>'
			. '<sheet name="' . $sheet_name . '" sheetId="1" r:id="rId1"/>'
			. '</sheets>'
			. '</workbook>';
	}

	private static function workbook_rels(): string {
		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '</Relationships>';
	}

	private static function styles_xml(): string {
		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="1"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>'
			. '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs>'
			. '</styleSheet>';
	}

	private static function sheet_xml( array $headers, array $rows ): string {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<sheetData>';

		$r = 1;
		$xml .= self::row_xml( $r, $headers );
		$r++;
		foreach ( $rows as $row ) {
			$xml .= self::row_xml( $r, $row );
			$r++;
		}

		$xml .= '</sheetData></worksheet>';
		return $xml;
	}

	private static function row_xml( int $row_index, array $cells ): string {
		$xml = '<row r="' . $row_index . '">';
		$col = 1;
		foreach ( $cells as $v ) {
			$ref = self::col_name( $col ) . $row_index;
			$val = self::xml_escape( (string) $v );
			$xml .= '<c r="' . $ref . '" t="inlineStr" s="0"><is><t>' . $val . '</t></is></c>';
			$col++;
		}
		$xml .= '</row>';
		return $xml;
	}

	private static function col_name( int $n ): string {
		$name = '';
		while ( $n > 0 ) {
			$rem = ( $n - 1 ) % 26;
			$name = chr( 65 + $rem ) . $name;
			$n = (int) floor( ( $n - 1 ) / 26 );
		}
		return $name;
	}

	private static function xml_escape( string $s ): string {
		return htmlspecialchars( $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}
}

