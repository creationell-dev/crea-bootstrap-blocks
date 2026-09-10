<?php
/**
 * Render template of the `creabb/tabs` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_tabs()` (`blocks/tabs.php` des
 * Alt-Plugins). Klassenreihenfolge woertlich:
 *
 *   block-<uuid>  creabb-tabs areoi-tabs  <className>
 *   <sechs vertical_align_*>  <sechs horizontal_align_*>
 *   <hide-Kaskade mit block>
 *
 * DIE GRUNDKLASSE TRAEGT DEN ALTEN NAMENSRAUM. Das Original schreibt
 * `areoi-tabs` — hier steckt der Praefix in der KLASSE, nicht nur im
 * Blocknamen. Nach Vertragsebene 3 gibt der Nachbau beide Saetze aus, ueber
 * `crea_bootstrap_blocks_class_pair()`; Themes stylen gegen `areoi-tabs`, und
 * die Klasse verschwindet erst, wenn `CREA_BOOTSTRAP_BLOCKS_LEGACY_CLASSES`
 * abgeschaltet wird. Der Differenzrahmen streicht `creabb-*` vor dem Vergleich.
 *
 * DIE ZWOELF AUSRICHTUNGSATTRIBUTE STEHEN NICHT IM VERTRAG DIESES BLOCKS.
 * `areoi_render_block_tabs()` liest `vertical_align_<bp>` und
 * `horizontal_align_<bp>`, `blocks/tabs/block.json` deklariert sie aber nicht —
 * sie sind nie registriert und deshalb immer leer. Die Aufrufe bleiben 1:1
 * stehen, samt ihrer Kopplung an `hide_<bp>`: Ist ein Breakpoint ausgeblendet,
 * entfaellt seine Ausrichtungsklasse.
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

$cbb_parts = [
    crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
    crea_bootstrap_blocks_class_pair( 'tabs' ),
    $cbb_a['className'] ?? '',
];

foreach ( [ 'vertical_align', 'horizontal_align' ] as $cbb_axis ) {
    foreach ( [ 'xs', 'sm', 'md', 'lg', 'xl', 'xxl' ] as $cbb_bp ) {
        $cbb_parts[] = ( empty( $cbb_a[ 'hide_' . $cbb_bp ] ) && ! empty( $cbb_a[ $cbb_axis . '_' . $cbb_bp ] ) )
            ? (string) $cbb_a[ $cbb_axis . '_' . $cbb_bp ]
            : '';
    }
}

$cbb_parts[] = crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' );

$cbb_class = crea_bootstrap_blocks_class_str( $cbb_parts );

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>"><?php
echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />.
?></div>
