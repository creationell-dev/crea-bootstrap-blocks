<?php
/**
 * Render template of the `creabb/carousel-item` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_carousel_item()`
 * (`blocks/carousel-item.php` des Alt-Plugins). Klassenreihenfolge:
 *
 *   block-<uuid>  carousel-item  <className>  <hide-Kaskade mit block>
 *
 * DIE KLASSE `carousel-item` IST DER ANKNUEPFUNGSPUNKT DES ELTERNBLOCKS.
 * `areoi_render_block_carousel()` sucht im gerenderten Innenmarkup per XPath
 * nach `//div[contains(@class, "carousel-item")]`, zaehlt die Treffer und baut
 * daraus die Indikatorenleiste. Wer diese Klasse umbenennt oder die Huelle von
 * `div` auf etwas anderes aendert, macht das Karussell leer — ohne dass hier
 * etwas rot wuerde.
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
        'carousel-item',
        $cbb_a['className'] ?? '',
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' ),
    ]
);

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>"><?php
echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />.
?></div>
