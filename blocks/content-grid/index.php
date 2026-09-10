<?php
/**
 * Render template of the `creabb/content-grid` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_content_grid()`
 * (`blocks/content-grid.php` des Alt-Plugins). Klassenreihenfolge woertlich:
 *
 *   block-<uuid>  areoi-content-grid  areoi-content-grid-<layout>  d-flex
 *   <size ODER areoi-medium>  <sechs vertical_align_*>
 *   <sechs horizontal_align_*>  align<align>  <className>
 *   <hide-Kaskade mit block>  position-relative
 *
 * FRUEH-AUSSTIEG: `if ( !$content ) return $content;` — ohne Innenmarkup gibt
 * der Block nichts aus. Die Pruefung laeuft auf der Innenmarkup-Variablen
 * (Ruling E-46).
 *
 * DREI VORGABEN IM RENDERPFAD, KEINE DAVON IM VERTRAG:
 *   `layout`    leer -> `gird`   — ja, MIT TIPPFEHLER. So steht es im Original
 *                                  (`content-grid.php:8`), und die Klasse heisst
 *                                  dann `areoi-content-grid-gird`. Vertragsebene 3
 *                                  verlangt aequivalentes Markup, nicht das
 *                                  richtige (Ruling E-41).
 *   `container` leer -> `container`
 *   `columns`   leer -> `3`
 *   `size`      leer -> `areoi-medium`
 *
 * `horizontal_align_<bp>` STEHT NICHT IM VERTRAG dieses Blocks. Die
 * Renderfunktion liest es, `block.json` deklariert nur `vertical_align_<bp>` —
 * die zwoelf Aufrufe bleiben 1:1 stehen, sechs davon greifen ins Leere.
 *
 * `$allow_pattern` IST GESETZT (`content-grid.php:4`).
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

require_once dirname( __DIR__ ) . '/_partials/patterns.php';
require_once dirname( __DIR__ ) . '/_partials/background.php';

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';

/*
 * DER FRUEH-AUSSTIEG GILT NUR AUF DEM FRONTEND.
 *
 * Das Original steigt aus, solange kein Kindblock da ist, und der
 * Nachbau tut es deshalb auch — auf dem Frontend Zeichen fuer Zeichen
 * gleich. Im EDITOR waere derselbe Ausstieg eine Falle: Ein frisch
 * eingefuegter Block hat noch keine Kinder, gaebe also nichts aus,
 * erzeugte kein <InnerBlocks />-Feld — und liesse sich nie befuellen.
 * `$isEditor` stellt Blockstudio dem Template dafuer bereit.
 */
if ( empty( $isEditor ) && ( '' === $cbb_inner || '0' === $cbb_inner ) ) {
    echo $cbb_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Frueh-Ausstieg des Originals.
    return;
}

$cbb_a = crea_bootstrap_blocks_native_attributes( is_array( $attributes ?? null ) ? $attributes : [], $block ?? [] );

$cbb_layout    = empty( $cbb_a['layout'] ) ? 'gird' : esc_attr( (string) $cbb_a['layout'] );
$cbb_container = empty( $cbb_a['container'] ) ? 'container' : esc_attr( (string) $cbb_a['container'] );
$cbb_columns   = empty( $cbb_a['columns'] ) ? '3' : esc_attr( (string) $cbb_a['columns'] );

$cbb_parts = [
    crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
    'areoi-content-grid',
    'areoi-content-grid-' . $cbb_layout,
    'd-flex',
    empty( $cbb_a['size'] ) ? 'areoi-medium' : $cbb_a['size'],
];

foreach ( [ 'vertical_align', 'horizontal_align' ] as $cbb_axis ) {
    foreach ( [ 'xs', 'sm', 'md', 'lg', 'xl', 'xxl' ] as $cbb_bp ) {
        $cbb_parts[] = ( empty( $cbb_a[ 'hide_' . $cbb_bp ] ) && ! empty( $cbb_a[ $cbb_axis . '_' . $cbb_bp ] ) )
            ? (string) $cbb_a[ $cbb_axis . '_' . $cbb_bp ]
            : '';
    }
}

$cbb_parts[] = empty( $cbb_a['align'] ) ? '' : 'align' . $cbb_a['align'];
$cbb_parts[] = $cbb_a['className'] ?? '';
$cbb_parts[] = crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' );

$cbb_class      = crea_bootstrap_blocks_class_str( $cbb_parts );
$cbb_background = crea_bootstrap_blocks_background_markup( $cbb_a, true );
$cbb_prepend    = crea_bootstrap_blocks_prepend_content( $cbb_a );
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?> position-relative">
<?php echo $cbb_background; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup. ?>
<div class="<?php echo esc_attr( $cbb_container ); ?>">
<div class="row h-100">
<div class="col">
<?php echo $cbb_prepend; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup. ?>
<div class="row areoi-content-grid-columns areoi-content-grid-columns-<?php echo esc_attr( $cbb_columns ); ?>">
<?php echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />. ?>
</div>
</div>
</div>
</div>
</div>
