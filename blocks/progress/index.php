<?php
/**
 * Render template of the `creabb/progress` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_progress()` (`blocks/progress.php`
 * des Alt-Plugins). Klassenreihenfolge woertlich:
 *
 *   progress  block-<uuid>  <className>  <hide-Kaskade mit block>
 *
 * ZWEI FALLEN:
 *
 * 1. `block-<uuid>` STEHT AN ZWEITER STELLE, nicht am Anfang — wie bei
 *    `spinner` und anders als bei allen vierzehn Bloecken der Phase 1.
 *
 * 2. `label` ZEIGT `width`, NICHT SICH SELBST. Das Original schreibt
 *    `!empty( $attributes['label'] ) ? esc_attr( $attributes['width'] ) . '%' : ''`
 *    — der SCHALTER `label` entscheidet, ob der WERT von `width` als Text
 *    erscheint. Und `aria-valuenow` bleibt fest auf `0`, auch wenn `width`
 *    gesetzt ist. Beides ist Bestand und wird mitgebaut.
 *
 * DAS INNENMARKUP WIRD NICHT AUSGEGEBEN — der Balken hat keine Kindbloecke.
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
        'progress',
        crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
        $cbb_a['className'] ?? '',
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' ),
    ]
);

$cbb_bar = crea_bootstrap_blocks_class_str(
    [
        'progress-bar',
        $cbb_a['background'] ?? '',
        empty( $cbb_a['striped'] ) ? '' : 'progress-bar-striped',
        empty( $cbb_a['animated'] ) ? '' : 'progress-bar-animated',
    ]
);

$cbb_label = empty( $cbb_a['label'] ) ? '' : esc_attr( (string) ( $cbb_a['width'] ?? '' ) ) . '%';
$cbb_width = empty( $cbb_a['width'] ) ? '' : 'style="width: ' . esc_attr( (string) $cbb_a['width'] ) . '%;"';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>">
<div class="<?php echo esc_attr( $cbb_bar ); ?>" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" <?php echo $cbb_width; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?>>
<?php echo $cbb_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bereits durch esc_attr() gefiltert. ?>
</div>
</div>
