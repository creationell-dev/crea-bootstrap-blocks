<?php
/**
 * Render template of the `creabb/column-break` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_column_break()`
 * (`blocks/column-break.php` des Alt-Plugins). Klassenreihenfolge woertlich
 * uebernommen:
 *
 *   block-<uuid>  w-100  <className>  <hide-Kaskade mit block>
 *
 * DIE HUELLE IST LEER — UND DAS IST ABSICHT. Die Renderfunktion des Originals
 * gibt `$content` an keiner Stelle aus: Der Block ist der Flexbox-Umbruch aus
 * der Bootstrap-Dokumentation, ein `div` mit `width: 100%` und sonst nichts.
 * Er ist damit der einzige der 47 Bloecke ohne Innenmarkup. Wer aus einer
 * anderen Vorlage kopiert und `echo $inner_blocks;` stehen laesst, gibt
 * Kindbloecke aus, die das Original verwirft — `tests/test-block-column-break.php`
 * prueft genau diesen Fall.
 *
 * KEINE Elementklasse und kein Hintergrundkonstrukt — die Renderfunktion des
 * Originals kennt beides nicht.
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
        crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
        'w-100',
        $cbb_a['className'] ?? '',
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' ),
    ]
);
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>"></div>
