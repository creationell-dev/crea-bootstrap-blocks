<?php
/**
 * Render template of the `creabb/collapse` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_collapse()`
 * (`blocks/collapse.php` des Alt-Plugins). Klassenreihenfolge woertlich:
 *
 *   block-<uuid>  collapse  <className>  show (bei open)  <hide-Kaskade mit block>
 *
 * DER BLOCK HAT KEINE `hide_*`-ATTRIBUTE — und die Renderfunktion des Originals
 * ruft die Sichtbarkeitskaskade trotzdem auf. Der Helfer liefert dafuer den
 * Leerstring. Der Aufruf bleibt stehen, damit die Uebersetzung 1:1 bleibt.
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
        'collapse',
        $cbb_a['className'] ?? '',
        empty( $cbb_a['open'] ) ? '' : 'show',
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' ),
    ]
);

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>"><?php
echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />.
?></div>
