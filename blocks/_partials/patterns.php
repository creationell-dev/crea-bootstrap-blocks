<?php
/**
 * Background pattern partial — deliberately empty.
 *
 * Das Original-Partial (`blocks/_partials/patterns.php`, 26 Zeilen) erzeugt
 * ein Hintergrundmuster fuer den strip-Block. Es haengt vollstaendig an der
 * Lightspeed-Integration: `lightspeed_get_attribute()`,
 * `lightspeed_get_contrast_color()` und `lightspeed_get_patterns_directory()`
 * sind Funktionen des Themes `lightspeed`, das auf keiner der drei
 * Bestandsseiten laeuft. Die Repo-Konventionen schliessen den Nachbau
 * ausdruecklich aus.
 *
 * Dieses Partial liefert deshalb IMMER den Leerstring. Es bleibt trotzdem als
 * eigene Datei bestehen, weil es die eine Tuer ist, durch die eine
 * Installation doch ein Muster bekommen kann — ueber den Filter
 * `crea_bootstrap_blocks_background_pattern`. Das Hintergrund-Partial ruft es
 * unbedingt auf und braucht dafuer keinen Lightspeed-Zweig.
 *
 * DER RUECKGABEWERT WIRD UNGEPRUEFT INS MARKUP GESCHRIEBEN. Wer den Filter
 * belegt, escapt selbst — dasselbe Versprechen, das auch `render_block` gibt.
 * Diese Datei escapt nichts und darf nichts escapen: Ein Muster ist Markup,
 * kein Text, und ein `wp_kses()` hier naehme dem Erweiterungspunkt genau die
 * Faehigkeit, fuer die es ihn gibt.
 *
 * Der Parameter `$allow_pattern` ist bewusst ein Funktionsparameter, statt aus
 * dem Scope zu stammen. Im Original setzen sechs Renderfunktionen ihn auf
 * `true`; von den vierzehn Bloecken der Phase 1 sind es zwei — `strip.php:4`
 * und `media-grid.php:4`. `container.php` definiert ihn nie, deshalb ist der
 * Default `false`.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'crea_bootstrap_blocks_background_pattern_markup' ) ) {
    /**
     * The background pattern markup of one block.
     *
     * Liefert ohne Filter immer den Leerstring.
     *
     * @param array<string, mixed> $attributes    Block attributes.
     * @param bool                 $allow_pattern Whether the calling block allows a pattern.
     * @return string Ready-to-print HTML, or an empty string.
     */
    function crea_bootstrap_blocks_background_pattern_markup( array $attributes, bool $allow_pattern = false ): string {
        /**
         * Filters the background pattern markup of one block.
         *
         * Die eine Tuer fuer eine Installation, die ein Strip-Muster braucht.
         * Der Rueckgabewert wird UNGEPRUEFT ins Markup geschrieben; wer hier
         * etwas anhaengt, escapt selbst.
         *
         * @since 1.0.0
         *
         * @param string               $markup        Pattern markup, empty by default.
         * @param array<string, mixed> $attributes    Block attributes.
         * @param bool                 $allow_pattern Whether the calling block allows a pattern.
         */
        $markup = apply_filters(
            'crea_bootstrap_blocks_background_pattern',
            '',
            $attributes,
            $allow_pattern
        );

        return is_string( $markup ) ? $markup : '';
    }
}
