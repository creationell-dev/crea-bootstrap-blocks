<?php
/**
 * Render template of the `creabb/nav-and-tab` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_nav_and_tab()`
 * (`blocks/nav-and-tab.php` des Alt-Plugins). Huelle ist ein `<nav>`, nicht ein
 * `<div>`. Klassenreihenfolge woertlich:
 *
 *   block-<uuid>  nav  <className>  <style>  <vertical>  <fill>
 *   <sechs vertical_align_*>  <sechs horizontal_align_*>
 *   <hide-Kaskade mit block>
 *
 * `vertical` STEHT NICHT IM VERTRAG. Die Renderfunktion des Originals liest es
 * (`nav-and-tab.php:9`), `block.json` deklariert es aber nicht — das Attribut
 * ist nie registriert und der Wert deshalb immer leer. Der Aufruf bleibt 1:1
 * stehen. Die vertikale Ausrichtung laeuft stattdessen ueber
 * `vertical_align_<bp>`.
 *
 * DIE AUSRICHTUNGSKLASSEN SIND AN DIE SICHTBARKEIT GEKOPPELT: Ist ein
 * Breakpoint ausgeblendet, entfaellt seine Ausrichtungsklasse. Diese Kopplung
 * geht beim Nachbau leicht verloren; die Matrix der Differenzsuite kreuzt beide
 * Schalter.
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
    'nav',
    $cbb_a['className'] ?? '',
    $cbb_a['style'] ?? '',
    $cbb_a['vertical'] ?? '',
    $cbb_a['fill'] ?? '',
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
<nav <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>"><?php
echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />.
?></nav>
