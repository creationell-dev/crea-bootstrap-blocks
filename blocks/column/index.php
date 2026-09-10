<?php
/**
 * Render template of the `creabb/column` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_column()` (`blocks/column.php` des
 * Alt-Plugins). Klassenreihenfolge woertlich uebernommen:
 *
 *   block-<uuid>  col  areoi-element  <className>
 *   <vertical_align×6>  <col×6>  <offset×6>  <order×6>
 *   <utilities>  <hide-Kaskade mit block>
 *
 * Vier Klassenfamilien haengen am zugehoerigen `hide_<bp>`: Ist der Breakpoint
 * versteckt, entfallen `vertical_align_<bp>`, `col_<bp>`, `offset_<bp>` und
 * `order_<bp>` (Vertrag, Verhaltensdetail 3).
 *
 * Im CSS-Grid-Modus entfaellt die Klasse `col`, und die FERTIGE Klassenkette
 * wird umgeschrieben — `col-` zu `g-col-`, `offset-` zu `g-start-`
 * (column.php:47-50). Der Context `creabb/isFlex` schaltet den Modus fuer
 * diese Spalte ab; er kommt von der umgebenden `row`.
 *
 * Ausgabereihenfolge: Hintergrund, Inhalt, Link.
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
 * @var array<string, mixed> $context    Block context provided by Blockstudio.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/_partials/patterns.php';
require_once dirname( __DIR__ ) . '/_partials/background.php';
require_once dirname( __DIR__ ) . '/_partials/link-overlay.php';

$cbb_a = crea_bootstrap_blocks_native_attributes( is_array( $attributes ?? null ) ? $attributes : [], $block ?? [] );

$cbb_context = is_array( $context ?? null ) ? $context : [];
$cbb_is_grid = crea_bootstrap_blocks_css_grid_enabled();

if ( ! empty( $cbb_context['creabb/isFlex'] ) ) {
    $cbb_is_grid = false;
}

$cbb_parts = [
    $cbb_is_grid ? '' : 'col',
    crea_bootstrap_blocks_element_classes(),
    $cbb_a['className'] ?? '',
];

foreach ( [ 'vertical_align_', 'col_', 'offset_', 'order_' ] as $cbb_prefix ) {
    foreach ( [ 'xs', 'sm', 'md', 'lg', 'xl', 'xxl' ] as $cbb_bp ) {
        $cbb_parts[] = empty( $cbb_a[ 'hide_' . $cbb_bp ] ) ? ( $cbb_a[ $cbb_prefix . $cbb_bp ] ?? '' ) : '';
    }
}

$cbb_parts[] = crea_bootstrap_blocks_utilities_classes( $cbb_a );
$cbb_parts[] = crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' );

$cbb_class = crea_bootstrap_blocks_class_str( $cbb_parts );

if ( $cbb_is_grid ) {
    $cbb_class = crea_bootstrap_blocks_grid_class_str( $cbb_class );
}

$cbb_class = crea_bootstrap_blocks_class_str(
    [ crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ), $cbb_class ]
);

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>"><?php
echo crea_bootstrap_blocks_background_markup( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup.
echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />.
echo crea_bootstrap_blocks_link_overlay( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup.
?></div>
