<?php
/**
 * Render template of the `creabb/container` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_container()` (`blocks/container.php`
 * des Alt-Plugins). Klassenreihenfolge woertlich uebernommen:
 *
 *   block-<uuid>  areoi-element  <container>  align<align>  <className>
 *   <utilities>  <hide-Kaskade>
 *
 * Ausgabereihenfolge: Hintergrund, dann Inhalt.
 *
 * EINE BEWUSSTE ABWEICHUNG
 *
 *   1. Einfaches Escaping statt doppeltem. Die Helfer liefern rohe Strings,
 *      escapt wird einmal am Ausgabepunkt.
 *
 * `align` war bis zum 2026-09-01 eine zweite: Der Nachbau klemmte den Wert auf
 * `wide|full` mit Rueckfall `full`. Mit E-145 ist sie gefallen — die Klemmung
 * schrieb einen gespeicherten Wert in einen ANDEREN um, und der
 * Sicherheitsgewinn ist keiner: Das Original escapt die Klasse an derselben
 * Stelle. Fuenf andere Bloecke dieses Plugins (`banner`, `carousel`,
 * `content-grid`, `media-grid`, `post-grid`) reichten den Wert ohnehin schon
 * roh durch; die Klemmung war eine Inkonsistenz im Nachbau selbst.
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

// Das Original faellt bei leerem Wert auf die Klasse `container` zurueck
// (container.php:7) — der Literalwert `Default` faellt dagegen erst in
// crea_bootstrap_blocks_class_str() weg und erzeugt gar keine Klasse.
$cbb_container = ! empty( $cbb_a['container'] ) && is_string( $cbb_a['container'] )
    ? $cbb_a['container']
    : 'container';

$cbb_align = '';

if ( ! empty( $cbb_a['align'] ) && is_string( $cbb_a['align'] ) ) {
    // E-145: DURCHGEREICHT, NICHT GEKLEMMT. Das Original haengt den Wert
    // ungeprueft an das Literal `align` (container.php:8); escapt wird die Klasse
    // erst am Ausgabepunkt — auf beiden Seiten gleich. Eine Allowlist hier
    // schriebe einen gespeicherten Wert in einen ANDEREN um: aus
    // `aligncenter` wuerde `alignfull`. Vertragsebene 3 verlangt
    // aequivalentes Markup, nicht das bessere (E-41, E-77).
    $cbb_align = 'align' . $cbb_a['align'];
}

$cbb_class = crea_bootstrap_blocks_class_str(
    [
        crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
        crea_bootstrap_blocks_element_classes(),
        $cbb_container,
        $cbb_align,
        $cbb_a['className'] ?? '',
        crea_bootstrap_blocks_utilities_classes( $cbb_a ),
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' ),
    ]
);

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>"><?php
echo crea_bootstrap_blocks_background_markup( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup.
echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />.
?></div>
