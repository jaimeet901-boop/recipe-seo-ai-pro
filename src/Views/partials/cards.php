<?php
/**
 * Dashboard cards container (AJAX-filled).
 *
 * @var string      $id         Element id.
 * @var string|null $data_load  Optional data-load AJAX action.
 * @var string|null $class      Extra/override classes (default rsaip-cards rsaip-loading).
 *
 * @package RecipeSeoAiPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$id        = isset( $id ) ? (string) $id : 'rsaip-dashboard-cards';
$data_load = isset( $data_load ) ? (string) $data_load : '';
$class     = isset( $class ) ? (string) $class : 'rsaip-cards rsaip-loading';
$is_loading = strpos( $class, 'rsaip-loading' ) !== false;
?>
<div
	id="<?php echo esc_attr( $id ); ?>"
	class="<?php echo esc_attr( $class ); ?>"
	<?php echo $data_load !== '' ? ' data-load="' . esc_attr( $data_load ) . '"' : ''; ?>
	<?php echo $is_loading ? ' aria-busy="true"' : ''; ?>
>
	<?php if ( $is_loading ) : ?>
		<span class="rsaip-skeleton-card" aria-hidden="true"></span>
		<span class="rsaip-skeleton-card" aria-hidden="true"></span>
		<span class="rsaip-skeleton-card" aria-hidden="true"></span>
		<span class="rsaip-skeleton-card" aria-hidden="true"></span>
	<?php endif; ?>
</div>
