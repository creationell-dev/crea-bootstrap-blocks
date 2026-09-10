<?php
/**
 * Render template of the `creabb/modal-header` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_modal_header()`
 * (`blocks/modal-header.php` des Alt-Plugins). Klassenreihenfolge:
 *
 *   block-<uuid>  modal-header  <className>
 *
 * KEINE SICHTBARKEITSKASKADE.
 *
 * FESTER ZWISCHENBEHAELTER: Das Innenmarkup steht in
 * `<div class="modal-header-content">`, danach folgt der Schliesser — aber nur,
 * wenn `close_button` gesetzt ist.
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
        'modal-header',
        $cbb_a['className'] ?? '',
    ]
);

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>">
<div class="modal-header-content"><?php echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />. ?></div>
<?php if ( ! empty( $cbb_a['close_button'] ) ) : ?>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
<?php endif; ?>
</div>
