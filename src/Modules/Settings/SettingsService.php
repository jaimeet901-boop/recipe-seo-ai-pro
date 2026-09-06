<?php
declare(strict_types=1);

/**
 * Settings business logic extracted from helpers.php and RSAIP_Admin.
 *
 * Preserves:
 * - All historical option keys and defaults
 * - Fix 1: partial form saves merge with existing settings
 * - Fix 5: secrets never wiped on empty/placeholder submission
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\Settings;

use RecipeSeoAiPro\Contracts\SettingsRepositoryInterface;
use RecipeSeoAiPro\Contracts\SettingsServiceInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SettingsService
 */
final class SettingsService implements SettingsServiceInterface {

	private SettingsRepositoryInterface $repository;

	public function __construct( SettingsRepositoryInterface $repository ) {
		$this->repository = $repository;
	}

	/**
	 * @inheritDoc
	 */
	public function defaults(): array {
		return array(
			'thin_content_min_words'       => 600,
			'min_relevance_score'          => 45,
			'max_links_per_post'           => 10,
			'max_external_links_per_post'  => 3,
			'auto_insert_internal_links'   => 0,
			'never_modify_posts'           => 1,
			'auto_insert_post_types'       => array( 'post' ),
			'auto_insert_statuses'         => array( 'publish' ),
			'link_suggestion_limit'        => 10,
			'bulk_queue_batch_size'        => 10,
			'gsc_site_url'                 => '',
			'gsc_service_account_email'    => '',
			'gsc_private_key_pem'          => '',
			'gsc_token_cache_seconds'      => 3500,
			'gsc_lookback_days'            => 90,
			'psi_api_key'                  => '',
			'ai_provider'                  => 'openai_compatible',
			'ai_endpoint'                  => 'https://api.openai.com/v1/chat/completions',
			'ai_api_key'                   => '',
			'ai_model'                     => 'gpt-4o-mini',
			'ai_timeout_seconds'           => 25,
			'cache_ttl_seconds'            => 900,
			'link_graph_rebuild_batch'     => 50,
			'broken_link_timeout_seconds'  => 7,
			'image_large_threshold_kb'     => 350,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function all(): array {
		$defaults = $this->defaults();
		$stored   = $this->repository->read();
		$merged   = array_merge( $defaults, $stored );

		$merged['thin_content_min_words']      = max( 50, absint( $merged['thin_content_min_words'] ) );
		$merged['min_relevance_score']         = max( 0, min( 100, absint( $merged['min_relevance_score'] ) ) );
		$merged['max_links_per_post']          = max( 1, min( 25, absint( $merged['max_links_per_post'] ) ) );
		$merged['max_external_links_per_post'] = max( 1, min( 10, absint( $merged['max_external_links_per_post'] ) ) );
		$merged['auto_insert_internal_links']  = empty( $merged['auto_insert_internal_links'] ) ? 0 : 1;
		$merged['never_modify_posts']          = empty( $merged['never_modify_posts'] ) ? 0 : 1;
		$merged['link_suggestion_limit']       = max( 1, min( 20, absint( $merged['link_suggestion_limit'] ) ) );
		$merged['bulk_queue_batch_size']       = max( 1, min( 50, absint( $merged['bulk_queue_batch_size'] ) ) );
		$merged['gsc_token_cache_seconds']     = max( 300, absint( $merged['gsc_token_cache_seconds'] ) );
		$merged['gsc_lookback_days']           = max( 7, min( 365, absint( $merged['gsc_lookback_days'] ) ) );
		$merged['cache_ttl_seconds']           = max( 60, absint( $merged['cache_ttl_seconds'] ) );
		$merged['link_graph_rebuild_batch']    = max( 10, min( 300, absint( $merged['link_graph_rebuild_batch'] ) ) );
		$merged['broken_link_timeout_seconds'] = max( 2, min( 20, absint( $merged['broken_link_timeout_seconds'] ) ) );
		$merged['image_large_threshold_kb']    = max( 50, min( 5000, absint( $merged['image_large_threshold_kb'] ) ) );
		$merged['ai_timeout_seconds']          = max( 5, min( 60, absint( $merged['ai_timeout_seconds'] ) ) );

		if ( ! is_array( $merged['auto_insert_post_types'] ) ) {
			$merged['auto_insert_post_types'] = array( 'post' );
		}
		if ( ! is_array( $merged['auto_insert_statuses'] ) ) {
			$merged['auto_insert_statuses'] = array( 'publish' );
		}

		$merged['gsc_site_url']              = is_string( $merged['gsc_site_url'] ) ? trim( $merged['gsc_site_url'] ) : '';
		$merged['gsc_service_account_email'] = is_string( $merged['gsc_service_account_email'] ) ? trim( $merged['gsc_service_account_email'] ) : '';
		$merged['gsc_private_key_pem']       = is_string( $merged['gsc_private_key_pem'] ) ? trim( $merged['gsc_private_key_pem'] ) : '';
		$merged['psi_api_key']               = is_string( $merged['psi_api_key'] ) ? trim( $merged['psi_api_key'] ) : '';
		$merged['ai_provider']               = is_string( $merged['ai_provider'] ) ? trim( $merged['ai_provider'] ) : 'openai_compatible';
		$merged['ai_endpoint']               = is_string( $merged['ai_endpoint'] ) ? trim( $merged['ai_endpoint'] ) : '';
		$merged['ai_api_key']                = is_string( $merged['ai_api_key'] ) ? trim( $merged['ai_api_key'] ) : '';
		$merged['ai_model']                  = is_string( $merged['ai_model'] ) ? trim( $merged['ai_model'] ) : '';

		return $merged;
	}

	/**
	 * @inheritDoc
	 */
	public function update( array $settings ): void {
		$current = $this->all();
		$merged  = array_merge( $current, $settings );
		$merged  = array_merge( $this->defaults(), $merged );
		$this->repository->write( $merged );
	}

	/**
	 * @inheritDoc
	 */
	public function sanitize( $value ): array {
		$defaults = $this->defaults();
		$current  = $this->repository->read();

		// Start from existing settings so partial forms do not wipe sibling keys (Fix 1).
		$clean = array_merge( $defaults, $current );

		if ( ! is_array( $value ) ) {
			return $clean;
		}

		if ( array_key_exists( 'thin_content_min_words', $value ) ) {
			$clean['thin_content_min_words'] = max( 50, absint( $value['thin_content_min_words'] ) );
		}
		if ( array_key_exists( 'min_relevance_score', $value ) ) {
			$clean['min_relevance_score'] = max( 0, min( 100, absint( $value['min_relevance_score'] ) ) );
		}
		if ( array_key_exists( 'max_links_per_post', $value ) ) {
			$clean['max_links_per_post'] = max( 1, min( 25, absint( $value['max_links_per_post'] ) ) );
		}
		if ( array_key_exists( 'max_external_links_per_post', $value ) ) {
			$clean['max_external_links_per_post'] = max( 1, min( 10, absint( $value['max_external_links_per_post'] ) ) );
		}

		// Checkbox is omitted when unchecked. Only treat absence as "off" when this
		// form is clearly the Auto Linking screen (other related fields are present).
		$is_auto_linking_form = array_key_exists( 'min_relevance_score', $value )
			|| array_key_exists( 'max_links_per_post', $value )
			|| array_key_exists( 'auto_insert_post_types', $value )
			|| array_key_exists( 'auto_insert_statuses', $value )
			|| array_key_exists( 'auto_insert_internal_links', $value );
		if ( $is_auto_linking_form ) {
			$clean['auto_insert_internal_links'] = empty( $value['auto_insert_internal_links'] ) ? 0 : 1;
		}

		// Hidden sentinel from Settings → Security so an unchecked checkbox is stored as 0.
		// WordPress calls sanitize_option twice (options.php, then update_option).
		// never_modify_posts_present is not a stored key, so pass 2 only has 0|1.
		if ( array_key_exists( 'never_modify_posts_present', $value ) ) {
			$clean['never_modify_posts'] = empty( $value['never_modify_posts'] ) ? 0 : 1;
		} elseif ( array_key_exists( 'never_modify_posts', $value ) ) {
			$clean['never_modify_posts'] = empty( $value['never_modify_posts'] ) ? 0 : 1;
		}

		if ( array_key_exists( 'auto_insert_post_types', $value ) ) {
			$post_types = $value['auto_insert_post_types'];
			if ( ! is_array( $post_types ) ) {
				$post_types = array();
			}
			$post_types                      = array_values( array_unique( array_filter( array_map( 'sanitize_key', $post_types ) ) ) );
			$clean['auto_insert_post_types'] = $post_types ? $post_types : $defaults['auto_insert_post_types'];
		}

		if ( array_key_exists( 'auto_insert_statuses', $value ) ) {
			$statuses = $value['auto_insert_statuses'];
			if ( ! is_array( $statuses ) ) {
				$statuses = array();
			}
			$statuses                      = array_values( array_unique( array_filter( array_map( 'sanitize_key', $statuses ) ) ) );
			$clean['auto_insert_statuses'] = $statuses ? $statuses : $defaults['auto_insert_statuses'];
		}

		if ( array_key_exists( 'link_suggestion_limit', $value ) ) {
			$clean['link_suggestion_limit'] = max( 1, min( 20, absint( $value['link_suggestion_limit'] ) ) );
		}
		if ( array_key_exists( 'bulk_queue_batch_size', $value ) ) {
			$clean['bulk_queue_batch_size'] = max( 1, min( 50, absint( $value['bulk_queue_batch_size'] ) ) );
		}
		if ( array_key_exists( 'cache_ttl_seconds', $value ) ) {
			$clean['cache_ttl_seconds'] = max( 60, absint( $value['cache_ttl_seconds'] ) );
		}
		if ( array_key_exists( 'link_graph_rebuild_batch', $value ) ) {
			$clean['link_graph_rebuild_batch'] = max( 10, min( 300, absint( $value['link_graph_rebuild_batch'] ) ) );
		}
		if ( array_key_exists( 'broken_link_timeout_seconds', $value ) ) {
			$clean['broken_link_timeout_seconds'] = max( 2, min( 20, absint( $value['broken_link_timeout_seconds'] ) ) );
		}
		if ( array_key_exists( 'image_large_threshold_kb', $value ) ) {
			$clean['image_large_threshold_kb'] = max( 50, min( 5000, absint( $value['image_large_threshold_kb'] ) ) );
		}

		if ( array_key_exists( 'gsc_site_url', $value ) ) {
			$clean['gsc_site_url'] = sanitize_text_field( (string) $value['gsc_site_url'] );
		}
		if ( array_key_exists( 'gsc_service_account_email', $value ) ) {
			$clean['gsc_service_account_email'] = sanitize_email( (string) $value['gsc_service_account_email'] );
		}
		if ( array_key_exists( 'gsc_private_key_pem', $value ) ) {
			// Empty / placeholder keeps the existing PEM (Fix 5).
			if ( ! $this->is_unchanged_secret_submission( $value['gsc_private_key_pem'] ) ) {
				$pem                          = (string) $value['gsc_private_key_pem'];
				$clean['gsc_private_key_pem'] = trim( str_replace( array( "\r\n", "\r" ), "\n", $pem ) );
			}
		}
		if ( array_key_exists( 'gsc_token_cache_seconds', $value ) ) {
			$clean['gsc_token_cache_seconds'] = max( 300, absint( $value['gsc_token_cache_seconds'] ) );
		}
		if ( array_key_exists( 'gsc_lookback_days', $value ) ) {
			$clean['gsc_lookback_days'] = max( 7, min( 365, absint( $value['gsc_lookback_days'] ) ) );
		}

		if ( array_key_exists( 'psi_api_key', $value ) ) {
			if ( ! $this->is_unchanged_secret_submission( $value['psi_api_key'] ) ) {
				$clean['psi_api_key'] = sanitize_text_field( (string) $value['psi_api_key'] );
			}
		}

		if ( array_key_exists( 'ai_provider', $value ) ) {
			$clean['ai_provider'] = sanitize_key( (string) $value['ai_provider'] );
		}
		if ( array_key_exists( 'ai_endpoint', $value ) ) {
			$clean['ai_endpoint'] = esc_url_raw( (string) $value['ai_endpoint'] );
		}
		if ( array_key_exists( 'ai_api_key', $value ) ) {
			if ( ! $this->is_unchanged_secret_submission( $value['ai_api_key'] ) ) {
				$clean['ai_api_key'] = sanitize_text_field( (string) $value['ai_api_key'] );
			}
		}
		if ( array_key_exists( 'ai_model', $value ) ) {
			$clean['ai_model'] = sanitize_text_field( (string) $value['ai_model'] );
		}
		if ( array_key_exists( 'ai_timeout_seconds', $value ) ) {
			$clean['ai_timeout_seconds'] = max( 5, min( 60, absint( $value['ai_timeout_seconds'] ) ) );
		}

		return $clean;
	}

	/**
	 * @inheritDoc
	 */
	public function secret_keys(): array {
		return array(
			'ai_api_key',
			'psi_api_key',
			'gsc_private_key_pem',
		);
	}

	/**
	 * @inheritDoc
	 */
	public function secret_placeholder(): string {
		return '••••••••';
	}

	/**
	 * @inheritDoc
	 */
	public function is_unchanged_secret_submission( $value ): bool {
		if ( ! is_string( $value ) ) {
			return true;
		}
		$trimmed = trim( $value );
		return $trimmed === '' || $trimmed === $this->secret_placeholder();
	}

	/**
	 * @inheritDoc
	 */
	public function has_secret( array $settings, string $key ): bool {
		return ! empty( $settings[ $key ] ) && is_string( $settings[ $key ] ) && trim( $settings[ $key ] ) !== '';
	}

	/**
	 * Expose repository option key for legacy rsaip_option_key().
	 */
	public function option_key(): string {
		return $this->repository->option_key();
	}
}
