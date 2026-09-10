<?php
/**
 * Render template of the `creabb/list-group` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_list_group()`
 * (`blocks/list-group.php` des Alt-Plugins). Klassenreihenfolge woertlich:
 *
 *   block-<uuid>  list-group  <className>  <flush>  <style>  <layout>
 *   <hide-Kaskade mit flex>
 *
 * `style` STEHT NICHT IM VERTRAG DIESES BLOCKS. Die Renderfunktion des
 * Originals liest es trotzdem (`list-group.php:11`); da das Attribut nie
 * registriert ist, ist der Wert dort immer leer und die Stelle folgenlos. Der
 * Aufruf bleibt stehen, weil die Uebersetzung 1:1 sein soll — und weil eine
 * spaetere Registrierung des Attributs im Original hier sofort greifen wuerde.
 *
 * Ebenso ohne Wirkung: `layout_xs` bis `layout_xxl`. Sie stehen im Vertrag,
 * haben im Original weder ein Bedienelement noch eine Renderstelle.
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
        'list-group',
        $cbb_a['className'] ?? '',
        $cbb_a['flush'] ?? '',
        $cbb_a['style'] ?? '',
        $cbb_a['layout'] ?? '',
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'flex' ),
    ]
);

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>"><?php
echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />.
?></div>
