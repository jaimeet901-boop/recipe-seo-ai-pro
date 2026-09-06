<?php
/**
 * Admin data table shell (panel + widefat table + AJAX tbody).
 *
 * @var array<int, string> $columns      Translated column labels.
 * @var string             $tbody_id     tbody element id (JS hook).
 * @var string|null        $tbody_class  Extra tbody classes (default rsaip-loading when data_load set).
 * @var string|null        $data_load    Optional data-load AJAX action.
 * @var bool|null          $wrap_panel   Wrap in .rsaip-panel (default true).
 *
 * @package RecipeSeoAiPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$columns     = isset( $columns ) && is_array( $columns ) ? $columns : array();
$tbody_id    = isset( $tbody_id ) ? (string) $tbody_id : '';
$data_load   = isset( $data_load ) ? (string) $data_load : '';
$wrap_panel  = ! isset( $wrap_panel ) || $wrap_panel;
$tbody_class = isset( $tbody_class ) ? (string) $tbody_class : ( $data_load !== '' ? 'rsaip-loading' : '' );

if ( $wrap_panel ) {
	echo '<div class="rsaip-panel rsaip-table-panel">';
}
?>
<div class="rsaip-table-scroll">
<table class="widefat striped rsaip-table">
	<thead>
		<tr>
			<?php foreach ( $columns as $label ) : ?>
				<th scope="col"><?php echo esc_html( (string) $label ); ?></th>
			<?php endforeach; ?>
		</tr>
	</thead>
	<tbody
		<?php echo $tbody_id !== '' ? ' id="' . esc_attr( $tbody_id ) . '"' : ''; ?>
		<?php echo $tbody_class !== '' ? ' class="' . esc_attr( $tbody_class ) . '"' : ''; ?>
		<?php echo $data_load !== '' ? ' data-load="' . esc_attr( $data_load ) . '"' : ''; ?>
	</tbody>
</table>
</div>
<?php
if ( $wrap_panel ) {
	echo '</div>';
}
