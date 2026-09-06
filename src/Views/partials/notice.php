<?php
/**
 * Notice / tip block (class rsaip-note).
 *
 * @var string      $message     Escaped by this partial via esc_html.
 * @var string|null $id          Optional element id.
 * @var string|null $extra_class Extra CSS classes (e.g. "rsaip-panel").
 * @var string|null $tag         HTML tag: div (default) or p — preserves legacy markup.
 *
 * @package RecipeSeoAiPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$id          = isset( $id ) ? (string) $id : '';
$extra_class = isset( $extra_class ) ? trim( (string) $extra_class ) : '';
$tag         = isset( $tag ) ? strtolower( (string) $tag ) : 'div';
if ( ! in_array( $tag, array( 'div', 'p' ), true ) ) {
	$tag = 'div';
}
// Prefer legacy order when panel+note: "rsaip-panel rsaip-note".
$classes = $extra_class !== '' ? trim( $extra_class . ' rsaip-note' ) : 'rsaip-note';
?>
<<?php echo esc_html( $tag ); ?><?php echo $id !== '' ? ' id="' . esc_attr( $id ) . '"' : ''; ?> class="<?php echo esc_attr( $classes ); ?>"><?php echo esc_html( (string) $message ); ?></<?php echo esc_html( $tag ); ?>>
