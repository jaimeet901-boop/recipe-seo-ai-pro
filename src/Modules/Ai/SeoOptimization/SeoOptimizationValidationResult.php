<?php
declare(strict_types=1);

/**
 * Result of validating AI SEO JSON into a trusted proposal.
 *
 * Avoids WP_Error so standalone tests can run without WordPress.
 *
 * @package RecipeSeoAiPro
 */

namespace RecipeSeoAiPro\Modules\Ai\SeoOptimization;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SeoOptimizationValidationResult
 */
final class SeoOptimizationValidationResult {

	private bool $ok;
	private string $code;
	private string $message;
	private ?SeoOptimizationProposal $proposal;
	private ?SeoOptimizationContext $context;

	private function __construct(
		bool $ok,
		string $code,
		string $message,
		?SeoOptimizationProposal $proposal,
		?SeoOptimizationContext $context = null
	) {
		$this->ok       = $ok;
		$this->code     = $code;
		$this->message  = $message;
		$this->proposal = $proposal;
		$this->context  = $context;
	}

	public static function success( SeoOptimizationProposal $proposal, ?SeoOptimizationContext $context = null ): self {
		return new self( true, '', '', $proposal, $context );
	}

	public static function failure( string $code, string $message ): self {
		return new self( false, $code, $message, null, null );
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

	public function proposal(): ?SeoOptimizationProposal {
		return $this->proposal;
	}

	public function context(): ?SeoOptimizationContext {
		return $this->context;
	}

	/**
	 * Attach context without changing proposal (e.g. after analyze_context).
	 */
	public function with_context( SeoOptimizationContext $context ): self {
		return new self( $this->ok, $this->code, $this->message, $this->proposal, $context );
	}
}
