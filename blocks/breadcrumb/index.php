<?php
/**
 * Render template of the `creabb/breadcrumb` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_breadcrumb()`
 * (`blocks/breadcrumb.php` des Alt-Plugins). Huelle ist ein `<nav>`.
 * Klassenreihenfolge woertlich:
 *
 *   block-<uuid>  breadcrumb  <className>  <hide-Kaskade mit flex>
 *
 * DER BLOCK IST DATENABHAENGIG. Seine Ausgabe folgt nicht aus den Attributen,
 * sondern aus der Seitenhierarchie; die Kette baut
 * `crea_bootstrap_blocks_breadcrumbs()` in `includes/helpers.php`.
 *
 * DER TRENNER LAEUFT DURCH `esc_attr( htmlentities( … ) )` — doppelte
 * Kodierung, wie im Original (`breadcrumb.php:18`). Ein `/` bleibt dabei ein
 * `/`, ein `&` wird zu `&amp;amp;`. Das ist Bestand und wird mitgebaut.
 *
 * AKTIVE EINTRAEGE TRAGEN KEINEN ANKER, sondern
 * `class="breadcrumb-item active" aria-current="page"`.
 *
 * DAS INNENMARKUP WIRD NICHT AUSGEGEBEN — der Block hat keine Kindbloecke.
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

$cbb_crumbs = crea_bootstrap_blocks_breadcrumbs( $cbb_a );

$cbb_class = crea_bootstrap_blocks_class_str(
    [
        crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
        'breadcrumb',
        $cbb_a['className'] ?? '',
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'flex' ),
    ]
);

$cbb_divider = esc_attr( htmlentities( (string) ( $cbb_a['divider'] ?? '' ) ) );

$cbb_list = '';
foreach ( $cbb_crumbs as $cbb_crumb ) {
    if ( ! empty( $cbb_crumb['active'] ) ) {
        $cbb_list .= '<li class="breadcrumb-item active" aria-current="page">' . wp_kses_post( $cbb_crumb['label'] ?? '' ) . '</li>';
    } else {
        $cbb_list .= '<li class="breadcrumb-item"><a href="' . esc_url( (string) ( $cbb_crumb['permalink'] ?? '' ) ) . '">'
            . wp_kses_post( $cbb_crumb['label'] ?? '' ) . '</a></li>';
    }
}
?>
<nav <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>" aria-label="breadcrumb" style="--bs-breadcrumb-divider: '<?php echo $cbb_divider; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bereits doppelt kodiert, wie im Original. ?>';">
<ol class="breadcrumb">
<?php echo $cbb_list; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Werte einzeln escapt. ?>
</ol>
</nav>
