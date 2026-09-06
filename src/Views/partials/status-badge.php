<?php
/**
 * Status badge chip (for reusable status UI; JS may also inject similar markup).
 *
 * @var string      $label  Badge text.
 * @var string|null $status Status key used as rsaip-badge-{status} (e.g. ok, warn, error).
 *
 * @package RecipeSeoAiPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$label  = isset( $label ) ? (string) $label : '';
$status = isset( $status ) ? sanitize_html_class( (string) $status ) : '';
$class = 'rsaip-badge';
if ( $status !== '' ) {
	// Map legacy keys to design-system variants.
	$map = array(
		'ok'      => 'ok',
		'success' => 'ok',
		'warn'    => 'warn',
		'warning' => 'warn',
		'error'   => 'error',
		'danger'  => 'error',
	);
	$key    = isset( $map[ $status ] ) ? $map[ $status ] : $status;
	$class .= ' rsaip-badge-' . $key;
}
?>
<span class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></span>
