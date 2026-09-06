<?php
declare(strict_types=1);

/**
 * Resolved recipe/topic phrase for title generation.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\TitleGeneration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TitleTopic
 */
final class TitleTopic {

	private string $base;
	private string $required_phrase;
	private string $title_anchor;

	public function __construct( string $base, string $required_phrase, string $title_anchor ) {
		$this->base             = $base;
		$this->required_phrase  = $required_phrase;
		$this->title_anchor     = $title_anchor;
	}

	/**
	 * Phrase used as the heuristic/AI title stem.
	 */
	public function base(): string {
		return $this->base;
	}

	/**
	 * Contiguous phrase every accepted suggestion must contain.
	 */
	public function required_phrase(): string {
		return $this->required_phrase;
	}

	/**
	 * Topic extracted from the current post title (may be empty).
	 */
	public function title_anchor(): string {
		return $this->title_anchor;
	}
}
