<?php
/**
 * Render template of the `creabb/modal` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_modal()` (`blocks/modal.php` des
 * Alt-Plugins). Aussere Klassenreihenfolge:
 *
 *   block-<uuid>  modal  fade  <className>
 *
 * Darin `div.modal-dialog` mit `<scrollable> <centered> <size>`, darin
 * `div.modal-content` mit dem Innenmarkup.
 *
 * KEINE SICHTBARKEITSKASKADE — der Block hat keine `hide_*`-Attribute, und die
 * Renderfunktion ruft den Helfer nicht auf.
 *
 * `backdrop` HAT EINE DOPPELTE BEDINGUNG: `!empty( $attributes['backdrop'] ) &&
 * $attributes['backdrop'] != 'Default'`. Der Literalwert `Default` unterdrueckt
 * das Attribut ebenso wie ein leerer Wert — `Default` ist hier ein echter
 * Optionswert, kein Platzhalter (Ruling E-15).
 *
 * DAS INNENMARKUP STEHT IN `$inner_blocks`, NICHT IN `$content` (Ruling E-33).
 *
 * @package Creationell\BootstrapBlocks
 *
 * @var array<string, mixed> $attributes   Block attributes.
 * @var string               $inner_blocks Inner blocks output.
 * @var array<string, mixed> $block        Block data provided by Blockstudio.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$cbb_a = crea_bootstrap_blocks_native_attributes( is_array( $attributes ?? null ) ? $attributes : [], $block ?? [] );

$cbb_class = crea_bootstrap_blocks_class_str(
    [
        crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
        'modal',
        'fade',
        $cbb_a['className'] ?? '',
    ]
);

$cbb_dialog = crea_bootstrap_blocks_class_str(
    [
        'modal-dialog',
        $cbb_a['scrollable'] ?? '',
        $cbb_a['centered'] ?? '',
        $cbb_a['size'] ?? '',
    ]
);

$cbb_backdrop = ( ! empty( $cbb_a['backdrop'] ) && 'Default' !== $cbb_a['backdrop'] )
    ? 'data-bs-backdrop="' . esc_attr( (string) $cbb_a['backdrop'] ) . '"'
    : '';

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>" tabindex="-1" aria-hidden="true" <?php echo $cbb_backdrop; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?>>
<div class="<?php echo esc_attr( $cbb_dialog ); ?>">
<div class="modal-content">
<?php echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />. ?>
</div>
</div>
</div>
