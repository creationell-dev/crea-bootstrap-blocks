<?php
/**
 * Render template of the `creabb/content-grid-item` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_content_grid_item()`
 * (`blocks/content-grid-item.php` des Alt-Plugins). Aussere Klassenreihenfolge:
 *
 *   block-<uuid>  areoi-content-grid-item  has-image  <className>
 *   <hide-Kaskade mit block>
 *
 * DREI HUELLEN, GEWAEHLT UEBER `style` DES ELTERNBLOCKS: `full` haengt `p-0` an
 * und setzt das Medium VOR den Kartenkoerper; `flush` legt das Medium in ein
 * eigenes `div.card-body.pb-0`; jeder andere Wert — Vorgabe `card` — erzeugt die
 * gewoehnliche Kartenform mit `div.card.h-100`.
 *
 * `layout` UND `style` KOMMEN VOM ELTERNBLOCK ueber `parent_id`
 * (`crea_bootstrap_blocks_parent_block_attributes()`), Vorgaben `grid` und
 * `card`. `layout` wird gelesen und nirgends benutzt — toter Code des Originals.
 *
 * `$allow_pattern` IST HIER NICHT GESETZT — anders als bei den vier anderen
 * Strips der Gruppe L. `content-grid-item.php` schreibt die Zeile nicht; das
 * Hintergrundkonstrukt laeuft mit `false`.
 *
 * DIE ZWOELF AUSRICHTUNGSKLASSEN STEHEN IM KARTENKOERPER, nicht in der aeusseren
 * Kette — und sie sind an `hide_<bp>` gekoppelt.
 *
 * `media_fit == 'set'` SCHALTET HOEHE UND BREITE FREI: nur dann entstehen
 * `max-height` und `max-width` als Inline-Stil, mit den Vorgaben 50 und 100.
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

$cbb_parent = crea_bootstrap_blocks_parent_block_attributes( (string) ( $cbb_a['parent_id'] ?? '' ) );
$cbb_style  = empty( $cbb_parent['style'] ) ? 'card' : esc_attr( (string) $cbb_parent['style'] );

$cbb_has_image = ( ! empty( $cbb_a['image'] ) || ! empty( $cbb_a['video'] ) ) ? 'has-image' : '';

$cbb_card_parts = [ 'card-body', 'd-flex', 'position-relative' ];
foreach ( [ 'vertical_align', 'horizontal_align' ] as $cbb_axis ) {
    foreach ( [ 'xs', 'sm', 'md', 'lg', 'xl', 'xxl' ] as $cbb_bp ) {
        $cbb_card_parts[] = ( empty( $cbb_a[ 'hide_' . $cbb_bp ] ) && ! empty( $cbb_a[ $cbb_axis . '_' . $cbb_bp ] ) )
            ? (string) $cbb_a[ $cbb_axis . '_' . $cbb_bp ]
            : '';
    }
}
$cbb_card_class = crea_bootstrap_blocks_class_str( $cbb_card_parts );

$cbb_class = crea_bootstrap_blocks_class_str(
    [
        crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
        'areoi-content-grid-item',
        $cbb_has_image,
        $cbb_a['className'] ?? '',
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' ),
    ]
);

$cbb_background = crea_bootstrap_blocks_background_markup( $cbb_a );
$cbb_url        = crea_bootstrap_blocks_link_overlay( $cbb_a );
$cbb_has_url    = empty( $cbb_a['url'] ) ? '' : 'areoi-has-url';

$cbb_image = is_array( $cbb_a['image'] ?? null ) ? $cbb_a['image'] : [];
$cbb_video = is_array( $cbb_a['video'] ?? null ) ? $cbb_a['video'] : [];

$cbb_fit        = empty( $cbb_a['media_fit'] ) ? 'cover' : esc_attr( (string) $cbb_a['media_fit'] );
$cbb_align      = empty( $cbb_a['media_align'] ) ? 'center' : esc_attr( (string) $cbb_a['media_align'] );
$cbb_set        = ! empty( $cbb_a['media_fit'] ) && 'set' === $cbb_a['media_fit'];
$cbb_h          = $cbb_set ? ( empty( $cbb_a['media_height'] ) ? '50' : esc_attr( (string) $cbb_a['media_height'] ) ) : false;
$cbb_w          = $cbb_set ? ( empty( $cbb_a['media_width'] ) ? '100' : esc_attr( (string) $cbb_a['media_width'] ) ) : false;
$cbb_style_attr = ( false !== $cbb_h ? 'max-height: ' . $cbb_h . 'px;' : '' ) . ( false !== $cbb_w ? 'max-width: ' . $cbb_w . 'px;' : '' );

$cbb_media = '';
if ( ! empty( $cbb_a['image'] ) || ! empty( $cbb_a['video'] ) ) {
    $cbb_media .= '<div class="card-img-top areoi-media position-relative">'
        . '<div class="areoi-media-container ' . $cbb_fit . ' ' . $cbb_align . '">';
    if ( ! empty( $cbb_a['image'] ) ) {
        $cbb_media .= '<img src="' . ( empty( $cbb_image['url'] ) ? '' : esc_url( (string) $cbb_image['url'] ) ) . '"'
            . ' width="' . ( empty( $cbb_image['width'] ) ? '' : esc_attr( (string) $cbb_image['width'] ) ) . '"'
            . ' height="' . ( empty( $cbb_image['height'] ) ? '' : esc_attr( (string) $cbb_image['height'] ) ) . '"'
            . ' alt="' . ( empty( $cbb_image['alt'] ) ? '' : esc_attr( (string) $cbb_image['alt'] ) ) . '"'
            . ' style="' . $cbb_style_attr . '" />';
    }
    if ( ! empty( $cbb_a['video'] ) ) {
        $cbb_media .= '<video class="" autoplay loop playsinline muted style="' . $cbb_style_attr . '">'
            . '<source src="' . esc_url( (string) ( $cbb_video['url'] ?? '' ) ) . '" />'
            . '</video>';
    }
    $cbb_media .= '</div></div>';
}

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';

if ( 'full' === $cbb_style ) :
    ?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?> p-0">
<div class="d-flex flex-column h-100 overflow-hidden position-relative <?php echo esc_attr( $cbb_has_url ); ?>">
	<?php echo $cbb_background; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup. ?>
	<?php echo $cbb_media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Werte einzeln escapt. ?>
<div class="<?php echo esc_attr( $cbb_card_class ); ?>">
<div class="w-100"><?php echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />. ?></div>
</div>
	<?php echo $cbb_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup. ?>
</div>
</div>
<?php elseif ( 'flush' === $cbb_style ) : ?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>">
<div class="d-flex flex-column h-100 overflow-hidden position-relative <?php echo esc_attr( $cbb_has_url ); ?>">
	<?php echo $cbb_background; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup. ?>
<div class="card-body pb-0"><?php echo $cbb_media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Werte einzeln escapt. ?></div>
<div class="<?php echo esc_attr( $cbb_card_class ); ?>">
<div class="w-100"><?php echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />. ?></div>
</div>
	<?php echo $cbb_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup. ?>
</div>
</div>
<?php else : ?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>">
<div class="card h-100 overflow-hidden position-relative <?php echo esc_attr( $cbb_has_url ); ?>">
	<?php echo $cbb_background; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup. ?>
	<?php echo $cbb_media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Werte einzeln escapt. ?>
<div class="<?php echo esc_attr( $cbb_card_class ); ?>">
<div class="w-100"><?php echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />. ?></div>
</div>
	<?php echo $cbb_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup. ?>
</div>
</div>
<?php endif; ?>
