<?php
/**
 * Render template of the `creabb/carousel` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_carousel()` (`blocks/carousel.php`
 * des Alt-Plugins). Aussere Klassenreihenfolge woertlich:
 *
 *   block-<uuid>  carousel  slide  <className>
 *   <style, NUR wenn item_style leer>  <item_style>  <transition>
 *   align<align>  <hide-Kaskade mit block>
 *
 * `style` und `align` STEHEN NICHT IM VERTRAG dieses Blocks. Die
 * Renderfunktion liest beide, `block.json` deklariert sie nicht — sie sind nie
 * registriert und deshalb immer leer. Die Auswahlliste „Style" des Editors
 * schreibt auf `item_style`, nicht auf `style`. Die Aufrufe bleiben 1:1 stehen.
 *
 * DER BLOCK BAUT SEIN INNENMARKUP UM. Er laedt es in ein `DOMDocument`, sucht
 * per XPath nach `//div[contains(@class, "carousel-item")]`, klont die Treffer,
 * setzt beim ERSTEN die Klasse auf `carousel-item active` und setzt daraus das
 * Innenmarkup neu zusammen. Aus derselben Trefferzahl entstehen die
 * Indikatoren; Knoepfe und Indikatoren erscheinen nur bei MEHR ALS EINEM Element.
 *
 * ZUR VERSIONSWEICHE DES ORIGINALS: Sie waehlt zwischen
 * `mb_encode_numericentity()` (ab PHP 8.1) und
 * `mb_convert_encoding( …, 'HTML-ENTITIES', … )` (darunter). Das Plugin
 * verlangt PHP >= 8.3; der untere Zweig kann nie laufen und ist deshalb NICHT
 * uebersetzt. Der obere ist nicht deprecated. Das ist keine Verhaltensaenderung,
 * sondern das Weglassen von unerreichbarem Code.
 *
 * `data-bs-ride` UND `data-bs-interval` FOLGEN EINER DREISTUFIGEN LOGIK, die
 * woertlich uebernommen ist — einschliesslich des Vergleichs
 * `$attributes['interval'] === true`, der auf einem `string`-Attribut mit dem
 * Vorgabewert `'4000'` nie zutrifft.
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

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';

$cbb_previous = libxml_use_internal_errors( true );

$cbb_dom           = new DOMDocument();
$cbb_dom->encoding = 'utf-8';

$cbb_map    = [ 0x80, 0x10FFFF, 0, 0xFFFF ];
$cbb_source = mb_encode_numericentity( $cbb_inner, $cbb_map, 'UTF-8' );

if ( '' !== trim( $cbb_source ) ) {
    $cbb_dom->loadHTML( $cbb_source );
}

$cbb_xpath = new DOMXPath( $cbb_dom );
$cbb_items = $cbb_xpath->query( '//div[contains(@class, "carousel-item")]' );
$cbb_count = false !== $cbb_items ? $cbb_items->length : 0;

$cbb_class = crea_bootstrap_blocks_class_str(
    [
        crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
        'carousel',
        'slide',
        $cbb_a['className'] ?? '',
        ( ! empty( $cbb_a['style'] ) && empty( $cbb_a['item_style'] ) ) ? $cbb_a['style'] : '',
        $cbb_a['item_style'] ?? '',
        $cbb_a['transition'] ?? '',
        empty( $cbb_a['align'] ) ? '' : 'align' . $cbb_a['align'],
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' ),
    ]
);

/*
 * DASSELBE TOR WIE DIE KLASSE. Bis zum 2026-08-31 stand hier der Rohwert, nur
 * durch esc_attr() geschickt — waehrend die Huelle oben ihre Klasse an der
 * Pruefung verlor. Fuer block_id=team-slider zeigten Pfeile und Indikatoren
 * damit auf `.block-team-slider`, eine Klasse, die der Block nie ausgab.
 */
$cbb_target = empty( $cbb_a['anchor'] )
    ? '.block-' . esc_attr( crea_bootstrap_blocks_block_id_value( $cbb_a['block_id'] ?? null ) )
    : '#' . esc_attr( (string) $cbb_a['anchor'] );

$cbb_buttons = '';
if ( ! empty( $cbb_a['controls'] ) && $cbb_count > 1 ) {
    $cbb_buttons = '<button class="carousel-control-prev" type="button" data-bs-target="' . $cbb_target . '" data-bs-slide="prev">'
        . '<span class="carousel-control-prev-icon" aria-hidden="true"></span>'
        . '<span class="visually-hidden">Previous</span>'
        . '</button>'
        . '<button class="carousel-control-next" type="button" data-bs-target="' . $cbb_target . '" data-bs-slide="next">'
        . '<span class="carousel-control-next-icon" aria-hidden="true"></span>'
        . '<span class="visually-hidden">Next</span>'
        . '</button>';
}

$cbb_indicators = '';
if ( ! empty( $cbb_a['indicators'] ) && $cbb_count > 1 && false !== $cbb_items ) {
    $cbb_indicators = '<div class="carousel-indicators">';
    foreach ( $cbb_items as $cbb_key => $cbb_item ) {
        $cbb_indicators .= '<button type="button" data-bs-target="' . $cbb_target . '"'
            . ' data-bs-slide-to="' . esc_attr( (string) $cbb_key ) . '"'
            . ' class="' . ( 0 === $cbb_key ? 'active' : '' ) . '"'
            . ' aria-current="true"'
            . ' aria-label="Slide ' . esc_attr( (string) $cbb_key ) . '"'
            . '></button>';
    }
    $cbb_indicators .= '</div>';
}

/*
 * OHNE ZAEHLBEDINGUNG, UND DAS IST GEMESSEN. Das Original schreibt
 * `if ( !empty( $items ) )` — `$items` ist ein DOMNodeList-OBJEKT, und
 * `empty()` liefert fuer Objekte immer `false`. Die Bedingung ist damit IMMER
 * wahr: Findet der XPath keinen `carousel-item`, laeuft die Schleife nicht und
 * `$content` wird auf das leere Dokument gesetzt — das Innenmarkup
 * VERSCHWINDET. Ein Nachbau mit `$cbb_count > 0` behielte es und weicht in
 * jedem Fall ohne Folien ab; die Differenzsuite hat genau das gefunden.
 */
if ( false !== $cbb_items ) {
    $cbb_new = new DOMDocument();
    foreach ( $cbb_items as $cbb_key => $cbb_item ) {
        $cbb_clone = $cbb_item->cloneNode( true );
        if ( 0 === $cbb_key && $cbb_clone instanceof DOMElement ) {
            $cbb_clone->setAttribute( 'class', 'carousel-item active' );
        }
        $cbb_new->appendChild( $cbb_new->importNode( $cbb_clone, true ) );
    }
    $cbb_inner = (string) $cbb_new->saveHTML();
}

libxml_use_internal_errors( $cbb_previous );

$cbb_auto = false;
if ( empty( $cbb_a['auto_scroll'] ) && true === ( $cbb_a['interval'] ?? null ) ) {
    $cbb_auto = 'carousel';
}
if ( ! empty( $cbb_a['auto_scroll'] ) ) {
    $cbb_auto = $cbb_a['auto_scroll'] ? 'carousel' : false;
}

$cbb_interval = false;
if ( 'carousel' === $cbb_auto ) {
    if ( true === ( $cbb_a['interval'] ?? null ) ) {
        $cbb_interval = 4000;
    } elseif ( is_numeric( $cbb_a['interval'] ?? null ) ) {
        $cbb_interval = (int) $cbb_a['interval'];
    }
}

$cbb_ride = false !== $cbb_auto ? 'data-bs-ride="' . esc_attr( (string) $cbb_auto ) . '"' : '';
$cbb_int  = false !== $cbb_interval ? 'data-bs-interval="' . esc_attr( (string) $cbb_interval ) . '"' : '';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>" <?php echo $cbb_ride; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> data-bs-touch="<?php echo empty( $cbb_a['touch'] ) ? 'false' : 'true'; ?>" data-bs-pause="<?php echo empty( $cbb_a['pause'] ) ? 'hover' : esc_attr( (string) $cbb_a['pause'] ); ?>" <?php echo $cbb_int; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?>>
<?php echo $cbb_buttons; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes Markup, Werte einzeln escapt. ?>
<?php echo $cbb_indicators; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes Markup, Werte einzeln escapt. ?>
<?php echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />. ?>
<div class="clearfix"></div>
</div>
