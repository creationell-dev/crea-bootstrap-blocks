<?php
/**
 * Render template of the `creabb/banner-item` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_banner_item()`
 * (`blocks/banner-item.php` des Alt-Plugins). Klassenreihenfolge woertlich:
 *
 *   block-<uuid>  areoi-banner-item  <className>  <hide-Kaskade mit block>
 *
 * ZWEI HUELLEN, GEWAEHLT UEBER DAS LAYOUT DES ELTERNBLOCKS: `grid` erzeugt eine
 * `d-flex`-Form mit `div.areoi-banner-content.flex-grow-1`, jeder andere Wert
 * die `position-relative`-Form mit Container, Zeile und Spalten.
 *
 * DAS LAYOUT KOMMT VOM ELTERNBLOCK, nicht von diesem: `parent_id` wird ueber
 * `crea_bootstrap_blocks_parent_block_attributes()` aufgeloest, Vorgabe `grid`.
 * Ebenso `size` (Vorgabe `areoi-large`) — beide werden GELESEN, aber nirgends
 * in die Klassenkette geschrieben; `size` ist im Original toter Code.
 *
 * `$allow_pattern` IST GESETZT: `banner-item.php:4` schreibt es wortgleich, das
 * Hintergrundkonstrukt laeuft also mit Muster.
 *
 * SECHS ATTRIBUTE OHNE EDITORFELD: `preview`, `anchor` und die vier
 * objektwertigen `image`, `video`, `background_image`, `background_video` —
 * kein erlaubter Feldtyp bedient deren Speicherform (Vertragsregel 1.4). Sie
 * bleiben im Vertrag und werden gerendert, sobald ein Wert vorliegt; das
 * Medienmarkup unten liest `url`, `width`, `height` und `alt`.
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

$cbb_parent    = crea_bootstrap_blocks_parent_block_attributes( (string) ( $cbb_a['parent_id'] ?? '' ) );
$cbb_layout    = empty( $cbb_parent['layout'] ) ? 'grid' : esc_attr( (string) $cbb_parent['layout'] );
$cbb_container = 'grid' === $cbb_layout ? 'container-fluid' : 'container';

$cbb_class = crea_bootstrap_blocks_class_str(
    [
        crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
        'areoi-banner-item',
        $cbb_a['className'] ?? '',
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' ),
    ]
);

$cbb_background = crea_bootstrap_blocks_background_markup( $cbb_a, true );
$cbb_url        = crea_bootstrap_blocks_link_overlay( $cbb_a );

$cbb_image = is_array( $cbb_a['image'] ?? null ) ? $cbb_a['image'] : [];
$cbb_video = is_array( $cbb_a['video'] ?? null ) ? $cbb_a['video'] : [];

$cbb_media = '';
if ( ! empty( $cbb_a['image'] ) || ! empty( $cbb_a['video'] ) ) {
    $cbb_media .= '<div class="col-12 col-lg-6">';
    if ( ! empty( $cbb_a['image'] ) ) {
        $cbb_media .= '<img src="' . esc_url( (string) ( $cbb_image['url'] ?? '' ) ) . '"'
            . ' width="' . esc_attr( (string) ( $cbb_image['width'] ?? '' ) ) . '"'
            . ' height="' . esc_attr( (string) ( $cbb_image['height'] ?? '' ) ) . '"'
            . ' alt="' . esc_attr( (string) ( $cbb_image['alt'] ?? '' ) ) . '"'
            . ' class="img-fluid areoi-banner-media" />';
    }
    if ( ! empty( $cbb_a['video'] ) ) {
        $cbb_media .= '<video class="img-fluid areoi-banner-media" autoplay loop playsinline muted>'
            . '<source src="' . esc_url( (string) ( $cbb_video['url'] ?? '' ) ) . '" />'
            . '</video>';
    }
    $cbb_media .= '</div>';
}

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';

if ( 'grid' === $cbb_layout ) :
    ?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?> d-flex">
	<?php echo $cbb_background; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup. ?>
<div class="areoi-banner-content flex-grow-1">
	<?php echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />. ?>
</div>
	<?php echo $cbb_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup. ?>
</div>
<?php else : ?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?> position-relative">
	<?php echo $cbb_background; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup. ?>
<div class="<?php echo esc_attr( $cbb_container ); ?> h-100 position-relative">
<div class="row justify-content-<?php echo '' === $cbb_media ? 'center text-center' : 'between'; ?> align-items-center h-100">
<div class="col-11 col-md-8 col-lg-6 col-xl-5">
	<?php echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />. ?>
</div>
	<?php echo $cbb_media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Werte einzeln escapt. ?>
	<?php echo $cbb_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup. ?>
</div>
</div>
</div>
<?php endif; ?>
