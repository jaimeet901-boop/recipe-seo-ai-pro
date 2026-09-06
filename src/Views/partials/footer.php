<?php
/**
 * Unified premium admin shell footer.
 *
 * Closes content / main / shell / wrap opened by partials/header.
 * Renders context sidebar unless the page requested hide_sidebar / wide layout.
 *
 * @var bool|null $hide_sidebar
 * @var string|null $ai_provider
 * @var bool|null $ai_ready
 *
 * @package RecipeSeoAiPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$hide_sidebar = ! empty( $hide_sidebar );

$wide_pages = array(
	'rsaip-recipe-builder',
	'rsaip-recipe-ai',
	'rsaip-content-calendar',
	'rsaip-keyword-workspace',
	'rsaip-content-optimizer',
	'rsaip-seo-projects',
	'rsaip-ai',
);
if ( in_array( $current_page, $wide_pages, true ) ) {
	$hide_sidebar = true;
}

$settings = function_exists( 'rsaip_get_settings' ) ? rsaip_get_settings() : array();
$provider = isset( $ai_provider ) ? sanitize_key( (string) $ai_provider ) : ( isset( $settings['ai_provider'] ) ? sanitize_key( (string) $settings['ai_provider'] ) : '' );
$endpoint = isset( $settings['ai_endpoint'] ) ? trim( (string) $settings['ai_endpoint'] ) : '';
$model    = isset( $settings['ai_model'] ) ? trim( (string) $settings['ai_model'] ) : '';
$has_key  = function_exists( 'rsaip_setting_has_secret' )
	? rsaip_setting_has_secret( $settings, 'ai_api_key' )
	: ( isset( $settings['ai_api_key'] ) && trim( (string) $settings['ai_api_key'] ) !== '' );

if ( ! isset( $ai_ready ) ) {
	$ai_ready = ( $provider === 'openai_compatible' ) && $endpoint !== '' && $model !== '' && $has_key;
}

$provider_label = $provider;
if ( $provider_label === '' ) {
	$provider_label = __( 'None', 'recipe-seo-ai-pro' );
} elseif ( $provider_label === 'openai_compatible' ) {
	$provider_label = __( 'OpenAI Compatible', 'recipe-seo-ai-pro' );
}

$status_label = ! empty( $ai_ready ) ? __( 'Ready', 'recipe-seo-ai-pro' ) : __( 'Not Configured', 'recipe-seo-ai-pro' );
?>
			</div><!-- .rsaip-app-content -->
		</div><!-- .rsaip-app-main -->
		<?php if ( ! $hide_sidebar ) : ?>
			<?php
			\RecipeSeoAiPro\Views\View::render(
				'partials/app-sidebar',
				array(
					'current_page'      => $current_page,
					'ai_ready'          => ! empty( $ai_ready ),
					'ai_provider_label' => $provider_label,
					'status_label'      => $status_label,
				)
			);
			?>
		<?php endif; ?>
	</div><!-- .rsaip-app-shell -->
</div><!-- .wrap -->
