<?php
/**
 * Render template of the `creabb/row` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_row()` (`blocks/row.php` des
 * Alt-Plugins). Zwei Klassenzweige:
 *
 *   Flexbox   block-<uuid>  row   areoi-element  <className>
 *             <vertical_align×6>  <horizontal_align×6>  <row_cols×6>
 *             <hide-Kaskade mit flex>
 *   CSS-Grid  block-<uuid>  grid  areoi-element  <className>
 *             <hide-Kaskade mit flex>
 *
 * Im Grid-Modus entfallen ALLE Ausrichtungsklassen — das ist keine Auslassung,
 * sondern der Zweig `row.php:39-49` des Originals.
 *
 * Jede Ausrichtungsklasse haengt am zugehoerigen `hide_<bp>`: Ist der
 * Breakpoint versteckt, entfaellt sie (Vertrag, Verhaltensdetail 3).
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

$cbb_is_grid = crea_bootstrap_blocks_css_grid_enabled();

if ( ! empty( $cbb_a['is_flex'] ) ) {
    $cbb_is_grid = false;
}

$cbb_parts = [
    $cbb_is_grid ? 'grid' : 'row',
    crea_bootstrap_blocks_element_classes(),
    $cbb_a['className'] ?? '',
];

if ( ! $cbb_is_grid ) {
    foreach ( [ 'vertical_align_', 'horizontal_align_', 'row_cols_' ] as $cbb_prefix ) {
        foreach ( [ 'xs', 'sm', 'md', 'lg', 'xl', 'xxl' ] as $cbb_bp ) {
            $cbb_parts[] = empty( $cbb_a[ 'hide_' . $cbb_bp ] ) ? ( $cbb_a[ $cbb_prefix . $cbb_bp ] ?? '' ) : '';
        }
    }
}

$cbb_parts[] = crea_bootstrap_blocks_display_class_str( $cbb_a, 'flex' );

$cbb_class = crea_bootstrap_blocks_class_str(
    array_merge(
        [ crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ) ],
        $cbb_parts
    )
);

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>"><?php
echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />.
?></div>
