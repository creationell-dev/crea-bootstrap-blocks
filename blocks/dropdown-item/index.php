<?php
/**
 * Render template of the `creabb/dropdown-item` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_dropdown_item()`
 * (`blocks/dropdown-item.php` des Alt-Plugins). Klassenreihenfolge woertlich:
 *
 *   <type>  active  disabled  <className>  <hide-Kaskade mit block>
 *
 * ZWEI BESONDERHEITEN:
 *
 * 1. DER PARENT IST EIN BLOCK DER PHASE 1: `creabb/button`. `parent` ist eine
 *    Angabe des Kindes; `button` selbst bleibt unveraendert.
 *
 * 2. DIE WEICHE LAEUFT UEBER `switch ( $attributes['type'] )`, NICHT UEBER
 *    `!empty()`. Nur der Wert `dropdown-item` erzeugt den Anker; jeder andere
 *    Wert — der leere eingeschlossen — faellt in den `default`-Zweig und
 *    erzeugt ein `<div>`. Beide Zweige geben `wp_kses_post( $attributes['text'] )`
 *    aus, nicht das Innenmarkup.
 *
 * Der Block hat KEINE `hide_*`-Attribute; die Renderfunktion ruft die
 * Sichtbarkeitskaskade trotzdem auf und bekommt den Leerstring. Der Aufruf
 * bleibt 1:1 stehen.
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
        $cbb_a['type'] ?? '',
        empty( $cbb_a['active'] ) ? '' : 'active',
        empty( $cbb_a['disabled'] ) ? '' : 'disabled',
        $cbb_a['className'] ?? '',
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' ),
    ]
);

$cbb_full = crea_bootstrap_blocks_class_str(
    [
        crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
        $cbb_class,
    ]
);

$cbb_text = wp_kses_post( $cbb_a['text'] ?? '' );
$cbb_id   = crea_bootstrap_blocks_anchor_attr( $cbb_a );

if ( 'dropdown-item' === ( $cbb_a['type'] ?? '' ) ) :
    $cbb_extra = '';
    if ( ! empty( $cbb_a['url'] ) ) {
        $cbb_extra .= ' href="' . esc_url( (string) $cbb_a['url'] ) . '"';
    }
    if ( ! empty( $cbb_a['url_title'] ) ) {
        $cbb_extra .= ' title="' . esc_attr( (string) $cbb_a['url_title'] ) . '"';
    }
    if ( ! empty( $cbb_a['rel'] ) ) {
        $cbb_extra .= ' rel="' . esc_attr( (string) $cbb_a['rel'] ) . '"';
    }
    if ( ! empty( $cbb_a['linkTarget'] ) ) {
        $cbb_extra .= ' target="' . esc_attr( (string) $cbb_a['linkTarget'] ) . '"';
    }
    ?>
<a <?php echo $cbb_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_full ); ?>"<?php echo $cbb_extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jedes Attribut ist einzeln escapt. ?>>
	<?php echo $cbb_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bereits durch wp_kses_post() gefiltert. ?>
</a>
<?php else : ?>
<div <?php echo $cbb_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_full ); ?>">
	<?php echo $cbb_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bereits durch wp_kses_post() gefiltert. ?>
</div>
<?php endif; ?>
