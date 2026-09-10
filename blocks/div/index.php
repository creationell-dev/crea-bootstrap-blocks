<?php
/**
 * Render template of the `creabb/div` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_div()` (`blocks/div.php` des
 * Alt-Plugins). Der einfachste der vier Layoutbloecke: kein `container`, kein
 * `align`, kein CSS-Grid-Modus, keine Spalten- oder Ordnungsklassen — nur der
 * gemeinsame Attributkern.
 *
 * Ausgabereihenfolge wie im Original: Hintergrund, Inhalt, Link.
 *
 * Ausgegeben wird `creabb-full-link` plus `areoi-full-link`, NICHT
 * `areoi-has-url`: Diese Klasse entsteht im Original ausschliesslich in der
 * Lightspeed-Integration und in keiner Renderfunktion unter `blocks/`.
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
require_once dirname( __DIR__ ) . '/_partials/link-overlay.php';

$cbb_a      = crea_bootstrap_blocks_native_attributes( is_array( $attributes ?? null ) ? $attributes : [], $block ?? [] );
$cbb_anchor = crea_bootstrap_blocks_anchor_attr( $cbb_a );

$cbb_class = crea_bootstrap_blocks_class_str(
    [
        crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
        crea_bootstrap_blocks_element_classes(),
        $cbb_a['className'] ?? '',
        crea_bootstrap_blocks_utilities_classes( $cbb_a ),
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' ),
    ]
);

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div <?php echo $cbb_anchor; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>"><?php
echo crea_bootstrap_blocks_background_markup( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup.
echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />.
echo crea_bootstrap_blocks_link_overlay( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup.
?></div>
