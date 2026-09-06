<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSAIP_Export_PDF {
	public static function build_simple( string $title, array $lines ): string {
		$objects = array();

		$font_obj = 1;
		$pages_obj = 2;
		$page_obj = 3;
		$content_obj = 4;
		$catalog_obj = 5;

		$objects[ $font_obj ] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
		$objects[ $pages_obj ] = "<< /Type /Pages /Kids [ {$page_obj} 0 R ] /Count 1 >>";
		$objects[ $page_obj ] = "<< /Type /Page /Parent {$pages_obj} 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 {$font_obj} 0 R >> >> /Contents {$content_obj} 0 R >>";

		$content = self::build_content_stream( $title, $lines );
		$objects[ $content_obj ] = "<< /Length " . strlen( $content ) . " >>\nstream\n" . $content . "\nendstream";
		$objects[ $catalog_obj ] = "<< /Type /Catalog /Pages {$pages_obj} 0 R >>";

		$pdf = "%PDF-1.4\n";
		$xref = array();
		$xref[] = 0;

		for ( $i = 1; $i <= 5; $i++ ) {
			$xref[ $i ] = strlen( $pdf );
			$pdf .= $i . " 0 obj\n" . $objects[ $i ] . "\nendobj\n";
		}

		$start_xref = strlen( $pdf );
		$pdf .= "xref\n0 6\n";
		$pdf .= "0000000000 65535 f \n";
		for ( $i = 1; $i <= 5; $i++ ) {
			$pdf .= str_pad( (string) $xref[ $i ], 10, '0', STR_PAD_LEFT ) . " 00000 n \n";
		}
		$pdf .= "trailer\n<< /Size 6 /Root {$catalog_obj} 0 R >>\nstartxref\n{$start_xref}\n%%EOF";

		return $pdf;
	}

	private static function build_content_stream( string $title, array $lines ): string {
		$y = 760;
		$leading = 14;

		$out = "BT\n/F1 14 Tf\n50 {$y} Td\n";
		$out .= "(" . self::pdf_escape( $title ) . ") Tj\n";
		$out .= "ET\n";

		$y -= 28;
		$out .= "BT\n/F1 10 Tf\n50 {$y} Td\n";

		$line_count = 0;
		foreach ( $lines as $line ) {
			$line = (string) $line;
			$line = preg_replace( "/[\r\n]+/", ' ', $line );
			$line = trim( (string) $line );
			if ( $line === '' ) {
				continue;
			}
			$chunks = self::wrap_line( $line, 95 );
			foreach ( $chunks as $c ) {
				$out .= "(" . self::pdf_escape( $c ) . ") Tj\n0 -" . $leading . " Td\n";
				$line_count++;
				if ( $line_count > 45 ) {
					break 2;
				}
			}
		}

		$out .= "ET\n";
		return $out;
	}

	private static function wrap_line( string $text, int $max_len ): array {
		if ( strlen( $text ) <= $max_len ) {
			return array( $text );
		}
		$words = preg_split( '/\s+/', $text );
		if ( ! is_array( $words ) ) {
			return array( substr( $text, 0, $max_len ) );
		}
		$lines = array();
		$cur = '';
		foreach ( $words as $w ) {
			$w = (string) $w;
			if ( $cur === '' ) {
				$cur = $w;
				continue;
			}
			if ( strlen( $cur . ' ' . $w ) > $max_len ) {
				$lines[] = $cur;
				$cur = $w;
			} else {
				$cur .= ' ' . $w;
			}
		}
		if ( $cur !== '' ) {
			$lines[] = $cur;
		}
		return $lines;
	}

	private static function pdf_escape( string $s ): string {
		if ( function_exists( 'iconv' ) ) {
			$converted = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s );
			if ( is_string( $converted ) && $converted !== '' ) {
				$s = $converted;
			}
		}
		$s = str_replace( "\\", "\\\\", $s );
		$s = str_replace( "(", "\\(", $s );
		$s = str_replace( ")", "\\)", $s );
		$s = preg_replace( "/[^\x09\x0A\x0D\x20-\xFF]/", '', $s );
		return (string) $s;
	}
}

