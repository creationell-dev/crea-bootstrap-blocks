<?php
/**
 * Render template of the `creabb/spinner` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_spinner()` (`blocks/spinner.php`
 * des Alt-Plugins). Klassenreihenfolge woertlich:
 *
 *   <style>  <color>  <style . size>  block-<uuid>  <className>
 *   <hide-Kaskade mit block>
 *
 * ZWEI BESONDERHEITEN:
 *
 * 1. `block-<uuid>` STEHT AN VIERTER STELLE, nicht am Anfang. Alle vierzehn
 *    Bloecke der Phase 1 setzen es zuerst; `spinner` und `progress` nicht. Die
 *    Klassenreihenfolge ist Teil des Zeichenvergleichs.
 *
 * 2. `size` WIRD AN `style` ANGEHAENGT: `$attributes['style'] .
 *    $attributes['size']`, also `spinner-border` plus `-sm`. Ist `style` leer
 *    und `size` gesetzt, entsteht ein Klassenname aus `size` allein — auch das
 *    ist Bestand.
 *
 * DAS INNENMARKUP IST FEST. Die Renderfunktion gibt `$content` nirgends aus;
 * im Inneren steht ausschliesslich `<span class="visually-hidden">Loading...</span>`.
 * Das englische `Loading...` ist ein Literal des Originals und bleibt
 * unuebersetzt — es ist kein Editorstring.
 *
 * @package Creationell\BootstrapBlocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var array<string, mixed> $block      Block data provided by Blockstudio.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$cbb_a = crea_bootstrap_blocks_native_attributes( is_array( $attributes ?? null ) ? $attributes : [], $block ?? [] );

$cbb_class = crea_bootstrap_blocks_class_str(
    [
        $cbb_a['style'] ?? '',
        $cbb_a['color'] ?? '',
        empty( $cbb_a['size'] ) ? '' : ( (string) ( $cbb_a['style'] ?? '' ) . (string) $cbb_a['size'] ),
        crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
        $cbb_a['className'] ?? '',
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' ),
    ]
);
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>">
<span class="visually-hidden">Loading...</span>
</div>
