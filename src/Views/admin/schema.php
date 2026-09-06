<?php
/**
 * Admin: Schema Validator.
 *
 * @var string $title
 *
 * @package RecipeSeoAiPro
 */

use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

View::render( 'partials/header', array( 'title' => $title ) );
?>
<div class="rsaip-actions">
	<button class="button button-primary rsaip-btn" data-action="rsaip_schema_scan"><?php echo esc_html__( 'Scan Schema', 'recipe-seo-ai-pro' ); ?></button>
	<button class="button rsaip-btn" data-action="rsaip_schema_fix_batch" data-limit="50"><?php echo esc_html__( 'Auto Fix Common Issues', 'recipe-seo-ai-pro' ); ?></button>
	<button class="button rsaip-btn" data-action="rsaip_schema_enqueue_queue" data-limit="200"><?php echo esc_html__( 'Queue Auto Fix All', 'recipe-seo-ai-pro' ); ?></button>
	<button class="button rsaip-btn" data-action="rsaip_schema_process_queue" data-limit="50"><?php echo esc_html__( 'Process Schema Queue', 'recipe-seo-ai-pro' ); ?></button>
	<button class="button rsaip-btn" data-action="rsaip_schema_queue_stats"><?php echo esc_html__( 'Schema Queue Stats', 'recipe-seo-ai-pro' ); ?></button>
</div>
<?php
View::render(
	'partials/table',
	array(
		'columns'   => array(
			__( 'Post', 'recipe-seo-ai-pro' ),
			__( 'Detected Schema', 'recipe-seo-ai-pro' ),
			__( 'Errors', 'recipe-seo-ai-pro' ),
			__( 'Suggestions', 'recipe-seo-ai-pro' ),
		),
		'tbody_id'  => 'rsaip-schema-body',
		'data_load' => 'rsaip_schema_scan',
	)
);
View::render(
	'partials/notice',
	array(
		'id'          => 'rsaip-schema-fix-result',
		'extra_class' => 'rsaip-panel',
		'message'     => __( 'Automatic schema fixes will appear here.', 'recipe-seo-ai-pro' ),
	)
);
View::render( 'partials/footer' );
