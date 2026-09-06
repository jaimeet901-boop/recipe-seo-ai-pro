<?php
declare(strict_types=1);

/**
 * Maps raw AI JSON into KeywordResearchDTO.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Keywords\Research;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KeywordResearchBuilder
 */
final class KeywordResearchBuilder {

	private const CATEGORIES = array(
		'primary',
		'secondary',
		'long_tail',
		'question',
		'comparison',
		'commercial',
		'informational',
		'transactional',
		'local',
		'seasonal',
	);

	private const INTENTS = array(
		'informational',
		'navigational',
		'commercial',
		'transactional',
	);

	/**
	 * @param string               $seed    Original seed.
	 * @param string               $raw     Raw model output.
	 * @param array<string, mixed> $options Context options.
	 * @return KeywordResearchDTO|\WP_Error
	 */
	public function from_ai_response( string $seed, string $raw, array $options = array() ) {
		$data = $this->decode_json( $raw );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$dto           = new KeywordResearchDTO();
		$dto->seed     = trim( $seed );
		$dto->language = isset( $options['language'] ) ? sanitize_text_field( (string) $options['language'] ) : '';
		$dto->country  = isset( $options['country'] ) ? strtoupper( sanitize_text_field( (string) $options['country'] ) ) : '';
		$dto->audience = isset( $options['audience'] ) ? sanitize_text_field( (string) $options['audience'] ) : '';
		$dto->related_entities = $this->string_list( $data, 'related_entities' );

		$buckets = array();
		foreach ( self::CATEGORIES as $cat ) {
			$buckets[ $cat ] = array();
		}

		$seen     = array();
		$keywords = array();
		$index    = 0;

		$rows = isset( $data['keywords'] ) && is_array( $data['keywords'] ) ? $data['keywords'] : array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$keyword = isset( $row['keyword'] ) ? trim( (string) $row['keyword'] ) : '';
			if ( $keyword === '' ) {
				continue;
			}
			$key = mb_strtolower( $keyword );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;

			$category = $this->normalize_category( isset( $row['category'] ) ? (string) $row['category'] : 'secondary' );
			$intent   = $this->normalize_intent( isset( $row['intent'] ) ? (string) $row['intent'] : '', $category );
			$diff     = $this->clamp_int( $row['difficulty'] ?? 40, 0, 100 );
			$priority = $this->clamp_int( $row['priority'] ?? 50, 0, 100 );
			$cluster  = isset( $row['suggested_cluster'] ) ? trim( (string) $row['suggested_cluster'] ) : '';
			if ( $cluster === '' ) {
				$cluster = ucwords( str_replace( '_', ' ', $category ) );
			}

			++$index;
			$item = array(
				'id'                => 'kr_' . $index,
				'keyword'           => mb_substr( $keyword, 0, 255 ),
				'category'          => $category,
				'intent'            => $intent,
				'difficulty'        => $diff,
				'priority'          => $priority,
				'suggested_cluster' => mb_substr( $cluster, 0, 255 ),
			);
			$keywords[]           = $item;
			$buckets[ $category ][] = $item['keyword'];
		}

		// Accept alternate bucket-shaped payloads if keywords[] was empty.
		if ( ! $keywords ) {
			foreach ( self::CATEGORIES as $cat ) {
				$list_key = $cat === 'long_tail' ? 'long_tail_keywords' : ( $cat . '_keywords' );
				$alts     = array( $list_key, $cat );
				foreach ( $alts as $alt ) {
					foreach ( $this->string_list( $data, $alt ) as $kw ) {
						$key = mb_strtolower( $kw );
						if ( isset( $seen[ $key ] ) ) {
							continue;
						}
						$seen[ $key ] = true;
						++$index;
						$item = array(
							'id'                => 'kr_' . $index,
							'keyword'           => mb_substr( $kw, 0, 255 ),
							'category'          => $cat,
							'intent'            => $this->normalize_intent( '', $cat ),
							'difficulty'        => 40,
							'priority'          => 50,
							'suggested_cluster' => ucwords( str_replace( '_', ' ', $cat ) ),
						);
						$keywords[]       = $item;
						$buckets[ $cat ][] = $item['keyword'];
					}
				}
			}
		}

		$dto->keywords = $keywords;
		$dto->buckets  = $buckets;

		if ( ! $dto->keywords ) {
			return new \WP_Error( 'rsaip_kw_research_empty', 'AI returned no keywords.' );
		}

		return $dto;
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	private function decode_json( string $raw ) {
		$raw = trim( $raw );
		if ( $raw === '' ) {
			return new \WP_Error( 'rsaip_kw_research_parse', 'Empty AI response.' );
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

		return new \WP_Error( 'rsaip_kw_research_parse', 'Could not parse keyword research JSON.' );
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
			if ( is_array( $item ) && isset( $item['keyword'] ) ) {
				$item = $item['keyword'];
			}
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

	private function normalize_category( string $category ): string {
		$category = sanitize_key( str_replace( array( ' ', '-' ), '_', strtolower( $category ) ) );
		$map      = array(
			'longtail'       => 'long_tail',
			'long_tail'      => 'long_tail',
			'questions'      => 'question',
			'faq'            => 'question',
			'comparisons'    => 'comparison',
			'vs'             => 'comparison',
			'info'           => 'informational',
			'transact'       => 'transactional',
			'buy'            => 'transactional',
		);
		if ( isset( $map[ $category ] ) ) {
			$category = $map[ $category ];
		}
		return in_array( $category, self::CATEGORIES, true ) ? $category : 'secondary';
	}

	private function normalize_intent( string $intent, string $category ): string {
		$intent = sanitize_key( $intent );
		if ( in_array( $intent, self::INTENTS, true ) ) {
			return $intent;
		}
		if ( in_array( $category, array( 'commercial', 'comparison' ), true ) ) {
			return 'commercial';
		}
		if ( $category === 'transactional' ) {
			return 'transactional';
		}
		if ( in_array( $category, array( 'question', 'informational', 'seasonal' ), true ) ) {
			return 'informational';
		}
		return 'informational';
	}

	/**
	 * @param mixed $value Raw value.
	 */
	private function clamp_int( $value, int $min, int $max ): int {
		$n = is_numeric( $value ) ? (int) $value : $min;
		return max( $min, min( $max, $n ) );
	}
}
