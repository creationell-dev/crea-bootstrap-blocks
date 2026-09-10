<?php
/**
 * Render template of the `creabb/icon` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_icon()` (`blocks/icon.php` des
 * Alt-Plugins). Die Renderfunktion des Originals lautet vollstaendig:
 *
 *     function areoi_render_block_icon( $attributes, $content )
 *     {
 *         return $content;
 *     }
 *
 * ACHTZEHN VERTRAGSATTRIBUTE, KEINES DAVON WIRD GERENDERT. Kein Wrapper, keine
 * Klasse, kein `block_id`, keine Sichtbarkeitskaskade — der Block reicht sein
 * Innenmarkup durch und sonst nichts. Registriert werden muessen die achtzehn
 * trotzdem: Sie stehen im gespeicherten Content und werden im Editor bedient;
 * ein nicht registriertes Attribut verschwindet beim ersten Speichern.
 *
 * Wer hier „aus Ordnungsliebe" eine Huelle ergaenzt, bricht jede Seite mit
 * diesem Block — `tests/test-diff-icon.php` faellt sofort um.
 *
 * DAS INNENMARKUP STEHT IN `$inner_blocks`, NICHT IN `$content` (Ruling E-33).
 *
 * @package Creationell\BootstrapBlocks
 *
 * @var string $inner_blocks Inner blocks output.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo crea_bootstrap_blocks_inner_blocks( is_string( $inner_blocks ?? null ) ? $inner_blocks : '', ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />.
