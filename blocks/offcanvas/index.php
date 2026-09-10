<?php
/**
 * Render template of the `creabb/offcanvas` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_offcanvas()`
 * (`blocks/offcanvas.php` des Alt-Plugins). Klassenreihenfolge woertlich:
 *
 *   block-<uuid>  offcanvas  <placement>  <className>
 *
 * KEINE SICHTBARKEITSKASKADE.
 *
 * ZWEI Attribute mit der `!= 'Default'`-Bedingung: `backdrop` wird zu
 * `data-bs-backdrop`, `scrollable` zu `data-bs-scroll`. Beide entfallen, wenn
 * der Wert leer ODER der Literalwert `Default` ist.
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
        'offcanvas',
        $cbb_a['placement'] ?? '',
        $cbb_a['className'] ?? '',
    ]
);

$cbb_backdrop = ( ! empty( $cbb_a['backdrop'] ) && 'Default' !== $cbb_a['backdrop'] )
    ? 'data-bs-backdrop="' . esc_attr( (string) $cbb_a['backdrop'] ) . '"'
    : '';

$cbb_scroll = ( ! empty( $cbb_a['scrollable'] ) && 'Default' !== $cbb_a['scrollable'] )
    ? 'data-bs-scroll="' . esc_attr( (string) $cbb_a['scrollable'] ) . '"'
    : '';

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>" tabindex="-1" aria-hidden="true" <?php echo $cbb_backdrop; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> <?php echo $cbb_scroll; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?>>
<?php echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />. ?>
</div>
