<?php
/**
 * Render template of the `creabb/alert` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_alert()` (`blocks/alert.php` des
 * Alt-Plugins). Klassenreihenfolge woertlich:
 *
 *   block-<uuid>  alert  <className>  <style ODER alert-primary>
 *   alert-dismissible fade show (bei close)
 *   d-flex align-items-center (bei icon)
 *   <hide-Kaskade mit flex>
 *
 * ZWEI BESONDERHEITEN, BEIDE FALLEN:
 *
 * 1. `style` HAT EINE ERSATZVORGABE IM RENDERPFAD, NICHT IM VERTRAG. Das
 *    Original schreibt `!empty( $attributes['style'] ) ? $attributes['style'] :
 *    'alert-primary'`. Ein leeres `style` erzeugt also die Klasse
 *    `alert-primary`, kein Weglassen. `alert` ist der einzige Block der Phase 2
 *    mit einer solchen Vorgabe.
 *
 *    Deshalb traegt auch die Auswahlliste in `block.json` fuer den Eintrag
 *    „Default" den WERT `alert-primary` und nicht den Literalwert `Default`,
 *    den die Bloecke der Phase 1 benutzen. Wer das angleicht, aendert das
 *    gerenderte Markup.
 *
 * 2. `icon` IST OBJEKTWERTIG und wird an vier Stellen gelesen: `url`, `width`
 *    (Vorgabe 50), `height` (Vorgabe 50) und `alt` (Vorgabe Leerstring). Kein
 *    erlaubter Feldtyp bedient diese Speicherform (Vertragsregel 1.4), das
 *    Attribut bekommt deshalb kein Editorfeld und bleibt im Vertrag.
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

$cbb_a = crea_bootstrap_blocks_native_attributes( is_array( $attributes ?? null ) ? $attributes : [], $block ?? [] );

$cbb_icon_data = is_array( $cbb_a['icon'] ?? null ) ? $cbb_a['icon'] : [];

$cbb_class = crea_bootstrap_blocks_class_str(
    [
        crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
        'alert',
        $cbb_a['className'] ?? '',
        empty( $cbb_a['style'] ) ? 'alert-primary' : $cbb_a['style'],
        empty( $cbb_a['close'] ) ? '' : 'alert-dismissible fade show',
        empty( $cbb_a['icon'] ) ? '' : 'd-flex align-items-center',
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'flex' ),
    ]
);

$cbb_width  = empty( $cbb_icon_data['width'] ) ? '50' : (string) $cbb_icon_data['width'];
$cbb_height = empty( $cbb_icon_data['height'] ) ? '50' : (string) $cbb_icon_data['height'];
$cbb_alt    = empty( $cbb_icon_data['alt'] ) ? '' : (string) $cbb_icon_data['alt'];

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>" role="alert">
<?php if ( ! empty( $cbb_a['icon'] ) ) : ?>
<img class="icon bi flex-shrink-0 me-2" src="<?php echo esc_url( (string) ( $cbb_icon_data['url'] ?? '' ) ); ?>" width="<?php echo esc_attr( $cbb_width ); ?>" height="<?php echo esc_attr( $cbb_height ); ?>" alt="<?php echo esc_attr( $cbb_alt ); ?>">
<?php endif; ?>
<div><?php echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />. ?></div>
<?php if ( ! empty( $cbb_a['close'] ) ) : ?>
<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
<?php endif; ?>
</div>
