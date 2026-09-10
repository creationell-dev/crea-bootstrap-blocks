<?php
/**
 * Render template of the `creabb/strip` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_strip()` (`blocks/strip.php` des
 * Alt-Plugins). Klassenreihenfolge woertlich uebernommen:
 *
 *   block-<uuid>  areoi-strip  areoi-element  strip  align<align>
 *   <className>  <utilities>  <hide-Kaskade mit block>
 *
 * Die beiden ersten Klassen werden als Paar ausgegeben (Vertragsregel 2.2);
 * `strip` ohne Praefix ist eine eigene Klasse des Alt-Plugins und bleibt
 * unveraendert.
 *
 * EINER DER ZWEI BLOECKE MIT `$allow_pattern = true` (strip.php:4); der
 * andere ist media-grid (media-grid.php:4). Ohne Filter liefert das
 * Muster-Partial trotzdem den Leerstring (Aufgabe 4).
 *
 * `align` wird DURCHGEREICHT, nicht geklemmt — bis zum 2026-09-01 war das die
 * bewusste Abweichung dieses Blocks, mit E-145 ist sie gefallen. Das Original
 * haengt den Attributwert ungeprueft an das Literal `align` an (strip.php:11)
 * und escapt die Klasse am Ausgabepunkt; genau so macht es der Nachbau jetzt.
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

require_once dirname( __DIR__ ) . '/_partials/patterns.php';
require_once dirname( __DIR__ ) . '/_partials/background.php';

$cbb_a = crea_bootstrap_blocks_native_attributes( is_array( $attributes ?? null ) ? $attributes : [], $block ?? [] );

$cbb_align = '';

if ( ! empty( $cbb_a['align'] ) && is_string( $cbb_a['align'] ) ) {
    // E-145: DURCHGEREICHT, NICHT GEKLEMMT. Das Original haengt den Wert
    // ungeprueft an das Literal `align` (strip.php:11); escapt wird die Klasse
    // erst am Ausgabepunkt — auf beiden Seiten gleich. Eine Allowlist hier
    // schriebe einen gespeicherten Wert in einen ANDEREN um: aus
    // `aligncenter` wuerde `alignfull`. Vertragsebene 3 verlangt
    // aequivalentes Markup, nicht das bessere (E-41, E-77).
    $cbb_align = 'align' . $cbb_a['align'];
}

$cbb_class = crea_bootstrap_blocks_class_str(
    [
        crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
        crea_bootstrap_blocks_class_pair( 'strip' ),
        crea_bootstrap_blocks_element_classes(),
        'strip',
        $cbb_align,
        $cbb_a['className'] ?? '',
        crea_bootstrap_blocks_utilities_classes( $cbb_a ),
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' ),
    ]
);

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>"><?php
echo crea_bootstrap_blocks_background_markup( $cbb_a, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup.
echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />.
?></div>
