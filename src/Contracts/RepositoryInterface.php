<?php
declare(strict_types=1);

/**
 * Marker contract for data repositories.
 *
 * Phase 1: interface only. All SQL remains in legacy RSAIP_DB consumers
 * (Link_Graph, Audit, Bulk_Optimizer, etc.).
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface RepositoryInterface
 */
interface RepositoryInterface {

}
