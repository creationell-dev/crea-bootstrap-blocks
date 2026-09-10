<?php
/**
 * Render template of the `creabb/content-with-media` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_content_with_media()`
 * (`blocks/content-with-media.php` des Alt-Plugins). Klassenreihenfolge:
 *
 *   block-<uuid>  areoi-content-with-media  align<align>  <className>
 *   <hide-Kaskade mit block>  position-relative
 *
 * `alignment` STEUERT DREI DINGE AUF EINMAL: die Zeilenklasse des
 * Hintergrundmediums (`justify-content-end` bei `start`, sonst
 * `justify-content-start`), die Spaltenreihenfolge (`order-lg-0` bei `start`,
 * sonst `order-lg-1`) — und nichts davon steht in der aeusseren Klassenkette.
 * Vorgabe ist `start`.
 *
 * ZWEI MEDIENPFADE: Das gewoehnliche Medium steht in einem `div`, das bei
 * `layout == 'full-width'` die Klasse `d-lg-none` traegt. Nur bei
 * `full-width` entsteht zusaetzlich das Hintergrundmedium
 * `div.areoi-background.d-none.d-lg-block` mit `container-fluid`, Zeile, Spalte
 * und `div.areoi-background__image` samt Inline-`background-image`.
 *
 * `$allow_pattern` IST GESETZT (`content-with-media.php:4`).
 *
 * SECHS ATTRIBUTE OHNE EDITORFELD: `preview`, `anchor`, `image`, `video`,
 * `background_image`, `background_video` — die vier letzten sind objektwertig
 * und werden trotzdem gerendert, sobald ein Wert vorliegt.
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
require_once dirname( __DIR__ ) . '/_partials/link-overlay.php';

$cbb_a = crea_bootstrap_blocks_native_attributes( is_array( $attributes ?? null ) ? $attributes : [], $block ?? [] );

$cbb_class = crea_bootstrap_blocks_class_str(
    [
        crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
        'areoi-content-with-media',
        empty( $cbb_a['align'] ) ? '' : 'align' . $cbb_a['align'],
        $cbb_a['className'] ?? '',
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' ),
    ]
);

$cbb_alignment = empty( $cbb_a['alignment'] ) ? 'start' : esc_attr( (string) $cbb_a['alignment'] );
$cbb_row       = 'start' === $cbb_alignment ? 'justify-content-end' : 'justify-content-start';
$cbb_order     = 'start' === $cbb_alignment ? 0 : 1;

$cbb_background = crea_bootstrap_blocks_background_markup( $cbb_a, true );
$cbb_url        = crea_bootstrap_blocks_link_overlay( $cbb_a );

$cbb_image = is_array( $cbb_a['image'] ?? null ) ? $cbb_a['image'] : [];
$cbb_video = is_array( $cbb_a['video'] ?? null ) ? $cbb_a['video'] : [];

$cbb_full = ! empty( $cbb_a['layout'] ) && 'full-width' === $cbb_a['layout'];

$cbb_media            = '';
$cbb_background_media = '';

if ( ! empty( $cbb_a['image'] ) || ! empty( $cbb_a['video'] ) ) {
    $cbb_media .= '<div class="' . ( $cbb_full ? 'd-lg-none' : '' ) . '">';
    if ( ! empty( $cbb_a['image'] ) ) {
        $cbb_media .= '<img src="' . esc_url( (string) ( $cbb_image['url'] ?? '' ) ) . '"'
            . ' width="' . ( empty( $cbb_image['width'] ) ? '' : esc_attr( (string) $cbb_image['width'] ) ) . '"'
            . ' height="' . ( empty( $cbb_image['height'] ) ? '' : esc_attr( (string) $cbb_image['height'] ) ) . '"'
            . ' alt="' . ( empty( $cbb_image['alt'] ) ? '' : esc_attr( (string) $cbb_image['alt'] ) ) . '"'
            . ' class="img-fluid areoi-banner-media" />';
    }
    if ( ! empty( $cbb_a['video'] ) ) {
        $cbb_media .= '<video class="img-fluid areoi-banner-media" autoplay loop playsinline muted>'
            . '<source src="' . esc_url( (string) ( $cbb_video['url'] ?? '' ) ) . '" />'
            . '</video>';
    }
    $cbb_media .= '</div>';

    if ( $cbb_full ) {
        $cbb_background_media .= '<div class="areoi-background d-none d-lg-block">'
            . '<div class="container-fluid p-0">'
            . '<div class="row ' . $cbb_row . '">'
            . '<div class="col-6 position-relative overflow-hidden">'
            . ( empty( $cbb_a['image'] ) ? '' : '<div class="areoi-background__image" style="background-image:url(' . esc_url( (string) ( $cbb_image['url'] ?? '' ) ) . ')"></div>' )
            . ( empty( $cbb_a['video'] ) ? '' : '<video autoplay loop playsinline muted><source src="' . esc_url( (string) ( $cbb_video['url'] ?? '' ) ) . '" /></video>' )
            . '</div></div></div></div>';
    }
}

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?> position-relative">
<?php echo $cbb_background; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup. ?>
<div class="d-flex flex-grow-1 position-relative">
<?php echo $cbb_background_media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Werte einzeln escapt. ?>
<div class="container h-100 position-relative">
<div class="row justify-content-between align-items-center h-100">
<div class="col-11 col-md-8 col-lg-6 col-xl-5 order-lg-<?php echo (int) $cbb_order; ?>">
<?php echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />. ?>
</div>
<div class="col-12 col-lg-6">
<?php echo $cbb_media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Werte einzeln escapt. ?>
</div>
</div>
</div>
</div>
<?php echo $cbb_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup. ?>
</div>
