<?php
/**
 * Render template of the `creabb/toast-header` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_toast_header()` (`blocks/toast-header.php` des
 * Alt-Plugins). Klassenreihenfolge woertlich:
 *
 *   block-<uuid>  toast-header  <className>
 *
 * KEINE SICHTBARKEITSKASKADE. Der Block hat keine `hide_*`-Attribute, und die
 * Renderfunktion des Originals ruft `areoi_get_display_class_str()` NICHT auf —
 * anders als bei 23 der 33 Bloecke der Phase 2.
 *
 * ZWISCHENBEHAELTER UND FESTER SCHLIESSER, wie bei `offcanvas-header` und
 * anders als bei `toast-body`: Das Innenmarkup steht in
 * `<div class="toast-header-content me-auto">`, danach folgt IMMER ein
 * Schliessen-Knopf — es gibt kein Attribut, das ihn abschaltet.
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
        'toast-header',
        $cbb_a['className'] ?? '',
    ]
);

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>">
<div class="toast-header-content me-auto"><?php echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />. ?></div>
<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
</div>
