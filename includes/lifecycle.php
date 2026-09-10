<?php
/**
 * Activation and deactivation of CreaBootstrapBlocks.
 *
 * Beide Ereignisse feuern eine eigene Aktion. Sie sind der einzige Ort, an dem
 * spaetere Module Einmaliges erledigen koennen — eine Migration, ein Cron-Slot,
 * ein Transient. Deshalb gibt es sie schon jetzt, obwohl das Plugin in Phase 1
 * und 2 nichts davon braucht.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Seeds the plugin's settings on the site that is currently switched to.
 *
 * `add_option()` statt `update_option()`: Eine bereits vorhandene Einstellung
 * bleibt unangetastet. Ein Deaktivieren und Wiederaktivieren — im Alltag der
 * haeufigste Grund fuer einen zweiten Aktivierungslauf — darf die
 * Backend-Einstellungen nicht zuruecksetzen.
 */
function crea_bootstrap_blocks_activate_site(): void {
    add_option( 'crea_bootstrap_blocks_settings', crea_bootstrap_blocks_default_settings() );

    crea_bootstrap_blocks_log( 'activate: seeded crea_bootstrap_blocks_settings' );
}

/**
 * Runs on plugin activation.
 *
 * Optionen sind Site-Daten. Ohne den Rundgang bekaeme bei einer netzwerkweiten
 * Aktivierung nur die gerade aktive Site ihre Einstellungen, und auf jeder
 * anderen stuende das Backend ohne gespeicherte Werte da.
 *
 * Der Rundgang laeuft in Stapeln zu hundert Sites: Ein Netzwerk mit einigen
 * tausend Sites brauchte sonst alle Site-Objekte gleichzeitig im Speicher.
 *
 * @param bool $network_wide Whether the plugin is being activated network-wide.
 *                           WordPress passes this as the single argument of
 *                           `activate_{$plugin}`.
 */
function crea_bootstrap_blocks_activate( bool $network_wide = false ): void {
    if ( is_multisite() && $network_wide ) {
        $batch  = 100;
        $offset = 0;

        do {
            $site_ids = get_sites(
                [
                    'fields'  => 'ids',
                    'number'  => $batch,
                    'offset'  => $offset,
                    'orderby' => 'id',
                ]
            );

            foreach ( $site_ids as $site_id ) {
                switch_to_blog( (int) $site_id );
                crea_bootstrap_blocks_activate_site();
                restore_current_blog();
            }

            $offset += $batch;
        } while ( count( $site_ids ) === $batch );
    } else {
        crea_bootstrap_blocks_activate_site();
    }

    /**
     * Fires once on plugin activation.
     *
     * Feuert genau einmal, auch bei einer netzwerkweiten Aktivierung. Ein
     * Handler, dem Site-Daten gehoeren, muss das Netzwerk selbst ablaufen;
     * `$network_wide` sagt, wann das noetig ist.
     *
     * @since 1.0.0
     *
     * @param bool $network_wide Whether the plugin was activated network-wide.
     */
    do_action( 'crea_bootstrap_blocks_activated', $network_wide );
}

/**
 * Runs on plugin deactivation.
 *
 * Das Plugin besitzt in Phase 1 und 2 weder Cron-Slots noch eigene Transients,
 * es gibt hier also nichts wegzuräumen — die eine Option bleibt bewusst stehen,
 * damit ein Deaktivieren/Aktivieren die Einstellungen nicht kostet. Entfernt
 * wird sie ausschließlich beim Deinstallieren (`uninstall.php`).
 *
 * Die Aktion ist trotzdem da: Module, die später eigene Cron-Slots oder
 * Transients anlegen, brauchen einen Ort zum Aufräumen, und dieser Ort muss
 * existieren, bevor das erste Modul ihn braucht.
 *
 * @param bool $network_deactivating Whether the plugin is being deactivated for
 *                                   the whole network. WordPress passes this as
 *                                   the single argument of `deactivate_{$plugin}`.
 */
function crea_bootstrap_blocks_deactivate( bool $network_deactivating = false ): void {
    crea_bootstrap_blocks_log( 'deactivate: network=' . ( $network_deactivating ? 'yes' : 'no' ) );

    /**
     * Fires once on plugin deactivation.
     *
     * Feuert genau einmal, auch bei einer netzwerkweiten Deaktivierung. Ein
     * Handler, dem Site-Daten gehören, muss das Netzwerk selbst ablaufen;
     * `$network_deactivating` sagt, wann das nötig ist.
     *
     * @since 1.0.0
     *
     * @param bool $network_deactivating Whether the plugin was deactivated network-wide.
     */
    do_action( 'crea_bootstrap_blocks_deactivated', $network_deactivating );
}
