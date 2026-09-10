<?php
/**
 * Render template of the `creabb/toast` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_toast()` (`blocks/toast.php` des
 * Alt-Plugins).
 *
 * ZWEI HUELLEN. Aussen ein Behaelter, der WEDER `block_id` NOCH `id` traegt:
 *
 *   <div class="position-fixed p-3 <placement>" style="z-index: 11">
 *
 * Das feste `style="z-index: 11"` ist Teil des Zeichenvergleichs. Darin erst
 * der eigentliche Block:
 *
 *   block-<uuid>  toast  <background>  <text_color>  <border_color>  <className>
 *
 * KEINE SICHTBARKEITSKASKADE.
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

$cbb_class = crea_bootstrap_blocks_class_str(
    [
        crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
        'toast',
        $cbb_a['background'] ?? '',
        $cbb_a['text_color'] ?? '',
        $cbb_a['border_color'] ?? '',
        $cbb_a['className'] ?? '',
    ]
);

$cbb_container = crea_bootstrap_blocks_class_str(
    [
        'position-fixed',
        'p-3',
        $cbb_a['placement'] ?? '',
    ]
);

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div class="<?php echo esc_attr( $cbb_container ); ?>" style="z-index: 11">
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>" role="alert" aria-live="assertive" aria-atomic="true">
<?php echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />. ?>
</div>
</div>
