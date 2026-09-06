<?php
declare(strict_types=1);

/**
 * Maps raw AI JSON into BriefDTO.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentBrief;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ContentBriefBuilder
 */
final class ContentBriefBuilder {

	/**
	 * @param string               $topic    Original topic.
	 * @param string               $raw      Raw model output.
	 * @return BriefDTO|\WP_Error
	 */
	public function from_ai_response( string $topic, string $raw ) {
		$data = $this->decode_json( $raw );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$dto                        = new BriefDTO();
		$dto->topic                 = trim( $topic );
		$dto->search_intent         = $this->string_field( $data, 'search_intent' );
		$dto->primary_keyword       = $this->string_field( $data, 'primary_keyword' );
		$dto->secondary_keywords    = $this->string_list( $data, 'secondary_keywords' );
		$dto->long_tail_keywords    = $this->string_list( $data, 'long_tail_keywords' );
		$dto->semantic_keywords     = $this->string_list( $data, 'semantic_keywords' );
		$dto->entities              = $this->string_list( $data, 'entities' );
		$dto->faq_ideas             = $this->string_list( $data, 'faq_ideas' );
		$dto->h1                    = $this->string_field( $data, 'h1' );
		$dto->h2_structure          = $this->string_list( $data, 'h2_structure' );
		$dto->h3_suggestions        = $this->string_list( $data, 'h3_suggestions' );
		$dto->meta_description      = $this->string_field( $data, 'meta_description' );
		$dto->suggested_slug        = $this->normalize_slug( $this->string_field( $data, 'suggested_slug' ) );
		$dto->internal_linking_opportunities = $this->string_list( $data, 'internal_linking_opportunities' );
		$dto->external_authority_suggestions = $this->string_list( $data, 'external_authority_suggestions' );
		$dto->schema_recommendation = $this->string_field( $data, 'schema_recommendation' );
		$dto->eeat_recommendations  = $this->string_list( $data, 'eeat_recommendations' );
		$dto->recommended_word_count = $this->word_count_field( $data );

		if ( $dto->primary_keyword === '' && $dto->h1 === '' ) {
			return new \WP_Error( 'rsaip_brief_empty', 'AI returned an empty content brief.' );
		}

		return $dto;
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	private function decode_json( string $raw ) {
		$raw = trim( $raw );
		if ( $raw === '' ) {
			return new \WP_Error( 'rsaip_brief_parse', 'Empty AI response.' );
		}

		// Strip optional markdown fences without changing legacy AI parsers.
		if ( preg_match( '/^```(?:json)?\s*(.*?)\s*```$/is', $raw, $m ) ) {
			$raw = trim( $m[1] );
		}

		$data = json_decode( $raw, true );
		if ( is_array( $data ) ) {
			return $data;
		}

		// Attempt to extract first JSON object.
		$start = strpos( $raw, '{' );
		$end   = strrpos( $raw, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$slice = substr( $raw, $start, $end - $start + 1 );
			$data  = json_decode( $slice, true );
			if ( is_array( $data ) ) {
				return $data;
			}
		}

		return new \WP_Error( 'rsaip_brief_parse', 'Could not parse content brief JSON.' );
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
		if ( $n < 800 ) {
			$n = 800;
		}
		if ( $n > 3500 ) {
			$n = 3500;
		}
		return $n;
	}

	private function normalize_slug( string $slug ): string {
		$slug = strtolower( $slug );
		$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug ) ?? '';
		return trim( $slug, '-' );
	}
}
