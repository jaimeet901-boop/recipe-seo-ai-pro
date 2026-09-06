<?php
declare(strict_types=1);

/**
 * Result of validating AI article JSON into a trusted proposal.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\ArticleGeneration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ArticleGenerationValidationResult
 */
final class ArticleGenerationValidationResult {

	private bool $ok;
	private string $code;
	private string $message;
	private ?ArticleGenerationProposal $proposal;
	private ?ArticleGenerationContext $context;

	/** @var array<string, mixed> Safe diagnostic fields for failure responses. */
	private array $meta;

	private function __construct(
		bool $ok,
		string $code,
		string $message,
		?ArticleGenerationProposal $proposal,
		?ArticleGenerationContext $context = null,
		array $meta = array()
	) {
		$this->ok       = $ok;
		$this->code     = $code;
		$this->message  = $message;
		$this->proposal = $proposal;
		$this->context  = $context;
		$this->meta     = $meta;
	}

	public static function success( ArticleGenerationProposal $proposal, ?ArticleGenerationContext $context = null ): self {
		return new self( true, '', '', $proposal, $context );
	}

	/**
	 * @param array<string, mixed> $meta Safe diagnostics only (no secrets).
	 */
	public static function failure( string $code, string $message, array $meta = array() ): self {
		return new self( false, $code, $message, null, null, $meta );
	}

	public function ok(): bool {
		return $this->ok;
	}

	public function code(): string {
		return $this->code;
	}

	public function message(): string {
		return $this->message;
	}

	public function proposal(): ?ArticleGenerationProposal {
		return $this->proposal;
	}

	public function context(): ?ArticleGenerationContext {
		return $this->context;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function meta(): array {
		return $this->meta;
	}

	public function with_context( ArticleGenerationContext $context ): self {
		return new self( $this->ok, $this->code, $this->message, $this->proposal, $context, $this->meta );
	}
}
