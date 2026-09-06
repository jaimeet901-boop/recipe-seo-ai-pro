<?php
declare(strict_types=1);

/**
 * PSR-4 autoloader fallback for the RecipeSeoAiPro namespace.
 *
 * Used when Composer’s vendor/autoload.php is not present. Composer remains
 * the preferred autoloader when available; this class guarantees the skeleton
 * loads on every WordPress install without a build step.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Autoloader
 *
 * Registers spl_autoload for RecipeSeoAiPro\* → src/*.php
 */
final class Autoloader {

	/** @var string Absolute path to the src/ directory with trailing slash. */
	private string $base_dir;

	/**
	 * @param string $base_dir Absolute path to src/ (trailing slash optional).
	 */
	public function __construct( string $base_dir ) {
		$this->base_dir = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;
	}

	/**
	 * Register this autoloader with SPL.
	 */
	public function register(): void {
		spl_autoload_register( array( $this, 'load' ) );
	}

	/**
	 * Attempt to load a class file for the given fully-qualified class name.
	 *
	 * @param string $class Fully-qualified class name.
	 */
	public function load( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = $this->base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Bootstrap Composer autoload if present, otherwise the internal fallback.
	 *
	 * Safe to call multiple times; does not alter legacy include loading.
	 *
	 * @param string $plugin_dir Absolute plugin root with trailing slash.
	 */
	public static function bootstrap( string $plugin_dir ): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		$composer = $plugin_dir . 'vendor/autoload.php';
		if ( is_readable( $composer ) ) {
			require_once $composer;
			return;
		}

		$autoloader = new self( $plugin_dir . 'src' );
		$autoloader->register();
	}
}
