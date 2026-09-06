<?php
declare(strict_types=1);

/**
 * Content analysis (read-only; never modifies the article).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentOptimizer;

use RecipeSeoAiPro\Contracts\LoggerInterface;
use RecipeSeoAiPro\Contracts\SettingsServiceInterface;
use RecipeSeoAiPro\Modules\Ai\AiProviderRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ContentAnalyzer
 */
final class ContentAnalyzer {

	private AiProviderRegistry $providers;

	private SettingsServiceInterface $settings;

	private LoggerInterface $logger;

	private PromptBuilder $prompts;

	public function __construct(
		AiProviderRegistry $providers,
		SettingsServiceInterface $settings,
		LoggerInterface $logger,
		PromptBuilder $prompts
	) {
		$this->providers = $providers;
		$this->settings  = $settings;
		$this->logger    = $logger;
		$this->prompts   = $prompts;
	}

	/**
	 * @param string               $content HTML/text.
	 * @param array<string, mixed> $context title, meta, keywords, enrich (bool).
	 * @return array<string, mixed>
	 */
	public function analyze( string $content, array $context = array() ): array {
		$title = (string) ( $context['title'] ?? '' );
		$meta  = (string) ( $context['meta'] ?? '' );
		$text  = wp_strip_all_tags( $content );
		$words = str_word_count( $text );
		$html  = $content;

		$h2 = preg_match_all( '/<h2\b[^>]*>/i', $html, $m2 ) ? (int) $m2[0] : 0;
		if ( ! $h2 ) {
			$h2 = preg_match_all( '/^##\s+/m', $content, $m2b ) ? count( $m2b[0] ) : 0;
		}
		$h3 = preg_match_all( '/<h3\b[^>]*>/i', $html, $m3 ) ? (int) $m3[0] : 0;
		$faq_like = (bool) preg_match( '/\b(faq|frequently asked|questions?)\b/i', $text );
		$has_links = (bool) preg_match( '/<a\s[^>]*href=/i', $html );
		$recipe_signals = (bool) preg_match( '/\b(ingredient|instruction|prep time|cook time|servings|recipe)\b/i', $text );
		$recipe_block = (bool) preg_match( '/rsaip-recipe|wp-block-recipe|itemprop=["\']recipe/i', $html );

		$sentences = preg_split( '/[.!?]+/', $text ) ?: array();
		$sentences = array_values( array_filter( array_map( 'trim', $sentences ) ) );
		$avg_len   = 0;
		if ( $sentences ) {
			$total = 0;
			foreach ( $sentences as $s ) {
				$total += str_word_count( $s );
			}
			$avg_len = (int) round( $total / max( 1, count( $sentences ) ) );
		}

		$thin       = $words < 600;
		$missing_h  = $h2 < 2;
		$missing_faq = ! $faq_like;
		$missing_il = ! $has_links;
		$dup_hint   = $this->repetition_ratio( $text );

		$seo = 40;
		if ( $title !== '' ) {
			$seo += 10;
		}
		if ( mb_strlen( $meta ) >= 80 && mb_strlen( $meta ) <= 165 ) {
			$seo += 15;
		} elseif ( $meta !== '' ) {
			$seo += 5;
		}
		if ( ! $missing_h ) {
			$seo += 10;
		}
		if ( ! $thin ) {
			$seo += 10;
		}
		if ( ! $missing_il ) {
			$seo += 10;
		}
		if ( $words >= 1200 ) {
			$seo += 5;
		}
		$seo = max( 0, min( 100, $seo ) );

		$read = 70;
		if ( $avg_len > 28 ) {
			$read -= 15;
		}
		if ( $avg_len > 35 ) {
			$read -= 15;
		}
		if ( $dup_hint > 0.12 ) {
			$read -= 10;
		}
		if ( $words < 300 ) {
			$read -= 20;
		}
		$read = max( 0, min( 100, $read ) );

		$eeat = 45;
		if ( preg_match( '/\b(I|we|tested|tested this|in my kitchen|as a|years? of)\b/i', $text ) ) {
			$eeat += 15;
		}
		if ( preg_match( '/\b(source|study|according to|registered|chef|nutrition)\b/i', $text ) ) {
			$eeat += 15;
		}
		if ( preg_match( '/\b(update|reviewed|trust|disclaimer)\b/i', $text ) ) {
			$eeat += 10;
		}
		if ( ! $thin ) {
			$eeat += 10;
		}
		$eeat = max( 0, min( 100, $eeat ) );

		$recipe = 20;
		if ( $recipe_signals ) {
			$recipe += 30;
		}
		if ( $recipe_block ) {
			$recipe += 25;
		}
		if ( preg_match( '/\bingredients?\b/i', $text ) && preg_match( '/\b(instructions?|directions|method)\b/i', $text ) ) {
			$recipe += 15;
		}
		if ( preg_match( '/\b(tip|storage|substitution)\b/i', $text ) ) {
			$recipe += 10;
		}
		$recipe = max( 0, min( 100, $recipe ) );

		$analysis = array(
			'seo_score'               => $seo,
			'readability_score'       => $read,
			'eeat_score'              => $eeat,
			'recipe_quality_score'    => $recipe,
			'word_count'              => $words,
			'avg_sentence_words'      => $avg_len,
			'thin_content'            => $thin,
			'duplicate_risk'          => $dup_hint > 0.15,
			'duplicate_ratio'         => $dup_hint,
			'missing_headings'        => $missing_h,
			'heading_counts'          => array(
				'h2' => $h2,
				'h3' => $h3,
			),
			'missing_entities'        => array(),
			'missing_faq'             => $missing_faq,
			'missing_internal_links'  => $missing_il,
			'issues'                  => array(),
			'opportunities'           => array(),
			'enriched'                => false,
		);

		if ( $thin ) {
			$analysis['issues'][] = 'Thin content detected (under ~600 words).';
		}
		if ( $missing_h ) {
			$analysis['issues'][] = 'Missing or weak H2 structure.';
		}
		if ( $missing_faq ) {
			$analysis['issues'][] = 'No FAQ section detected.';
		}
		if ( $missing_il ) {
			$analysis['issues'][] = 'No internal links detected in content.';
		}
		if ( $analysis['duplicate_risk'] ) {
			$analysis['issues'][] = 'High repetition / possible duplicate phrasing.';
		}
		if ( $meta === '' ) {
			$analysis['issues'][] = 'Meta description is empty.';
		}
		if ( $title === '' ) {
			$analysis['issues'][] = 'Title is empty.';
		}

		if ( ! empty( $context['enrich'] ) ) {
			$analysis = $this->enrich_with_ai( $content, $analysis );
		}

		$this->logger->debug(
			'content_optimizer.analyze',
			array(
				'words' => $words,
				'seo'   => $seo,
			)
		);

		return $analysis;
	}

	/**
	 * @param array<string, mixed> $heuristic Heuristic result.
	 * @return array<string, mixed>
	 */
	private function enrich_with_ai( string $content, array $heuristic ): array {
		$all = $this->settings->all();
		if ( ( $all['ai_provider'] ?? '' ) !== 'openai_compatible' ) {
			return $heuristic;
		}
		$provider = $this->providers->get( 'openai_compatible' );
		$parts    = $this->prompts->build_analysis_enrichment_parts( $content, $heuristic );
		$result   = $provider->complete(
			(string) ( $parts['user'] ?? '' ),
			array(
				'max_tokens'      => 900,
				'system'          => (string) ( $parts['system'] ?? '' ),
				'article_content' => (string) ( $parts['article_content'] ?? '' ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $heuristic;
		}
		$data = $this->decode_json( (string) $result );
		if ( ! is_array( $data ) ) {
			return $heuristic;
		}
		foreach ( array( 'missing_entities', 'missing_faq', 'missing_internal_links', 'eeat_gaps', 'opportunities', 'thin_content_notes' ) as $key ) {
			if ( empty( $data[ $key ] ) || ! is_array( $data[ $key ] ) ) {
				continue;
			}
			$vals = array();
			foreach ( $data[ $key ] as $item ) {
				if ( is_scalar( $item ) && trim( (string) $item ) !== '' ) {
					$vals[] = trim( (string) $item );
				}
			}
			if ( $key === 'missing_faq' && $vals ) {
				$heuristic['missing_faq'] = true;
				$heuristic['faq_ideas']   = $vals;
			} elseif ( $key === 'missing_internal_links' && $vals ) {
				$heuristic['missing_internal_links'] = true;
				$heuristic['internal_link_ideas']    = $vals;
			} elseif ( $key === 'missing_entities' ) {
				$heuristic['missing_entities'] = $vals;
			} else {
				$heuristic[ $key ] = $vals;
			}
		}
		$heuristic['enriched'] = true;
		return $heuristic;
	}

	private function repetition_ratio( string $text ): float {
		$words = preg_split( '/\s+/', mb_strtolower( $text ) ) ?: array();
		$words = array_values( array_filter( $words, static fn( $w ) => mb_strlen( $w ) > 3 ) );
		$total = count( $words );
		if ( $total < 40 ) {
			return 0.0;
		}
		$freq = array_count_values( $words );
		$repeated = 0;
		foreach ( $freq as $count ) {
			if ( $count >= 4 ) {
				$repeated += $count;
			}
		}
		return round( $repeated / $total, 3 );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function decode_json( string $raw ): ?array {
		$raw = trim( $raw );
		if ( preg_match( '/^```(?:json)?\s*(.*?)\s*```$/is', $raw, $m ) ) {
			$raw = trim( $m[1] );
		}
		$data = json_decode( $raw, true );
		if ( is_array( $data ) ) {
			return $data;
		}
		$start = strpos( $raw, '{' );
		$end   = strrpos( $raw, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$data = json_decode( substr( $raw, $start, $end - $start + 1 ), true );
			return is_array( $data ) ? $data : null;
		}
		return null;
	}
}
