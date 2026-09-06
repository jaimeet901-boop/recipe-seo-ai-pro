<?php
/**
 * Secret setting field (password or textarea). Never echoes stored secret values.
 *
 * @var string      $name         Full input name attribute (e.g. rsaip_settings[ai_api_key]).
 * @var string      $type         "password" or "textarea".
 * @var bool        $has_secret   Whether a secret is already stored.
 * @var string      $placeholder  Placeholder text (••••••••).
 * @var string      $saved_note   Translated note shown when a secret is saved.
 * @var int|null    $rows         Textarea rows (default 8).
 * @var string|null $autocomplete Autocomplete attribute for password inputs.
 *
 * @package RecipeSeoAiPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$type         = isset( $type ) ? (string) $type : 'password';
$has_secret   = ! empty( $has_secret );
$placeholder  = isset( $placeholder ) ? (string) $placeholder : '';
$saved_note   = isset( $saved_note ) ? (string) $saved_note : '';
$rows         = isset( $rows ) ? (int) $rows : 8;
$autocomplete = isset( $autocomplete ) ? (string) $autocomplete : 'new-password';

if ( 'textarea' === $type ) :
	?>
<textarea name="<?php echo esc_attr( (string) $name ); ?>" rows="<?php echo esc_attr( (string) $rows ); ?>" spellcheck="false" autocomplete="off" placeholder="<?php echo esc_attr( $placeholder ); ?>"></textarea>
	<?php
else :
	?>
<input type="password" name="<?php echo esc_attr( (string) $name ); ?>" value="" autocomplete="<?php echo esc_attr( $autocomplete ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"/>
	<?php
endif;

if ( $has_secret && $saved_note !== '' ) :
	?>
<p class="rsaip-note"><?php echo esc_html( $saved_note ); ?></p>
	<?php
endif;
