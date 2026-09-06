<?php
/**
 * Open a Settings API form (options.php).
 *
 * @var string|null $settings_group settings_fields() group (e.g. rsaip_settings_group).
 * @var string|null $class          Form class (default rsaip-form).
 *
 * @package RecipeSeoAiPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_group = isset( $settings_group ) ? (string) $settings_group : '';
$class          = isset( $class ) ? (string) $class : 'rsaip-form';
?>
<form method="post" action="options.php" class="<?php echo esc_attr( $class ); ?>">
<?php
if ( $settings_group !== '' ) {
	settings_fields( $settings_group );
}
