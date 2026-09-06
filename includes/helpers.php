<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function rsaip_capability(): string {
	return 'manage_options';
}

/**
 * Safe Article Mode (Milestone 1).
 *
 * When enabled, existing-post mutation writers refuse before any wp_update_post /
 * SEO meta write. Missing setting defaults to ON (fail-safe).
 */
function rsaip_never_modify_posts(): bool {
	if ( ! function_exists( 'rsaip_get_settings' ) ) {
		return true;
	}

	$settings = rsaip_get_settings();
	if ( ! is_array( $settings ) || ! array_key_exists( 'never_modify_posts', $settings ) ) {
		return true;
	}

	return ! empty( $settings['never_modify_posts'] );
}

/**
 * Shared refusal message for frozen existing-post mutations.
 */
function rsaip_existing_post_mutation_frozen_message(): string {
	return __( 'This existing-post mutation is temporarily disabled while Safe Article Mode is enabled.', 'recipe-seo-ai-pro' );
}

/**
 * Milestone 4: Fix With AI stays blocked even if Safe Article Mode is OFF.
 * Missing helper must fail closed (callers treat absence as blocked).
 */
function rsaip_fix_with_ai_blocked(): bool {
	return true;
}

function rsaip_fix_with_ai_frozen_message(): string {
	return __( 'Fix With AI is disabled in Milestone 4. Use Generate → Preview → Apply for title, meta description, and keywords only.', 'recipe-seo-ai-pro' );
}

/**
 * Bulk missing-meta Apply is not part of Milestone 4 (no per-post Preview).
 */
function rsaip_bulk_apply_meta_blocked(): bool {
	return true;
}

function rsaip_bulk_apply_meta_frozen_message(): string {
	return __( 'Bulk meta Apply is not enabled in Milestone 4. Preview then Apply a single post.', 'recipe-seo-ai-pro' );
}

/**
 * Bulk queue task types that mutate existing posts.
 *
 * @return string[]
 */
function rsaip_existing_post_mutation_task_types(): array {
	return array( 'fix_with_ai', 'schema_fix' );
}

/**
 * @param string $task_type Queue task type.
 */
function rsaip_is_existing_post_mutation_task( string $task_type ): bool {
	return in_array( sanitize_key( $task_type ), rsaip_existing_post_mutation_task_types(), true );
}

/**
 * Resolve the AI provider registry (container when booted, otherwise default).
 *
 * Phase 2C: HTTP transport selection for RSAIP_AI::chat().
 *
 * @return \RecipeSeoAiPro\Modules\Ai\AiProviderRegistry
 */
function rsaip_ai_provider_registry() {
	static $fallback = null;

	if ( function_exists( 'rsaip_app' ) ) {
		$app = rsaip_app();
		if ( $app && $app->is_booted() ) {
			$container = $app->container();
			if ( $container->has( \RecipeSeoAiPro\Modules\Ai\AiProviderRegistry::class ) ) {
				return $container->get( \RecipeSeoAiPro\Modules\Ai\AiProviderRegistry::class );
			}
		}
	}

	if ( null === $fallback ) {
		$fallback = \RecipeSeoAiPro\Modules\Ai\AiProviderRegistry::create_default();
	}

	return $fallback;
}

/**
 * Resolve a table repository (container when booted, otherwise a local instance).
 *
 * Phase 2B: legacy RSAIP_* classes call repositories for SQL only.
 *
 * @template T of object
 * @param class-string<T> $class Fully-qualified repository class name.
 * @return T
 */
function rsaip_repo( string $class ) {
	static $fallback = array();

	if ( function_exists( 'rsaip_app' ) ) {
		$app = rsaip_app();
		if ( $app && $app->is_booted() ) {
			$container = $app->container();
			if ( $container->has( $class ) ) {
				return $container->get( $class );
			}
		}
	}

	if ( ! isset( $fallback[ $class ] ) ) {
		$fallback[ $class ] = new $class();
	}

	return $fallback[ $class ];
}

/**
 * Resolve the Settings service (container when booted, otherwise a local instance).
 *
 * Safe during activation (before plugins_loaded) because Autoloader is registered
 * in the main plugin file before this helper is used.
 *
 * @return \RecipeSeoAiPro\Modules\Settings\SettingsService
 */
function rsaip_settings_service() {
	static $fallback = null;

	if ( function_exists( 'rsaip_app' ) ) {
		$app = rsaip_app();
		if ( $app && $app->is_booted() ) {
			$container = $app->container();
			if ( $container->has( \RecipeSeoAiPro\Modules\Settings\SettingsService::class ) ) {
				return $container->get( \RecipeSeoAiPro\Modules\Settings\SettingsService::class );
			}
			if ( $container->has( \RecipeSeoAiPro\Contracts\SettingsServiceInterface::class ) ) {
				return $container->get( \RecipeSeoAiPro\Contracts\SettingsServiceInterface::class );
			}
		}
	}

	if ( null === $fallback ) {
		$fallback = new \RecipeSeoAiPro\Modules\Settings\SettingsService(
			new \RecipeSeoAiPro\Modules\Settings\SettingsRepository()
		);
	}

	return $fallback;
}

/**
 * Resolve PostMutationService (container when booted, otherwise a local instance).
 *
 * Milestone 3A: used only for propose/preview. Must never be used to bypass Safe Mode Apply freezes.
 *
 * @return \RecipeSeoAiPro\Modules\PostMutation\PostMutationService
 */
function rsaip_post_mutation_service() {
	static $fallback = null;

	if ( function_exists( 'rsaip_app' ) ) {
		$app = rsaip_app();
		if ( $app && $app->is_booted() ) {
			$container = $app->container();
			if ( $container->has( \RecipeSeoAiPro\Contracts\PostMutationServiceInterface::class ) ) {
				return $container->get( \RecipeSeoAiPro\Contracts\PostMutationServiceInterface::class );
			}
			if ( $container->has( \RecipeSeoAiPro\Modules\PostMutation\PostMutationService::class ) ) {
				return $container->get( \RecipeSeoAiPro\Modules\PostMutation\PostMutationService::class );
			}
		}
	}

	if ( null === $fallback ) {
		$env       = new \RecipeSeoAiPro\Modules\PostMutation\WpPostMutationEnvironment();
		$ownership = new \RecipeSeoAiPro\Modules\PostMutation\SeoOwnershipResolver( $env );
		$fallback  = new \RecipeSeoAiPro\Modules\PostMutation\PostMutationService(
			$env,
			$ownership,
			new \RecipeSeoAiPro\Modules\PostMutation\FieldFingerprint(),
			new \RecipeSeoAiPro\Modules\PostMutation\NullMutationSnapshotStore(),
			array(
				new \RecipeSeoAiPro\Modules\PostMutation\Writers\TitleWriter( $env ),
				new \RecipeSeoAiPro\Modules\PostMutation\Writers\MetaDescriptionWriter( $env, $ownership ),
				new \RecipeSeoAiPro\Modules\PostMutation\Writers\FocusKeywordWriter( $env, $ownership ),
				new \RecipeSeoAiPro\Modules\PostMutation\Writers\KeywordsWriter( $env, $ownership ),
			)
		);
	}

	return $fallback;
}

/**
 * Facade ? SettingsService::option_key() (still returns rsaip_settings).
 */
function rsaip_option_key(): string {
	return rsaip_settings_service()->option_key();
}

/**
 * Setting keys that must never be echoed into HTML and must keep existing values when left blank.
 *
 * Facade ? SettingsService::secret_keys()
 *
 * @return string[]
 */
function rsaip_secret_setting_keys(): array {
	return rsaip_settings_service()->secret_keys();
}

/**
 * Facade ? SettingsService::secret_placeholder()
 */
function rsaip_secret_placeholder(): string {
	return rsaip_settings_service()->secret_placeholder();
}

/**
 * True when a submitted secret should be ignored (keep stored value).
 *
 * Facade ? SettingsService::is_unchanged_secret_submission()
 *
 * @param mixed $value Submitted form value.
 */
function rsaip_is_unchanged_secret_submission( $value ): bool {
	return rsaip_settings_service()->is_unchanged_secret_submission( $value );
}

/**
 * Facade ? SettingsService::has_secret()
 */
function rsaip_setting_has_secret( array $settings, string $key ): bool {
	return rsaip_settings_service()->has_secret( $settings, $key );
}

/**
 * Facade ? SettingsService::defaults()
 */
function rsaip_default_settings(): array {
	return rsaip_settings_service()->defaults();
}

/**
 * Facade ? SettingsService::all()
 */
function rsaip_get_settings(): array {
	return rsaip_settings_service()->all();
}

/**
 * Facade ? SettingsService::update()
 */
function rsaip_update_settings( array $settings ): void {
	rsaip_settings_service()->update( $settings );
}

function rsaip_admin_url( string $page, array $args = array() ): string {
	$args = array_merge(
		array(
			'page' => $page,
		),
		$args
	);
	return add_query_arg( $args, admin_url( 'admin.php' ) );
}

function rsaip_text_normalize( string $text ): string {
	$text = wp_strip_all_tags( $text );
	$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	$text = preg_replace( '/\s+/u', ' ', $text );
	$text = trim( (string) $text );
	return mb_strtolower( $text );
}

function rsaip_get_word_count_from_post( WP_Post $post ): int {
	$content = wp_strip_all_tags( (string) $post->post_content );
	$content = preg_replace( '/\s+/u', ' ', $content );
	$content = trim( (string) $content );
	if ( $content === '' ) {
		return 0;
	}
	$parts = preg_split( '/\s+/u', $content );
	if ( ! is_array( $parts ) ) {
		return 0;
	}
	return count( $parts );
}

function rsaip_get_focus_keywords( int $post_id ): array {
	$keywords = array();

	$rank_math = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
	if ( is_string( $rank_math ) && $rank_math !== '' ) {
		$parts = array_map( 'trim', explode( ',', $rank_math ) );
		foreach ( $parts as $p ) {
			if ( $p !== '' ) {
				$keywords[] = $p;
			}
		}
	}

	$yoast = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
	if ( is_string( $yoast ) && $yoast !== '' ) {
		$keywords[] = trim( $yoast );
	}

	$yoast_multi = get_post_meta( $post_id, '_yoast_wpseo_focuskeywords', true );
	if ( is_string( $yoast_multi ) && $yoast_multi !== '' ) {
		$decoded = json_decode( $yoast_multi, true );
		if ( is_array( $decoded ) ) {
			foreach ( $decoded as $row ) {
				if ( is_array( $row ) && ! empty( $row['keyword'] ) && is_string( $row['keyword'] ) ) {
					$keywords[] = trim( $row['keyword'] );
				}
			}
		}
	}

	$keywords = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $keywords ) ) ) );
	return $keywords;
}

function rsaip_save_focus_keywords( int $post_id, array $keywords, string $target = 'auto' ): array {
	$keywords = array_values(
		array_unique(
			array_filter(
				array_map(
					static function ( $keyword ) {
						return sanitize_text_field( (string) $keyword );
					},
					$keywords
				)
			)
		)
	);

	if ( empty( $keywords ) ) {
		return array();
	}

	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$rank_active  = function_exists( 'is_plugin_active' ) ? is_plugin_active( 'seo-by-rank-math/rank-math.php' ) : false;
	$yoast_active = function_exists( 'is_plugin_active' ) ? is_plugin_active( 'wordpress-seo/wp-seo.php' ) : false;
	$target       = $target ? sanitize_key( $target ) : 'auto';
	$updated      = array();

	if ( 'rankmath' === $target || ( 'auto' === $target && $rank_active ) ) {
		update_post_meta( $post_id, 'rank_math_focus_keyword', implode( ', ', $keywords ) );
		$updated[] = 'rank_math_focus_keyword';
	}

	if ( 'yoast' === $target || ( 'auto' === $target && $yoast_active ) ) {
		update_post_meta( $post_id, '_yoast_wpseo_focuskw', (string) $keywords[0] );
		$updated[] = '_yoast_wpseo_focuskw';
	}

	update_post_meta( $post_id, 'rsaip_generated_keywords', wp_json_encode( $keywords ) );
	$updated[] = 'rsaip_generated_keywords';

	return array_values( array_unique( $updated ) );
}

function rsaip_get_meta_description( int $post_id ): string {
	$rank_math_desc = get_post_meta( $post_id, 'rank_math_description', true );
	if ( is_string( $rank_math_desc ) && trim( $rank_math_desc ) !== '' ) {
		return trim( $rank_math_desc );
	}
	$yoast_desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
	if ( is_string( $yoast_desc ) && trim( $yoast_desc ) !== '' ) {
		return trim( $yoast_desc );
	}
	return '';
}

function rsaip_is_same_site_url( string $url, string $site_url ): bool {
	$u1 = wp_parse_url( $url );
	$u2 = wp_parse_url( $site_url );
	if ( ! is_array( $u1 ) || ! is_array( $u2 ) ) {
		return false;
	}
	$host1 = $u1['host'] ?? '';
	$host2 = $u2['host'] ?? '';
	if ( $host1 === '' || $host2 === '' ) {
		return false;
	}
	return strtolower( $host1 ) === strtolower( $host2 );
}

function rsaip_string_starts_with( string $haystack, string $needle ): bool {
	return $needle === '' ? true : strncmp( $haystack, $needle, strlen( $needle ) ) === 0;
}

/**
 * SSRF-safe URL check for general outbound fetches (blocks localhost / private IPs).
 */
function rsaip_is_safe_remote_url( string $url ): bool {
	$url = trim( $url );
	if ( $url === '' ) {
		return false;
	}

	$url = esc_url_raw( $url );
	if ( $url === '' ) {
		return false;
	}

	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) ) {
		return false;
	}

	$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
	if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
		return false;
	}

	$host = strtolower( (string) ( $parts['host'] ?? '' ) );
	if ( $host === '' ) {
		return false;
	}

	if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
		return false;
	}

	if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
		return filter_var(
			$host,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		) !== false;
	}

	$resolved_ips = array();
	if ( function_exists( 'dns_get_record' ) ) {
		$records = @dns_get_record( $host, DNS_A | DNS_AAAA );
		if ( is_array( $records ) ) {
			foreach ( $records as $record ) {
				if ( ! is_array( $record ) ) {
					continue;
				}
				if ( ! empty( $record['ip'] ) && is_string( $record['ip'] ) ) {
					$resolved_ips[] = $record['ip'];
				}
				if ( ! empty( $record['ipv6'] ) && is_string( $record['ipv6'] ) ) {
					$resolved_ips[] = $record['ipv6'];
				}
			}
		}
	}

	if ( empty( $resolved_ips ) && function_exists( 'gethostbynamel' ) ) {
		$ipv4 = @gethostbynamel( $host );
		if ( is_array( $ipv4 ) ) {
			$resolved_ips = array_merge( $resolved_ips, $ipv4 );
		}
	}

	if ( empty( $resolved_ips ) ) {
		return false;
	}

	foreach ( $resolved_ips as $ip ) {
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
			return false;
		}
	}

	return true;
}

/**
 * SSRF-safe AI endpoint check.
 * Same protections as rsaip_is_safe_remote_url, but allows localhost / loopback
 * for Ollama, LM Studio, and other local OpenAI-compatible servers.
 */
function rsaip_is_safe_ai_endpoint( string $url ): bool {
	$url = trim( $url );
	if ( $url === '' ) {
		return false;
	}

	$url = esc_url_raw( $url );
	if ( $url === '' ) {
		return false;
	}

	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) ) {
		return false;
	}

	$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
	if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
		return false;
	}

	$host = strtolower( (string) ( $parts['host'] ?? '' ) );
	if ( $host === '' ) {
		return false;
	}

	// Explicit local AI allowlist only ? not arbitrary private LAN hosts.
	if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
		return true;
	}

	return rsaip_is_safe_remote_url( $url );
}
