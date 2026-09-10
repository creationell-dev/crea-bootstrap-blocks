<?php
/**
 * Render template of the `creabb/card` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_card()` (`blocks/card.php` des
 * Alt-Plugins). Klassenreihenfolge woertlich uebernommen:
 *
 *   block-<uuid>  card  <className>  <background>  <text_color>
 *   <border_color>  <hide-Kaskade mit flex>
 *
 * KEINE Elementklasse. `areoi_render_block_card()` beginnt mit `card` und
 * kennt weder `areoi-element` noch das Hintergrundkonstrukt — das ist die
 * gemessene Ausgabe des Originals, kein Versehen.
 *
 * DAS INNENMARKUP STEHT IN `$inner_blocks`, NICHT IN `$content`. Blockstudios
 * Signatur lautet `render( $attributes, $inner_blocks = '', $wp_block = '',
 * $content = '' )`; WordPress ruft ein `render_callback` mit drei Argumenten
 * auf, `$content` behaelt deshalb auf jeder echten Seite seinen Vorgabewert
 * `''`. Ein Template mit `echo $content;` gibt die Kindbloecke nirgends aus.
 *
 * @package Creationell\BootstrapBlocks
 *
 * @var array<string, mixed> $attributes   Block attributes.
 * @var string               $inner_blocks Inner blocks output — the carrier of the child markup.
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
        'card',
        $cbb_a['className'] ?? '',
        $cbb_a['background'] ?? '',
        $cbb_a['text_color'] ?? '',
        $cbb_a['border_color'] ?? '',
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'flex' ),
    ]
);

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>"><?php
echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />.
?></div>
