<?php
declare(strict_types=1);

/**
 * Export saved content briefs (Phase 3.2).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentBrief;

use RecipeSeoAiPro\Contracts\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BriefExportService
 */
final class BriefExportService {

	private BriefManagerService $manager;

	private LoggerInterface $logger;

	public function __construct( BriefManagerService $manager, LoggerInterface $logger ) {
		$this->manager = $manager;
		$this->logger  = $logger;
	}

	/**
	 * @return array{filename: string, mime: string, content: string, encoding: string}|\WP_Error
	 */
	public function export( int $id, string $format ) {
		$brief = $this->manager->get( $id );
		if ( is_wp_error( $brief ) ) {
			return $brief;
		}

		$format = sanitize_key( $format );
		$slug   = ! empty( $brief['suggested_slug'] ) ? (string) $brief['suggested_slug'] : 'content-brief-' . $id;
		$slug   = sanitize_title( $slug );

		switch ( $format ) {
			case 'json':
				$content = (string) wp_json_encode( $brief, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				return array(
					'filename' => $slug . '.json',
					'mime'     => 'application/json',
					'content'  => $content,
					'encoding' => 'utf8',
				);

			case 'markdown':
			case 'md':
				return array(
					'filename' => $slug . '.md',
					'mime'     => 'text/markdown',
					'content'  => $this->to_markdown( $brief ),
					'encoding' => 'utf8',
				);

			case 'pdf':
				$pdf = $this->to_pdf( $brief );
				if ( is_wp_error( $pdf ) ) {
					return $pdf;
				}
				return array(
					'filename' => $slug . '.pdf',
					'mime'     => 'application/pdf',
					'content'  => base64_encode( $pdf ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- transport for binary PDF.
					'encoding' => 'base64',
				);

			case 'text':
			case 'clipboard':
				return array(
					'filename' => $slug . '.txt',
					'mime'     => 'text/plain',
					'content'  => $this->to_plaintext( $brief ),
					'encoding' => 'utf8',
				);

			default:
				return new \WP_Error( 'rsaip_brief_export', 'Unsupported export format.' );
		}
	}

	/**
	 * @param array<string, mixed> $brief Presented brief.
	 */
	public function to_markdown( array $brief ): string {
		$lines   = array();
		$lines[] = '# ' . ( $brief['title'] !== '' ? $brief['title'] : 'Content Brief' );
		$lines[] = '';
		$lines[] = '- **Topic:** ' . ( $brief['topic'] ?? '' );
		$lines[] = '- **Status:** ' . ( $brief['status'] ?? '' );
		$lines[] = '- **Primary keyword:** ' . ( $brief['primary_keyword'] ?? '' );
		$lines[] = '- **Slug:** ' . ( $brief['suggested_slug'] ?? '' );
		$lines[] = '- **Word count:** ' . (string) ( $brief['recommended_word_count'] ?? 0 );
		$lines[] = '';
		$lines[] = '## Search Intent';
		$lines[] = (string) ( $brief['search_intent'] ?? '' );
		$lines[] = '';
		$lines[] = '## Keywords';
		$lines[] = '### Secondary';
		$lines   = array_merge( $lines, $this->md_list( $brief['secondary_keywords'] ?? array() ) );
		$lines[] = '### Long tail';
		$lines   = array_merge( $lines, $this->md_list( $brief['long_tail_keywords'] ?? array() ) );
		$lines[] = '### Semantic';
		$lines   = array_merge( $lines, $this->md_list( $brief['semantic_keywords'] ?? array() ) );
		$lines[] = '';
		$lines[] = '## Entities';
		$lines   = array_merge( $lines, $this->md_list( $brief['entities'] ?? array() ) );
		$lines[] = '';
		$lines[] = '## Outline';
		$lines[] = '### H1';
		$lines[] = (string) ( $brief['h1'] ?? '' );
		$lines[] = '### H2';
		$lines   = array_merge( $lines, $this->md_list( $brief['h2_structure'] ?? array() ) );
		$lines[] = '### H3';
		$lines   = array_merge( $lines, $this->md_list( $brief['h3_suggestions'] ?? array() ) );
		$lines[] = '';
		$lines[] = '## Meta Description';
		$lines[] = (string) ( $brief['meta_description'] ?? '' );
		$lines[] = '';
		$lines[] = '## FAQ';
		$lines   = array_merge( $lines, $this->md_list( $brief['faq_ideas'] ?? array() ) );
		$lines[] = '';
		$lines[] = '## Internal Linking';
		$lines   = array_merge( $lines, $this->md_list( $brief['internal_linking_opportunities'] ?? array() ) );
		$lines[] = '';
		$lines[] = '## External Authority';
		$lines   = array_merge( $lines, $this->md_list( $brief['external_authority_suggestions'] ?? array() ) );
		$lines[] = '';
		$lines[] = '## Schema';
		$lines[] = (string) ( $brief['schema_recommendation'] ?? '' );
		$lines[] = '';
		$lines[] = '## EEAT';
		$lines   = array_merge( $lines, $this->md_list( $brief['eeat_recommendations'] ?? array() ) );
		$lines[] = '';

		return implode( "\n", $lines );
	}

	/**
	 * @param array<string, mixed> $brief Presented brief.
	 */
	public function to_plaintext( array $brief ): string {
		return wp_strip_all_tags( str_replace( array( '#', '*' ), '', $this->to_markdown( $brief ) ) );
	}

	/**
	 * @param array<string, mixed> $brief Presented brief.
	 * @return string|\WP_Error Binary PDF contents.
	 */
	public function to_pdf( array $brief ) {
		if ( ! class_exists( 'RSAIP_Export_PDF' ) ) {
			return new \WP_Error( 'rsaip_brief_pdf', 'PDF exporter unavailable.' );
		}

		$title = (string) ( $brief['title'] !== '' ? $brief['title'] : 'Content Brief' );
		$lines = preg_split( "/\r\n|\n|\r/", $this->to_plaintext( $brief ) );
		$lines = is_array( $lines ) ? $lines : array();

		$this->logger->info( 'content_brief.library.export_pdf', array( 'id' => (int) ( $brief['id'] ?? 0 ) ) );

		return \RSAIP_Export_PDF::build_simple( $title, $lines );
	}

	/**
	 * @param mixed $items List-ish.
	 * @return list<string>
	 */
	private function md_list( $items ): array {
		if ( ! is_array( $items ) || ! $items ) {
			return array( '- —' );
		}
		$out = array();
		foreach ( $items as $item ) {
			if ( is_scalar( $item ) && trim( (string) $item ) !== '' ) {
				$out[] = '- ' . trim( (string) $item );
			}
		}
		return $out ? $out : array( '- —' );
	}
}
