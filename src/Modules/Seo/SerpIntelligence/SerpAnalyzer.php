<?php
declare(strict_types=1);

/**
 * Maps raw AI JSON into SerpIntelligenceDTO.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Seo\SerpIntelligence;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SerpAnalyzer
 */
final class SerpAnalyzer {

	/**
	 * @param string               $query   Original query.
	 * @param string               $raw     Raw model output.
	 * @param array<string, mixed> $options Context.
	 * @return SerpIntelligenceDTO|\WP_Error
	 */
	public function from_ai_response( string $query, string $raw, array $options = array() ) {
		$data = $this->decode_json( $raw );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$dto                              = new SerpIntelligenceDTO();
		$dto->query                       = trim( $query );
		$dto->language                    = isset( $options['language'] ) ? sanitize_text_field( (string) $options['language'] ) : '';
		$dto->country                     = isset( $options['country'] ) ? strtoupper( sanitize_text_field( (string) $options['country'] ) ) : '';
		$dto->search_intent               = $this->string_field( $data, 'search_intent' );
		$dto->expected_serp_features      = $this->string_list( $data, 'expected_serp_features' );
		$dto->recommended_article_type    = $this->string_field( $data, 'recommended_article_type' );
		$dto->recommended_heading_structure = $this->string_list( $data, 'recommended_heading_structure' );
		$dto->missing_topics              = $this->string_list( $data, 'missing_topics' );
		$dto->related_entities            = $this->string_list( $data, 'related_entities' );
		$dto->recommended_word_count      = $this->word_count_field( $data );
		$dto->recommended_media           = $this->string_list( $data, 'recommended_media' );
		$dto->suggested_faq               = $this->string_list( $data, 'suggested_faq' );
		$dto->eeat_recommendations        = $this->string_list( $data, 'eeat_recommendations' );
		$dto->common_mistakes             = $this->string_list( $data, 'common_mistakes' );
		$dto->opportunities               = $this->string_list( $data, 'opportunities' );

		if ( $dto->search_intent === '' && $dto->recommended_article_type === '' && ! $dto->expected_serp_features ) {
			return new \WP_Error( 'rsaip_serp_empty', 'AI returned an empty SERP analysis.' );
		}

		return $dto;
	}

	/**
	 * Rebuild DTO from a DB row.
	 *
	 * @param array<string, mixed> $row DB row.
	 */
	public function from_row( array $row ): SerpIntelligenceDTO {
		$dto             = new SerpIntelligenceDTO();
		$dto->id         = (int) ( $row['id'] ?? 0 );
		$dto->query      = (string) ( $row['query_text'] ?? '' );
		$dto->language   = (string) ( $row['language'] ?? '' );
		$dto->country    = (string) ( $row['country'] ?? '' );
		$dto->project_id = (int) ( $row['project_id'] ?? 0 );
		$dto->keyword_id = (int) ( $row['keyword_id'] ?? 0 );
		$dto->brief_id   = (int) ( $row['brief_id'] ?? 0 );
		$dto->user_id    = (int) ( $row['user_id'] ?? 0 );
		$dto->created_at = (string) ( $row['created_at'] ?? '' );
		$dto->updated_at = (string) ( $row['updated_at'] ?? '' );
		$dto->recommended_article_type = (string) ( $row['recommended_article_type'] ?? '' );
		$dto->recommended_word_count   = (int) ( $row['recommended_word_count'] ?? 0 );
		$dto->search_intent            = (string) ( $row['search_intent'] ?? '' );

		$payload = array();
		if ( ! empty( $row['payload'] ) && is_string( $row['payload'] ) ) {
			$decoded = json_decode( $row['payload'], true );
			if ( is_array( $decoded ) ) {
				$payload = $decoded;
			}
		}

		if ( $payload ) {
			$dto->search_intent                 = $this->string_field( $payload, 'search_intent' ) ?: $dto->search_intent;
			$dto->expected_serp_features        = $this->string_list( $payload, 'expected_serp_features' );
			$dto->recommended_article_type      = $this->string_field( $payload, 'recommended_article_type' ) ?: $dto->recommended_article_type;
			$dto->recommended_heading_structure = $this->string_list( $payload, 'recommended_heading_structure' );
			$dto->missing_topics                = $this->string_list( $payload, 'missing_topics' );
			$dto->related_entities              = $this->string_list( $payload, 'related_entities' );
			$dto->recommended_word_count        = $this->word_count_field( $payload ) ?: $dto->recommended_word_count;
			$dto->recommended_media             = $this->string_list( $payload, 'recommended_media' );
			$dto->suggested_faq                 = $this->string_list( $payload, 'suggested_faq' );
			$dto->eeat_recommendations          = $this->string_list( $payload, 'eeat_recommendations' );
			$dto->common_mistakes               = $this->string_list( $payload, 'common_mistakes' );
			$dto->opportunities                 = $this->string_list( $payload, 'opportunities' );
		}

		return $dto;
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	private function decode_json( string $raw ) {
		$raw = trim( $raw );
		if ( $raw === '' ) {
			return new \WP_Error( 'rsaip_serp_parse', 'Empty AI response.' );
		}

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
			$slice = substr( $raw, $start, $end - $start + 1 );
			$data  = json_decode( $slice, true );
			if ( is_array( $data ) ) {
				return $data;
			}
		}

		return new \WP_Error( 'rsaip_serp_parse', 'Could not parse SERP intelligence JSON.' );
	}

	/**
	 * @param array<string, mixed> $data Payload.
	 */
	private function string_field( array $data, string $key ): string {
		if ( ! isset( $data[ $key ] ) || ! is_scalar( $data[ $key ] ) ) {
			return '';
		}
		return trim( (string) $data[ $key ] );
	}

	/**
	 * @param array<string, mixed> $data Payload.
	 * @return list<string>
	 */
	private function string_list( array $data, string $key ): array {
		if ( ! isset( $data[ $key ] ) ) {
			return array();
		}
		$value = $data[ $key ];
		if ( is_string( $value ) ) {
			$parts = preg_split( '/[\n,]+/', $value ) ?: array();
			$value = $parts;
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}
			$text = trim( (string) $item );
			if ( $text === '' ) {
				continue;
			}
			$out[] = $text;
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param array<string, mixed> $data Payload.
	 */
	private function word_count_field( array $data ): int {
		$n = 0;
		if ( isset( $data['recommended_word_count'] ) && is_numeric( $data['recommended_word_count'] ) ) {
			$n = (int) $data['recommended_word_count'];
		}
		if ( $n <= 0 ) {
			return 0;
		}
		return max( 800, min( 4000, $n ) );
	}
}
