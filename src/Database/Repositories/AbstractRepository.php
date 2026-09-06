<?php
declare(strict_types=1);

/**
 * Shared $wpdb helpers for table repositories.
 *
 * Repositories hold SQL only — no scoring, SEO rules, or HTTP logic.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Database\Repositories;

use RecipeSeoAiPro\Contracts\RepositoryInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AbstractRepository
 */
abstract class AbstractRepository implements RepositoryInterface {

	/**
	 * @return \wpdb
	 */
	protected function db() {
		global $wpdb;
		return $wpdb;
	}

	/**
	 * Fully-qualified table name for this repository.
	 */
	abstract public function table(): string;
}
