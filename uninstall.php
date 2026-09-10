<?php
/**
 * Uninstall routine for CreaBootstrapBlocks.
 *
 * Läuft ohne geladenes Plugin: Jeder Name steht hier ausgeschrieben statt aus
 * einer Konstante zu kommen, und keine Funktion des Plugins wird aufgerufen.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Removes the plugin's data from the site that is currently switched to.
 *
 * Das Plugin besitzt genau eine Option. Blockinhalte, Attribute und das
 * generierte CSS stehen im `post_content` und gehören dem Inhalt, nicht dem
 * Plugin — sie werden hier NICHT angefasst. Eine Deinstallation, die Seiten
 * inhaltlich verändert, wäre Datenverlust, und die Migration zurück auf
 * `areoi/*` ist Sache der WP-CLI mit einem Menschen davor.
 */
function crea_bootstrap_blocks_uninstall_site(): void {
    delete_option( 'crea_bootstrap_blocks_settings' );
}

/*
 * Optionen sind Site-Daten. Ohne den Rundgang bliebe die Option auf jeder Site
 * außer der gerade aktuellen stehen — beim Löschen des Plugin-Ordners über FTP
 * oder beim Deinstallieren aus dem Netzwerk-Backend heraus ist das die
 * Hauptsite, und alle anderen behielten ihre Zeile.
 */
if ( is_multisite() ) {
    $crea_bootstrap_blocks_batch  = 100;
    $crea_bootstrap_blocks_offset = 0;

    do {
        $crea_bootstrap_blocks_site_ids = get_sites(
            [
                'fields'  => 'ids',
                'number'  => $crea_bootstrap_blocks_batch,
                'offset'  => $crea_bootstrap_blocks_offset,
                'orderby' => 'id',
            ]
        );

        foreach ( $crea_bootstrap_blocks_site_ids as $crea_bootstrap_blocks_site_id ) {
            switch_to_blog( (int) $crea_bootstrap_blocks_site_id );
            crea_bootstrap_blocks_uninstall_site();
            restore_current_blog();
        }

        $crea_bootstrap_blocks_offset += $crea_bootstrap_blocks_batch;
    } while ( count( $crea_bootstrap_blocks_site_ids ) === $crea_bootstrap_blocks_batch );
} else {
    crea_bootstrap_blocks_uninstall_site();
}
