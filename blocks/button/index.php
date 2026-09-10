<?php
/**
 * Render template of the `creabb/button` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_button()` (`blocks/button.php` des
 * Alt-Plugins). Fuenf ineinandergeschachtelte Zweige:
 *
 *   [Dropdown-Wrapper]  [Popover-Container]  Button  [Icon]  Text  [Icon]
 *   [Badge]  /Button  [/Popover]  [Dropdown-Menue]  [/Wrapper]
 *
 * VIER BEWUSSTE ABWEICHUNGEN, die Liste ist abschliessend:
 *
 *   1. Tag-Allowlist. Das Original baut den Tagnamen aus `type` und schuetzt
 *      ihn nur mit esc_attr() (button.php:66, :121). Erlaubt sind a, button,
 *      div und span; alles andere faellt auf a zurueck.
 *   2. wp_kses_post() auf `text`. Das Original gibt den Wert roh aus
 *      (button.php:118) — als einziges Textattribut des ganzen Plugins.
 *   3. Allowlisten fuer die drei Richtungen und die Dropdown-Richtung.
 *      `dropdown_direction` landet in einem class-Attribut, die
 *      `*_direction`-Werte in data-bs-placement.
 *   4. Der Modal-Zweig ist aus. Das Original prueft
 *      `!areoi2_get_option( 'areoi-dashboard-global-bootstrap-js', 1 )`
 *      (button.php:87); die Option ist nirgends gesetzt, ihr Vorgabewert 1
 *      macht die Bedingung ueberall falsch — der Zweig ist im Bestand tot.
 *      `crea_bootstrap_blocks_button_modal` ist der Ausweg.
 *
 * `icon_size` laeuft durch Styles::css_number(), weil der Wert in ein
 * style-Attribut geht; ein unbrauchbarer Wert faellt auf 24 zurueck.
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

$cbb_tag = crea_bootstrap_blocks_tag_name(
    is_string( $cbb_a['type'] ?? null ) ? $cbb_a['type'] : '',
    [ 'a', 'button', 'div', 'span' ],
    'a'
);

$cbb_class = crea_bootstrap_blocks_class_str(
    [
        crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
        'btn',
        crea_bootstrap_blocks_class_pair( 'has-url' ),
        'position-relative',
        $cbb_a['className'] ?? '',
        $cbb_a['style'] ?? '',
        $cbb_a['size'] ?? '',
        ! empty( $cbb_a['dropdown'] ) ? 'dropdown-toggle' : '',
        $cbb_a['text_wrap'] ?? '',
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'inline-block' ),
        crea_bootstrap_blocks_block_display_class_str( $cbb_a, 'inline-block' ),
    ]
);

// --- Das oeffnende Tag samt Attributen ------------------------------------
$cbb_open = '<' . $cbb_tag . ' ' . crea_bootstrap_blocks_anchor_attr( $cbb_a )
    . ' class="' . esc_attr( $cbb_class ) . '"';

// Die Reihenfolge dieser vier ist Vertrag: class, href, title, rel, target
// — so gibt das Original sie aus, und die Diffsuite vergleicht zeichenweise.
$cbb_link_attrs = [
    'href'   => 'url',
    'title'  => 'url_title',
    'rel'    => 'rel',
    'target' => 'linkTarget',
];

foreach ( $cbb_link_attrs as $cbb_attribute => $cbb_name ) {
    $cbb_value = $cbb_a[ $cbb_name ] ?? null;

    if ( empty( $cbb_value ) || ! is_string( $cbb_value ) ) {
        continue;
    }

    $cbb_open .= ' ' . $cbb_attribute . '="'
        . ( 'href' === $cbb_attribute ? esc_url( $cbb_value ) : esc_attr( $cbb_value ) )
        . '"';
}

if ( ! empty( $cbb_a['dropdown'] ) ) {
    $cbb_open .= ' data-bs-toggle="dropdown" data-bs-auto-close="'
        . esc_attr( is_string( $cbb_a['dropdown_auto_close'] ?? null ) ? $cbb_a['dropdown_auto_close'] : 'true' )
        . '"';
}

/**
 * Filters whether the button may carry Bootstrap's modal attributes.
 *
 * @param bool                 $enabled    False by default.
 * @param array<string, mixed> $attributes Block attributes.
 */
if ( ! empty( $cbb_a['url'] ) && ! empty( $cbb_a['link_to_modal'] )
    && (bool) apply_filters( 'crea_bootstrap_blocks_button_modal', false, $cbb_a )
) {
    $cbb_open .= ' data-bs-toggle="modal" data-bs-target="' . esc_url( (string) $cbb_a['url'] ) . '"';
}

if ( ! empty( $cbb_a['tooltip'] ) ) {
    $cbb_open .= ' data-bs-placement="'
        . esc_attr(
            crea_bootstrap_blocks_tag_name(
                is_string( $cbb_a['tooltip_direction'] ?? null ) ? $cbb_a['tooltip_direction'] : '',
                [ 'top', 'bottom', 'left', 'right' ],
                'top'
            )
        )
        . '" data-bs-toggle="tooltip" data-bs-html="true" title="'
        . esc_attr( is_string( $cbb_a['tooltip_content'] ?? null ) ? $cbb_a['tooltip_content'] : '' )
        . '"';
}

$cbb_open .= '>';

// --- Icon, Badge ----------------------------------------------------------
$cbb_icon = '';

if ( ! empty( $cbb_a['include_icon'] ) && ! empty( $cbb_a['icon'] ) && ! empty( $cbb_a['icon_size'] ) ) {
    $cbb_icon_size = \Creationell\BootstrapBlocks\Styles::css_number( $cbb_a['icon_size'] ) ?? '24';
    $cbb_icon      = '<i class="'
        . esc_attr( (string) $cbb_a['icon'] )
        . ' ' . ( 'prepend' === ( $cbb_a['icon_position'] ?? '' ) ? 'me-3' : 'ms-3' )
        . ' align-middle" style="font-size: ' . $cbb_icon_size . 'px;"></i>';
}

$cbb_badge = '';

if ( ! empty( $cbb_a['badge'] ) ) {
    $cbb_badge = '<span class="'
        . esc_attr(
            crea_bootstrap_blocks_class_str(
                [
                    'badge',
                    $cbb_a['badge_style'] ?? '',
                    $cbb_a['badge_background'] ?? '',
                    $cbb_a['badge_text_color'] ?? '',
                    $cbb_a['badge_classes'] ?? '',
                ]
            )
        )
        . '">' . wp_kses_post( (string) ( $cbb_a['badge_content'] ?? '' ) ) . '</span>';
}

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';

// --- Ausgabe --------------------------------------------------------------
if ( ! empty( $cbb_a['dropdown'] ) ) {
    echo '<div class="'
        . esc_attr(
            crea_bootstrap_blocks_class_str(
                [
                    crea_bootstrap_blocks_block_display_class_str( $cbb_a, 'inline-block' ),
                    crea_bootstrap_blocks_tag_name(
                        is_string( $cbb_a['dropdown_direction'] ?? null ) ? $cbb_a['dropdown_direction'] : '',
                        [ 'dropdown', 'dropup', 'dropend', 'dropstart' ],
                        'dropdown'
                    ),
                ]
            )
        )
        . '">';
}

if ( ! empty( $cbb_a['popover'] ) ) {
    echo '<span class="'
        . esc_attr(
            crea_bootstrap_blocks_class_str(
                [ 'popover-container', crea_bootstrap_blocks_block_display_class_str( $cbb_a, 'inline-block' ) ]
            )
        )
        . '" data-bs-container="body" title="'
        . esc_attr( is_string( $cbb_a['popover_title'] ?? null ) ? $cbb_a['popover_title'] : '' )
        . '" data-bs-content="'
        . esc_attr( is_string( $cbb_a['popover_content'] ?? null ) ? $cbb_a['popover_content'] : '' )
        . '" data-bs-placement="'
        . esc_attr(
            crea_bootstrap_blocks_tag_name(
                is_string( $cbb_a['popover_direction'] ?? null ) ? $cbb_a['popover_direction'] : '',
                [ 'top', 'bottom', 'left', 'right' ],
                'top'
            )
        )
        . '" data-bs-trigger="focus '
        . esc_attr(
            ! empty( $cbb_a['popover_trigger'] ) && is_string( $cbb_a['popover_trigger'] )
                ? $cbb_a['popover_trigger']
                : 'click'
        )
        . '" data-bs-toggle="popover" tabindex="0">';
}

echo $cbb_open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jedes Attribut ist oben einzeln escapt.

// BEIDE Zweige pruefen auf GLEICHHEIT — genau wie das Original
// (button.php:117 und :119). Ein leeres oder unbekanntes icon_position gibt
// dort KEIN Icon aus, weder vorn noch hinten. Ein `!== 'prepend'` hinten waere
// eine stille Erweiterung: Es liesse das Icon bei jedem anderen Wert erscheinen.
if ( 'prepend' === ( $cbb_a['icon_position'] ?? '' ) ) {
    echo $cbb_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- intern escaptes Markup.
}

// DIE empty()-FALLE DES ORIGINALS WIRD NACHGEBILDET, nicht repariert.
// `button.php:118` schreibt `!empty( $attributes['text'] ) ? … : ''`, und
// in PHP ist `empty( "0" )` WAHR — ein `text` von "0" erzeugt im Original
// also KEINEN Textknoten. Ein `'' !== $text` im Nachbau gaebe dort eine 0
// aus; gemessen an der Belegung `null-string:"0"` der Matrix, die genau
// diesen Wert setzt. Vertragsebene 3 verlangt aequivalentes Markup, nicht
// das bessere.
$cbb_text = (string) ( $cbb_a['text'] ?? '' );

if ( ! empty( $cbb_text ) ) {
    echo wp_kses_post( $cbb_text );
}

if ( 'append' === ( $cbb_a['icon_position'] ?? '' ) ) {
    echo $cbb_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- intern escaptes Markup.
}

echo $cbb_badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- intern escaptes Markup.
echo '</' . $cbb_tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $cbb_tag stammt aus crea_bootstrap_blocks_tag_name() und ist auf a, button, div, span geklemmt.

if ( ! empty( $cbb_a['popover'] ) ) {
    echo '</span>';
}

if ( ! empty( $cbb_a['dropdown'] ) ) {
    echo '<div class="'
        . esc_attr(
            crea_bootstrap_blocks_class_str(
                [ 'dropdown-menu', $cbb_a['dropdown_style'] ?? '', $cbb_a['dropdown_menu_alignment'] ?? '' ]
            )
        )
        . '" aria-labelledby="'
        . esc_attr( is_string( $cbb_a['anchor'] ?? null ) ? $cbb_a['anchor'] : '' )
        . '">';
    echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />.
    echo '</div></div>';
}
