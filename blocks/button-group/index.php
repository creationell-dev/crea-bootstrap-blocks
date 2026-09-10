<?php
/**
 * Render template of the `creabb/button-group` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_button_group()`
 * (`blocks/button-group.php` des Alt-Plugins). Klassenkette:
 *
 *   block-<uuid>  <className>  <style|btn-group>  <size>
 *   <hide-Kaskade mit inline-flex>
 *
 * Der Rueckfall auf `btn-group` bleibt stehen, obwohl der Vertragsdefault
 * schon `btn-group` lautet: Er greift, wenn jemand den Wert leert
 * (button-group.php:8).
 *
 * DAS INNENMARKUP STEHT IN `$inner_blocks`, NICHT IN `$content`. Blockstudios
 * Signatur lautet `render( $attributes, $inner_blocks = '', $wp_block = '',
 * $content = '' )`; WordPress ruft ein `render_callback` mit drei Argumenten
 * auf, `$content` behaelt deshalb auf jeder echten Seite seinen Vorgabewert
 * `''`. Ein Template mit `echo $content;` gibt die Kindbloecke nirgends aus.
 *
 * @package Creationell\BootstrapBlocks
 *
 * @var array<string, mixed> $attributes   Block attributes.
 * @var string               $inner_blocks Inner blocks output — the carrier of the child markup.
 * @var array<string, mixed> $block        Block data provided by Blockstudio.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Wie in allen uebrigen Templates wird ab hier ausschliesslich mit dem
// aufgeloesten Attributsatz gearbeitet — auch `style`, `size`, `block_id` und
// die hide-Kaskade. `$attributes` selbst ist nach dieser Zeile tabu: Der
// Helfer normalisiert die Typen und legt `anchor`/`className` unabhaengig von
// der Ablagestelle flach, und nur so laeuft die Kaskade mit derselben
// Absicherung wie ueberall sonst.
$cbb_a          = crea_bootstrap_blocks_native_attributes( is_array( $attributes ?? null ) ? $attributes : [], $block ?? [] );
$cbb_anchor     = $cbb_a['anchor'];
$cbb_class_name = $cbb_a['className'];
$cbb_style      = ! empty( $cbb_a['style'] ) && is_string( $cbb_a['style'] ) ? $cbb_a['style'] : 'btn-group';

$cbb_classes = crea_bootstrap_blocks_class_str(
	array(
		crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
		$cbb_class_name,
		$cbb_style,
		$cbb_a['size'] ?? null,
		crea_bootstrap_blocks_display_class_str( $cbb_a, 'inline-flex' ),
	)
);

// Auflösung Nummer 11 der globalen Vorgaben: Alle Templates geben
// `$inner_blocks` aus, nicht `<InnerBlocks />` und nicht `$content`.
$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( array( 'anchor' => $cbb_anchor ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- der Helfer escapt selbst. ?> class="<?php echo esc_attr( $cbb_classes ); ?>">
	<?php echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />. ?>
</div>
