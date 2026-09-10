<?php
/**
 * Render template of the `creabb/nav-and-tab-item` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_nav_and_tab_item()`
 * (`blocks/nav-and-tab-item.php` des Alt-Plugins). Klassenreihenfolge:
 *
 *   block-<uuid>  nav-link  <className>  active  disabled
 *   <hide-Kaskade mit flex>
 *
 * DIE HUELLE IST IMMER EIN ANKER — anders als bei `list-group-item` und
 * `dropdown-item` gibt es KEINE Weiche. `href`, `title`, `rel` und `target`
 * werden einzeln nur bei belegtem Wert angehaengt; ein Anker ohne `href` ist
 * das regulaere Ergebnis eines Tab-Umschalters.
 *
 * Der Inhalt ist `wp_kses_post( $attributes['text'] )` mit `!empty()`-Wache —
 * NICHT das Innenmarkup. Wo das Original `!empty()` prueft, prueft der Nachbau
 * `!empty()` (Ruling E-41).
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
        'nav-link',
        $cbb_a['className'] ?? '',
        empty( $cbb_a['active'] ) ? '' : 'active',
        empty( $cbb_a['disabled'] ) ? '' : 'disabled',
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'flex' ),
    ]
);

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

$cbb_text = empty( $cbb_a['text'] ) ? '' : wp_kses_post( $cbb_a['text'] );
?>
<a <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>"<?php echo $cbb_extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jedes Attribut ist einzeln escapt. ?>>
<?php echo $cbb_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bereits durch wp_kses_post() gefiltert. ?>
</a>
