<?php
declare(strict_types=1);

/**
 * Encode/decode content briefs for persistence (Phase 3.2).
 *
 * Does not call AI providers.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Ai\ContentBrief;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BriefStorageService
 */
final class BriefStorageService {

	public const STATUS_DRAFT     = 'draft';
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_ARCHIVED  = 'archived';

	/**
	 * Ensure library table exists (idempotent).
	 */
	public function ensure_table(): void {
		if ( class_exists( 'RSAIP_DB' ) ) {
			\RSAIP_DB::migrate_content_briefs_table();
		}
	}

	/**
	 * @return list<string>
	 */
	public function allowed_statuses(): array {
		return array( self::STATUS_DRAFT, self::STATUS_COMPLETED, self::STATUS_ARCHIVED );
	}

	public function normalize_status( string $status ): string {
		$status = sanitize_key( $status );
		if ( ! in_array( $status, $this->allowed_statuses(), true ) ) {
			return self::STATUS_DRAFT;
		}
		return $status;
	}

	/**
	 * Map a generated BriefDTO (+ optional title/status) to a DB row (no id/timestamps).
	 *
	 * @param array<string, mixed> $meta title, status, user_id.
	 * @return array<string, mixed>
	 */
	public function row_from_dto( BriefDTO $dto, array $meta = array() ): array {
		$title  = isset( $meta['title'] ) ? sanitize_text_field( (string) $meta['title'] ) : '';
		$status = $this->normalize_status( isset( $meta['status'] ) ? (string) $meta['status'] : self::STATUS_DRAFT );
		$user_id = isset( $meta['user_id'] ) ? absint( $meta['user_id'] ) : 0;

		if ( $title === '' ) {
			$title = $dto->h1 !== '' ? $dto->h1 : ( $dto->primary_keyword !== '' ? $dto->primary_keyword : $dto->topic );
		}

		$outline = array(
			'h1'             => $dto->h1,
			'h2_structure'   => $dto->h2_structure,
			'h3_suggestions' => $dto->h3_suggestions,
		);

		return array(
			'title'                  => mb_substr( $title, 0, 255 ),
			'topic'                  => mb_substr( $dto->topic, 0, 500 ),
			'search_intent'          => $dto->search_intent,
			'primary_keyword'        => mb_substr( $dto->primary_keyword, 0, 255 ),
			'secondary_keywords'     => $this->encode_list( $dto->secondary_keywords ),
			'long_tail_keywords'     => $this->encode_list( $dto->long_tail_keywords ),
			'semantic_keywords'      => $this->encode_list( $dto->semantic_keywords ),
			'entities'               => $this->encode_list( $dto->entities ),
			'faq'                    => $this->encode_list( $dto->faq_ideas ),
			'outline'                => wp_json_encode( $outline ),
			'meta_description'       => $dto->meta_description,
			'slug'                   => mb_substr( $dto->suggested_slug, 0, 255 ),
			'internal_links'         => $this->encode_list( $dto->internal_linking_opportunities ),
			'external_links'         => $this->encode_list( $dto->external_authority_suggestions ),
			'schema_recommendation'  => $dto->schema_recommendation,
			'eeat'                   => $this->encode_list( $dto->eeat_recommendations ),
			'word_count'             => max( 0, (int) $dto->recommended_word_count ),
			'status'                 => $status,
			'user_id'                => $user_id,
			'payload'                => wp_json_encode( $dto->to_array() ),
		);
	}

	/**
	 * Map arbitrary POST/JSON brief payload to a DB row.
	 *
	 * @param array<string, mixed> $input Brief fields.
	 * @param array<string, mixed> $meta  title, status, user_id.
	 * @return array<string, mixed>
	 */
	public function row_from_input( array $input, array $meta = array() ): array {
		$dto = $this->dto_from_input( $input );
		return $this->row_from_dto( $dto, $meta );
	}

	/**
	 * @param array<string, mixed> $input Brief-like array.
	 */
	public function dto_from_input( array $input ): BriefDTO {
		$dto = new BriefDTO();
		$dto->topic             = $this->str( $input, 'topic' );
		$dto->search_intent     = $this->str( $input, 'search_intent' );
		$dto->primary_keyword   = $this->str( $input, 'primary_keyword' );
		$dto->secondary_keywords = $this->list_field( $input, 'secondary_keywords' );
		$dto->long_tail_keywords = $this->list_field( $input, 'long_tail_keywords' );
		$dto->semantic_keywords  = $this->list_field( $input, 'semantic_keywords' );
		$dto->entities           = $this->list_field( $input, 'entities' );
		$dto->faq_ideas          = $this->list_field( $input, array( 'faq_ideas', 'faq' ) );
		$dto->h1                 = $this->str( $input, 'h1' );
		$dto->h2_structure       = $this->list_field( $input, 'h2_structure' );
		$dto->h3_suggestions     = $this->list_field( $input, 'h3_suggestions' );
		$dto->meta_description   = $this->str( $input, 'meta_description' );
		$dto->suggested_slug     = $this->str( $input, array( 'suggested_slug', 'slug' ) );
		$dto->internal_linking_opportunities = $this->list_field( $input, array( 'internal_linking_opportunities', 'internal_links' ) );
		$dto->external_authority_suggestions = $this->list_field( $input, array( 'external_authority_suggestions', 'external_links' ) );
		$dto->schema_recommendation = $this->str( $input, 'schema_recommendation' );
		$dto->eeat_recommendations  = $this->list_field( $input, array( 'eeat_recommendations', 'eeat' ) );
		$wc = 0;
		if ( isset( $input['recommended_word_count'] ) && is_numeric( $input['recommended_word_count'] ) ) {
			$wc = (int) $input['recommended_word_count'];
		} elseif ( isset( $input['word_count'] ) && is_numeric( $input['word_count'] ) ) {
			$wc = (int) $input['word_count'];
		}
		$dto->recommended_word_count = max( 0, $wc );

		// Outline object support.
		if ( isset( $input['outline'] ) && is_array( $input['outline'] ) ) {
			$outline = $input['outline'];
			if ( $dto->h1 === '' && isset( $outline['h1'] ) ) {
				$dto->h1 = $this->str( $outline, 'h1' );
			}
			if ( ! $dto->h2_structure && isset( $outline['h2_structure'] ) ) {
				$dto->h2_structure = $this->list_field( $outline, 'h2_structure' );
			}
			if ( ! $dto->h3_suggestions && isset( $outline['h3_suggestions'] ) ) {
				$dto->h3_suggestions = $this->list_field( $outline, 'h3_suggestions' );
			}
		}

		return $dto;
	}

	/**
	 * Hydrate a public brief array from a DB row.
	 *
	 * @param array<string, mixed> $row DB row.
	 * @return array<string, mixed>
	 */
	public function present_row( array $row ): array {
		$payload = array();
		if ( ! empty( $row['payload'] ) && is_string( $row['payload'] ) ) {
			$decoded = json_decode( $row['payload'], true );
			if ( is_array( $decoded ) ) {
				$payload = $decoded;
			}
		}

		$outline = array();
		if ( ! empty( $row['outline'] ) && is_string( $row['outline'] ) ) {
			$decoded = json_decode( $row['outline'], true );
			if ( is_array( $decoded ) ) {
				$outline = $decoded;
			}
		}

		return array(
			'id'                             => (int) ( $row['id'] ?? 0 ),
			'title'                          => (string) ( $row['title'] ?? '' ),
			'topic'                          => (string) ( $row['topic'] ?? '' ),
			'search_intent'                  => (string) ( $row['search_intent'] ?? '' ),
			'primary_keyword'                => (string) ( $row['primary_keyword'] ?? '' ),
			'secondary_keywords'             => $this->decode_list( $row['secondary_keywords'] ?? null ),
			'long_tail_keywords'             => $this->decode_list( $row['long_tail_keywords'] ?? null ),
			'semantic_keywords'              => $this->decode_list( $row['semantic_keywords'] ?? null ),
			'entities'                       => $this->decode_list( $row['entities'] ?? null ),
			'faq_ideas'                      => $this->decode_list( $row['faq'] ?? null ),
			'h1'                             => (string) ( $outline['h1'] ?? ( $payload['h1'] ?? '' ) ),
			'h2_structure'                   => $this->decode_list(
				$outline['h2_structure'] ?? ( $payload['h2_structure'] ?? null )
			),
			'h3_suggestions'                 => $this->decode_list(
				$outline['h3_suggestions'] ?? ( $payload['h3_suggestions'] ?? null )
			),
			'meta_description'               => (string) ( $row['meta_description'] ?? '' ),
			'suggested_slug'                 => (string) ( $row['slug'] ?? '' ),
			'internal_linking_opportunities' => $this->decode_list( $row['internal_links'] ?? null ),
			'external_authority_suggestions' => $this->decode_list( $row['external_links'] ?? null ),
			'schema_recommendation'          => (string) ( $row['schema_recommendation'] ?? '' ),
			'eeat_recommendations'           => $this->decode_list( $row['eeat'] ?? null ),
			'recommended_word_count'         => (int) ( $row['word_count'] ?? 0 ),
			'status'                         => (string) ( $row['status'] ?? self::STATUS_DRAFT ),
			'user_id'                        => (int) ( $row['user_id'] ?? 0 ),
			'created_at'                     => (string) ( $row['created_at'] ?? '' ),
			'updated_at'                     => (string) ( $row['updated_at'] ?? '' ),
			'payload'                        => $payload,
		);
	}

	/**
	 * @param list<string> $items Items.
	 */
	public function encode_list( array $items ): string {
		$clean = array();
		foreach ( $items as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}
			$text = trim( (string) $item );
			if ( $text !== '' ) {
				$clean[] = $text;
			}
		}
		return (string) wp_json_encode( array_values( $clean ) );
	}

	/**
	 * @return list<string>
	 */
	public function decode_list( $raw ): array {
		if ( is_array( $raw ) ) {
			$out = array();
			foreach ( $raw as $item ) {
				if ( is_scalar( $item ) && trim( (string) $item ) !== '' ) {
					$out[] = trim( (string) $item );
				}
			}
			return $out;
		}
		if ( ! is_string( $raw ) || $raw === '' ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		return $this->decode_list( $decoded );
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @param string|list<string>  $keys Key or fallbacks.
	 */
	private function str( array $data, $keys ): string {
		$keys = (array) $keys;
		foreach ( $keys as $key ) {
			if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ) {
				return trim( (string) $data[ $key ] );
			}
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @param string|list<string>  $keys Key or fallbacks.
	 * @return list<string>
	 */
	private function list_field( array $data, $keys ): array {
		$keys = (array) $keys;
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			return $this->decode_list( $data[ $key ] );
		}
		return array();
	}
}
