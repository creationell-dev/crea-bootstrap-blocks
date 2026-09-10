<?php
/**
 * Render template of the `creabb/modal-footer` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_modal_footer()` (`blocks/modal-footer.php` des
 * Alt-Plugins). Klassenreihenfolge woertlich:
 *
 *   block-<uuid>  modal-footer  <className>
 *
 * KEINE SICHTBARKEITSKASKADE. Der Block hat keine `hide_*`-Attribute, und die
 * Renderfunktion des Originals ruft `areoi_get_display_class_str()` NICHT auf —
 * anders als bei 23 der 33 Bloecke der Phase 2. Ein Template, das sie „der
 * Ordnung halber" ergaenzt, erzeugt eine Klasse, die das Original nicht kennt,
 * und faellt im Zeichenvergleich sofort um.
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
        'modal-footer',
        $cbb_a['className'] ?? '',
    ]
);

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>"><?php
echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />.
?></div>
