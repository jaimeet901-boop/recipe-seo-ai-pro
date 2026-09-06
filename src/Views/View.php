<?php
declare(strict_types=1);

/**
 * PHP view renderer for admin templates and partials.
 *
 * Phase 2D: loads templates from src/Views/{admin,partials}/.
 * Controllers (RSAIP_Admin) prepare data; templates own HTML only.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Views;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class View
 */
final class View {

	/**
	 * Absolute path to the Views directory.
	 */
	public static function base_path(): string {
		return dirname( __DIR__ ) . '/Views';
	}

	/**
	 * Resolve a template path (relative, no .php) to an absolute file path.
	 *
	 * @param string $template Relative template path without .php (e.g. "admin/dashboard").
	 * @return string Absolute path, or empty string if invalid / missing.
	 */
	public static function resolve( string $template ): string {
		$template = str_replace( '\\', '/', $template );
		$template = ltrim( $template, '/' );

		if ( $template === '' || strpos( $template, '..' ) !== false ) {
			return '';
		}

		$file = self::base_path() . '/' . $template . '.php';
		if ( ! is_file( $file ) ) {
			return '';
		}

		return $file;
	}

	/**
	 * Render a template by relative name (e.g. "admin/dashboard").
	 *
	 * @param string               $template Relative template path without .php.
	 * @param array<string, mixed> $data     Variables extracted into template scope.
	 */
	public static function render( string $template, array $data = array() ): void {
		$file = self::resolve( $template );
		if ( $file === '' ) {
			return;
		}

		// Isolated scope so template locals cannot clobber View statics.
		( static function ( string $__rsaip_view_file, array $__rsaip_view_data ): void {
			extract( $__rsaip_view_data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- intentional view scope.
			include $__rsaip_view_file;
		} )( $file, $data );
	}

	/**
	 * Render a template and return its HTML as a string.
	 *
	 * @param string               $template Relative template path without .php.
	 * @param array<string, mixed> $data     Variables extracted into template scope.
	 */
	public static function render_to_string( string $template, array $data = array() ): string {
		ob_start();
		self::render( $template, $data );
		return (string) ob_get_clean();
	}
}
